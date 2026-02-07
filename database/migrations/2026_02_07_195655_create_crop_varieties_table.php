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
        Schema::create('crop_varieties', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
			$table->unsignedBigInteger('crop_id');
			$table->string('name');
			$table->integer('maturity_days')->nullable();
			$table->integer('expected_yield')->nullable();
			$table->string('description')->nullable();
			$table->string('harvest_type')->nullable();
			$table->unsignedTinyInteger('status')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crop_varieties');
    }
};
