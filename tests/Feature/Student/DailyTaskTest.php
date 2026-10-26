<?php

use App\Enums\DailyTaskType;
use App\Enums\StoryStatus;
use App\Enums\UserDailyTaskStatus;
use App\Models\DailyTask;
use App\Models\DictionaryEntry;
use App\Models\KeyTransaction;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use App\Models\UserDailyTask;
use App\Services\DailyTaskService;
use Database\Seeders\DailyTaskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DailyTaskSeeder::class);
    $this->student = User::factory()->create(['is_setup_complete' => true]);
});

function tasksStudent(User $user)
{
    $user->refresh();

    return UserDailyTask::query()
        ->where('user_id', $user->id)
        ->whereDate('task_date', Carbon::now()->toDateString())
        ->with('task')
        ->orderBy('id')
        ->get();
}

function completeTask(UserDailyTask $assignment): void
{
    $date = Carbon::now()->toDateString();
    $user = ['user_id' => $assignment->user_id, 'activity_date' => $date];
    $target = max(1, (int) $assignment->task->target_value);

    // Complete the task to its actual target value. The pool rotates by day so
    // which task lands first varies by calendar date — completing to target
    // keeps these assertions deterministic regardless of rotation order.
    match ($assignment->task->type) {
        DailyTaskType::ReadStory => UserDailyActivity::updateOrCreate($user, ['read_sessions' => $target]),
        DailyTaskType::GymDrill => UserDailyActivity::updateOrCreate($user, ['drills_completed' => $target]),
        DailyTaskType::PlayGame => UserDailyActivity::updateOrCreate($user, ['game_rounds' => $target]),
        DailyTaskType::ScoreQuiz => collect(range(1, $target))->each(fn () => TestAttempt::create(['user_id' => $assignment->user_id, 'story_id' => makeStory()->id, 'attempt_uuid' => (string) Str::uuid(), 'score_pct' => 80, 'passed' => true, 'correct_count' => 5])),
        DailyTaskType::LearnWords => collect(range(1, $target))->each(fn ($i) => SavedWord::create(['user_id' => $assignment->user_id, 'word' => 'word-'.uniqid().'-'.$i, 'definition' => 'test'])),
    };
}

function makeStory(): Story
{
    return Story::create([
        'title' => 'Task Story',
        'author' => 'Jane Doe',
        'synopsis' => 'A short task story.',
        'content' => 'The waves crashed against the rocks.',
        'word_count' => 100,
        'reading_level' => 8,
        'duration_tier' => 'quick',
        'difficulty_label' => 'Easy',
        'status' => StoryStatus::Published,
    ]);
}

test('guests are redirected from daily tasks', function () {
    $this->get(route('tasks'))->assertRedirect(route('login'));
});

test('the first visit of a day lazily assigns three tasks and renders them', function () {
    $this->actingAs($this->student)
        ->get(route('tasks'))
        ->assertOk()
        ->assertSee('Daily Tasks')
        ->assertSee('Rerolls left today: 1');

    $rows = tasksStudent($this->student);
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('status')->map->value->all())->each->toBe('pending');
});

test('a completed task can be claimed for keys and points', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignment = tasksStudent($this->student)->first();

    completeTask($assignment);

    $this->actingAs($this->student)
        ->from(route('tasks'))
        ->post(route('tasks.claim'), ['task' => $assignment->id])
        ->assertRedirect(route('tasks'))
        ->assertSessionHas('task_claim');

    $keys = (int) $assignment->task->reward_keys;
    $points = (int) $assignment->task->reward_xp;

    $this->student->refresh();
    expect($this->student->total_keys)->toBe($keys)
        ->and($this->student->points)->toBe($points);

    KeyTransaction::where('reason', 'daily_task')->get()->tap(function ($rows) use ($keys) {
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->amount)->toBe($keys);
    });

    $assignment->refresh();
    expect($assignment->status)->toBe(UserDailyTaskStatus::Claimed);
});

test('claiming the same task twice is refused', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignment = tasksStudent($this->student)->first();
    completeTask($assignment);

    $this->actingAs($this->student)->post(route('tasks.claim'), ['task' => $assignment->id])->assertRedirect();
    $keys = (int) $assignment->task->reward_keys;

    $this->actingAs($this->student)->post(route('tasks.claim'), ['task' => $assignment->id])->assertRedirect();

    $this->student->refresh();
    expect($this->student->total_keys)->toBe($keys);
});

test('claiming an unfinished task is refused', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignment = tasksStudent($this->student)->first();

    $this->actingAs($this->student)
        ->post(route('tasks.claim'), ['task' => $assignment->id])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'error');

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(0)
        ->and($this->student->points)->toBe(0);
});

test('claiming via json returns the payout payload and pays exactly once', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignment = tasksStudent($this->student)->first();
    completeTask($assignment);

    $keys = (int) $assignment->task->reward_keys;
    $points = (int) $assignment->task->reward_xp;

    $this->actingAs($this->student)
        ->postJson(route('tasks.claim'), ['task' => $assignment->id])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('keys', $keys)
        ->assertJsonPath('points', $points)
        ->assertJsonPath('total_keys', $keys)
        ->assertJsonPath('total_points', $points);

    $this->actingAs($this->student)
        ->postJson(route('tasks.claim'), ['task' => $assignment->id])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false);

    expect(KeyTransaction::where('reason', 'daily_task')->count())->toBe(1)
        ->and($this->student->refresh()->total_keys)->toBe($keys);
});

test('claim-all pays every ready task in one request and never twice', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignments = tasksStudent($this->student);

    $assignments->each(fn (UserDailyTask $assignment) => completeTask($assignment));

    $expectedKeys = (int) $assignments->sum(fn (UserDailyTask $assignment) => (int) $assignment->task->reward_keys);
    $expectedPoints = (int) $assignments->sum(fn (UserDailyTask $assignment) => (int) $assignment->task->reward_xp);

    $this->actingAs($this->student)
        ->postJson(route('tasks.claim-all'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('claimed', $assignments->count())
        ->assertJsonPath('keys', $expectedKeys)
        ->assertJsonPath('points', $expectedPoints)
        ->assertJsonPath('total_keys', $expectedKeys)
        ->assertJsonPath('total_points', $expectedPoints);

    $this->actingAs($this->student)
        ->postJson(route('tasks.claim-all'))
        ->assertUnprocessable()
        ->assertJsonPath('ok', false);

    expect($this->student->refresh()->total_keys)->toBe($expectedKeys)
        ->and($this->student->points)->toBe($expectedPoints)
        ->and(KeyTransaction::where('reason', 'daily_task')->count())->toBe($assignments->count());
});

test('a pending task can be rerolled to a different type, once per day', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignment = tasksStudent($this->student)->first();
    $oldType = $assignment->task->type;
    $oldTaskId = $assignment->daily_task_id;

    $this->student->update(['total_keys' => 10]);

    $this->actingAs($this->student)
        ->from(route('tasks'))
        ->post(route('tasks.reroll'), ['task' => $assignment->id])
        ->assertRedirect(route('tasks'))
        ->assertSessionHas('toast');

    $assignment->refresh();
    expect($assignment->task->type)->not->toBe($oldType);

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(8);

    KeyTransaction::where('reason', 'task_reroll')->get()->tap(function ($rows) use ($oldTaskId) {
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->amount)->toBe(-2)
            ->and($rows->first()->subject_type)->toBe('daily_task')
            ->and($rows->first()->subject_id)->toBe($oldTaskId)
            ->and($rows->first()->balance_after)->toBe(8);
    });

    $activity = UserDailyActivity::where('user_id', $this->student->id)
        ->whereDate('activity_date', Carbon::now()->toDateString())->first();
    expect((int) $activity->task_rerolls)->toBe(1);

    $second = tasksStudent($this->student)->where('id', '!=', $assignment->id)->first();
    $this->actingAs($this->student)
        ->post(route('tasks.reroll'), ['task' => $second->id])
        ->assertRedirect()
        ->assertSessionHas('toast.error');
});

test('a pending task is kept when the student cannot afford the reroll key cost', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $assignment = tasksStudent($this->student)->first();
    $oldType = $assignment->task->type;

    $this->actingAs($this->student)
        ->from(route('tasks'))
        ->post(route('tasks.reroll'), ['task' => $assignment->id])
        ->assertRedirect(route('tasks'))
        ->assertSessionHas('toast.error');

    $assignment->refresh();
    expect($assignment->task->type)->toBe($oldType);

    $this->student->refresh();
    expect($this->student->total_keys)->toBe(0);

    expect(KeyTransaction::where('reason', 'task_reroll')->count())->toBe(0);
});

test('the page renders an empty state when the task pool is empty', function () {
    DailyTask::query()->delete();

    $this->actingAs($this->student)
        ->get(route('tasks'))
        ->assertOk()
        ->assertSee('No tasks today');
});

test('task state reports claimable and in-progress counters', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $rows = tasksStudent($this->student);

    $service = app(DailyTaskService::class);
    $now = Carbon::now();

    expect($service->state($this->student, $now))->toMatchArray([
        'claimable' => 0,
        'in_progress' => 0,
    ]);

    completeTask($rows->get(0));
    $state = $service->state($this->student, $now);

    expect($state['claimable'])->toBe(1)
        ->and($state['tasks']->where('claimable', true)->pluck('id')->all())->toContain($rows->get(0)->id);

    // Part-way progress on a second task flips it to in-progress.
    $activity = UserDailyActivity::firstOrCreate([
        'user_id' => $this->student->id,
        'activity_date' => $now->toDateString(),
    ]);
    $activity->update(['game_rounds' => 1]);
    $playGame = $rows->first(fn ($a) => $a->task->type === DailyTaskType::PlayGame);

    if ($playGame !== null && $playGame->task->target_value > 1) {
        $state = $service->state($this->student, $now);
        expect($state['in_progress'])->toBeGreaterThanOrEqual(1);
    }
});

test('completedNow reports exactly the tasks that just became claimable once', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $rows = tasksStudent($this->student);
    $service = app(DailyTaskService::class);
    $now = Carbon::now();

    $baseline = $service->seenShape($this->student, $now);
    $first = $service->completedNow($this->student, $now, $baseline);
    expect($first)->toBeEmpty();

    completeTask($rows->get(0));

    $later = $service->completedNow($this->student, $now, $baseline);
    expect($later)->toContain($rows->get(0)->id);

    // Once the fresh shape is seen, no further delta fires.
    expect($service->completedNow($this->student, $now, $service->seenShape($this->student, $now)))->toBeEmpty();
    // And with no prior snapshot it reports all currently-claimable tasks.
    expect($service->completedNow($this->student, $now, null))->toContain($rows->get(0)->id);
});

test('a freshly saved new word reports task deltas so the reader can raise a banner', function () {
    // Seed a minimal WordNet fixture so a real word resolves in the test DB.
    DictionaryEntry::create(['lemma' => 'run', 'pos' => 'verb', 'sense_num' => 1, 'definition' => 'move fast by using the feet', 'freq' => 240]);

    $this->actingAs($this->student)->get(route('tasks'));

    $this->actingAs($this->student)
        ->postJson(route('words.save'), ['word' => 'run'])
        ->assertOk()
        ->assertJsonPath('saved', true)
        ->assertJsonStructure([
            'completed_now',
            'tasks' => ['claimable', 'in_progress'],
        ]);

    // Re-saving the same word is not new activity: no task delta is refreshed.
    $this->actingAs($this->student)
        ->postJson(route('words.save'), ['word' => 'run'])
        ->assertOk()
        ->assertJsonPath('already_saved', true)
        ->assertJsonMissingPath('completed_now');
});

test('syncSeen returns a single-pass delta and keeps the task_seen guard in sync', function () {
    $this->actingAs($this->student)->get(route('tasks'));
    $rows = tasksStudent($this->student);
    $service = app(DailyTaskService::class);
    $now = Carbon::now();
    $request = app(Request::class);

    // First interaction of the session: completed_now reports everything
    // currently claimable (none yet) and gate stays all-false.
    $first = $service->syncSeen($this->student, $now, $request);
    expect($first['completed_now'])->toBeEmpty()
        ->and($first['tasks']['claimable'])->toBe(0)
        ->and($first['tasks']['in_progress'])->toBe(0);
    $baseline = session('task_seen');
    expect(array_values($baseline))->each->toBeFalse();

    // Complete exactly one task, then observe the transition.
    $completed = $rows->get(0);
    completeTask($completed);
    $second = $service->syncSeen($this->student, $now, $request);
    expect($second['completed_now'])->toContain($completed->id)
        ->and($second['tasks']['claimable'])->toBe(1);

    // Re-visiting with the guard now true fires no further delta.
    $third = $service->syncSeen($this->student, $now, $request);
    expect($third['completed_now'])->toBeEmpty()
        ->and($third['tasks']['claimable'])->toBe(1);

    // The guard reflects the fresh shape (claimable id now true).
    $after = session('task_seen');
    expect($after[$completed->id])->toBeTrue();
});
