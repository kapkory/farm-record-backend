<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a flock to a treatment plan. When set on creation, AnimalGroupObserver
     * generates the plan's vaccination Tasks dated from the flock's acquired_date.
     */
    public function up(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('treatment_plan_id')->nullable()->after('animal_breed_id');
            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->dropForeign(['treatment_plan_id']);
            $table->dropColumn('treatment_plan_id');
        });
    }
};
