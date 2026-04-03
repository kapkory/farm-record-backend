<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_types', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name')->unique();
            $table->enum('category', ['livestock', 'poultry', 'apiculture', 'aquaculture'])->default('livestock');
            $table->enum('tracking_mode', ['group_only', 'individual_only', 'both'])->default('both');
            $table->string('count_label', 50)->default('animals');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_types');
    }
};

