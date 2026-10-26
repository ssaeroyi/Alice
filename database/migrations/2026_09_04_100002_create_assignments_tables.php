<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained('stories')->nullOnDelete();
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->enum('scope', ['all', 'grade', 'section', 'students'])->default('all');
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->string('section', 30)->nullable();
            $table->enum('status', ['draft', 'active', 'cancelled'])->default('draft');
            $table->string('share_code', 12)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('teacher_id');
            $table->index(['scope', 'section']);
        });

        Schema::create('assignment_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_targets');
        Schema::dropIfExists('assignments');
    }
};
