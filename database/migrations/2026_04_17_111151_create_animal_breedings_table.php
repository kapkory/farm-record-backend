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
        Schema::create('animal_breedings', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('dam_id'); // Mother animal
            $table->unsignedBigInteger('sire_id')->nullable(); // Father animal (nullable for AI)
            $table->enum('sire_type', ['natural', 'ai', 'embryo'])->default('natural');
            $table->date('service_date');
            $table->date('expected_birth_date')->nullable();
            $table->enum('status', ['pending', 'born', 'aborted', 'failed'])->default('pending');
            $table->unsignedBigInteger('birth_event_id')->nullable(); // Links to animal_events when birth occurs
            $table->string('ai_straw_code')->nullable(); // For AI breeding
            $table->string('ai_bull_name')->nullable(); // For AI breeding
            $table->string('ai_technician')->nullable(); // For AI breeding
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // User who recorded this breeding
            $table->softDeletes();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('farm_id')->references('id')->on('farms')->onDelete('cascade');
            $table->foreign('dam_id')->references('id')->on('animals')->onDelete('cascade');
            $table->foreign('sire_id')->references('id')->on('animals')->onDelete('set null');
            $table->foreign('birth_event_id')->references('id')->on('animal_events')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['farm_id', 'status']);
            $table->index(['dam_id']);
            $table->index(['expected_birth_date']);
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animal_breedings');
    }
};
