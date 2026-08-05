<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Live weight readings taken every few weeks, for an individual animal or
     * a sample of a group.
     *
     * `weight_kg` is always PER HEAD and always in kilograms, so an ox and a
     * flock of broilers sit on the same axis and every gain calculation is a
     * plain subtraction. What the farmer actually typed is kept alongside it
     * in `entered_value` / `entered_unit` — chicks are weighed in grams and it
     * would be unhelpful to echo 0.85 kg back at someone who entered 850 g.
     */
    public function up(): void
    {
        Schema::create('animal_weights', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->morphs('weighable'); // Animal or AnimalGroup
            $table->date('measured_on');
            $table->decimal('weight_kg', 10, 3);
            $table->decimal('entered_value', 10, 3);
            $table->enum('entered_unit', ['kg', 'g', 'lb'])->default('kg');
            // Head weighed. 1 for an individual; a sample for a group.
            $table->unsignedInteger('sample_size')->default(1);
            $table->decimal('sample_total_kg', 10, 3)->nullable();
            // The next weighing task this reading scheduled, so the following
            // reading can close it without matching on the title.
            $table->unsignedBigInteger('next_task_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('next_task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['weighable_type', 'weighable_id', 'measured_on'], 'animal_weights_subject_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_weights');
    }
};
