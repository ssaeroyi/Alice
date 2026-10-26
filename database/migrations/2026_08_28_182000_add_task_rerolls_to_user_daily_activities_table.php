<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily-task rerolls (gamification model, slice S7): students get one
     * task reroll per day. Track the count per (user, activity_date) exactly
     * like the other daily counters so "1 reroll/day" is enforced cross-device
     * without touching the per-task assignment rows.
     */
    public function up(): void
    {
        Schema::table('user_daily_activities', function (Blueprint $table) {
            $table->unsignedTinyInteger('task_rerolls')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('user_daily_activities', function (Blueprint $table) {
            $table->dropColumn('task_rerolls');
        });
    }
};
