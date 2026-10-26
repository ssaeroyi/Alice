<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student-facing view of a teacher assignment: full instructions (never
 * truncated), who sent it, due date, and the student's live completion state.
 * Available to anyone the assignment targets, and to any signed-in user via a
 * valid share code (mirrors the reader handoff).
 */
class AssignmentController extends Controller
{
    public function show(Request $request, Assignment $assignment): View
    {
        $user = $request->user();

        abort_unless($this->canView($assignment, $user), 404);

        $assignment->load(['story', 'teacher']);

        $progress = $assignment->studentProgress($user);

        return view('student.assignment', [
            'assignment' => $assignment,
            'completed' => $progress['completed'],
            'readDone' => $progress['read'],
            'quizDone' => $progress['quiz'],
            'hasQuiz' => $assignment->hasQuiz(),
        ]);
    }

    /**
     * The shared visibility gate lives on the Assignment model so the inbox
     * follow-through uses the exact same rule (see Assignment::isVisibleTo).
     */
    private function canView(Assignment $assignment, $user): bool
    {
        return $assignment->isVisibleTo($user);
    }
}
