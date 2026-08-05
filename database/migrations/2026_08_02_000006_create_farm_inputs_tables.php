<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inputs a farm buys in bulk and uses across many animals — dip, drugs,
     * vaccines, feed. Every other cost in the app attaches to exactly one
     * planting/animal/group, which is why a 1,000 tin of dip that treats the
     * whole herd three times previously had nowhere to go.
     *
     * Three tables: the purchase (a stock lot), each usage event, and who
     * each usage benefited plus their share of the cost.
     */
    public function up(): void
    {
        Schema::create('farm_inputs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->enum('category', ['dip', 'drug', 'vaccine', 'feed', 'fertilizer', 'seed', 'other'])
                ->default('other');
            // Links a product to what it treats, so applying it can write a
            // Treatment of the right type.
            $table->unsignedBigInteger('treatment_type_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20)->default('unit');
            $table->decimal('quantity_remaining', 12, 3);
            $table->decimal('total_cost', 12, 2);
            // Stored, not derived: correcting a purchase later must not silently
            // re-price applications that were already charged at the old rate.
            $table->decimal('unit_cost', 12, 4);
            $table->date('purchase_date');
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->foreign('farmer_id')->references('id')->on('farmers')->cascadeOnDelete();
            $table->foreign('treatment_type_id')->references('id')->on('treatment_types')->nullOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['farm_id', 'category']);
            $table->index('purchase_date');
        });

        Schema::create('input_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farm_input_id');
            $table->unsignedBigInteger('farm_id');
            $table->date('date');
            $table->decimal('quantity_used', 12, 3);
            // quantity_used × the input's unit_cost, snapshotted at the time.
            $table->decimal('total_cost', 12, 2);
            $table->enum('allocation_basis', ['per_head', 'by_weight', 'manual'])->default('per_head');
            $table->string('details');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('farm_input_id')->references('id')->on('farm_inputs')->cascadeOnDelete();
            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['farm_input_id', 'date']);
        });

        Schema::create('input_application_targets', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('input_application_id');
            $table->morphs('targetable'); // animal | animal_group
            $table->unsignedInteger('head_count')->default(1);
            // The number the split was weighted by — kg per head for by_weight.
            $table->decimal('basis_value', 12, 3)->nullable();
            $table->decimal('allocated_cost', 12, 2);
            $table->unsignedBigInteger('treatment_id')->nullable();
            $table->timestamps();

            $table->foreign('input_application_id')->references('id')->on('input_applications')->cascadeOnDelete();
            $table->foreign('treatment_id')->references('id')->on('treatments')->nullOnDelete();
            $table->unique(
                ['input_application_id', 'targetable_type', 'targetable_id'],
                'iat_application_target_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('input_application_targets');
        Schema::dropIfExists('input_applications');
        Schema::dropIfExists('farm_inputs');
    }
};
