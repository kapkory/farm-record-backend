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
        Schema::create('schedule_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid();
			$table->unsignedBigInteger('schedule_id');
			$table->string('activity_name');
			$table->integer('days_since_planting')->default(1);
			$table->unsignedTinyInteger('priority')->default(1);
			$table->string('notes')->nullable();
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
        Schema::dropIfExists('schedule_activities');
    }
};
