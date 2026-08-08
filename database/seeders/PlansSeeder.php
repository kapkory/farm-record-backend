<?php

namespace Database\Seeders;

use App\Models\Core\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlansSeeder extends Seeder
{
    /**
     * While the product is in testing every new farmer lands on the free
     * six-month plan below, which carries no limits. The paid tiers stay
     * defined but inactive so they can be switched on later without a
     * migration — see $paidTiersActive.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $plans = [
        [
            'name' => 'Free Trial',
            'slug' => 'free-trial',
            'description' => 'Full access to everything while we are in testing. Six months free, no payment details needed.',
            'price' => 0,
            'interval' => 'monthly',
            // Six months.
            'trial_days' => 180,
            'max_farms' => null,
            'max_animals' => null,
            'max_users' => null,
            'features' => [
                'Every feature, no limits',
                'Unlimited farms, animals and team members',
                'Crops, livestock, bees and beekeeping',
                'Sales, costs and profitability',
                'Offline mobile app',
            ],
            'sort_order' => 0,
        ],
        [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'For a single small farm getting started.',
            'price' => 2500,
            'interval' => 'monthly',
            'trial_days' => 14,
            'max_farms' => 1,
            'max_animals' => 50,
            'max_users' => 2,
            'features' => [
                'Basic crop tracking',
                'Up to 50 animals',
                'Record sales and costs',
                'Offline mobile app',
            ],
            'sort_order' => 1,
        ],
        [
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'For growing farms that need the full toolkit.',
            'price' => 5000,
            'interval' => 'monthly',
            'trial_days' => 14,
            'max_farms' => 5,
            'max_animals' => 500,
            'max_users' => 10,
            'features' => [
                'Everything in Starter',
                'Up to 5 farms',
                'Breeding & treatment planning',
                'Reports and profitability',
                'Team roles',
            ],
            'sort_order' => 2,
        ],
        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For cooperatives and large operations.',
            'price' => 10000,
            'interval' => 'monthly',
            'trial_days' => 14,
            'max_farms' => null,
            'max_animals' => null,
            'max_users' => null,
            'features' => [
                'Everything in Professional',
                'Unlimited farms & animals',
                'Unlimited team members',
                'Priority support',
            ],
            'sort_order' => 3,
        ],
    ];

    /** Flip to true once the paid tiers should be offered again. */
    protected bool $paidTiersActive = false;

    public function run(): void
    {
        foreach ($this->plans as $plan) {
            $model = Plan::firstOrNew(['slug' => $plan['slug']]);
            $model->fill(array_merge($plan, [
                'currency' => 'KES',
                'is_active' => $plan['slug'] === Plan::DEFAULT_SLUG ? true : $this->paidTiersActive,
            ]));
            $model->uuid ??= (string) Str::orderedUuid();
            $model->save();
        }
    }
}
