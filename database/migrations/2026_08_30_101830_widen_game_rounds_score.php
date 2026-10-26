<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sentence arcade scores (H) can exceed 255, so the score column is
     * widened from unsignedTinyInteger (max 255) to unsignedSmallInteger
     * (max 65535) — the 999 clamp fits comfortably inside. words_count stays
     * a tinyint; game rounds never have more than 12 words.
     */
    public function up(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->unsignedSmallInteger('score')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->default(0)->change();
        });
    }
};
