<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            // Self-referencing parentage, so we can detect inbreeding risk by
            // walking a few generations of ancestors. Nullable — most animals
            // won't have known parents recorded in the system.
            $table->unsignedBigInteger('dam_id')->nullable()->after('animal_breed_id');
            $table->unsignedBigInteger('sire_id')->nullable()->after('dam_id');

            $table->foreign('dam_id')->references('id')->on('animals')->nullOnDelete();
            $table->foreign('sire_id')->references('id')->on('animals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropForeign(['dam_id']);
            $table->dropForeign(['sire_id']);
            $table->dropColumn(['dam_id', 'sire_id']);
        });
    }
};
