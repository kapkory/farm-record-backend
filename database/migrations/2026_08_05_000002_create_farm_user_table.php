<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which farms a user may see. Deliberately opt-in: a user with NO rows
     * here is unrestricted (sees every farm of their farmer), so existing
     * owners and managers keep working with no backfill. Staff logins get
     * explicit rows to pin them to one or more farms.
     */
    public function up(): void
    {
        Schema::create('farm_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'farm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_user');
    }
};
