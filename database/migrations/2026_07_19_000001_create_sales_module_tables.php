<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farmer_id');
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('farmer_id')->references('id')->on('farmers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('farmer_id');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farm_id');
            $table->unsignedBigInteger('farmer_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->date('date');
            $table->string('payment_method', 20);
            $table->decimal('amount_total', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            // paid | partial | owed | void
            $table->string('status', 20)->default('paid');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->foreign('farmer_id')->references('id')->on('farmers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('buyer_id')->references('id')->on('buyers')->nullOnDelete();
            $table->index(['farm_id', 'date']);
            $table->index(['farmer_id', 'status']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('sale_id');
            // animal | animal_group | planting | hive (morph aliases), null = whole farm / other
            $table->string('sellable_type', 40)->nullable();
            $table->unsignedBigInteger('sellable_id')->nullable();
            // animal | crop | animal_product | bee_product | other — drives the income account
            $table->string('category', 30);
            $table->string('product');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_total', 14, 2);
            $table->unsignedBigInteger('production_id')->nullable();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('production_id')->references('id')->on('productions')->nullOnDelete();
            $table->index(['sellable_type', 'sellable_id']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->decimal('amount', 14, 2);
            // cash | mobile_money | bank
            $table->string('payment_method', 20);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('buyers');
    }
};
