<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Notification;
use App\Support\NotificationService;
use App\Support\StudentClasswork;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Student-facing classwork page (dedicated /classwork) plus class
 * enrollment: join by code / invite, and leave.
 */
class ClassController extends Controller
{
    /**
     * The dedicated classwork page: school-wide feed, classes grid with
     * per-class drill-downs, classmates drawer, join/leave. The hub tab is a
     * lean preview that links here.
     */
    public function classwork(Request $request): View
    {
        $user = $request->user();
        $classwork = StudentClasswork::data($user);
        $assignments = $classwork['assignments'];
        $finished = $classwork['finished'];
        $joinedClasses = $classwork['joinedClasses'];

        return view('student.classwork', [
            'assignments' => $assignments,
            'finished' => $finished,
            'joinedClasses' => $joinedClasses,
            'activeCount' => $assignments->count(),
            'assignmentCompletion' => Assignment::completedStatusForStudent(
                $user,
                collect()
                    ->merge($assignments)
                    ->merge($finished)
                    ->merge($joinedClasses->flatMap(fn ($group) => collect($group->active_assignments)->merge($group->finished_assignments))),
            ),
        ]);
    }

    public function join(Request $request): RedirectResponse|JsonResponse
    {
        // Name gate: a classmate list full of usernames helps nobody. A
        // student must have a real name on file before they can join.
        if (trim($request->user()->first_name.' '.$request->user()->last_name) === '') {
            $nameError = ['join_code' => 'Please add your first and last name in Settings before joining a class.'];
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'errors' => $nameError, 'reason' => 'name_required'], 422);
            }

            return back()->withErrors($nameError)->withInput();
        }

        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:16'],
        ]);

        $group = Group::findByJoinCode($validated['join_code']);

        if ($group === null) {
            $error = ['join_code' => 'We couldn\'t find a class with that code.'];
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'errors' => $error], 422);
            }

            return back()->withErrors($error)->withInput();
        }

        $already = $group->isMember($request->user());
        $joined = $group->addMember($request->user());
        $message = $already
            ? 'You\'re already in "'.$group->name.'".'
            : 'You joined "'.$group->name.'".';

        if ($joined) {
            NotificationService::classJoined($group, $request->user());
        }

        if ($request->expectsJson()) {
            return $this->classworkJson($message);
        }

        return redirect()
            ->route('student.classwork')
            ->with('toast', ['message' => $message]);
    }

    public function leave(Request $request, Group $group): RedirectResponse|JsonResponse
    {
        abort_unless($group->isMember($request->user()), 403);

        $group->removeMember($request->user());
        $message = 'You left "'.$group->name.'".';

        if ($request->expectsJson()) {
            return $this->classworkJson($message);
        }

        return back()->with('toast', ['message' => $message]);
    }

    /**
     * JSON payload for async join/leave: the fresh classwork panel markup, the
     * new active-assignment count, and a toast. Lets the hub re-render in place
     * without a full page reload, so the student keeps their place.
     */
    private function classworkJson(string $message): JsonResponse
    {
        $user = request()->user();
        $classwork = StudentClasswork::data($user);

        return response()->json([
            'ok' => true,
            'toast' => ['message' => $message],
            'html' => view('partials.classwork-widget', [
                'assignments' => $classwork['assignments'],
                'finished' => $classwork['finished'],
                'joinedClasses' => $classwork['joinedClasses'],
                'activeCount' => $classwork['assignments']->count(),
                'assignmentCompletion' => Assignment::completedStatusForStudent(
                    $user,
                    collect()
                        ->merge($classwork['assignments'])
                        ->merge($classwork['finished'])
                        ->merge($classwork['joinedClasses']->flatMap(fn ($group) => collect($group->active_assignments)->merge($group->finished_assignments))),
                ),
            ])->render(),
            'activeCount' => $classwork['assignments']->count(),
        ]);
    }

    /**
     * Accept ("Accept") or decline ("Not now") a pending class invite from an
     * inbox notification. Accepting funnels through Group::addMember so the
     * result always matches joining by code.
     */
    public function decide(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->type !== 'class.invite.teacher') {
            abort(404);
        }

        $decision = $request->validate(['decision' => ['required', 'in:accept,decline']])['decision'];

        $groupId = str_starts_with($notification->link, 'class-invite:')
            ? (int) substr($notification->link, strlen('class-invite:'))
            : null;

        $group = $groupId ? Group::find($groupId) : null;

        if ($decision === 'accept' && $group !== null) {
            if ($group->addMember($request->user())) {
                NotificationService::classInviteAccepted($group, $request->user());
            }
        }

        // A decided invite is spent — remove it entirely (marking it read would
        // keep the Accept / Not now buttons around forever). Deleting also lets
        // a teacher re-invite a student who declined.
        $notification->delete();

        if ($decision === 'accept' && $group !== null) {
            return redirect(route('student.classwork'))->with('toast', ['message' => 'You joined "'.$group->name.'".']);
        }

        return redirect(route('student.classwork'))->with('toast', ['message' => 'You declined the class invitation.']);
    }

    /**
     * Accept ("Accept") or dismiss ("Not now") a pending class invite that
     * arrived as a shared link, carried in the session. Accepting funnels
     * through Group::addMember with the same name gate as joining by code,
     * so link invites and typed codes can never diverge.
     */
    public function decideLinkInvite(Request $request): RedirectResponse
    {
        $groupId = (int) session('pending_class_invite', 0);
        session()->forget('pending_class_invite');

        $group = $groupId > 0 ? Group::find($groupId) : null;

        if ($request->boolean('accept')) {
            if ($group === null) {
                return redirect(route('student.classwork'))->with('toast', ['message' => "That class no longer exists."]);
            }

            if (trim($request->user()->first_name.' '.$request->user()->last_name) === '') {
                session(['pending_class_invite' => $groupId]);

                return redirect(route('settings'))
                    ->with('toast', ['message' => 'Add your first and last name in Settings first, then accept the invite again.']);
            }

            if ($group->addMember($request->user())) {
                NotificationService::classJoined($group, $request->user());
            }

            return redirect(route('student.classwork'))->with('toast', ['message' => 'You joined "'.$group->name.'".']);
        }

        return redirect()->route('hub');
    }
}
