<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->index();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('story_id', 'question_sections_story_id_foreign')
                ->references('id')->on('stories')->cascadeOnDelete();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('story_id')->index();

            $table->foreign('section_id', 'questions_section_id_foreign')
                ->references('id')->on('question_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign('questions_section_id_foreign');
            $table->dropColumn('section_id');
        });

        Schema::dropIfExists('question_sections');
    }
};
