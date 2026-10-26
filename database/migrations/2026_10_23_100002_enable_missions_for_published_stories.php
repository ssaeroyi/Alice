<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Journal battles ship to every published story at once: the auto
     * generator guarantees a solvable action set for any story with
     * enough tokens, so there is no per-story setup to wait on.
     */
    public function up(): void
    {
        DB::table('stories')
            ->where('status', 'published')
            ->where('missions_enabled', false)
            ->update(['missions_enabled' => true]);
    }

    public function down(): void
    {
        // The sweep is intentionally not reversible: per-story opt-outs
        // and opt-ins made afterwards must survive a rollback.
    }
};
