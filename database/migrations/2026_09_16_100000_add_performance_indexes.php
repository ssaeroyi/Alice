<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the app's heaviest query shapes:
 * per-user orderings, teacher rollup lookups, notification dedupe,
 * and the mini-game leaderboard grouped scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->index(['game_type', 'created_at', 'user_id', 'score'], 'game_rounds_type_created_user_score_idx');
            $table->index(['user_id', 'game_type'], 'game_rounds_user_type_idx');
        });

        Schema::table('test_attempts', function (Blueprint $table) {
            $table->index(['story_id', 'user_id', 'created_at'], 'test_attempts_story_user_created_idx');
            $table->index(['user_id', 'created_at'], 'test_attempts_user_created_idx');
        });

        Schema::table('story_progress', function (Blueprint $table) {
            $table->index(['user_id', 'updated_at'], 'story_progress_user_updated_idx');
            $table->index(['status', 'story_id'], 'story_progress_status_story_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'notifications_user_created_idx');
            $table->index(['user_id', 'type', 'link'], 'notifications_user_type_link_idx');
        });

        Schema::table('request_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'request_logs_user_created_idx');
        });

        Schema::table('saved_words', function (Blueprint $table) {
            $table->index(['user_id', 'last_drilled_at'], 'saved_words_user_last_drilled_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'audit_logs_action_created_idx');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'assignments_status_created_idx');
        });

        Schema::table('reading_progress', function (Blueprint $table) {
            $table->index(['comprehension_level'], 'reading_progress_comprehension_level_idx');
        });
    }

    public function down(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->dropIndex('game_rounds_type_created_user_score_idx');
            $table->dropIndex('game_rounds_user_type_idx');
        });

        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropIndex('test_attempts_story_user_created_idx');
            $table->dropIndex('test_attempts_user_created_idx');
        });

        Schema::table('story_progress', function (Blueprint $table) {
            $table->dropIndex('story_progress_user_updated_idx');
            $table->dropIndex('story_progress_status_story_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_created_idx');
            $table->dropIndex('notifications_user_type_link_idx');
        });

        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropIndex('request_logs_user_created_idx');
        });

        Schema::table('saved_words', function (Blueprint $table) {
            $table->dropIndex('saved_words_user_last_drilled_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_created_idx');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex('assignments_status_created_idx');
        });

        Schema::table('reading_progress', function (Blueprint $table) {
            $table->dropIndex('reading_progress_comprehension_level_idx');
        });
    }
};
