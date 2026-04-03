<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('field_id')->nullable();
            $table->unsignedBigInteger('animal_type_id');
            $table->unsignedBigInteger('animal_breed_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('initial_count')->default(1);
            $table->unsignedInteger('current_count')->default(1);
            $table->date('acquired_date');
            $table->enum('acquisition_type', ['born', 'purchased', 'donated', 'transferred'])->default('purchased');
            $table->enum('purpose', ['commercial', 'subsistence', 'mixed'])->default('commercial');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->foreign('field_id')->references('id')->on('fields')->nullOnDelete();
            $table->foreign('animal_type_id')->references('id')->on('animal_types')->restrictOnDelete();
            $table->foreign('animal_breed_id')->references('id')->on('animal_breeds')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['farm_id', 'animal_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_groups');
    }
};

