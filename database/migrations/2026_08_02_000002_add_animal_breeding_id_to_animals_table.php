<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Points an animal back at the pregnancy it was born from, so a breeding
     * record can list its offspring directly instead of us keeping uuid lists
     * in the birth event's metadata. Nullable — purchased animals and anything
     * recorded before this feature have no breeding record.
     */
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->unsignedBigInteger('animal_breeding_id')->nullable()->after('sire_id');

            $table->foreign('animal_breeding_id')->references('id')->on('animal_breedings')->nullOnDelete();
            $table->index('animal_breeding_id');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropForeign(['animal_breeding_id']);
            $table->dropIndex(['animal_breeding_id']);
            $table->dropColumn('animal_breeding_id');
        });
    }
};
