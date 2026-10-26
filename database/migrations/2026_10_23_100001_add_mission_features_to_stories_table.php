<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Word-feasibility snapshot for the teacher journal tab
            // ({'suffix:ed': 51, ...}); students never read it.
            $table->json('mission_features')->nullable()->after('missions_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('mission_features');
        });
    }
};
