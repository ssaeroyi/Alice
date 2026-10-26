<?php

namespace App\Support;

use App\Models\Story;

/**
 * Collision-safe URL slugs for stories. Titles are not unique in the
 * catalog (teacher uploads may repeat), so duplicates get -2, -3, ...
 * suffixes. Slugs are assigned once at creation and never change on
 * rename — story URLs stay stable.
 */
class StorySlug
{
    private const MAX_LENGTH = 120;

    public static function make(string $title): string
    {
        $base = self::slugify($title);

        $taken = Story::query()
            ->where('slug', 'like', $base.'%')
            ->pluck('slug')
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        for ($suffix = 2; ; $suffix++) {
            $candidate = substr($base, 0, self::MAX_LENGTH - strlen((string) $suffix) - 1).'-'.$suffix;

            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }
    }

    public static function slugify(string $title): string
    {
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($title)));

        return substr(trim($slug, '-'), 0, self::MAX_LENGTH);
    }
}
