<?php

use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Plan;
use App\Models\Core\Subscription;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function billingContext(bool $superadmin = false): array
{
    test()->seed(PlansSeeder::class);

    $user = User::factory()->create(['is_superadmin' => $superadmin]);
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Billing Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 1]);

    return [$user, $farmer];
}

it('lists active plans to any authenticated farmer', function () {
    [$user] = billingContext();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/billing/plans')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Starter')
        ->assertJsonPath('data.0.currency', 'KES');
});

it('starts a trial when a farmer subscribes to a plan', function () {
    [$user, $farmer] = billingContext();
    $plan = Plan::where('slug', 'professional')->first();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/billing/subscribe', ['plan_uuid' => $plan->uuid])
        ->assertOk()
        ->assertJsonPath('data.status', 'trialing')
        ->assertJsonPath('data.plan.slug', 'professional');

    $subscription = $farmer->fresh()->subscription;
    expect($subscription->trial_ends_at->isFuture())->toBeTrue()
        ->and((int) round($subscription->trial_ends_at->diffInDays(now()->addDays($plan->trial_days))))->toBe(0);

    // Own subscription endpoint reflects it.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/billing/subscription')
        ->assertOk()
        ->assertJsonPath('data.effective_status', 'trialing');
});

it('blocks non-superadmins from the admin billing area', function () {
    [$user] = billingContext(superadmin: false);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/subscriptions')->assertForbidden();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/plans')->assertForbidden();
});

it('lets a superadmin record a payment that activates the subscription', function () {
    [$admin, $farmer] = billingContext(superadmin: true);
    $plan = Plan::where('slug', 'starter')->first();

    // Farmer is on a trial.
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/subscriptions/assign/{$farmer->uuid}", ['plan_uuid' => $plan->uuid])
        ->assertOk()
        ->assertJsonPath('data.status', 'trialing');

    $subscription = $farmer->fresh()->subscription;

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/subscriptions/{$subscription->uuid}/payments", [
            'amount' => 2500,
            'method' => 'mpesa',
            'reference' => 'QWE123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.subscription.status', 'active')
        ->assertJsonPath('data.payment.amount', 2500);

    $subscription->refresh();
    expect($subscription->current_period_end->isFuture())->toBeTrue()
        // Monthly plan → paid through ~30 days out.
        ->and((int) round(now()->diffInDays($subscription->current_period_end)))->toBeGreaterThanOrEqual(29);

    // A second payment stacks on top of the current period.
    $firstEnd = $subscription->current_period_end->copy();
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/subscriptions/{$subscription->uuid}/payments", [
            'amount' => 2500,
            'method' => 'cash',
        ])->assertCreated();

    expect($subscription->fresh()->current_period_end->greaterThan($firstEnd))->toBeTrue();
});

it('reports a lapsed trial as past_due via effective status', function () {
    [$user, $farmer] = billingContext();
    $plan = Plan::where('slug', 'starter')->first();

    Subscription::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_TRIALING,
        'started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(6),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/billing/subscription')
        ->assertOk()
        ->assertJsonPath('data.status', 'trialing')
        ->assertJsonPath('data.effective_status', 'past_due');
});

it('gives the superadmin stats and a filterable subscription list', function () {
    [$admin, $farmer] = billingContext(superadmin: true);
    $plan = Plan::where('slug', 'professional')->first();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/subscriptions/assign/{$farmer->uuid}", ['plan_uuid' => $plan->uuid])
        ->assertOk();

    $subscription = $farmer->fresh()->subscription;
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/subscriptions/{$subscription->uuid}/payments", ['amount' => 5000, 'method' => 'manual'])
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/subscriptions/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.active', 1)
        ->assertJsonPath('data.mrr', 5000)
        ->assertJsonPath('data.collected_this_month', 5000);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/subscriptions?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.farmer.display_name', 'Billing Farmer');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/subscriptions?status=canceled')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('archives a plan with subscribers instead of deleting it', function () {
    [$admin, $farmer] = billingContext(superadmin: true);
    $plan = Plan::where('slug', 'starter')->first();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/subscriptions/assign/{$farmer->uuid}", ['plan_uuid' => $plan->uuid])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/admin/plans/{$plan->uuid}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(Plan::where('slug', 'starter')->exists())->toBeTrue();

    // A plan without subscribers deletes cleanly, and CRUD works.
    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/admin/plans', [
            'name' => 'Test Tier',
            'price' => 1500,
            'interval' => 'monthly',
            'features' => ['One thing'],
        ])->assertCreated()->json('data.uuid');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/admin/plans/{$created}", [
            'name' => 'Test Tier',
            'price' => 1800,
            'interval' => 'monthly',
        ])->assertOk()->assertJsonPath('data.price', 1800);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/admin/plans/{$created}")
        ->assertOk();

    expect(Plan::where('uuid', $created)->exists())->toBeFalse();
});

it('starts a trial for the plan chosen at registration', function () {
    test()->seed(PlansSeeder::class);
    $plan = Plan::where('slug', 'professional')->first();

    $this->postJson('/register', [
        'name' => 'New Farmer',
        'email' => 'new@farmer.test',
        'phone' => '0700000001',
        'farm_name' => 'New Farm',
        'farm_type' => 'individual',
        'password' => 'Password1234',
        'plan_uuid' => $plan->uuid,
    ])->assertNoContent();

    $farmer = Farmer::where('display_name', 'New Farm')->first();
    expect($farmer->subscription)->not->toBeNull()
        ->and($farmer->subscription->status)->toBe(Subscription::STATUS_TRIALING)
        ->and($farmer->subscription->plan_id)->toBe($plan->id)
        ->and($farmer->subscription->trial_ends_at->isFuture())->toBeTrue();
});

it('serves the plan catalogue publicly for the register page', function () {
    test()->seed(PlansSeeder::class);

    $this->getJson('/api/v1/public/plans')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.trial_days', 14);
});
