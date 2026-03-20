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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->morphs('productionable');
            $table->string('name')->nullable();
			$table->unsignedInteger('quantity');
			$table->date('date')->nullable();
			$table->string('unit')->nullable();
			$table->unsignedBigInteger('user_id');
			$table->string('trace_number')->nullable();
			$table->string('grade')->nullable();
			$table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
