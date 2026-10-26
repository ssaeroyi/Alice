<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rank_title')->nullable()->after('points');
        });

        // Backfill: existing users start at their current title so the
        // promotion ceremony only ever fires for a FUTURE tier crossing. Without
        // this, every returning student's first settle after launch would pay a
        // giant one-time cascade of badges + keys for tiers they'd long since
        // passed. The backfill records their today-tier as already "reached".
        $ladder = config('gamification.points.titles', []);
        usort($ladder, fn ($a, $b) => $a['min_points'] <=> $b['min_points']);

        if (count($ladder) > 0) {
            DB::table('users')
                ->orderBy('id')
                ->select(['id', 'points'])
                ->chunkById(500, function ($users) use ($ladder) {
                    foreach ($users as $user) {
                        $title = $ladder[0]['name'];
                        foreach ($ladder as $step) {
                            if ((int) $user->points >= (int) $step['min_points']) {
                                $title = $step['name'];
                            }
                        }
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['rank_title' => $title]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rank_title']);
        });
    }
};
