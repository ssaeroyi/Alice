<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The table already exists out-of-band (the migration record went
        // missing), so guard the create to keep `php artisan migrate` runnable.
        if (Schema::hasTable('streak_leaderboard_snapshots')) {
            return;
        }

        Schema::create('streak_leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->unsignedTinyInteger('rank');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_name');
            $table->unsignedSmallInteger('streak');
            $table->timestamps();

            $table->unique(['week_start', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streak_leaderboard_snapshots');
    }
};
