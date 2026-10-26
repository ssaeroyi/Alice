<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the legacy free-text "section" cleanup: removes the orphan
 * `section_teacher` table (safety net for environments migrated before the
 * create-migration was neutralised) and drops "section" as an allowed value of
 * the `assignments.scope` enum. The `users.section` / `assignments.section`
 * columns are kept as display-only legacy and dropped in a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('section_teacher');

        Schema::table('assignments', function (Blueprint $table) {
            $table->enum('scope', ['all', 'grade', 'students', 'group'])->default('all')->change();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->enum('scope', ['all', 'grade', 'section', 'students', 'group'])->default('all')->change();
        });

        Schema::create('section_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section', 30);
            $table->timestamps();

            $table->unique(['user_id', 'section']);
            $table->index('section');
        });
    }
};
