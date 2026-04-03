<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_breeds', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('animal_type_id');
            $table->string('name');
            $table->enum('purpose', ['meat', 'dairy', 'eggs', 'honey', 'wool', 'breeding', 'dual', 'other'])->default('dual');
            $table->unsignedInteger('average_lifespan_months')->nullable();
            $table->unsignedInteger('gestation_days')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('animal_type_id')->references('id')->on('animal_types')->cascadeOnDelete();
            $table->unique(['animal_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_breeds');
    }
};

