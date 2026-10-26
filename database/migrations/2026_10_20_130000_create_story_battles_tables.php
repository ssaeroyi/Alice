<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->boolean('missions_enabled')->default(false)->after('featured');
        });

        Schema::create('story_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('hearts')->default(5);
            $table->unsignedTinyInteger('boss_hp')->default(5);
            $table->string('status', 16)->default('active'); // active | won
            $table->timestamps();
            $table->unique(['user_id', 'story_id']);
        });

        Schema::create('story_battle_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_battle_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 40);
            $table->string('kind', 16); // find | fill | choice
            $table->string('flavor', 40);
            $table->string('prompt');
            $table->json('payload');        // answers + params (server-only)
            $table->json('public_payload'); // what the UI may render
            $table->string('status', 16)->default('pending'); // pending | active | done
            $table->unsignedSmallInteger('timer_seconds');
            $table->unsignedTinyInteger('position');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();
            $table->index(['story_battle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_battle_actions');
        Schema::dropIfExists('story_battles');
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('missions_enabled');
        });
    }
};
