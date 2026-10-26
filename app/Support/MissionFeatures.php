<?php

namespace App\Support;

use App\Games\Journal\AuthoredActions;
use App\Models\Story;

/**
 * Word-feasibility snapshot per story for the teacher journal tab: which
 * actions this story's text and vocab can actually support and how many
 * candidates each one has. The gallery grays out moves below their floor,
 * so a teacher never authors a move the story cannot pay out.
 *
 * Computed fresh from the LIVE content + story_vocab every time the story
 * editor opens (single call site in StoryController::edit), then persisted
 * to stories.mission_features as a read snapshot. Students never read this:
 * BattleFactory always revalidates against live data at battle time.
 */
class MissionFeatures
{
    /**
     * Recompute the catalogue rows from live content and persist the
     * count map when it drifted.
     *
     * @return list<array{key: string, type: string, value: ?string, label: string, group: string, count: int, unit: string, need_floor: int, prompt: string, flavor: string}>
     */
    public static function refresh(Story $story): array
    {
        $rows = AuthoredActions::catalog($story);

        $snapshot = [];
        foreach ($rows as $row) {
            $snapshot[$row['key']] = $row['count'];
        }

        if ($story->mission_features !== $snapshot) {
            Story::whereKey($story->id)->update(['mission_features' => $snapshot]);
            $story->mission_features = $snapshot;
            $story->syncOriginalAttribute('mission_features');
        }

        return $rows;
    }
}
