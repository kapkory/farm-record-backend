<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apiary_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('animal_group_id')->unique();
            $table->string('naming_prefix', 10)->nullable();
            $table->string('naming_scheme', 20)->default('alpha');
            $table->unsignedInteger('next_sequence')->default(1);
            $table->unsignedSmallInteger('default_harvest_interval_days')->default(90);
            $table->timestamps();

            $table->foreign('animal_group_id')->references('id')->on('animal_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apiary_profiles');
    }
};
