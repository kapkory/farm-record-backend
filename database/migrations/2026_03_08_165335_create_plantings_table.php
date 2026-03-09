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
        Schema::create('plantings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
			$table->unsignedBigInteger('farm_id');
			$table->unsignedBigInteger('field_id')->nullable();
			$table->unsignedBigInteger('crop_id');
			$table->unsignedBigInteger('crop_variety_id')->nullable();
			$table->date('date_planted');
			$table->date('expected_harvest_date')->nullable();
			$table->date('actual_harvest_date')->nullable();
			$table->unsignedInteger('quantity_planted')->nullable();
			$table->enum('purpose',['commercial','subsistence','mixed'])->default('commercial');
			$table->unsignedBigInteger('user_id');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantings');
    }
};
