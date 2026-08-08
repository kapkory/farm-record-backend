<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A personnel can optionally be given a login. That login is a real User
     * attached to the farmer (via farmer_users), and this column links the
     * personnel record to it. Distinct from `user_id`, which records who
     * created the personnel entry.
     */
    public function up(): void
    {
        Schema::table('farm_personnels', function (Blueprint $table) {
            $table->foreignId('login_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farm_personnels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('login_user_id');
        });
    }
};
