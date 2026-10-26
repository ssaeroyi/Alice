<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_mission_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 40); // find-ed, find-ing, ...
            $table->string('kind', 16)->default('find'); // find | fill | choice
            $table->json('config'); // rule + need (no answers: instantiated at battle time)
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['story_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_mission_actions');
    }
};
