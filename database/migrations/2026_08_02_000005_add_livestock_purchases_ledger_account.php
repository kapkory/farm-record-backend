<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Buying an animal had nowhere to post: the chart of accounts had a
     * Livestock asset and Livestock Sales revenue, but no purchase expense.
     *
     * LedgerAccountsSeeder now includes this account for fresh installs; this
     * migration adds it to existing ones. Inserting directly rather than
     * re-running the seeder, because the seeder's updateOrCreate rewrites the
     * uuid of every system account it touches.
     */
    public function up(): void
    {
        $exists = DB::table('ledger_accounts')
            ->where('name', 'Livestock Purchases')
            ->whereNull('farmer_id')
            ->exists();

        if ($exists) {
            return;
        }

        $parentId = DB::table('ledger_accounts')
            ->where('name', 'Expenses')
            ->whereNull('parent_id')
            ->whereNull('farmer_id')
            ->value('id');

        DB::table('ledger_accounts')->insert([
            'uuid' => (string) Str::orderedUuid(),
            'name' => 'Livestock Purchases',
            'slug' => 'livestock-purchases',
            'type' => 'expense',
            'description' => 'Code: 5650 | What you paid to buy animals',
            'is_system' => true,
            'status' => 1,
            'parent_id' => $parentId,
            'farmer_id' => null,
            'user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ledger_accounts')
            ->where('name', 'Livestock Purchases')
            ->whereNull('farmer_id')
            ->delete();
    }
};
