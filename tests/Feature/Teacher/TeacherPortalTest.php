<?php

use App\Enums\ComprehensionLevel;
use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Enums\StoryStatus;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login when visiting the teacher portal', function () {
    $this->get(route('teacher.dashboard'))->assertRedirect(route('login'));
});

test('students are forbidden from the teacher portal', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)
        ->get(route('teacher.dashboard'))
        ->assertForbidden();
});

test('teachers can open the teacher dashboard', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Teacher Portal')
        ->assertSee('Overview');
});

test('admins can open the teacher dashboard', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('teachers are forbidden from the student zone', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('hub'))
        ->assertForbidden();
});

test('teacher dashboard shows student counts and setup completion', function () {
    User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    User::factory()->create(['role' => Role::Student, 'is_setup_complete' => false]);
    User::factory()->create(['role' => Role::Student, 'suspended_at' => now()]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Students', '4'])
        ->assertSee('Completed setup')
        ->assertSee('2')
        ->assertSee('Suspended')
        ->assertSee('1');
});

test('teacher dashboard groups students by effective grade', function () {
    User::factory()->create([
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade7,
        'is_setup_complete' => true,
    ]);
    User::factory()->create([
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade7,
        'is_setup_complete' => true,
    ]);
    User::factory()->create([
        'role' => Role::Student,
        'grade_level' => GradeLevel::Grade9,
        'is_setup_complete' => true,
    ]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Grade 7', '2'])
        ->assertSeeInOrder(['Grade 9', '1']);
});

test('teacher dashboard comprehension bands come from live reading evidence', function () {
    $independent = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    ReadingProgress::create([
        'user_id' => $independent->id,
        'live_score' => 80,
        'teacher_score' => 80,
        'comprehension_level' => ComprehensionLevel::Independent,
        'reading_grade' => 4,
        'sample_count' => 2,
        'distinct_grades' => 2,
        'last_activity_at' => now(),
        'computed_at' => now(),
    ]);

    $frustration = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    ReadingProgress::create([
        'user_id' => $frustration->id,
        'live_score' => 30,
        'teacher_score' => 30,
        'comprehension_level' => ComprehensionLevel::Frustration,
        'reading_grade' => 2,
        'sample_count' => 2,
        'distinct_grades' => 1,
        'last_activity_at' => now(),
        'computed_at' => now(),
    ]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Where the class stands')
        ->assertSeeInOrder(['Independent', '1'])
        ->assertSeeInOrder(['Frustration', '1'])
        ->assertDontSee('Reading level distribution');
});

test('teacher dashboard ignores the placement comprehension snapshot for bands', function () {
    User::factory()->create([
        'role' => Role::Student,
        'comprehension_level' => ComprehensionLevel::Independent,
        'is_setup_complete' => true,
    ]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('No reading levels yet');
});

test('teacher dashboard shows the class and assignments panel', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $student = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);

    $group = Group::create([
        'teacher_id' => $teacher->id,
        'name' => '7 Diamond',
        'join_code' => Group::makeJoinCode(),
    ]);
    $group->addMember($student);

    $story = Story::create([
        'title' => 'The Lost Coin',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'source_url' => 'https://example.com/lost-coin',
        'synopsis' => 'A coin goes missing.',
        'content' => 'The coin was lost in the market square. A child found it.',
        'status' => StoryStatus::Published,
        'created_by' => $teacher->id,
    ]);

    $this->actingAs($teacher)
        ->post(route('teacher.assignments.store'), [
            'title' => 'Read The Lost Coin',
            'story_id' => $story->id,
            'scope' => 'group',
            'group_id' => $group->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    $this->actingAs($teacher)
        ->post(route('teacher.assignments.store'), [
            'title' => 'Read The Lost Coin (overdue)',
            'story_id' => $story->id,
            'scope' => 'group',
            'group_id' => $group->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    Assignment::where('title', 'Read The Lost Coin (overdue)')->first()->forceFill([
        'due_at' => now()->subDays(1),
    ])->save();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Class & assignments')
        ->assertSee('Active')
        ->assertSee('due this week')
        ->assertSee('Overdue')
        ->assertSee('Read The Lost Coin')
        ->assertSee('7 Diamond')
        ->assertSee('1 student');
});

test('teacher dashboard flags students who need attention', function () {
    $suspended = User::factory()->create([
        'role' => Role::Student,
        'suspended_at' => now(),
    ]);
    $pending = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => false,
    ]);
    $inactive = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'last_login_at' => now()->subDays(20),
    ]);
    $slipping = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'last_login_at' => now()->subHours(2),
    ]);

    ReadingProgress::create([
        'user_id' => $slipping->id,
        'comprehension_level' => ComprehensionLevel::Instructional,
        'live_score' => 55,
        'teacher_score' => 55,
        'reading_grade' => 8,
        'sample_count' => 5,
        'distinct_grades' => 1,
        'last_activity_at' => now(),
        'computed_at' => now(),
        'trend' => 'down',
        'slipping' => true,
    ]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSee($suspended->nickname)
        ->assertSee('Suspended')
        ->assertSee($pending->nickname)
        ->assertSee('Setup pending')
        ->assertSee($inactive->nickname)
        ->assertSee('No recent sign-in')
        ->assertSee($slipping->nickname)
        ->assertSee('Slipping');
});

test('teachers are redirected to the teacher portal after login', function () {
    $teacher = User::factory()->create([
        'role' => Role::Teacher,
        'password' => 'password',
        'is_setup_complete' => false,
    ]);

    $this->post('/login', [
        'email' => $teacher->email,
        'password' => 'password',
    ])->assertRedirect(route('teacher.dashboard'));
});

test('teacher dashboard counts active students this week', function () {
    User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'last_login_at' => now(),
    ]);
    User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'last_login_at' => now()->subDay(),
    ]);
    User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'last_login_at' => now()->subDays(60),
    ]);
    User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'last_login_at' => null,
    ]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Active this week')
        ->assertSeeInOrder(['Active this week', '2']);
});

test('teacher dashboard renders the explorer chart canvas', function () {
    $student = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    UserDailyActivity::create([
        'user_id' => $student->id,
        'activity_date' => now()->toDateString(),
        'read_sessions' => 1,
        'reading_seconds_today' => 3600,
    ]);
    ReadingProgress::create([
        'user_id' => $student->id,
        'live_score' => 80,
        'teacher_score' => 80,
        'comprehension_level' => ComprehensionLevel::Independent,
        'reading_grade' => 4,
        'sample_count' => 2,
        'distinct_grades' => 2,
        'last_activity_at' => now(),
        'computed_at' => now(),
    ]);

    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('data-metric-canvas')
        ->assertSee('data-reading-canvas')
        ->assertSee(now()->format('Y-m-d'));
});

test('the explorer still renders when there is no activity yet', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('data-metric-canvas')
        ->assertSee(now()->format('Y-m-d'));
});

test('teacher dashboard lists the hardest stories for the class', function () {
    $teacher = User::factory()->create(['role' => Role::Teacher]);
    $studentA = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);
    $studentB = User::factory()->create(['role' => Role::Student, 'is_setup_complete' => true]);

    $hard = Story::create([
        'title' => 'The Tricky Tale',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'source_url' => 'https://example.com/tricky',
        'synopsis' => 'A tricky tale.',
        'content' => 'Once upon a time it was hard.',
        'status' => StoryStatus::Published,
        'created_by' => $teacher->id,
    ]);
    $easy = Story::create([
        'title' => 'The Simple Tale',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'source_url' => 'https://example.com/simple',
        'synopsis' => 'A simple tale.',
        'content' => 'Once upon a time it was simple.',
        'status' => StoryStatus::Published,
        'created_by' => $teacher->id,
    ]);
    $thin = Story::create([
        'title' => 'The Quiet Tale',
        'author' => 'Jane Doe',
        'license' => 'Public domain',
        'source_url' => 'https://example.com/quiet',
        'synopsis' => 'A quiet tale.',
        'content' => 'Once upon a time it was quiet.',
        'status' => StoryStatus::Published,
        'created_by' => $teacher->id,
    ]);

    // The hard story carries three low attempts, the easy one three high,
    // and the quiet one a single attempt — below the 3-attempt floor.
    foreach ([25, 30, 35] as $index => $score) {
        TestAttempt::create([
            'user_id' => $studentA->id,
            'story_id' => $hard->id,
            'score_pct' => $score,
            'passed' => false,
            'correct_count' => 1,
            'wrong_count' => 4,
        ]);
    }
    foreach ([85, 90, 95] as $index => $score) {
        TestAttempt::create([
            'user_id' => $studentB->id,
            'story_id' => $easy->id,
            'score_pct' => $score,
            'passed' => true,
            'correct_count' => 4,
            'wrong_count' => 1,
        ]);
    }
    TestAttempt::create([
        'user_id' => $studentA->id,
        'story_id' => $thin->id,
        'score_pct' => 50,
        'passed' => false,
        'correct_count' => 2,
        'wrong_count' => 3,
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Text difficulty')
        ->assertSee('The Tricky Tale')
        ->assertSee('The Simple Tale')
        ->assertDontSee('The Quiet Tale');
});
