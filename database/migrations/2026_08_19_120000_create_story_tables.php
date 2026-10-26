<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('license')->nullable();
            $table->string('source_url')->nullable();
            $table->unsignedTinyInteger('reading_level')->nullable();
            $table->text('synopsis')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('story_genre', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->primary(['story_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_genre');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('genres');
    }
};
