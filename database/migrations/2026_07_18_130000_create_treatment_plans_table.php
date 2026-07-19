<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A treatment plan is a reusable vaccination / treatment schedule for a kind
     * of animal (e.g. the "Layers Chicken Vaccination Schedule"). Global system
     * plans have farmer_id = null and is_system = true; farmers can also create
     * their own. Mirrors the crop `schedules` table.
     */
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->unsignedBigInteger('animal_type_id')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedTinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index('animal_type_id');
            $table->index('farmer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};
