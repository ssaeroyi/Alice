<?php

namespace App\Support;

use App\Models\Story;

/**
 * The story completeness meter: which metadata fields are filled, what
 * still blocks publishing, and the overall percent.
 *
 * Core fields gate publishing (a story with no text is empty in the
 * library, so content is part of the gate too); the optional fields
 * (synopsis, genres, cover, source URL) count toward the meter but never
 * block a publish on their own. The reading level is derived by the
 * readability analyzer from the story text, so it fills in on its own
 * once the text is long enough.
 */
class StoryCompleteness
{
    /** @var array<string, string> field key => friendly label */
    public const FIELDS = [
        'title' => 'Title',
        'author' => 'Author',
        'license' => 'License',
        'source_url' => 'Source',
        'reading_level' => 'Reading level (auto)',
        'content' => 'Story text',
        'synopsis' => 'Synopsis',
        'genres' => 'Genres',
        'cover_image' => 'Cover',
    ];

    /** Field keys that gate publishing. */
    public const CORE = ['title', 'author', 'license', 'content'];

    /** @param array<string, bool> $fields */
    public function __construct(
        public readonly array $fields,
    ) {}

    public static function evaluate(Story $story): self
    {
        $hasGenres = $story->relationLoaded('genres')
            ? $story->genres->isNotEmpty()
            : $story->genres()->exists();

        return new self([
            'title' => filled($story->title),
            'author' => filled($story->author),
            'license' => filled($story->license),
            'source_url' => filled($story->source_url),
            'reading_level' => $story->reading_level !== null,
            'content' => filled($story->content),
            'synopsis' => filled($story->synopsis),
            'genres' => $hasGenres,
            'cover_image' => filled($story->cover_image),
        ]);
    }

    public function total(): int
    {
        return count(self::FIELDS);
    }

    public function done(): int
    {
        return count(array_filter($this->fields));
    }

    public function percent(): int
    {
        return (int) round($this->done() / $this->total() * 100);
    }

    /**
     * Friendly labels of the fields still missing.
     *
     * @return array<int, string>
     */
    public function missing(): array
    {
        $labels = [];

        foreach (self::FIELDS as $key => $label) {
            if (! $this->fields[$key]) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function publishable(): bool
    {
        foreach (self::CORE as $key) {
            if (! $this->fields[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Friendly labels of the core fields still missing.
     *
     * @return array<int, string>
     */
    public function missingCore(): array
    {
        $labels = [];

        foreach (self::CORE as $key) {
            if (! $this->fields[$key]) {
                $labels[] = self::FIELDS[$key];
            }
        }

        return $labels;
    }
}
