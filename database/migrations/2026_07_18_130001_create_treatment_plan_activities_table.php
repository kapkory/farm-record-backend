<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single dose/step in a treatment plan (e.g. "Marek's Vaccine at day 1,
     * given subcutaneously"). `age_days` is the animal's age in days at which
     * the step is due, counted from the flock's acquired_date. Mirrors the crop
     * `schedule_activities` table.
     */
    public function up(): void
    {
        Schema::create('treatment_plan_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->unsignedBigInteger('treatment_plan_id');
            $table->unsignedBigInteger('treatment_type_id')->nullable();
            $table->string('vaccine');
            $table->string('disease')->nullable();
            $table->string('route')->nullable();
            $table->integer('age_days')->default(0);
            $table->unsignedTinyInteger('priority')->default(2);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('treatment_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_activities');
    }
};
