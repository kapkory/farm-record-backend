<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an individual animal to a treatment plan. When set on creation,
     * AnimalObserver generates the plan's vaccination Tasks dated from the
     * animal's date_of_birth (or acquisition_date as a fallback).
     */
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->unsignedBigInteger('treatment_plan_id')->nullable()->after('animal_breed_id');
            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropForeign(['treatment_plan_id']);
            $table->dropColumn('treatment_plan_id');
        });
    }
};
