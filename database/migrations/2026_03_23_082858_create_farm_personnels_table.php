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
        Schema::create('farm_personnels', function (Blueprint $table) {
            $table->id();
            $table->uuid();
			$table->string('name');
			$table->string('role');
			$table->string('phone')->nullable();
			$table->string('email')->nullable();
			$table->string('notes')->nullable();
			$table->unsignedBigInteger('farmer_id');
			$table->unsignedBigInteger('user_id');
			$table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_personnels');
    }
};
