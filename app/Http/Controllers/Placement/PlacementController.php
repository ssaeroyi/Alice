<?php

namespace App\Http\Controllers\Placement;

use App\Enums\GradeLevel;
use App\Enums\PlacementAttemptStatus;
use App\Enums\PlacementStep;
use App\Http\Controllers\Controller;
use App\Models\PlacementAttempt;
use App\Models\PlacementSet;
use App\Services\PlacementService;
use App\Support\AliceLibrary;
use App\Support\Readability;
use App\Support\SchoolYear;
use App\Support\SeededShuffle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlacementController extends Controller
{
    /**
     * The scored steps, in the order a student must complete them.
     *
     * @var array<int, PlacementStep>
     */
    private array $scoredSteps = [
        PlacementStep::Vocab,
        PlacementStep::Cloze,
        PlacementStep::Comprehension,
    ];

    public function __construct(private readonly PlacementService $placements) {}

    public function showStep1(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_setup_complete) {
            return redirect()->route('hub')->with('toast', [
                'message' => 'You already finished the quick check.',
            ]);
        }

        $this->attempt();

        return view('placement.step-1-name', [
            'gradeLevels' => GradeLevel::cases(),
            'selectedGrade' => $request->user()->grade_level?->value,
            'selectedGoal' => $request->user()->daily_reading_goal,
        ]);
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        $this->attempt();

        $validated = $request->validate([
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'daily_reading_goal' => ['nullable', 'integer', 'min:'.config('placement.reading_goal_min'), 'max:'.config('placement.reading_goal_max')],
        ], [
            'grade_level.required' => 'Please select your grade.',
        ]);

        $request->user()->update([
            'grade_level' => $validated['grade_level'],
            'grade_anchor_year' => SchoolYear::startYear(),
            'daily_reading_goal' => $validated['daily_reading_goal'] ?? null,
        ]);

        return redirect()->route('placement.intro');
    }

    /**
     * The friendly pitch screen between profile setup and the scored check.
     * Stores nothing; its only gate is that profile setup (step 1) is done.
     */
    public function showIntro(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_setup_complete) {
            return redirect()->route('hub');
        }

        if ($request->user()->grade_level === null) {
            return redirect()->route('placement.step1');
        }

        $this->attempt();

        return view('placement.intro');
    }

    public function showStep2(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_setup_complete) {
            return redirect()->route('hub');
        }

        $attempt = $this->attempt();

        if ($redirect = $this->guardStoredStep($request, $attempt, PlacementStep::Vocab)) {
            return $redirect;
        }

        return view('placement.step-2-vocab', [
            'set' => $attempt->set,
            'vocab' => $this->shuffledItems($attempt, 'vocab', $attempt->set->content['vocab']),
            'saved' => $this->savedAnswers($attempt, PlacementStep::Vocab),
        ]);
    }

    public function storeStep2(Request $request): RedirectResponse
    {
        $attempt = $this->attempt();

        if ($redirect = $this->guardStoredStep($request, $attempt, PlacementStep::Vocab)) {
            return $redirect;
        }

        $payload = $this->validateAndPayload($request, $this->shuffledItems($attempt, 'vocab', $attempt->set->content['vocab']), 'vocab');

        $attempt->responses()->updateOrCreate(
            ['step' => PlacementStep::Vocab->value],
            ['payload' => $payload],
        );

        return redirect()->route('placement.step3');
    }

    public function showStep3(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_setup_complete) {
            return redirect()->route('hub');
        }

        $attempt = $this->attempt();

        if ($redirect = $this->guardStoredStep($request, $attempt, PlacementStep::Cloze)) {
            return $redirect;
        }

        $content = $attempt->set->content;
        $cloze = $content['cloze'];
        $cloze['blanks'] = $this->shuffledItems($attempt, 'cloze', $content['cloze']['blanks']);

        return view('placement.step-3-cloze', [
            'set' => $attempt->set,
            'cloze' => $cloze,
            'saved' => $this->savedAnswers($attempt, PlacementStep::Cloze),
        ]);
    }

    public function storeStep3(Request $request): RedirectResponse
    {
        $attempt = $this->attempt();

        if ($redirect = $this->guardStoredStep($request, $attempt, PlacementStep::Cloze)) {
            return $redirect;
        }

        $payload = $this->validateAndPayload($request, $this->shuffledItems($attempt, 'cloze', $attempt->set->content['cloze']['blanks']), 'cloze');

        $attempt->responses()->updateOrCreate(
            ['step' => PlacementStep::Cloze->value],
            ['payload' => $payload],
        );

        return redirect()->route('placement.step4');
    }

    public function showStep4(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_setup_complete) {
            return redirect()->route('hub');
        }

        $attempt = $this->attempt();

        if ($redirect = $this->guardStoredStep($request, $attempt, PlacementStep::Comprehension)) {
            return $redirect;
        }

        $content = $attempt->set->content;
        $comprehension = $content['comprehension'];
        $comprehension['questions'] = $this->shuffledItems($attempt, 'comprehension', $content['comprehension']['questions']);

        return view('placement.step-4-comprehension', [
            'set' => $attempt->set,
            'comprehension' => $comprehension,
            'saved' => $this->savedAnswers($attempt, PlacementStep::Comprehension),
        ]);
    }

    public function storeStep4(Request $request): RedirectResponse
    {
        $attempt = $this->attempt();

        if ($redirect = $this->guardStoredStep($request, $attempt, PlacementStep::Comprehension)) {
            return $redirect;
        }

        $payload = $this->validateAndPayload($request, $this->shuffledItems($attempt, 'comprehension', $attempt->set->content['comprehension']['questions']), 'comp');

        $attempt->responses()->updateOrCreate(
            ['step' => PlacementStep::Comprehension->value],
            ['payload' => $payload],
        );

        $this->placements->complete($attempt);

        // New account is fully set up; flag the hub to greet the student once.
        $request->session()->put('pending_welcome', true);

        return redirect()->route('placement.step5');
    }

    public function showStep5(Request $request): View|RedirectResponse
    {
        $attempt = $this->attempt();

        if ($request->user()->grade_level === null) {
            return redirect()->route('placement.step1');
        }

        if (! $this->allScoredCompleted($attempt)) {
            return $this->redirectToNext($attempt);
        }

        $result = $this->placements->scoreOf($attempt);
        $user = $request->user();
        $serveGrade = min(10, max((int) config('placement.reading_level_floor'), (int) $user->reading_level));
        $grade = (int) ($user->grade_level?->value ?? 0);

        return view('placement.step-5-result', [
            'set' => $attempt->set,
            'result' => $result,
            'review' => $this->reviewFor($attempt),
            'readingLevel' => (int) $user->reading_level,
            'serveGrade' => $serveGrade,
            'diagnosedBelowFloor' => $grade > 0 && $grade < (int) config('placement.reading_level_floor'),
            'bandLabel' => AliceLibrary::difficultyBand($serveGrade),
            'motivation' => $result['level']->motivation((string) $attempt->id),
        ]);
    }

    /**
     * Diagnostic report for a completed attempt, one clean page that proves
     * every score: item-by-item answers, the threshold math, and the
     * reading-level derivation. Owner only (the student who took the test).
     */
    public function showReport(PlacementAttempt $attempt): View
    {
        abort_unless(auth()->id() === $attempt->user_id, 403);

        $result = $this->placements->scoreOf($attempt);
        $grade = (int) ($attempt->user->grade_level?->value ?? 0);
        $offset = (int) config('placement.reading_level_offsets.'.$result['level']->value);
        $content = $attempt->set->content;

        return view('placement.report', [
            'attempt' => $attempt,
            'result' => $result,
            'review' => $this->reviewFor($attempt),
            'grade' => $grade,
            'offset' => $offset,
            'readability' => [
                'cloze' => $this->readabilityOf($content['cloze']['text']),
                'comprehension' => $this->readabilityOf($content['comprehension']['passage']),
            ],
        ]);
    }

    /**
     * @return array{flesch: float, grade: float}
     */
    private function readabilityOf(string $text): array
    {
        return [
            'flesch' => round(Readability::fleschReadingEase($text) ?? 0, 1),
            'grade' => round(Readability::fleschKincaidGrade($text) ?? 0, 1),
        ];
    }

    /**
     * Build the per-item answer review (authored order) for the result screen
     * and the diagnostic report. Comparisons are text-based, mirroring the
     * scoring logic.
     */
    private function reviewFor(PlacementAttempt $attempt): array
    {
        $content = $attempt->set->content;
        $sections = [
            'vocab' => $content['vocab'],
            'cloze' => $content['cloze']['blanks'],
            'comprehension' => $content['comprehension']['questions'],
        ];
        $payloads = [];
        $review = [];

        foreach ($attempt->responses as $response) {
            $payloads[$response->step->value] = $response->payload;
        }

        foreach ($sections as $key => $items) {
            $payload = $payloads[PlacementStep::from($key)->value] ?? [];

            foreach ($items as $i => $item) {
                $answer = $payload[$i + 1] ?? null;

                $review[$key][] = [
                    'question' => match ($key) {
                        'vocab' => $item['word'],
                        'cloze' => 'Blank '.$item['key'],
                        default => $item['prompt'],
                    },
                    'answer' => $answer,
                    'expected' => $item['answer'],
                    'correct' => $answer === $item['answer'],
                ];
            }
        }

        return $review;
    }

    /**
     * Copy the authored items, deterministically shuffling each item's
     * options for this attempt. The same seed reproduces the same order,
     * so renders and validation always agree, while each attempt differs.
     */
    private function shuffledItems(PlacementAttempt $attempt, string $stepKey, array $items): array
    {
        $shuffled = [];

        foreach ($items as $i => $item) {
            $shuffled[$i] = $item;
            $shuffled[$i]['options'] = SeededShuffle::options(
                $item['options'],
                "attempt-{$attempt->id}-{$stepKey}-{$i}",
            );
        }

        return $shuffled;
    }

    /**
     * Resolve (or create) the current user's placement attempt, assigning a
     * random set on first creation.
     */
    private function attempt(): PlacementAttempt
    {
        $setId = PlacementSet::inRandomOrder()->value('id');

        abort_unless($setId !== null, 503, 'Placement sets are not configured.');

        return PlacementAttempt::firstOrCreate(
            ['user_id' => auth()->id()],
            [
                'set_id' => $setId,
                'status' => PlacementAttemptStatus::InProgress,
                'started_at' => now(),
            ],
        );
    }

    /**
     * Shared guard for viewing and storing a scored step: completed students
     * go to the hub, answers submitted before step 1 are bounced without
     * saving, and skipping ahead past an unfinished step is blocked. A step
     * that is already answered may still be re-visited and re-submitted, so
     * going back and changing an answer works. Returns null when the request
     * may proceed.
     */
    private function guardStoredStep(Request $request, PlacementAttempt $attempt, PlacementStep $step): ?RedirectResponse
    {
        if ($request->user()->is_setup_complete) {
            return redirect()->route('hub')->with('toast', [
                'message' => 'You already finished the quick check.',
            ]);
        }

        if ($request->user()->grade_level === null) {
            return redirect()->route('placement.step1');
        }

        foreach ($this->scoredSteps as $candidate) {
            if ($candidate === $step) {
                return null;
            }

            if (! $this->hasCompleted($attempt, $candidate)) {
                return redirect()->route($this->routeFor($candidate));
            }
        }

        return null;
    }

    private function redirectToNext(PlacementAttempt $attempt): RedirectResponse
    {
        foreach ($this->scoredSteps as $step) {
            if (! $this->hasCompleted($attempt, $step)) {
                return redirect()->route($this->routeFor($step));
            }
        }

        return redirect()->route('placement.step5');
    }

    private function routeFor(PlacementStep $step): string
    {
        return match ($step) {
            PlacementStep::Vocab => 'placement.step2',
            PlacementStep::Cloze => 'placement.step3',
            PlacementStep::Comprehension => 'placement.step4',
        };
    }

    private function hasCompleted(PlacementAttempt $attempt, PlacementStep $step): bool
    {
        return $attempt->responses()->where('step', $step->value)->exists();
    }

    /**
     * The already-submitted answers for a scored step, keyed 1-based by
     * question number, so a re-visit can prefill the form. Empty when the
     * step has not been answered yet.
     *
     * @return array<int, string>
     */
    private function savedAnswers(PlacementAttempt $attempt, PlacementStep $step): array
    {
        return $attempt->responses()->where('step', $step->value)->first()?->payload ?? [];
    }

    private function allScoredCompleted(PlacementAttempt $attempt): bool
    {
        foreach ($this->scoredSteps as $step) {
            if (! $this->hasCompleted($attempt, $step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a set of answer fields against the authored options and return
     * the payload to persist, keyed by question number (1-based).
     */
    private function validateAndPayload(Request $request, array $items, string $prefix): array
    {
        $rules = [];

        foreach ($items as $i => $item) {
            $rules[$prefix.'_'.($i + 1)] = ['required', Rule::in($item['options'])];
        }

        $validated = $request->validate($rules, [
            '*.required' => 'Please answer every question before continuing.',
        ]);

        $payload = [];

        foreach ($items as $i => $item) {
            $payload[$i + 1] = $validated[$prefix.'_'.($i + 1)];
        }

        return $payload;
    }
}
