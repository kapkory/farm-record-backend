<?php

use App\Models\Core\Plan;
use App\Models\Core\Subscription;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlansSeeder::class);
});

it('offers only the free six-month plan while testing', function () {
    $plans = $this->getJson('/api/v1/public/plans')->assertOk()->json('data');

    expect($plans)->toHaveCount(1);
    expect($plans[0]['slug'])->toBe('free-trial');
    expect((float) $plans[0]['price'])->toBe(0.0);
    expect($plans[0]['trial_days'])->toBe(180);
});

it('puts a new registration on the free six-month trial without choosing a plan', function () {
    $this->postJson('/register', [
        'name' => 'Jane Wanjiku',
        'phone' => '0700000001',
        'email' => 'jane@example.com',
        'password' => 'Str0ngPass!23',
        'farm_name' => 'Wanjiku Farm',
        'farm_type' => 'individual',
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->first();
    $farmer = $user->farmers()->first();
    $subscription = Subscription::where('farmer_id', $farmer->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe(Subscription::STATUS_TRIALING);
    expect($subscription->plan->slug)->toBe('free-trial');

    // Six months of access, starting today.
    expect($subscription->trial_ends_at->toDateString())
        ->toBe(now()->addDays(180)->toDateString());
});

it('has no limits on the default plan', function () {
    $plan = Plan::default();

    expect($plan->slug)->toBe('free-trial');
    expect($plan->max_farms)->toBeNull();
    expect($plan->max_animals)->toBeNull();
    expect($plan->max_users)->toBeNull();
});

it('falls back to any active plan if the default is switched off', function () {
    Plan::where('slug', 'free-trial')->update(['is_active' => false]);
    Plan::where('slug', 'professional')->update(['is_active' => true]);

    expect(Plan::default()?->slug)->toBe('professional');
});
