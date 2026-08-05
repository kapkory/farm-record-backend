<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How often this stock should be weighed. Resolved by
     * WeighingIntervalResolver: the individual record wins, then its animal
     * type, then a built-in default for the type's category — so a farmer
     * never has to configure anything before the first weighing.
     */
    public function up(): void
    {
        foreach (['animal_types', 'animals', 'animal_groups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedSmallInteger('weighing_interval_days')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['animal_types', 'animals', 'animal_groups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('weighing_interval_days');
            });
        }
    }
};
