<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily goal auto-credit markers (tuning round): the day a student meets
     * their reading or study-word goal, keys are auto-credited once. These
     * timestamps on the per-day activity row make that "once per day" guard
     * cross-device safe — a replayed heartbeat or repeated drill grade can
     * never double-pay a goal reward.
     */
    public function up(): void
    {
        Schema::table('user_daily_activities', function (Blueprint $table) {
            $table->timestamp('reading_goal_claimed_at')->nullable()->after('reading_seconds_today');
            $table->timestamp('study_goal_claimed_at')->nullable()->after('reading_goal_claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_daily_activities', function (Blueprint $table) {
            $table->dropColumn(['study_goal_claimed_at', 'reading_goal_claimed_at']);
        });
    }
};
