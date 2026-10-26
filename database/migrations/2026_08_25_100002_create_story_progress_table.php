<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('reading');
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedInteger('current_page')->default(0);
            $table->unsignedTinyInteger('best_score_pct')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'story_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_progress');
    }
};
