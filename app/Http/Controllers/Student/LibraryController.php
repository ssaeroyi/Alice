<?php

namespace App\Http\Controllers\Student;

use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\ReadingProgressService;
use App\Support\AliceLibrary;
use App\Support\LibraryPresentation;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LibraryController extends Controller
{
    /** Row cards per shelf; landings paginate at the same size. */
    private const PAGE_SIZE = 12;

    /** Valid sort orders for landing + collection browsing. */
    private const SORTS = ['title', 'newest', 'length', 'grade'];

    /** Reader-facing difficulty bands, each mapped to the single recalibrated
     * grade it represents (Easy = 7 ... Very Hard = 10 — AliceLibrary keeps a
     * band per grade on the 7-10 scale). Filtering by band is reader language:
     * the grade numbers hide in the drawer, the band matches the card pill.
     */
    private const DIFFICULTIES = [
        'easy' => 7,
        'intermediate' => 8,
        'hard' => 9,
        'very-hard' => 10,
    ];

    /**
     * Minimum word count for a story to be featured on the front-and-center
     * surfaces (Quick Reads, Recommended, hero fallback). Sub-150-ish word
     * single-paragraph texts read as truncated to older readers, so they stay
     * in All Stories / search / filters but are not pushed as prominent picks.
     */
    private const MIN_PROMINENT_WORDS = 200;

    /** §4.5 collection shelves, in tab-bar order. */
    private const COLLECTION_TABS = ['favorites', 'progress', 'finished'];

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('student.library', [
            'grade' => $this->displayGrade($user),
            'hero' => $this->heroStories(),
            'inProgress' => $this->inProgressQuery($user)
                ->limit(self::PAGE_SIZE)
                ->get(),
            'recommended' => $this->recommendedQuery($user)
                ->limit(self::PAGE_SIZE)
                ->get(),
            'genres' => $this->filterGenres('stories_count'),
            'quickReads' => $this->baseQuery()
                ->whereIn('duration_tier', LibraryPresentation::QUICK_TIERS)
                ->where('word_count', '>=', self::MIN_PROMINENT_WORDS)
                ->orderBy('word_count')
                ->limit(self::PAGE_SIZE)
                ->get(),
        ]);
    }

    public function all(Request $request, string $row): View|RedirectResponse
    {
        if ($row === 'in-progress') {
            // §4.5: the collection page owns this shelf now.
            return redirect()->route('collection', ['tab' => 'progress']);
        }

        if (! in_array($row, ['all', 'recommended', 'quick-reads'], true)) {
            abort(404);
        }

        $user = $request->user();
        $filters = $this->filters($request);

        $query = match ($row) {
            'recommended' => $this->recommendedQuery($user),
            'quick-reads' => $this->baseQuery()
                ->whereIn('duration_tier', LibraryPresentation::QUICK_TIERS)
                ->where('word_count', '>=', self::MIN_PROMINENT_WORDS),
            default => $this->baseQuery(),
        };

        $stories = $this->applyFilters($query, $filters)
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return view('student.library-landing', [
            'row' => $row,
            'grade' => $this->displayGrade($user),
            'stories' => $stories,
            'genres' => $this->filterGenres(),
            'filters' => $filters,
        ]);
    }

    /**
     * §4.5 My Collection: the student's three personal shelves. Tabs are
     * plain GET links so everything works without JavaScript; an unknown
     * ?tab= falls back to Favorites instead of erroring. A story may sit
     * on two shelves at once (a finished favourite) by design.
     */
    public function collection(Request $request): View
    {
        $user = $request->user();
        $requested = (string) $request->query('tab', 'favorites');
        $tab = in_array($requested, self::COLLECTION_TABS, true) ? $requested : 'favorites';
        $filters = $this->filters($request);

        return view('student.collection', [
            'tab' => $tab,
            'filters' => $filters,
            'genres' => $this->filterGenres(),
            'counts' => $this->collectionCounts($user, $filters),
            'stories' => $this->shelfQuery($user, $tab, $filters)->get(),
        ]);
    }

    public function genre(Request $request, string $slug): View
    {
        $genre = $this->resolveGenreBySlug($slug);

        abort_if($genre === null, 404);

        $user = $request->user();
        $filters = $this->filters($request);
        $filters['genre'] = $slug;

        $stories = $this->applyFilters(
            $this->baseQuery()->whereHas('genres', fn (Builder $query) => $query->where('genres.id', $genre->id)),
            $filters,
        )->paginate(self::PAGE_SIZE)->withQueryString();

        return view('student.library-landing', [
            'row' => 'genre',
            'genre' => $genre,
            'grade' => $this->displayGrade($user),
            'stories' => $stories,
            'genres' => $this->filterGenres(),
            'filters' => $filters,
        ]);
    }

    /**
     * §4.2 story details: every metadata chip computed from the row itself
     * (pure-function banding contract — the printed grade can never
     * contradict the difficulty pill) plus the favorite toggle state.
     */
    public function preview(Request $request, Story $story): View
    {
        abort_unless($story->status === StoryStatus::Published, 404);

        $story->load(['genres']);

        $progress = StoryProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('story_id', $story->id)
            ->first();

        $hasQuiz = $story->questions()->exists();

        // Step 2 reads from the attempts table, the source of truth for a
        // pass, so the checklist never disagrees with the badge that a passed
        // quiz grants (the progress row's cached best_score_pct can lag).
        $quizStats = $hasQuiz
            ? TestAttempt::query()
                ->where('user_id', $request->user()->id)
                ->where('story_id', $story->id)
                ->selectRaw('MAX(score_pct) AS best, MAX(passed) AS passed')
                ->first()
            : null;

        return view('student.preview', [
            'story' => $story,
            'band' => AliceLibrary::difficultyBand((int) $story->reading_level),
            'favorite' => $progress?->is_favorite ?? false,
            'hasQuiz' => $hasQuiz,
            'minutes' => LibraryPresentation::minutes((int) $story->word_count),
            // Quest checklist state: current_page stores scroll percent
            // (0-100); a favourite-only row still sits at 0 and reads as
            // "Not started" rather than a fabricated progress figure.
            'readStatus' => $progress?->status,
            'readPct' => match ($progress?->status) {
                StoryProgressStatus::Read, StoryProgressStatus::Completed => 100,
                default => min(100, (int) ($progress?->current_page ?? 0)),
            },
            'quizBest' => $quizStats?->best !== null ? (int) $quizStats->best : null,
            'quizPassed' => (bool) ($quizStats?->passed ?? false),
            'passPct' => (int) config('gamification.pass_threshold_pct'),
        ]);
    }

    /**
     * Favorite toggle: favorites may exist before any reading happens, so
     * this lazily creates the progress row (status reading, page 0). The
     * unique (user, story) constraint makes repeat posts idempotent flips.
     */
    public function toggleFavorite(Request $request, Story $story)
    {
        abort_unless($story->status === StoryStatus::Published, 404);

        $progress = StoryProgress::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'story_id' => $story->id,
        ], [
            'status' => StoryProgressStatus::Reading,
        ]);

        $progress->is_favorite = ! $progress->is_favorite;
        $progress->save();

        return back();
    }

    // ------------------------------------------------------------------

    private function baseQuery(): Builder
    {
        return Story::query()
            ->where('status', StoryStatus::Published)
            ->with('genres');
    }

    /**
     * The grade to surface to this student right now — their live,
     * evidence-based reading level (the "reading level that moves"), falling
     * back to the placement level until enough real reading evidence exists.
     */
    private function displayGrade($user): int
    {
        return ReadingProgressService::serveGrade($user);
    }

    /**
     * Carousel stories: admin-flagged picks when present, otherwise a
     * band-diverse fallback so the shelf never launches empty.
     */
    private function heroStories()
    {
        $featured = $this->baseQuery()
            ->where('featured', true)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        if ($featured->isNotEmpty()) {
            return $featured;
        }

        $bands = collect(['Easy', 'Intermediate', 'Hard', 'Very Hard'])
            ->map(fn (string $band) => $this->baseQuery()
                ->where('difficulty_label', $band)
                ->where('word_count', '>=', self::MIN_PROMINENT_WORDS)
                ->latest('id')
                ->first())
            ->filter();

        $pickedIds = $bands->pluck('id')->all();

        return $bands
            ->concat($this->baseQuery()
                ->whereNotIn('id', $pickedIds)
                ->where('word_count', '>=', self::MIN_PROMINENT_WORDS)
                ->latest('id')
                ->limit(max(0, 6 - $bands->count()))
                ->get())
            ->take(6)
            ->values();
    }

    /** Reading-in-progress + favorites shelf, most recently touched first. */
    private function inProgressQuery($user): Builder
    {
        return Story::query()
            ->where('stories.status', StoryStatus::Published)
            ->with('genres')
            ->join('story_progress', fn ($join) => $join
                ->on('story_progress.story_id', '=', 'stories.id')
                ->where('story_progress.user_id', $user->id))
            ->where(function (Builder $query) {
                $query->where('story_progress.is_favorite', true)
                    ->orWhere('story_progress.status', StoryProgressStatus::Reading->value);
            })
            ->orderByDesc('story_progress.updated_at')
            ->addSelect('stories.*', 'story_progress.current_page', 'story_progress.is_favorite', 'story_progress.completed_at as progress_completed_at');
    }

    /** One collection shelf; the joined aliases feed the story-card chips. */
    private function shelfQuery($user, string $shelf, array $filters = []): Builder
    {
        $query = Story::query()
            ->where('stories.status', StoryStatus::Published)
            ->with('genres')
            ->join('story_progress', fn ($join) => $join
                ->on('story_progress.story_id', '=', 'stories.id')
                ->where('story_progress.user_id', $user->id))
            ->when($shelf === 'favorites', fn (Builder $query) => $query
                ->where('story_progress.is_favorite', true))
            ->when($shelf === 'progress', fn (Builder $query) => $query
                ->where('story_progress.status', StoryProgressStatus::Reading->value)
                ->where('story_progress.current_page', '>', 0))
            ->when($shelf === 'finished', fn (Builder $query) => $query
                ->whereIn('story_progress.status', [
                    StoryProgressStatus::Read->value,
                    StoryProgressStatus::Completed->value,
                ]))
            ->addSelect('stories.*', 'story_progress.current_page', 'story_progress.is_favorite', 'story_progress.completed_at as progress_completed_at');

        if ($filters !== []) {
            $this->applyFilterCriteria($query, $filters);
        }

        // An explicit sort overrides the collection's natural recency order.
        if (($filters['sort'] ?? null) !== null && $filters['sort'] !== 'title') {
            $this->applyShelfSort($query, $filters['sort']);
        } else {
            $query->orderByDesc('story_progress.updated_at');
        }

        return $query;
    }

    /**
     * Single-query aggregate for all three collection tab counts (favorites /
     * in-progress / finished) sharing the same user+filter base, collapsing
     * three separate COUNT queries into one conditional-SUM.
     *
     * @return array{favorites: int, progress: int, finished: int}
     */
    private function collectionCounts(User $user, array $filters = []): array
    {
        $row = Story::query()
            ->where('stories.status', StoryStatus::Published)
            ->join('story_progress', fn ($join) => $join
                ->on('story_progress.story_id', '=', 'stories.id')
                ->where('story_progress.user_id', $user->id))
            ->selectRaw('
                SUM(CASE WHEN story_progress.is_favorite = 1 THEN 1 ELSE 0 END) as favorites,
                SUM(CASE WHEN story_progress.status = ? AND story_progress.current_page > 0 THEN 1 ELSE 0 END) as progress,
                SUM(CASE WHEN story_progress.status IN (?, ?) THEN 1 ELSE 0 END) as finished
            ', [
                StoryProgressStatus::Reading->value,
                StoryProgressStatus::Read->value,
                StoryProgressStatus::Completed->value,
            ])
            ->when($filters !== [], fn ($q) => $this->applyFilterCriteria($q, $filters))
            ->first();

        return [
            'favorites' => (int) ($row->favorites ?? 0),
            'progress' => (int) ($row->progress ?? 0),
            'finished' => (int) ($row->finished ?? 0),
        ];
    }

    /** Apply the shared landing filters (grade/genre/sort/search) to a base story query. */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $this->applyFilterCriteria($query, $filters);
        $this->applyShelfSort($query, $filters['sort'] ?? null);

        return $query;
    }

    /**
     * JSON suggestions for the library search combobox: title/author matches
     * only, a handful of rows, no navigation. Never errors on short input —
     * returns an empty list so the dropdown just stays shut.
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $needle = '[[:<:]]'.preg_quote($q, '/');

        $results = $this->baseQuery()
            ->where(fn (Builder $query) => $query->where('title', 'regexp', $needle)->orWhere('author', 'regexp', $needle))
            ->orderBy('title')
            ->limit(6)
            ->get(['title', 'author', 'slug'])
            ->map(fn (Story $story) => [
                'title' => $story->title,
                'author' => $story->author,
                'url' => route('story.preview', $story->slug),
            ])
            ->all();

        return response()->json(['results' => $results]);
    }

    /**
     * Apply the shared filter criteria (search / grade / genre) to any story
     * query — used by landings and (minus search) by collection shelves. Sort
     * is intentionally not applied here so the caller chooses.
     */
    private function applyFilterCriteria(Builder $query, array $filters): void
    {
        if (($filters['q'] ?? null) !== null) {
            // Every whitespace-separated term must word-START-match the title
            // or the author ("the" hits "The Legend", never "Mother"; "phil"
            // hits "Philip"). Multi-word queries like "hans andersen" stay
            // useful because each term must find its own home.
            $terms = preg_split('/\s+/', trim($filters['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $query->where(function (Builder $inner) use ($terms) {
                foreach ($terms as $term) {
                    // Escape regex metacharacters so "c++" or "love?" are
                    // searched literally.
                    $pattern = '[[:<:]]'.preg_quote($term, '/');

                    $inner->where(function (Builder $row) use ($pattern) {
                        $row->where('title', 'regexp', $pattern)
                            ->orWhere('author', 'regexp', $pattern);
                    });
                }
            });
        }

        if (($filters['grade'] ?? null) !== null) {
            $query->whereBetween('reading_level', [
                max(AliceLibrary::bandFloor($filters['grade']), 1),
                min(AliceLibrary::bandCeiling($filters['grade']), 12),
            ]);
        }

        if (($filters['genre'] ?? null) !== null) {
            $genreId = $this->resolveGenreBySlug($filters['genre'])?->id;

            $query->whereHas('genres', fn (Builder $inner) => $inner->where('genres.id', $genreId ?? 0));
        }
    }

    /** Deterministic sort orders for landings and collection shelves. */
    private function applyShelfSort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('stories.id'),
            'length' => $query->orderBy('stories.word_count')->orderBy('stories.title'),
            'grade' => $query->orderBy('stories.reading_level')->orderBy('stories.title'),
            default => $query->orderBy('stories.title'),
        };
    }

    /**
     * Stories served to this student around their live reading grade. Low-band
     * (Critical/Frustration) students are steered to the grade just below
     * theirs, short stories first, so early wins are achievable — the active
     * mitigation rail. Everyone else gets the grade band ±1.
     */
    private function recommendedQuery($user): Builder
    {
        $level = $this->displayGrade($user);
        $band = min(10, max(7, $level));

        if (ReadingProgressService::isLowBand($user)) {
            $floor = max(7, $band - 1);

            return $this->baseQuery()
                ->where('word_count', '>=', self::MIN_PROMINENT_WORDS)
                ->whereBetween('reading_level', [$floor, $band])
                ->orderBy('word_count')
                ->orderBy('title');
        }

        return $this->baseQuery()
            ->where('word_count', '>=', self::MIN_PROMINENT_WORDS)
            ->whereBetween('reading_level', [max(7, $band - 1), min(10, $band + 1)])
            ->orderByRaw('ABS(CAST(reading_level AS SIGNED) - ?), title', [$band]);
    }

    /**
     * Landing filters shared by every landing (and the collection shelves):
     * free-text search, difficulty band (accepts the legacy grade param too),
     * genre slug and sort order.
     *
     * @return array{q: ?string, grade: ?int, genre: ?string, sort: string}
     */
    private function filters(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));

        $grade = null;
        $difficulty = trim((string) $request->query('difficulty', ''));
        if (isset(self::DIFFICULTIES[$difficulty])) {
            $grade = self::DIFFICULTIES[$difficulty];
        } elseif (ctype_digit((string) $request->query('grade'))
            && (int) $request->query('grade') >= 7
            && (int) $request->query('grade') <= 10) {
            // Legacy links may still carry ?grade=; map onto the same band.
            $grade = (int) $request->query('grade');
        }

        $genre = trim((string) $request->query('genre', ''));
        $sort = (string) $request->query('sort', 'title');

        return [
            'q' => $q !== '' ? $q : null,
            'grade' => $grade,
            // Only honour slugs that actually map to a published genre; unknown
            // slugs are ignored rather than filtering every story out.
            'genre' => $genre !== '' && isset($this->genreSlugMap()[$genre]) ? $genre : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'title',
        ];
    }

    private function filterGenres(string $direction = 'name'): Collection
    {
        $cacheKey = "genres:with_counts:{$direction}";

        $rows = Cache::remember($cacheKey, 300, function () use ($direction) {
            $query = Genre::query()
                ->select(['id', 'name'])
                ->withCount(['stories' => fn (Builder $query) => $query->where('status', StoryStatus::Published)])
                ->having('stories_count', '>', 0);

            $query = $direction === 'stories_count'
                ? $query->orderByDesc('stories_count')
                : $query->orderBy('name');

            return $query->get()->map(fn (Genre $genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
                'stories_count' => $genre->stories_count,
            ])->values()->all();
        });

        // Rebuild lightweight Genre models in memory from the cached plain
        // arrays. Never cache Eloquent models/collections — serializing them
        // into the cache store produces "incomplete object" crashes when the
        // model classes aren't loaded yet on read.
        return collect($rows)->map(function (array $row) {
            $genre = new Genre;
            $genre->setAttribute('id', $row['id']);
            $genre->setAttribute('name', $row['name']);
            $genre->setAttribute('stories_count', $row['stories_count']);

            return $genre;
        });
    }

    /**
     * Forget both cached genre-count directions (name + stories_count). Called
     * whenever a story's publish status changes, since published counts shift.
     */
    public static function forgetGenreCounts(): void
    {
        Cache::forget('genres:with_counts:name');
        Cache::forget('genres:with_counts:stories_count');
    }

    private function resolveGenreBySlug(string $slug): ?Genre
    {
        return $this->genreSlugMap()[$slug] ?? null;
    }

    /**
     * Per-request cached slug→Genre map so repeated lookups (filter criteria +
     * genre route) hit the DB once instead of full-table scans each time.
     */
    private function genreSlugMap(): array
    {
        return once(fn () => collect(Genre::all())
            ->mapWithKeys(fn (Genre $g) => [LibraryPresentation::genreSlug($g->name) => $g])
            ->all());
    }
}
