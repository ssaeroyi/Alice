<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Support\Audit;
use App\Support\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A teacher's "Classes" area: create classes, get a join code, manage members.
 * Class membership is the self-serve enrollment that lets every student see
 * their class, teacher and classmates (and powers class-scoped assignments).
 */
class ClassController extends Controller
{
    public function index(): View
    {
        $groups = Group::query()
            ->withCount(['members', 'assignments'])
            ->where('teacher_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('teacher.classes.index', ['groups' => $groups]);
    }

    public function create(): View
    {
        return view('teacher.classes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group = Group::create([
            'teacher_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'join_code' => Group::makeJoinCode(),
        ]);

        Audit::log('group.created', $group);

        return redirect()
            ->route('teacher.classes.show', $group)
            ->with('toast', [
                'message' => 'Class "'.$group->name.'" created. Share its join code to add students.',
            ]);
    }

    public function show(Group $group): View
    {
        $this->authorizeOwner($group);

        $group->load([
            'members' => fn ($q) => $q->realAccounts()->orderBy('first_name')->orderBy('last_name'),
            'assignments' => fn ($q) => $q->with('story')->orderByDesc('created_at'),
        ]);

        return view('teacher.classes.show', [
            'group' => $group,
            'students' => $this->allStudents(),
        ]);
    }

    /** Editable class details (description) on the teacher's class page. */
    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeOwner($group);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group->update(['description' => $validated['description'] ?? null]);

        return back()->with('toast', ['message' => 'Class details saved.']);
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorizeOwner($group);

        Audit::log('group.deleted', $group);
        $group->delete();

        return redirect()
            ->route('teacher.classes.index')
            ->with('toast', ['message' => 'Class "'.$group->name.'" was removed.']);
    }

    /**
     * Remove a student from a class directly (teacher-managed).
     */
    public function removeMember(Group $group, User $user): RedirectResponse
    {
        $this->authorizeOwner($group);

        $group->removeMember($user);

        return back()->with('toast', ['message' => $user->first_name.' was removed from '.$group->name.'.']);
    }

    /**
     * Roster picker of students who can still be invited to this class.
     */
    public function membersCreate(Group $group): View
    {
        $this->authorizeOwner($group);

        $students = $this->allStudents();
        $availableStudents = $students->filter(
            fn (User $student) => ! $group->isMember($student)
        );

        return view('teacher.classes.members-create', [
            'group' => $group,
            'availableStudents' => $availableStudents,
            'anyStudentsExist' => $students->isNotEmpty(),
        ]);
    }

    /**
     * Send class-invite notifications to the selected students.
     */
    public function membersInvite(Group $group, Request $request): RedirectResponse
    {
        $this->authorizeOwner($group);

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $members = $group->members()->pluck('users.id')->all();
        $userIds = array_values(array_diff($validated['student_ids'], $members));

        $sent = NotificationService::classInvite($group, $userIds);

        return redirect()
            ->route('teacher.classes.show', $group)
            ->with('toast', [
                'message' => $sent > 0
                    ? ($sent === 1 ? '1 student invitation sent.' : $sent.' student invitations sent.')
                    : 'Those students are already in this class or invited.',
            ]);
    }

    private function authorizeOwner(Group $group): void
    {
        abort_unless($group->teacher_id === auth()->id(), 403);
    }

    /**
     * Real registered students only (no scratch/demo/test accounts), for
     * explicitly adding to a class.
     *
     * @return Collection<int, User>
     */
    private function allStudents()
    {
        return User::query()
            ->where('role', Role::Student)
            ->realAccounts()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'nickname', 'email', 'avatar']);
    }
}
