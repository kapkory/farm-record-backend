<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_events', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('eventable_type');
            $table->unsignedBigInteger('eventable_id');
            $table->enum('event_type', ['birth', 'death', 'sale', 'purchase', 'weight_check', 'movement', 'other']);
            $table->date('date');
            $table->unsignedInteger('quantity')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['eventable_type', 'eventable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_events');
    }
};

