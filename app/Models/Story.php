<?php

namespace App\Models;

use App\Enums\StoryStatus;
use App\Support\StorySlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['gutenberg_id', 'condensed_from_id', 'copied_from_id', 'slug', 'title', 'author', 'license', 'source_url', 'reading_level', 'synopsis', 'content', 'word_count', 'cover_image', 'status', 'created_by', 'duration_tier', 'quiz_difficulty', 'difficulty_label', 'difficulty_breakdown', 'provenance', 'featured', 'missions_enabled', 'mission_features'])]
class Story extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Story $story) {
            if ($story->slug === null) {
                $story->slug = StorySlug::make($story->title);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => StoryStatus::class,
            'reading_level' => 'integer',
            'word_count' => 'integer',
            'difficulty_breakdown' => 'array',
            'featured' => 'boolean',
            'missions_enabled' => 'boolean',
            'mission_features' => 'array',
        ];
    }

    /**
     * Browser-ready cover URL from whatever the column holds: pasted
     * http(s) URLs pass through untouched, storage-disk paths (uploads
     * and seeded manifest covers alike) resolve through /storage.
     */
    public function coverUrl(): string
    {
        if ($this->cover_image === null || $this->cover_image === '') {
            return '';
        }

        if (str_starts_with($this->cover_image, 'http')) {
            return $this->cover_image;
        }

        // A declared cover whose file went missing (delete + re-import
        // drift) degrades to the bundled placeholder instead of a broken
        // 403 tile; the next library import self-heals the real file.
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($this->cover_image)) {
            return asset('images/placeholder-cover.svg');
        }

        return asset('storage/'.$this->cover_image);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'story_genre');
    }

    public function storyProgress(): HasMany
    {
        return $this->hasMany(StoryProgress::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuestionSection::class)->orderBy('position');
    }

    /** Authored Field Journal battle moves (teacher journal tab). */
    public function missionActions(): HasMany
    {
        return $this->hasMany(StoryMissionAction::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function condensedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'condensed_from_id');
    }

    public function condensedDerivative(): HasOne
    {
        return $this->hasOne(self::class, 'condensed_from_id');
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }
}
