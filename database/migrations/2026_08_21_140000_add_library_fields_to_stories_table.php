<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('duration_tier')->nullable()->after('reading_level');
            $table->string('quiz_difficulty')->nullable()->after('duration_tier');
            $table->string('difficulty_label')->nullable()->after('quiz_difficulty');
            $table->json('difficulty_breakdown')->nullable()->after('difficulty_label');
            $table->text('provenance')->nullable()->after('difficulty_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn([
                'duration_tier',
                'quiz_difficulty',
                'difficulty_label',
                'difficulty_breakdown',
                'provenance',
            ]);
        });
    }
};
