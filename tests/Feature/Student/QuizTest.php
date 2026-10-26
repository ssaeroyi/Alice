<?php

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Enums\StoryStatus;
use App\Models\KeyTransaction;
use App\Models\Question;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Support\QuestionWriter;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function quizStudent(array $overrides = []): User
{
    return User::factory()->create([
        'role' => Role::Student,
        'grade_level' => '8',
        'comprehension_level' => 'instructional',
        'reading_level' => 8,
        'is_setup_complete' => true,
        ...$overrides,
    ]);
}

function quizStory(array $overrides = []): Story
{
    return Story::create([
        'title' => 'The Clockwork Garden',
        'author' => 'L. M. Everett',
        'synopsis' => 'A girl winds a forgotten garden back to life.',
        'content' => 'Mira found the brass key beside the greenhouse door.',
        'word_count' => 900,
        'reading_level' => 8,
        'duration_tier' => 'short',
        'difficulty_label' => 'Intermediate',
        'license' => 'Public domain',
        'source_url' => 'https://www.gutenberg.org/ebooks/1',
        'provenance' => 'From the public-domain original: The Clockwork Garden.',
        'status' => StoryStatus::Published,
        ...$overrides,
    ]);
}

function quizProgress(User $student, Story $story, int $page = 2): StoryProgress
{
    return StoryProgress::create([
        'user_id' => $student->id,
        'story_id' => $story->id,
        'status' => StoryProgressStatus::Reading,
        'current_page' => $page,
    ]);
}

function quizSection(Story $story, string $name): QuestionSection
{
    return $story->sections()->create(['name' => $name, 'position' => (int) $story->sections()->max('position') + 1]);
}

function quizMcq(Story $story, int $sectionId, ?string $correct = 'a'): Question
{
    return QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'Which word means nearly the same as forgotten?',
        'option_a' => 'Neglected',
        'option_b' => 'Celebrated',
        'option_c' => 'Rebuilt',
        'option_d' => 'Watered',
        'correct_option' => $correct,
        'section_id' => $sectionId,
    ]);
}

function quizSubmitPayload(array $answers, ?string $uuid = null, array $session = []): array
{
    return [
        'attempt_uuid' => $uuid ?? str()->uuid()->toString(),
        'time_spent_seconds' => 30,
        'answers' => $answers,
        ...$session,
    ];
}

function quizAttemptUuidOf($response): string
{
    expect(preg_match('/data-attempt="([0-9a-f-]{36})"/', $response->getContent(), $m))->toBe(1);

    return $m[1];
}

function quizOpen($case, User $student, Story $story): string
{
    return quizAttemptUuidOf($case->actingAs($student)->get(route('reader.quiz', $story)));
}

test('guests are redirected away from the quiz player and its posts', function () {
    $story = quizStory();

    $this->get(route('reader.quiz', $story))->assertRedirect(route('login'));
    $this->post(route('reader.quiz.submit', $story))->assertRedirect(route('login'));
    $this->post(route('reader.quiz.hint', $story))->assertRedirect(route('login'));
});

test('the B2 gate bounces students who never opened the story', function () {
    $student = quizStudent();
    $story = quizStory();
    quizMcq($story, quizSection($story, 'Chapter Checks')->id);

    $this->actingAs($student)->get(route('reader.quiz', $story))
        ->assertRedirect(route('story.preview', $story))
        ->assertSessionHas('toast.message', 'Open the story first. The quiz unlocks after you start reading.');
});

test('stories without questions 404 even after reading, and drafts stay hidden', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $draft = quizStory(['title' => 'Unpublished Quiz Draft', 'status' => StoryStatus::Draft]);
    quizProgress($student, $draft);
    quizMcq($draft, quizSection($draft, 'Chapter Checks')->id);

    $this->actingAs($student)->get(route('reader.quiz', $story))->assertNotFound();
    $this->actingAs($student)->get(route('reader.quiz', $draft))->assertNotFound();
});

test('the draw is balanced across sections and capped at the recommended size', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $checks = quizSection($story, 'Chapter Checks');
    $words = quizSection($story, 'Word Work');

    foreach (range(1, 6) as $i) {
        QuestionWriter::create($story, [
            'type' => 'mcq',
            'question_text' => "Chapter check number {$i}: what happened?",
            'option_a' => 'One', 'option_b' => 'Two', 'option_c' => 'Three', 'option_d' => 'Four',
            'correct_option' => 'a',
            'section_id' => $checks->id,
        ]);
        QuestionWriter::create($story, [
            'type' => 'mcq',
            'question_text' => "Word work number {$i}: pick the meaning.",
            'option_a' => 'Red', 'option_b' => 'Blue', 'option_c' => 'Green', 'option_d' => 'Amber',
            'correct_option' => 'a',
            'section_id' => $words->id,
        ]);
    }

    $response = $this->actingAs($student)->get(route('reader.quiz', $story));

    $response->assertOk()
        ->assertSee('Chapter Checks')
        ->assertSee('Word Work');

    expect(substr_count($response->getContent(), 'data-qpanel'))->toBe(5);
});

test('a pool smaller than the recommended draw uses every question', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $section = quizSection($story, 'Everything');

    foreach (range(1, 3) as $i) {
        QuestionWriter::create($story, [
            'type' => 'mcq',
            'question_text' => "Small pool question {$i} about the garden.",
            'option_a' => 'Aye', 'option_b' => 'Bee', 'option_c' => 'Sea', 'option_d' => 'Dee',
            'correct_option' => 'a',
            'section_id' => $section->id,
        ]);
    }

    expect(substr_count($this->actingAs($student)->get(route('reader.quiz', $story))->getContent(), 'data-qpanel'))->toBe(3);
});

test('sequence items are neutral chips carrying one fixed amber number badge', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $section = quizSection($story, 'Order');

    QuestionWriter::create($story, [
        'type' => 'sequence',
        'question_text' => 'Put the morning steps in order.',
        'sequence_items' => ['Woke', 'Ate', 'Walked', 'Read', 'Slept'],
        'section_id' => $section->id,
    ]);

    $html = $this->actingAs($student)->get(route('reader.quiz', $story))->getContent();

    // Every sequence item still draws, but none carries a per-item palette
    // colour: the pool is neutral and placement unlocks a fixed amber number.
    preg_match_all('/data-seq-item[^>]*/', $html, $items);
    expect(count($items[0]))->toBe(5);

    expect($html)->toContain('data-seq-item')
        ->and(str_contains($html, '--seq-color'))->toBeFalse()
        ->and(substr_count($html, 'class="seq-num"'))->toBe(5);

    // Order panels are hintable and advertise their hint.
    expect($html)->toContain('data-hintable')
        ->and($html)->toContain('data-hint-name="Pins the first event for you"');
});

test('hints are one world-level lightbulb, never a per-panel button', function () {
    $student = quizStudent(['total_keys' => 10]);
    $story = quizStory();
    quizProgress($student, $story);
    $section = quizSection($story, 'Chapter Checks');
    quizMcq($story, $section->id);
    $tf = QuestionWriter::create($story, [
        'type' => 'true_false',
        'question_text' => 'True or false: the lamp has no need of the key.',
        'correct_option' => 'b',
        'section_id' => $section->id,
    ]);

    $html = $this->actingAs($student)->get(route('reader.quiz', $story))->getContent();

    expect(substr_count($html, 'id="quiz-hint-btn"'))->toBe(1)
        ->and(substr_count($html, 'id="quiz-hint-chip"'))->toBe(1)
        ->and(str_contains($html, 'data-hint-note'))->toBeFalse()
        ->and(str_contains($html, '>HINT −'))->toBeFalse();

    // True/false panels are the one exempt type: no lightbulb, no name.
    preg_match('/data-type="true_false"[^>]*>/', $html, $tfArticle);
    expect($tfArticle[0] ?? '')->not->toContain('data-hintable')
        ->and($tfArticle[0] ?? '')->not->toContain('data-hint-name');

    preg_match('/data-type="mcq"[^>]*>/', $html, $mcqArticle);
    expect($mcqArticle[0] ?? '')->toContain('data-hintable')
        ->and($mcqArticle[0] ?? '')->toContain('data-hint-name="Crosses out one wrong answer"');

    // The shared confirm modal advertises the real key cost in the button.
    expect($html)->toContain('data-hint-confirm-cost>5')
        ->toContain('data-slot="confirm-balance"')
        ->not->toContain('NEED MORE KEYS');
});

test('refreshing the player keeps the exact same attempt and draw', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    quizMcq($story, quizSection($story, 'Chapter Checks')->id);

    $first = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));
    $second = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    expect($first)->toBe($second);
});

test('server scoring handles all five types with partial credit and pays first-clear keys', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);

    $section = quizSection($story, 'Mixed Bag');
    $mcq = quizMcq($story, $section->id, 'a');
    $tf = QuestionWriter::create($story, [
        'type' => 'true_false',
        'question_text' => 'True or false: Mira finds a brass key.',
        'correct_option' => 'b',
        'section_id' => $section->id,
    ]);
    $ident = QuestionWriter::create($story, [
        'type' => 'identification',
        'question_text' => 'What did Mira find beside the greenhouse door?',
        'acceptable_answers' => ['brass key'],
        'section_id' => $section->id,
    ]);
    $matching = QuestionWriter::create($story, [
        'type' => 'matching',
        'question_text' => 'Match each gadget to what it powers.',
        'match_pairs' => [
            ['left' => 'Crank', 'right' => 'Gate'],
            ['left' => 'Coil', 'right' => 'Fountain'],
            ['left' => 'Valve', 'right' => 'Lantern'],
        ],
        'section_id' => $section->id,
    ]);
    $sequence = QuestionWriter::create($story, [
        'type' => 'sequence',
        'question_text' => 'Put the steps in story order.',
        'sequence_items' => ['Finds key', 'Opens gate', 'Garden wakes'],
        'section_id' => $section->id,
    ]);

    $userBefore = $student->fresh();
    $uuid = quizOpen($this, $student, $story);
    $response = $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([
            ['question_id' => $mcq->id, 'value' => 'a'],
            ['question_id' => $tf->id, 'value' => 'b'],
            ['question_id' => $ident->id, 'value' => '  BRASS key !! '],
            [
                'question_id' => $matching->id,
                'value' => [
                    ['left' => 'Crank', 'right' => 'Gate'],
                    ['left' => 'Coil', 'right' => 'Lantern'],
                    ['left' => 'Valve', 'right' => 'Lantern'],
                ],
            ],
            ['question_id' => $sequence->id, 'value' => ['Finds key', 'Opens gate', 'Garden wakes']],
        ], $uuid),
    );

    $response->assertOk()->assertJsonPath('ok', true);
    expect((int) round(100 * 8 / 9))->toBe(89);
    $response->assertJsonPath('score_pct', 89)
        ->assertJsonPath('passed', true)
        ->assertJsonPath('reason', 'quiz_first_clear')
        ->assertJsonPath('keys', 12)
        ->assertJsonPath('xp', 25)
        ->assertJsonPath('breakdown.base_keys', 12)
        ->assertJsonPath('breakdown.base_xp', 25)
        ->assertJsonPath('back_url', route('story.preview', $story));

    $after = $student->fresh();
    expect($after->total_keys)->toBe($userBefore->total_keys + 12)
        ->and($after->points)->toBe($userBefore->points + 25);

    $attempt = TestAttempt::query()->sole();
    expect($attempt->reward)->toBe(12)
        ->and($attempt->xp_awarded)->toBe(25)
        ->and($attempt->score_pct)->toBe(89)
        ->and($attempt->passed)->toBeTrue();

    KeyTransaction::query()->where('reason', 'quiz_first_clear')->get()->tap(function ($rows) {
        expect($rows)->toHaveCount(1)->and($rows[0]->amount)->toBe(12);
    });

    $progress = StoryProgress::query()->sole();
    expect($progress->best_score_pct)->toBe(89)
        ->and($progress->status)->toBe(StoryProgressStatus::Completed)
        ->and($progress->completed_at)->not->toBeNull();
});

test('a failing first attempt banks partial XP only but still stamps the day', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizOpen($this, $student, $story);

    $response = $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'a']], $uuid),
    );

    $response->assertOk()
        ->assertJsonPath('passed', false)
        ->assertJsonPath('keys', 0)
        ->assertJsonPath('xp', 13)
        ->assertJsonPath('reason', 'quiz_first_attempt');

    expect(KeyTransaction::query()->count())->toBe(0);

    $stamp = UserDailyActivity::query()->sole();
    expect($stamp->quiz_submissions)->toBe(1)
        ->and($stamp->stamped_at)->not->toBeNull();

    $progress = StoryProgress::query()->sole();
    expect($progress->status)->toBe(StoryProgressStatus::Reading)
        ->and($progress->completed_at)->toBeNull()
        ->and($progress->best_score_pct)->toBe(0);
});

test('the results payload exposes weighted points and a full answer review', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $section = quizSection($story, 'Mixed Bag');

    // Four 1-point MCQs all correct (4 pts) + a 2-pair matching question with
    // both pairs wrong (0/2) => 4 of 6 points = 67% < 70% => FAIL, even though
    // "4 of 5 questions" were fully correct. This is the Horseshoe Nails case.
    foreach (range(1, 4) as $i) {
        QuestionWriter::create($story, [
            'type' => 'mcq',
            'question_text' => "MCQ {$i}: pick the meaning.",
            'option_a' => 'Neglected', 'option_b' => 'Celebrated',
            'option_c' => 'Rebuilt', 'option_d' => 'Watered',
            'correct_option' => 'a',
            'section_id' => $section->id,
        ]);
    }
    $matching = QuestionWriter::create($story, [
        'type' => 'matching',
        'question_text' => 'Match each gadget to what it powers.',
        'match_pairs' => [
            ['left' => 'Crank', 'right' => 'Gate'],
            ['left' => 'Coil', 'right' => 'Fountain'],
        ],
        'section_id' => $section->id,
    ]);

    $ids = Question::query()->where('story_id', $story->id)->orderBy('id')->pluck('id');
    $correct = $ids->reject(fn ($id) => $id === $matching->id)->values();

    $uuid = quizOpen($this, $student, $story);
    $response = $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload(
            array_merge(
                $correct->map(fn ($id) => ['question_id' => $id, 'value' => 'a'])->all(),
                [[
                    'question_id' => $matching->id,
                    'value' => [
                        ['left' => 'Crank', 'right' => 'Fountain'],
                        ['left' => 'Coil', 'right' => 'Gate'],
                    ],
                ]],
            ),
            $uuid,
        ),
    );

    $response->assertOk()
        ->assertJsonPath('score_pct', 67)
        ->assertJsonPath('passed', false)
        ->assertJsonPath('correct_count', 4)
        ->assertJsonPath('wrong_count', 1)
        ->assertJsonPath('points_earned', 4)
        ->assertJsonPath('points_total', 6)
        ->assertJsonPath('pass_threshold', 70)
        ->assertJsonPath('review.0.correct', true)
        ->assertJsonPath('review.0.earned', 1)
        ->assertJsonPath('review.0.worth', 1)
        ->assertJsonPath('review.0.expected', 'A. Neglected')
        ->assertJsonPath('review.4.correct', false)
        ->assertJsonPath('review.4.earned', 0)
        ->assertJsonPath('review.4.worth', 2)
        ->assertJsonPath('review.4.expected', 'Crank → Gate; Coil → Fountain')
        ->assertJsonPath('review.4.submitted', 'Crank → Fountain ✗; Coil → Gate ✗');

    expect(count($response->json('review')))->toBe(5);
});

test('retakes pay a quarter of the improvement delta and never keys', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $firstUuid = quizOpen($this, $student, $story);

    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'a']], $firstUuid),
    );

    // Improvement 0 -> 100: 25 * 1.0 * 0.25 * 0.75 (same-day decay) = 5 XP.
    // The RETAKE button mints a fresh attempt, so open the player again.
    $secondUuid = quizOpen($this, $student, $story);
    $response = $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'c']], $secondUuid),
    );

    $response->assertOk()
        ->assertJsonPath('reason', 'quiz_improvement')
        ->assertJsonPath('keys', 0)
        ->assertJsonPath('xp', 5)
        ->assertJsonPath('passed', true)
        ->assertJsonPath('breakdown.tier_multiplier', 1.25);

    expect($student->fresh()->total_keys)->toBe(0)
        ->and($student->fresh()->points)->toBe(13 + 5)
        ->and(StoryProgress::query()->sole()->status)->toBe(StoryProgressStatus::Completed);
});

test('retaking worse than your best pays nothing at all', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $firstUuid = quizOpen($this, $student, $story);

    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'c']], $firstUuid),
    );
    $balanceAfterPass = $student->fresh()->total_keys;

    $secondUuid = quizOpen($this, $student, $story);
    $response = $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'a']], $secondUuid),
    );

    $response->assertOk()
        ->assertJsonPath('reason', 'quiz_no_reward')
        ->assertJsonPath('keys', 0)
        ->assertJsonPath('xp', 0);

    expect($student->fresh()->total_keys)->toBe($balanceAfterPass)
        ->and(StoryProgress::query()->sole()->best_score_pct)->toBe(100);
});

test('replaying a settled attempt returns the stored result verbatim and never double-pays', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id);
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));
    $payload = quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'a']], $uuid);

    $first = $this->actingAs($student)->postJson(route('reader.quiz.submit', $story), $payload);
    $paidTo = $student->fresh()->total_keys;

    // A racing tab still holds the old session entry: the unique index
    // must turn this into an exact replay of the stored settlement.
    $replay = $this->actingAs($student)->withSession([
        "quiz_session.{$story->id}" => [
            'uuid' => $uuid,
            'question_ids' => [$mcq->id],
            'hints' => [],
            'hint_count' => 0,
        ],
    ])->postJson(route('reader.quiz.submit', $story), $payload);

    $replay->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('replayed', true)
        ->assertJsonPath('score_pct', $first->json('score_pct'))
        ->assertJsonPath('keys', $first->json('keys'))
        ->assertJsonPath('xp', $first->json('xp'))
        ->assertJsonPath('reason', $first->json('reason'));

    expect(TestAttempt::query()->count())->toBe(1)
        ->and($student->fresh()->total_keys)->toBe($paidTo);
});

test('a mismatched attempt uuid is rejected as stale instead of scored', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id);

    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'a']]),
    )->assertStatus(409)->assertJsonPath('error', 'stale_attempt');

    expect(TestAttempt::query()->count())->toBe(0);
});

test('hint tokens charge immediately, strike only wrong options and cap at two', function () {
    $student = quizStudent(['total_keys' => 10]);
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $hintRoute = route('reader.quiz.hint', $story);

    $first = $this->actingAs($student)->postJson($hintRoute, [
        'attempt_uuid' => $uuid,
        'question_id' => $mcq->id,
    ]);

    $first->assertOk()->assertJsonPath('ok', true);
    expect($first->json('kind'))->toBe('strike')
        ->and($first->json('letter'))->toBeIn(['a', 'b', 'd'])
        ->and($first->json('balance'))->toBe(5)
        ->and($first->json('hints_left'))->toBe(1);

    $second = $this->actingAs($student)->postJson($hintRoute, [
        'attempt_uuid' => $uuid,
        'question_id' => $mcq->id,
    ]);

    $second->assertOk()->assertJsonPath('hints_left', 0);

    $this->actingAs($student)->postJson($hintRoute, [
        'attempt_uuid' => $uuid,
        'question_id' => $mcq->id,
    ])->assertStatus(422)->assertJsonPath('error', 'no_hints_left');

    KeyTransaction::query()->where('reason', 'hint_token')->get()->tap(function ($rows) use ($student) {
        expect($rows)->toHaveCount(2)
            ->and($rows[0]->amount)->toBe(-5)
            ->and((int) $rows->sum('amount'))->toBe(-10)
            ->and($student->fresh()->total_keys)->toBe(0);
    });
});

test('hints cost more than the balance and are refused without charging', function () {
    $student = quizStudent(['total_keys' => 3]);
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $this->actingAs($student)->postJson(route('reader.quiz.hint', $story), [
        'attempt_uuid' => $uuid,
        'question_id' => $mcq->id,
    ])->assertStatus(422)->assertJsonPath('error', 'insufficient_keys');

    expect($student->fresh()->total_keys)->toBe(3)
        ->and(KeyTransaction::query()->count())->toBe(0);
});

test('true/false stays unhinted — it is already a coin flip', function () {
    $student = quizStudent(['total_keys' => 20]);
    $story = quizStory();
    quizProgress($student, $story);
    $tf = QuestionWriter::create($story, [
        'type' => 'true_false',
        'question_text' => 'True or false: the garden was loud and busy.',
        'correct_option' => 'b',
        'section_id' => quizSection($story, 'Chapter Checks')->id,
    ]);
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $this->actingAs($student)->postJson(route('reader.quiz.hint', $story), [
        'attempt_uuid' => $uuid,
        'question_id' => $tf->id,
    ])->assertStatus(422)->assertJsonPath('error', 'not_hintable');

    expect($student->fresh()->total_keys)->toBe(20);
});

test('identification hints reveal a letter clue without giving the answer away', function () {
    $student = quizStudent(['total_keys' => 5]);
    $story = quizStory();
    quizProgress($student, $story);
    $ident = QuestionWriter::create($story, [
        'type' => 'identification',
        'question_text' => 'What did Mira find beside the greenhouse door?',
        'acceptable_answers' => ['brass key'],
        'section_id' => quizSection($story, 'Chapter Checks')->id,
    ]);
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $this->actingAs($student)->postJson(route('reader.quiz.hint', $story), [
        'attempt_uuid' => $uuid,
        'question_id' => $ident->id,
    ])->assertOk()
        ->assertJsonPath('kind', 'clue')
        ->assertJsonPath('clue', "Starts with 'B', 9 letters.")
        ->assertJsonPath('balance', 0);
});

test('matching hints reveal each correct pair once, then run dry', function () {
    $student = quizStudent(['total_keys' => 10]);
    $story = quizStory();
    quizProgress($student, $story);
    $match = QuestionWriter::create($story, [
        'type' => 'matching',
        'question_text' => 'Match each gadget to what it powers.',
        'match_pairs' => [
            ['left' => 'Crank', 'right' => 'Gate'],
            ['left' => 'Coil', 'right' => 'Fountain'],
        ],
        'section_id' => quizSection($story, 'Chapter Checks')->id,
    ]);
    $route = route('reader.quiz.hint', $story);
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $first = $this->actingAs($student)->postJson($route, [
        'attempt_uuid' => $uuid, 'question_id' => $match->id,
    ])->assertOk()->assertJsonPath('kind', 'pair');

    expect([$first->json('left'), $first->json('right')])->toBe(['Crank', 'Gate']);

    $second = $this->actingAs($student)->postJson($route, [
        'attempt_uuid' => $uuid, 'question_id' => $match->id,
    ])->assertOk();

    expect([$second->json('left'), $second->json('right')])->toBe(['Coil', 'Fountain']);
});

test('sequence hints pin the first event exactly once', function () {
    $student = quizStudent(['total_keys' => 10]);
    $story = quizStory();
    quizProgress($student, $story);
    $seq = QuestionWriter::create($story, [
        'type' => 'sequence',
        'question_text' => 'Put the steps in story order.',
        'sequence_items' => ['Finds key', 'Opens gate', 'Garden wakes'],
        'section_id' => quizSection($story, 'Chapter Checks')->id,
    ]);
    $route = route('reader.quiz.hint', $story);
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $this->actingAs($student)->postJson($route, [
        'attempt_uuid' => $uuid, 'question_id' => $seq->id,
    ])->assertOk()->assertJsonPath('kind', 'first')->assertJsonPath('item', 'Finds key');

    // Budget still allows a second hint, but this question has none left.
    $this->actingAs($student)->postJson($route, [
        'attempt_uuid' => $uuid, 'question_id' => $seq->id,
    ])->assertStatus(422)->assertJsonPath('error', 'nothing_to_hint');
});

test('abandoned quizzes keep their spent hints on the next visit', function () {
    $student = quizStudent(['total_keys' => 5]);
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $this->actingAs($student)->postJson(route('reader.quiz.hint', $story), [
        'attempt_uuid' => $uuid,
        'question_id' => $mcq->id,
    ])->assertOk();

    $response = $this->actingAs($student)->get(route('reader.quiz', $story));

    $response->assertOk()
        ->assertSee('data-hints-used="1"', false)
        ->assertSee('data-balance="0"', false)
        ->assertSee('&quot;kind&quot;:&quot;strike&quot;', false)
        ->assertSee('&quot;letter&quot;:', false);
});

test('a drawn question vanishing mid-attempt forces a fresh draw and uuid', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $section = quizSection($story, 'Chapter Checks');
    $keep = quizMcq($story, $section->id);
    $doomed = QuestionWriter::create($story, [
        'type' => 'mcq',
        'question_text' => 'Which season does the story open in?',
        'option_a' => 'Spring', 'option_b' => 'Winter', 'option_c' => 'Autumn', 'option_d' => 'Summer',
        'correct_option' => 'a',
        'section_id' => $section->id,
    ]);

    $original = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    $doomed->delete();

    $fresh = quizAttemptUuidOf($this->actingAs($student)->get(route('reader.quiz', $story)));

    expect($fresh)->not->toBe($original);

    // And the rebuilt session scores cleanly against the survivors.
    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $keep->id, 'value' => 'a']], $fresh),
    )->assertOk()->assertJsonPath('score_pct', 100);
});

test('quiz chrome sends exits to the preview and renders the B2 interaction shells', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);

    $section = quizSection($story, 'Everything');
    QuestionWriter::create($story, [
        'type' => 'identification',
        'question_text' => 'What did Mira find beside the greenhouse door?',
        'acceptable_answers' => ['brass key'],
        'section_id' => $section->id,
    ]);
    QuestionWriter::create($story, [
        'type' => 'matching',
        'question_text' => 'Match each gadget to what it powers.',
        'match_pairs' => [
            ['left' => 'Crank', 'right' => 'Gate'],
            ['left' => 'Coil', 'right' => 'Fountain'],
        ],
        'section_id' => $section->id,
    ]);
    QuestionWriter::create($story, [
        'type' => 'sequence',
        'question_text' => 'Put the steps in story order.',
        'sequence_items' => ['Finds key', 'Opens gate', 'Garden wakes'],
        'section_id' => $section->id,
    ]);

    $html = $this->actingAs($student)->get(route('reader.quiz', $story))->getContent();

    expect($html)
        ->toContain('data-modal-open="quiz-exit-modal"')
        ->toContain('id="quiz-exit-modal"')
        ->toContain('href="'.route('story.preview', $story).'"')
        ->not->toContain('data-slots')
        ->not->toContain('data-laces')
        // B4: forward-only navigation - no going back to earlier questions.
        ->not->toContain('id="quiz-prev"')
        ->toContain('id="quiz-next" disabled')
        ->toContain('data-seq-pool')
        ->toContain('data-seq-strip')
        ->toContain('data-seq-item')
        // Issue pass: the header KEYS balance pill is removed from the quiz UI
        // (the live key balance now surfaces only in the hint-spend modal); the
        // quiz hint lightbulb remains.
        ->not->toContain('Keys buy hints in quizzes')
        ->toContain('id="quiz-hint-btn"');
});

test('the submit result exposes a results page URL for the PRG handoff', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizOpen($this, $student, $story);

    $response = $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'c']], $uuid),
    );

    $response->assertOk()->assertJsonPath('ok', true);
    expect($response->json('results_url'))
        ->toBe(route('reader.quiz.results', [$story, $uuid]));
});

test('the dedicated results page renders the settled attempt read-only', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizOpen($this, $student, $story);

    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'c']], $uuid),
    );

    $first = $this->actingAs($student)
        ->get(route('reader.quiz.results', [$story, $uuid]))
        ->assertOk()
        ->assertSee('Quiz Complete!')
        ->assertSee('PERFECT RUN!')
        ->assertSee('Accuracy')
        ->assertSee('You earned 1 of 1 points')
        ->assertSee('SEE MY ANSWERS')
        ->assertSee('CORRECT');

    // Read-only PRG page: a refresh returns the exact same settled result, so
    // nothing is paid or mutated a second time.
    $second = $this->actingAs($student)->get(route('reader.quiz.results', [$story, $uuid]));

    expect($second->getContent())->toBe($first->getContent());
});

test('a points-only result says "Points earned" and hides the zero-keys chip', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizOpen($this, $student, $story);

    // Submit a wrong answer so the attempt fails: the economy banks 0 keys but
    // still pays partial points (quiz_first_attempt), so the reward card must
    // say "Points earned" (not "Reward banked") and never show a "+0 keys" chip.
    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'a']], $uuid),
    );

    $this->actingAs($student)
        ->get(route('reader.quiz.results', [$story, $uuid]))
        ->assertOk()
        ->assertSee('Points earned')
        ->assertDontSee('Reward banked')
        ->assertDontSee('+0 keys');
});

test('the results page stops a foreign or unknown attempt', function () {
    $owner = quizStudent();
    $other = quizStudent();
    $story = quizStory();
    quizProgress($owner, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizOpen($this, $owner, $story);

    $this->actingAs($owner)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'c']], $uuid),
    );

    // Another student cannot read the owner's result.
    $this->actingAs($other)->get(route('reader.quiz.results', [$story, $uuid]))->assertNotFound();

    // A bogus UUID is a clean 404 (no info leak).
    $this->actingAs($other)->get(route('reader.quiz.results', [$story, str()->uuid()->toString()]))->assertNotFound();
});

test('guests are redirected away from the quiz results page', function () {
    $student = quizStudent();
    $story = quizStory();
    quizProgress($student, $story);
    $mcq = quizMcq($story, quizSection($story, 'Chapter Checks')->id, 'c');
    $uuid = quizOpen($this, $student, $story);

    $this->actingAs($student)->postJson(
        route('reader.quiz.submit', $story),
        quizSubmitPayload([['question_id' => $mcq->id, 'value' => 'c']], $uuid),
    );

    $this->app['auth']->forgetGuards();
    $this->get(route('reader.quiz.results', [$story, $uuid]))->assertRedirect(route('login'));
});
