<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SavedWord;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\RewardService;
use App\Services\StudyService;
use App\Support\Dictionary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Study (formerly Word Gym): dashboard, flashcard review sessions, saved
 * word management, and the Webster-style dictionary browser. Flashcard
 * grading is currency-free by design - self-grading never pays keys or
 * points. It does, however, stamp a "drill" major action on each graded
 * flashcard so the daily study-drill task, the activity chart's drill
 * bars and the daily study goal all progress from the same real event.
 */
class StudyController extends Controller
{
    public function __construct(private readonly StudyService $study) {}

    // ------------------------------------------------------------------
    // Dashboard
    // ------------------------------------------------------------------

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        $due = $this->study->dueCount($user);
        $goal = $this->study->goalFor($user);
        $reviewedToday = $this->study->reviewedTodayCount($user);
        $batchSize = $this->study->batchSizeFor($user);

        return view('student.study', [
            'stats' => $this->study->statsFor($user),
            'totalWords' => $user->savedWords()->count(),
            'forgottenCount' => $this->study->forgottenCount($user),
            'queueSize' => min($due, $batchSize),
            'bonusAvailable' => $reviewedToday >= $goal && $due > 0,
            'allCaughtUp' => $due === 0,
            'reviewedToday' => $reviewedToday,
            'goal' => $goal,
            'goalMin' => (int) config('study.min_goal'),
            'goalMax' => (int) config('study.max_goal'),
            'goalStep' => (int) config('study.goal_step'),
            'encouragement' => $this->study->encouragementFor($user),
            'goalComplete' => $due > 0 && $reviewedToday >= $goal,
        ]);
    }

    public function updateGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal' => ['required', 'integer', 'min:'.config('study.min_goal'), 'max:'.config('study.max_goal')],
        ]);

        $request->user()->update(['daily_study_goal' => $validated['goal']]);

        $fresh = $request->user()->fresh();
        $due = $this->study->dueCount($fresh);
        $batchSize = $this->study->batchSizeFor($fresh);

        return response()->json([
            'goal' => $this->study->goalFor($fresh),
            'queue_size' => min($due, $batchSize),
        ]);
    }

    // ------------------------------------------------------------------
    // Flashcard session
    // ------------------------------------------------------------------

    public function session(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $cards = $this->study->queueFor($user);

        if ($cards->isEmpty()) {
            return redirect()
                ->route('study')
                ->with('toast', ['message' => "You're all caught up. Nice work!"]);
        }

        return view('student.study-flashcards', [
            'cards' => $cards->map(fn (SavedWord $word) => $this->cardPayload($word))->values(),
        ]);
    }

    public function grade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'saved_word_id' => ['required', 'integer'],
            'grade' => ['required', Rule::in(StudyService::GRADES)],
        ]);

        $word = SavedWord::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($validated['saved_word_id']);

        $this->study->applyGrade($word, $validated['grade']);

        // Every graded flashcard is a real study action: stamp the "drill"
        // major action so the daily study-drill task and the activity chart's
        // drill bars progress from this event. Currency is never paid here.
        RewardService::stampDay($request->user(), 'drill', now());

        $badges = BadgeService::award($request->user(), ['drill', 'freeze'], now());
        if ($badges->isNotEmpty()) {
            $request->session()->flash('badges_awarded', BadgeService::summarize($badges));
        }

        return response()->json([
            'ok' => true,
            'mastery_level' => $word->mastery_level,
            'tier' => $this->study->tierFor($word->mastery_level),
            'status' => $this->study->statusFor($word),
        ]);
    }

    private function cardPayload(SavedWord $word): array
    {
        return [
            'id' => $word->id,
            'word' => $word->word,
            'definition' => $word->definition,
            'part_of_speech' => $word->part_of_speech,
            'example_sentence' => $word->example_sentence,
        ];
    }

    // ------------------------------------------------------------------
    // Saved word management
    // ------------------------------------------------------------------

    public function dictionary(Request $request): View
    {
        $q = trim((string) $request->query('word', ''));
        $entry = $q !== '' ? Dictionary::lookup($q) : null;

        $savedWord = null;

        if ($entry !== null && $entry['found']) {
            $savedWord = $request->user()->savedWords()->where('word', $entry['resolved_to'])->first();
        }

        return view('student.study-dictionary', [
            'entry' => $entry,
            'query' => $q !== '' ? $q : null,
            'savedWord' => $savedWord,
            'status' => $savedWord !== null ? $this->study->statusFor($savedWord) : null,
            'tier' => $savedWord !== null ? $this->study->tierFor($savedWord->mastery_level) : null,
            'back' => $this->safeBack($request),
        ]);
    }

    /**
     * Accept a reader round-trip target only when it is an absolute URL on
     * the application's own host, so the dictionary back button can never be
     * turned into an open redirect. Anything else falls back to the Study
     * dashboard link rendered by the view.
     */
    private function safeBack(Request $request): ?string
    {
        $raw = trim((string) $request->query('back', ''));

        if ($raw === '') {
            return null;
        }

        $parts = parse_url($raw);

        if (! isset($parts['host']) || ! isset($parts['scheme']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $appHost = (string) parse_url(config('app.url'), PHP_URL_HOST);

        return strcasecmp($parts['host'], $appHost) === 0 ? $raw : null;
    }

    public function words(Request $request): View
    {
        $filters = $this->wordFilters($request);
        $groups = $this->groupedWords($request->user(), $filters);

        return view('student.study-words', [
            'groups' => $groups,
            'totalCount' => $request->user()->savedWords()->count(),
            'q' => $filters['q'],
            'sort' => $filters['sort'],
            'filter' => $filters['filter'],
        ]);
    }

    /**
     * Fragment payload for AJAX swaps: only the scrolling results region.
     */
    public function wordsResults(Request $request): View
    {
        $filters = $this->wordFilters($request);

        return view('student.partials.study-word-results', [
            'groups' => $this->groupedWords($request->user(), $filters),
            'q' => $filters['q'],
            'filter' => $filters['filter'],
        ]);
    }

    public function showWord(Request $request, SavedWord $savedWord): View
    {
        abort_unless($savedWord->user_id === $request->user()->id, 404);

        $entry = Dictionary::lookup($savedWord->word);

        return view('student.study-word', [
            'entry' => $entry,
            'context' => 'saved',
            'savedWord' => $savedWord,
            'status' => $this->study->statusFor($savedWord),
            'tier' => $this->study->tierFor($savedWord->mastery_level),
        ]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete', 'reset', 'master'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $query = SavedWord::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids']);

        $count = (clone $query)->count();

        match ($validated['action']) {
            'delete' => $query->delete(),
            'reset' => $query->update([
                'mastery_level' => 0,
                'times_correct' => 0,
                'times_wrong' => 0,
                'last_drilled_at' => null,
                'due_at' => null,
            ]),
            'master' => $query->update([
                'mastery_level' => 4,
                'last_drilled_at' => now(),
                'due_at' => now()->addDays((int) ((array) config('study.intervals'))[4][3]),
            ]),
        };

        return response()->json(['ok' => true, 'affected' => $count]);
    }

    /** @return array{q: ?string, sort: string, filter: ?string} */
    private function wordFilters(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));

        return [
            'q' => $q !== '' ? $q : null,
            'sort' => in_array($request->query('sort'), ['date', 'alpha', 'mastery'], true)
                ? $request->query('sort')
                : 'date',
            'filter' => in_array($request->query('filter'), ['forgotten', 'strong', 'learning', 'not-studied'], true)
                ? $request->query('filter')
                : null,
        ];
    }

    private function wordsQuery(User $user, array $filters)
    {
        $strong = (int) config('study.learning_level');

        $query = SavedWord::query()->where('user_id', $user->id);

        if ($filters['q'] !== null) {
            $needle = "%{$filters['q']}%";
            $query->where(fn ($inner) => $inner
                ->where('word', 'like', $needle)
                ->orWhere('definition', 'like', $needle));
        }

        if ($filters['filter'] === 'forgotten') {
            $query->where('times_wrong', '>', 0)
                ->where('mastery_level', '<', $strong);
        } elseif ($filters['filter'] === 'strong') {
            $query->where('mastery_level', '>=', $strong);
        } elseif ($filters['filter'] === 'learning') {
            $query->where('mastery_level', '>=', 1)
                ->where('mastery_level', '<', $strong);
        } elseif ($filters['filter'] === 'not-studied') {
            $query->where('mastery_level', 0);
        }

        return match ($filters['sort']) {
            'alpha' => $query->orderBy('word'),
            'mastery' => $query->orderByRaw('CASE WHEN last_drilled_at IS NULL THEN 0 ELSE mastery_level END ASC')->orderBy('word'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * Query + decorate words, grouped by add-date (newest first). Each
     * item is ['model' => SavedWord, 'status' => string, 'tier' => string].
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupedWords(User $user, array $filters): array
    {
        $groups = [];

        foreach ($this->wordsQuery($user, $filters)->get() as $word) {
            $key = optional($word->created_at)->format('Y-m-d') ?? 'unknown';
            $groups[$key][] = [
                'model' => $word,
                'status' => $this->study->statusFor($word),
                'tier' => $this->study->tierFor($word->mastery_level),
            ];
        }

        krsort($groups);

        return $groups;
    }
}
