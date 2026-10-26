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
        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Live, evidence-based comprehension estimate (0-100) derived from
            // real post-placement reading + quiz activity. Null until the
            // minimum evidence sample is met (falls back to the placement
            // snapshot, labelled "from placement" on teacher surfaces).
            $table->unsignedTinyInteger('live_score')->nullable();

            // The honest, teacher-eyed estimate: mirrors live_score but decays
            // toward the conservative band once the student goes totally
            // inactive. The student bar always uses live_score so it never
            // visibly shrinks; this column keeps teachers honest on stale data.
            $table->unsignedTinyInteger('teacher_score')->nullable();

            // Derived level + server grade from the live score via the shared
            // placement thresholds/offsets (null = still on the snapshot).
            $table->string('comprehension_level', 20)->nullable();
            $table->unsignedTinyInteger('reading_grade')->nullable();

            // Honesty metadata: how much real evidence backs the live score,
            // and the last moment it was (re)computed.
            $table->unsignedSmallInteger('sample_count')->default(0);
            $table->unsignedTinyInteger('distinct_grades')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('computed_at')->nullable();

            // Trend vs. the prior persisted snapshot: up / steady / down / null.
            $table->string('trend', 10)->nullable();

            // Maintenance signal: an Instructional/Independent student who is
            // failing a pattern of recent quizzes. Teacher-facing only; the
            // student bar is never shrunk by this.
            $table->boolean('slipping')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_progress');
    }
};
