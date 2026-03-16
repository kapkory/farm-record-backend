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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid();
			$table->unsignedBigInteger('ledger_transaction_id');
			$table->unsignedBigInteger('ledger_account_id');
			$table->enum('type',['debit','credit'])->default('debit');
			$table->decimal('amount', 15, 2);
			$table->decimal('unit_price', 15, 2)->nullable();
			$table->unsignedInteger('quantity')->nullable();
			$table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
