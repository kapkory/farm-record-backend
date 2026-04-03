<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('farmer_id');
            $table->unsignedBigInteger('animal_group_id')->nullable();
            $table->unsignedBigInteger('animal_type_id');
            $table->unsignedBigInteger('animal_breed_id')->nullable();
            $table->string('tag_number', 100)->nullable();
            $table->string('name')->nullable();
            $table->enum('gender', ['male', 'female', 'unknown'])->default('unknown');
            $table->date('date_of_birth')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->enum('acquisition_type', ['born', 'purchased', 'donated', 'transferred'])->default('born');
            $table->decimal('purchase_price', 10, 2)->nullable();
//            $table->decimal('weight', 8, 2)->nullable();
//            $table->string('weight_unit', 10)->default('kg');
            $table->enum('status', ['active', 'sold', 'deceased', 'transferred'])->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['animal_group_id', 'tag_number']);
            $table->index(['farm_id', 'animal_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};

