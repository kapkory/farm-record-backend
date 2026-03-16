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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
			$table->unsignedBigInteger('farm_id');
			$table->date('date');
			$table->text('description')->nullable();
			$table->enum('payment_method',['cash','mobile_money','bank','credit'])->default('cash');
			$table->string('reference_number')->nullable();
            $table->string('transactionable_type')->nullable();
            $table->unsignedBigInteger('transactionable_id')->nullable();
            $table->unsignedBigInteger('farmer_id');
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['transactionable_type', 'transactionable_id'],
                'lt_transactionable_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
