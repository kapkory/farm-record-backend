<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->string('location')->nullable();
            $table->decimal('size', 10, 2)->nullable();
            $table->string('size_unit')->nullable();
            $table->date('established_date')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['mixed', 'crop', 'animal'])->default('mixed');
            $table->enum('ownership_type', ['leased', 'owned', 'shared'])->default('owned');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};

