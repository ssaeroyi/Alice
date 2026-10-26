<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('placement_sets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('author');
            $table->string('source_url');
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('placement_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('set_id')->constrained('placement_sets')->cascadeOnDelete();
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('placement_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('placement_attempts')->cascadeOnDelete();
            $table->string('step');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['attempt_id', 'step']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placement_responses');
        Schema::dropIfExists('placement_attempts');
        Schema::dropIfExists('placement_sets');
    }
};
