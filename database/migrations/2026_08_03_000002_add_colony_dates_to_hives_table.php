<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Occupancy tells you whether a hive has bees right now; these tell you
     * *when* that changed — when a colony moved in and when it left (absconded
     * or died) — so the beekeeper can see how long a box stood empty.
     */
    public function up(): void
    {
        Schema::table('hives', function (Blueprint $table) {
            $table->date('colonized_at')->nullable()->after('installed_date');
            $table->date('vacated_at')->nullable()->after('colonized_at');
        });

        // Backfill: hives currently occupied are treated as colonized on their
        // install date; ones already empty/absconded/dead have no known date.
        DB::table('hives')
            ->where('occupancy', 'occupied')
            ->whereNull('colonized_at')
            ->update(['colonized_at' => DB::raw('installed_date')]);
    }

    public function down(): void
    {
        Schema::table('hives', function (Blueprint $table) {
            $table->dropColumn(['colonized_at', 'vacated_at']);
        });
    }
};
