<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent offline creates rely on the uuid being unique at the
        // database level; these four tables were created without the index.
        Schema::table('plantings', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('plantings', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });
    }
};
