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
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->uuid();
			$table->unsignedBigInteger('treatment_type_id');
			$table->unsignedBigInteger('farm_id');
			$table->string('details');
            $table->morphs('treatmentable');
			$table->date('date')->nullable(); //date of treatment application
			$table->text('notes')->nullable();
			$table->date('retreat_date')->nullable();
			$table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
