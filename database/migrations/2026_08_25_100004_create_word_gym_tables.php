<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_gym_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('saved_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deck_id')->nullable()->constrained('word_gym_decks')->nullOnDelete();
            $table->string('word');
            $table->text('definition')->nullable();
            $table->string('part_of_speech', 60)->nullable();
            $table->string('phonetic', 120)->nullable();
            $table->text('example_sentence')->nullable();
            $table->unsignedInteger('lookup_count')->default(1);
            $table->unsignedTinyInteger('times_correct')->default(0);
            $table->unsignedTinyInteger('times_wrong')->default(0);
            $table->unsignedTinyInteger('mastery_level')->default(0);
            $table->timestamp('last_drilled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'word']);
            $table->index(['user_id', 'deck_id']);
            $table->index(['user_id', 'mastery_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_words');
        Schema::dropIfExists('word_gym_decks');
    }
};
