<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score_pct');
            $table->boolean('passed');
            $table->unsignedTinyInteger('correct_count');
            $table->unsignedTinyInteger('wrong_count')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->json('answers_json')->nullable();
            $table->integer('reward')->default(0);
            $table->integer('xp_awarded')->default(0);
            $table->unsignedTinyInteger('hint_tokens_used')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'story_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
