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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id');               // creator
            $table->unsignedBigInteger('assigned_to_user_id')->nullable(); // assignee
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('priority')
                ->default(2)
                ->comment('1=low 2=medium 3=high 4=critical');
            $table->unsignedTinyInteger('task_status')
                ->default(2)
                ->comment('1=pending 2=in_progress 3=on_hold 4=completed 5=cancelled');
            $table->unsignedBigInteger('parent_task_id')->nullable(); // sub-tasks
            $table->nullableMorphs('taskable');                  // farm, planting, treatment, etc.
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_task_id')->references('id')->on('tasks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
