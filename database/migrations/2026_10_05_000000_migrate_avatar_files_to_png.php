<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The avatar art moved from DiceBear SVGs to Mana Seed PNGs
     * (config/avatars.php keys unchanged, files now *.png). Rewrite every
     * stored filename across users + both leaderboard snapshot tables:
     * known values flip .svg → .png, anything unknown falls back to NULL so
     * the avatar component renders the letter bubble instead of a 404 img.
     */
    public function up(): void
    {
        $pngs = collect(config('avatars', []))->pluck('file')->all();
        $svgs = array_map(fn (string $f) => preg_replace('/\.png$/', '.svg', $f), $pngs);

        foreach (['users', 'streak_leaderboard_snapshots', 'leaderboard_snapshots'] as $table) {
            foreach ($svgs as $i => $svg) {
                DB::table($table)->where('avatar', $svg)->update(['avatar' => $pngs[$i]]);
            }

            DB::table($table)
                ->whereNotNull('avatar')
                ->where('avatar', '!=', '')
                ->whereNotIn('avatar', $pngs)
                ->update(['avatar' => null]);
        }
    }

    /**
     * Reverse the migrations (known files only; NULLed unknowns stay NULL).
     */
    public function down(): void
    {
        $pngs = collect(config('avatars', []))->pluck('file')->all();

        foreach (['users', 'streak_leaderboard_snapshots', 'leaderboard_snapshots'] as $table) {
            foreach ($pngs as $png) {
                DB::table($table)->where('avatar', $png)->update([
                    'avatar' => preg_replace('/\.png$/', '.svg', $png),
                ]);
            }
        }
    }
};
