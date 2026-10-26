<?php

use App\Enums\ComprehensionLevel;
use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function teacherActor(): User
{
    return User::factory()->create(['role' => Role::Teacher]);
}

test('guests are redirected to login on the roster', function () {
    $this->get(route('teacher.roster'))->assertRedirect(route('login'));
});

test('students are forbidden from the roster and student detail', function () {
    $student = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($student)->get(route('teacher.roster'))->assertForbidden();
    $this->actingAs($student)->get(route('teacher.student', $student))->assertForbidden();
});

test('teachers and admins can open the roster', function () {
    $teacher = teacherActor();

    $this->actingAs($teacher)
        ->get(route('teacher.roster'))
        ->assertOk()
        ->assertSee('Students');

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('teacher.roster'))
        ->assertOk();
});

test('roster lists students only', function () {
    $student = User::factory()->create(['role' => Role::Student]);
    User::factory()->create(['role' => Role::Teacher]);
    User::factory()->create(['role' => Role::Admin]);

    $this->actingAs(teacherActor())
        ->get(route('teacher.roster'))
        ->assertOk()
        ->assertSee($student->nickname)
        ->assertSee('1 student found');
});

test('roster search matches nickname, name and email', function () {
    $target = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'puzzler_ann',
        'first_name' => 'Ann',
        'last_name' => 'Puzon',
        'email' => 'ann@school.edu',
    ]);
    $other = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'quiet_bill',
        'first_name' => 'Bill',
        'last_name' => 'Quiet',
        'email' => 'bill@school.edu',
    ]);

    foreach (['puzzler_ann', 'Puzon', 'ann@school.edu'] as $q) {
        $this->actingAs(teacherActor())
            ->get(route('teacher.roster', ['q' => $q]))
            ->assertOk()
            ->assertSee($target->nickname)
            ->assertDontSee($other->nickname);
    }
});

test('roster filters by grade, comprehension, status and setup', function () {
    $grade7 = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'grade7_kid',
        'grade_level' => GradeLevel::Grade7,
        'comprehension_level' => ComprehensionLevel::Independent,
        'is_setup_complete' => true,
    ]);
    $suspended = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'suspended_kid',
        'grade_level' => GradeLevel::Grade9,
        'suspended_at' => now(),
    ]);
    $pending = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'pending_kid',
        'grade_level' => GradeLevel::Grade9,
        'is_setup_complete' => false,
        'comprehension_level' => ComprehensionLevel::Frustration,
    ]);
    ReadingProgress::create([
        'user_id' => $pending->id,
        'comprehension_level' => ComprehensionLevel::Frustration,
        'sample_count' => 5,
    ]);

    $teacher = teacherActor();

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['grade' => GradeLevel::Grade7->value]))
        ->assertSee($grade7->nickname)
        ->assertDontSee($suspended->nickname)
        ->assertDontSee($pending->nickname);

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['comprehension' => ComprehensionLevel::Frustration->value]))
        ->assertSee($pending->nickname)
        ->assertDontSee($grade7->nickname);

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['status' => 'suspended']))
        ->assertSee($suspended->nickname)
        ->assertDontSee($grade7->nickname);

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['setup' => 'pending']))
        ->assertSee($pending->nickname)
        ->assertDontSee($grade7->nickname);
});

test('roster comprehension filter reads live reading progress, not the placement snapshot', function () {
    $liveOnly = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'live_critical_kid',
        'comprehension_level' => ComprehensionLevel::Independent,
        'is_setup_complete' => true,
    ]);
    ReadingProgress::create([
        'user_id' => $liveOnly->id,
        'comprehension_level' => ComprehensionLevel::Critical,
        'sample_count' => 5,
    ]);

    $snapshotOnly = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'snapshot_only_kid',
        'comprehension_level' => ComprehensionLevel::Frustration,
        'is_setup_complete' => true,
    ]);

    $noResult = User::factory()->create([
        'role' => Role::Student,
        'nickname' => 'no_result_kid',
        'is_setup_complete' => true,
    ]);

    $teacher = teacherActor();

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['comprehension' => ComprehensionLevel::Critical->value]))
        ->assertSee($liveOnly->nickname)
        ->assertDontSee($snapshotOnly->nickname);

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['comprehension' => ComprehensionLevel::Frustration->value]))
        ->assertDontSee($snapshotOnly->nickname);

    $this->actingAs($teacher)
        ->get(route('teacher.roster', ['comprehension' => 'none']))
        ->assertSee($noResult->nickname)
        ->assertSee($snapshotOnly->nickname)
        ->assertDontSee($liveOnly->nickname);
});

test('roster paginates', function () {
    User::factory()->count(30)->create(['role' => Role::Student]);

    $this->actingAs(teacherActor())
        ->get(route('teacher.roster'))
        ->assertOk()
        ->assertSee('30 students found');
});

test('roster shows suspended students with their badge', function () {
    $suspended = User::factory()->create([
        'role' => Role::Student,
        'suspended_at' => now(),
    ]);

    $this->actingAs(teacherActor())
        ->get(route('teacher.roster'))
        ->assertOk()
        ->assertSee($suspended->nickname)
        ->assertSee('Suspended');
});

test('student detail shows identity, placement result and grade facts', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'first_name' => 'Bea',
        'last_name' => 'Reyes',
        'nickname' => 'bea_reads',
        'email' => 'bea@school.edu',
        'grade_level' => GradeLevel::Grade8,
        'grade_anchor_year' => 2026,
        'reading_level' => 9,
        'comprehension_level' => ComprehensionLevel::Instructional,
        'comprehension_bar' => 68,
        'is_setup_complete' => true,
    ]);

    $this->actingAs(teacherActor())
        ->get(route('teacher.student', $student))
        ->assertOk()
        ->assertSee('bea_reads')
        ->assertSee('Bea Reyes')
        ->assertSee('bea@school.edu')
        ->assertSee('Grade 8')
        ->assertSee('Grade 9')
        ->assertSee('Instructional')
        ->assertSee('68%');
});

test('student detail flags suspended accounts', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'suspended_at' => now(),
    ]);

    $this->actingAs(teacherActor())
        ->get(route('teacher.student', $student))
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('Suspended');
});

test('student detail returns 404 for non-students', function () {
    $teacher = teacherActor();
    $otherTeacher = User::factory()->create(['role' => Role::Teacher]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($teacher)
        ->get(route('teacher.student', $otherTeacher))
        ->assertNotFound();

    $this->actingAs($teacher)
        ->get(route('teacher.student', $admin))
        ->assertNotFound();
});

test('overview links point to the roster and student detail', function () {
    $student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => false,
    ]);

    $this->actingAs(teacherActor())
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee(route('teacher.roster'))
        ->assertSee(route('teacher.student', $student));
});
