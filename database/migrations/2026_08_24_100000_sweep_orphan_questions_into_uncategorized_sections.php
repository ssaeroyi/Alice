<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The implicit "General" bucket (questions.section_id IS NULL) is retired:
 * every question must live in a named section. Legacy ungrouped questions
 * are swept into a real, visible "Uncategorized" section on their story —
 * nothing is deleted or merged away. Idempotent: reruns find nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanStoryIds = DB::table('questions')
            ->whereNull('section_id')
            ->distinct()
            ->pluck('story_id');

        foreach ($orphanStoryIds as $storyId) {
            $sectionId = DB::table('question_sections')
                ->where('story_id', $storyId)
                ->whereRaw('LOWER(name) = ?', ['uncategorized'])
                ->value('id');

            if ($sectionId === null) {
                $position = (int) DB::table('question_sections')
                    ->where('story_id', $storyId)
                    ->max('position') + 1;

                $sectionId = DB::table('question_sections')->insertGetId([
                    'story_id' => $storyId,
                    'name' => 'Uncategorized',
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('questions')
                ->where('story_id', $storyId)
                ->whereNull('section_id')
                ->update(['section_id' => $sectionId]);
        }
    }

    public function down(): void
    {
        // One-way by design: the app no longer renders an ungrouped bucket,
        // so sweeping back would orphan questions in the UI again.
    }
};
