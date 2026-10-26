<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable "past week" leaderboard archive. Written idempotently the first
 * time a student opens the games page in a new week, so last week's podium
 * stays frozen even after the underlying rounds scroll out of view. Covers
 * the "record past rankers" note from feedback pass #2 (2026-08-29).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('game_type', 30);
            $table->date('week_start');
            $table->unsignedBigInteger('rank');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 60);
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['game_type', 'week_start', 'rank']);
        });

        // Weekly scans group by user within a game_type window, so the
        // existing ['user_id','created_at'] index is not enough.
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->index(['game_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->dropIndex(['game_type', 'created_at']);
        });

        Schema::dropIfExists('leaderboard_snapshots');
    }
};
