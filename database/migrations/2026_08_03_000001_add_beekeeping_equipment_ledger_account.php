<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Buying hives (and other bee gear) had nowhere dedicated to post — bee
     * costs would have to share a generic expense account. This gives them
     * their own line so beekeeping spend can be tracked separately.
     *
     * LedgerAccountsSeeder now includes this account for fresh installs; this
     * migration adds it to existing ones. Inserting directly rather than
     * re-running the seeder, because the seeder's updateOrCreate rewrites the
     * uuid of every system account it touches.
     */
    public function up(): void
    {
        $exists = DB::table('ledger_accounts')
            ->where('name', 'Beekeeping Equipment')
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
            'name' => 'Beekeeping Equipment',
            'slug' => 'beekeeping-equipment',
            'type' => 'expense',
            'description' => 'Code: 5450 | Hives, suits, smokers and other bee gear',
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
            ->where('name', 'Beekeeping Equipment')
            ->whereNull('farmer_id')
            ->delete();
    }
};
