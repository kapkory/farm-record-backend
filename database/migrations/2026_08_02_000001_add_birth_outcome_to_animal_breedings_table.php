<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the outcome of a pregnancy. Until now a breeding was "resolved"
     * by flipping `status` to born, which stored nothing about what was
     * actually born. `birth_task_id` links the pregnancy to the reminder task
     * created for its expected birth date so BirthTaskManager can re-date or
     * close it without a morph lookup.
     */
    public function up(): void
    {
        Schema::table('animal_breedings', function (Blueprint $table) {
            $table->date('actual_birth_date')->nullable()->after('expected_birth_date');
            $table->unsignedTinyInteger('offspring_count')->nullable()->after('actual_birth_date');
            $table->unsignedTinyInteger('stillborn_count')->default(0)->after('offspring_count');
            $table->unsignedBigInteger('birth_task_id')->nullable()->after('birth_event_id');

            $table->foreign('birth_task_id')->references('id')->on('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animal_breedings', function (Blueprint $table) {
            $table->dropForeign(['birth_task_id']);
            $table->dropColumn(['actual_birth_date', 'offspring_count', 'stillborn_count', 'birth_task_id']);
        });
    }
};
