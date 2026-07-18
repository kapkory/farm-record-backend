<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hives', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('farmer_id');
            $table->unsignedBigInteger('animal_group_id');
            $table->unsignedInteger('sequence');
            $table->string('code', 20);
            $table->string('name')->nullable();
            $table->string('hive_type', 40)->nullable();
            $table->string('occupancy', 20)->default('occupied');
            $table->date('installed_date')->nullable();
            $table->date('last_inspected_at')->nullable();
            $table->date('last_harvested_at')->nullable();
            $table->date('next_harvest_due')->nullable();
            $table->unsignedSmallInteger('harvest_interval_days')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->foreign('animal_group_id')->references('id')->on('animal_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Codes are painted on physical boxes: unique per apiary, never reused.
            $table->unique(['animal_group_id', 'code']);
            $table->unique(['animal_group_id', 'sequence']);
            $table->index(['farm_id', 'next_harvest_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hives');
    }
};
