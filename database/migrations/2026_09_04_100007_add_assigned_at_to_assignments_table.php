<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('due_at');
        });

        // Legacy rows: treat their creation as the go-live moment so existing
        // assignments stay date-gated rather than silently counting prior work.
        DB::table('assignments')
            ->whereNull('assigned_at')
            ->update(['assigned_at' => DB::raw('created_at')]);

        Schema::table('assignments', function (Blueprint $table) {
            $table->index('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['assigned_at']);
            $table->dropColumn('assigned_at');
        });
    }
};
