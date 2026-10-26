<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_vocab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('word');
            $table->text('definition')->nullable();
            $table->string('part_of_speech', 60)->nullable();
            $table->unsignedInteger('freq')->default(0);
            $table->string('frequency_tier', 20)->default('medium');
            $table->timestamps();

            $table->index(['frequency_tier', 'story_id']);
            $table->unique(['story_id', 'word']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_vocab');
    }
};
