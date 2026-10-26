<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('type', 30);
            $table->unsignedTinyInteger('target_value')->default(1);
            $table->unsignedTinyInteger('reward_keys');
            $table->unsignedTinyInteger('reward_xp');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_daily_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_task_id')->constrained()->cascadeOnDelete();
            $table->date('task_date');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'task_date', 'daily_task_id']);
            $table->index(['user_id', 'task_date']);
        });

        Schema::create('user_daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->unsignedTinyInteger('read_sessions')->default(0);
            $table->unsignedTinyInteger('quiz_submissions')->default(0);
            $table->unsignedTinyInteger('drills_completed')->default(0);
            $table->unsignedTinyInteger('game_rounds')->default(0);
            $table->timestamp('stamped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_activities');
        Schema::dropIfExists('user_daily_tasks');
        Schema::dropIfExists('daily_tasks');
    }
};
