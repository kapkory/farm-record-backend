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
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
			$table->uuid();
			$table->string('name');
			$table->string('slug');
			$table->enum('type',['asset','liability','equity','revenue','expense']);
			$table->text('description')->nullable();
			$table->boolean('is_system')->default(false);
			$table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
