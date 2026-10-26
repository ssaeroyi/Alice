<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_daily_activities', function (Blueprint $table) {
            $table->unsignedInteger('reading_seconds_today')->default(0)->after('game_rounds');
        });
    }

    public function down(): void
    {
        Schema::table('user_daily_activities', function (Blueprint $table) {
            $table->dropColumn('reading_seconds_today');
        });
    }
};
