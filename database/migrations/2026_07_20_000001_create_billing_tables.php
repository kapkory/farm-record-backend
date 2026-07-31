<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            // monthly | yearly
            $table->string('interval', 20)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(14);
            // Soft limits — shown to farmers and used for banners; not enforced yet.
            $table->unsignedInteger('max_farms')->nullable();
            $table->unsignedInteger('max_animals')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('farmer_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            // trialing | active | past_due | expired | canceled
            $table->string('status', 20)->default('trialing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            // Paid-through date; the subscription lapses to past_due/expired after this.
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('farmer_id')->references('id')->on('farmers')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            // One subscription row per farmer (the account's current plan).
            $table->unique('farmer_id');
            $table->index('status');
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('farmer_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            // manual | mpesa | bank | cash
            $table->string('method', 20)->default('manual');
            $table->string('reference')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('farmer_id')->references('id')->on('farmers')->cascadeOnDelete();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['farmer_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
