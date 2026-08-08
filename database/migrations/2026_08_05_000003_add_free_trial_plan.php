<?php

use App\Models\Core\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * While the product is in testing, everyone gets the same plan: six months
     * free with every feature and no limits. The paid tiers are switched off
     * rather than deleted, so they can be re-enabled later without losing
     * their uuids (and any subscriptions already pointing at them).
     */
    public function up(): void
    {
        $exists = DB::table('plans')->where('slug', Plan::DEFAULT_SLUG)->exists();

        if (! $exists) {
            DB::table('plans')->insert([
                'uuid' => (string) Str::orderedUuid(),
                'name' => 'Free Trial',
                'slug' => Plan::DEFAULT_SLUG,
                'description' => 'Full access to everything while we are in testing. Six months free, no payment details needed.',
                'price' => 0,
                'currency' => 'KES',
                'interval' => 'monthly',
                'trial_days' => 180,
                'max_farms' => null,
                'max_animals' => null,
                'max_users' => null,
                'features' => json_encode([
                    'Every feature, no limits',
                    'Unlimited farms, animals and team members',
                    'Crops, livestock, bees and beekeeping',
                    'Sales, costs and profitability',
                    'Offline mobile app',
                ]),
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Only the free plan is on offer for now.
        DB::table('plans')->where('slug', '!=', Plan::DEFAULT_SLUG)->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('plans')->where('slug', '!=', Plan::DEFAULT_SLUG)->update(['is_active' => true]);
        DB::table('plans')->where('slug', Plan::DEFAULT_SLUG)->delete();
    }
};
