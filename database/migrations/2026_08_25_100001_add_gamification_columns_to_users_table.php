<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('total_keys')->default(0);
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->unsignedTinyInteger('streak_freezes')->default(1);
            $table->date('last_stamped_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_keys', 'xp', 'current_streak', 'longest_streak', 'streak_freezes', 'last_stamped_date']);
        });
    }
};
