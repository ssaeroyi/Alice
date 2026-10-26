<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Points unification (gamification model, slice S1):
     *
     *  - users.xp becomes users.points — the single, universal progression
     *    "foundation" number. Every literacy act (reading, quiz, word game)
     *    feeds points via RewardService; the PointsRank title derives from it.
     *  - users.game_points is dropped. The old game-only arcade accumulator
     *    is retired; per-game personal best now comes from game_rounds.score,
     *    and game rounds award points (not a separate score column).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('xp', 'points');
            $table->dropColumn('game_points');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('points', 'xp');
            $table->unsignedInteger('game_points')->default(0);
        });
    }
};
