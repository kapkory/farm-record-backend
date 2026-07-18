<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Allows schedules to exist as global "system" templates (e.g. the default
     * Maize and Coffee planting schedules) that are not owned by any farmer.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('farmer_id')->nullable()->change();
            $table->boolean('is_system')->default(false)->after('farmer_id');
        });

        Schema::table('schedule_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('is_system');
            $table->unsignedBigInteger('farmer_id')->nullable(false)->change();
        });

        Schema::table('schedule_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
