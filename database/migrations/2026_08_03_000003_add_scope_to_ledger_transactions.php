<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Farm-wide expenses (salaries, a whole-herd dip day, general running
     * costs) post against the farm itself rather than one animal or planting.
     * `scope` says which part of the farm a whole-farm expense belongs to, so
     * a livestock-wide cost can surface in the livestock view and a crop-wide
     * one under crops. Null for record-level transactions (a single animal,
     * planting, hive), which already carry their own context.
     */
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->string('scope', 20)->nullable()->after('transactionable_id');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
