<?php

namespace App\Http\Controllers\Student\Concerns;

use App\Enums\StoryStatus;
use App\Models\Story;

/**
 * Shared story-access gate. ReaderController and MissionController both
 * require a published story; the mission API must never answer for
 * drafts/previews.
 */
trait ReadsStories
{
    private function assertReadable(Story $story): void
    {
        abort_unless($story->status === StoryStatus::Published, 404);
    }
}
