<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\User;
use App\Support\Audit;
use App\Support\ViewAs;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(GenreSeeder::class);
});

function viewAsStory(): Story
{
    $paragraph = fn (string $seed) => collect(range(1, 6))
        ->map(fn ($i) => "The {$seed} walker number {$i} went quickly down the old road.")
        ->implode(' ');

    return Story::create([
        'title' => 'ViewAs Trail',
        'author' => 'A. Walker',
        'synopsis' => 'Reading timer debug.',
        'reading_level' => 6,
        'word_count' => 300,
        'status' => 'published',
        'content' => $paragraph('morning')."\n\n".$paragraph('evening'),
    ]);
}

test('admin can one-click switch to the scratch student and reach the hub', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);

    $this->actingAs($admin)
        ->post(route('admin.preview.switch', 'student'))
        ->assertRedirect(route('hub'));

    $scratch = ViewAs::scratchStudent();

    expect(ViewAs::isActive())->toBeTrue()
        ->and(auth()->id())->toBe($scratch->id)
        ->and($scratch->role)->toBe(Role::Student)
        ->and(ViewAs::actor()->id)->toBe($admin->id);
});

test('admin can one-click switch to the scratch teacher', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);

    $this->actingAs($admin)
        ->post(route('admin.preview.switch', 'teacher'))
        ->assertRedirect(route('teacher.dashboard'));

    $scratch = ViewAs::scratchTeacher();

    expect(ViewAs::isActive())->toBeTrue()
        ->and(auth()->id())->toBe($scratch->id)
        ->and($scratch->role)->toBe(Role::Teacher)
        ->and(ViewAs::actor()->id)->toBe($admin->id);
});

test('scratch accounts resolve (create) even without the seeder', function () {
    expect(ViewAs::scratchStudent())->not->toBeNull()
        ->and(ViewAs::scratchTeacher())->not->toBeNull()
        ->and(User::where('email', ViewAs::SCRATCH_STUDENT_EMAIL)->exists())->toBeTrue()
        ->and(User::where('email', ViewAs::SCRATCH_TEACHER_EMAIL)->exists())->toBeTrue();
});

test('returning restores the admin and resets scratch preview activity', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);
    $story = viewAsStory();

    $this->actingAs($admin)->post(route('admin.preview.switch', 'student'));

    $scratch = ViewAs::scratchStudent();

    // Simulate preview activity left on the scratch account.
    StoryProgress::create([
        'user_id' => $scratch->id,
        'story_id' => $story->id,
        'status' => 'completed',
        'time_spent_seconds' => 120,
    ]);
    $scratch->forceFill(['total_keys' => 5, 'points' => 40, 'current_streak' => 2])->save();

    $this->post(route('preview.return'))->assertRedirect(route('admin.dashboard'));

    expect(auth()->id())->toBe($admin->id)
        ->and(ViewAs::isActive())->toBeFalse()
        ->and(StoryProgress::where('user_id', $scratch->id)->exists())->toBeFalse()
        ->and($scratch->fresh()->total_keys)->toBe(0)
        ->and($scratch->fresh()->points)->toBe(0)
        ->and($scratch->fresh()->current_streak)->toBe(0);
});

test('switching while a preview is already active is blocked', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);

    $this->actingAs($admin)->post(route('admin.preview.switch', 'student'));
    $studentScratch = ViewAs::scratchStudent();

    // While previewing, the session user is the student -> the role:admin
    // middleware on the switch route blocks a second switch (403). No nesting,
    // and the original preview remains intact.
    $this->post(route('admin.preview.switch', 'teacher'))->assertForbidden();

    expect(ViewAs::isActive())->toBeTrue()
        ->and(auth()->id())->toBe($studentScratch->id);
});

test('an unknown role value returns 404', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);

    $this->actingAs($admin)
        ->post('/admin/preview/switch/admin')
        ->assertNotFound();

    expect(ViewAs::isActive())->toBeFalse();
});

test('audit logs attribute quick-switch and return to the real admin', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);

    $this->actingAs($admin)->post(route('admin.preview.switch', 'student'));
    $scratch = ViewAs::scratchStudent();

    // A preview-time write must still be attributed to the admin, not the scratch.
    Audit::log('preview.heartbeat', $scratch, ['seconds' => 5]);

    $heartbeat = AuditLog::query()->where('action', 'preview.heartbeat')->latest('id')->first();

    expect($heartbeat)->not->toBeNull()
        ->and($heartbeat->actor_id)->toBe($admin->id)
        ->and($heartbeat->meta['view_as'] ?? false)->toBeTrue();

    expect(AuditLog::query()->where('action', 'preview.started')->latest('id')->first()->actor_id)->toBe($admin->id);

    $this->post(route('preview.return'));

    $stopped = AuditLog::query()->where('action', 'preview.stopped')->latest('id')->first();

    expect($stopped)->not->toBeNull()
        ->and($stopped->actor_id)->toBe($admin->id)
        ->and($stopped->target_id)->toBe($scratch->id);
});

test('scratch accounts are excluded from the admin account listing and CSV', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);
    $realStudent = User::factory()->create(['role' => Role::Student, 'suspended_at' => null]);
    ViewAs::scratchStudent();

    $this->actingAs($admin)
        ->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSee($realStudent->email)
        ->assertDontSee(ViewAs::SCRATCH_STUDENT_EMAIL);

    $csv = $this->actingAs($admin)->get(route('admin.accounts.export'))->streamedContent();

    expect($csv)->toBeString();
    $this->assertStringContainsString($realStudent->email, $csv);
    $this->assertStringNotContainsString(ViewAs::SCRATCH_STUDENT_EMAIL, $csv);
});

test('scratch student is excluded from the teacher roster', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);
    $teacher = User::factory()->create(['role' => Role::Teacher, 'suspended_at' => null]);
    $realStudent = User::factory()->create(['role' => Role::Student, 'suspended_at' => null]);
    ViewAs::scratchStudent();

    $this->actingAs($teacher)
        ->get(route('teacher.roster'))
        ->assertOk()
        ->assertSee($realStudent->email)
        ->assertDontSee(ViewAs::SCRATCH_STUDENT_EMAIL);
});

test('the per-account "View as" button is gone from the accounts show page', function () {
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);
    $student = User::factory()->create(['role' => Role::Student, 'suspended_at' => null]);

    $this->actingAs($admin)
        ->get(route('admin.accounts.show', $student))
        ->assertOk()
        ->assertDontSee('/view-as');
});

test('previewing keeps the gamified student UI and adds only the plain return banner', function () {
    $story = viewAsStory();

    // Normal student: no admin return banner.
    $student = User::factory()->create([
        'role' => Role::Student,
        'is_setup_complete' => true,
        'suspended_at' => null,
    ]);
    $studentHtml = $this->actingAs($student)->get(route('reader.show', $story))->getContent();
    expect(str_contains($studentHtml, 'VIEWING AS'))->toBeFalse()
        ->and(str_contains($studentHtml, 'RETURN TO ADMIN'))->toBeFalse();

    // Admin previewing the scratch student: the gamified page is NOT flattened
    // (no viewing-as-admin marker — the student UI keeps its real look), the
    // admin return banner IS present, and the reader debug actor marker is set.
    $admin = User::factory()->create(['role' => Role::Admin, 'suspended_at' => null]);
    $this->actingAs($admin)->post(route('admin.preview.switch', 'student'));

    $previewHtml = $this->get(route('reader.show', $story))->getContent();

    expect(str_contains($previewHtml, 'viewing-as-admin'))->toBeFalse()
        ->and(str_contains($previewHtml, 'VIEWING AS'))->toBeTrue()
        ->and(str_contains($previewHtml, 'RETURN TO ADMIN'))->toBeTrue()
        ->and(str_contains($previewHtml, 'data-debug-actor="admin"'))->toBeTrue();
});
