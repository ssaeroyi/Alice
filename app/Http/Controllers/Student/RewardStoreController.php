<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\BadgeService;
use App\Services\RewardService;
use App\Support\StreakRestore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RewardStoreController extends Controller
{
    /**
     * Buy a Streak Restore with keys (S6 sink). Refuses when the student
     * already holds the cap or lacks the keys; otherwise debits keys and
     * increments held restores under one lock.
     */
    public function buyFreeze(Request $request): RedirectResponse
    {
        $result = RewardService::buyStreakFreeze($request->user());

        if ($result['ok']) {
            $awarded = BadgeService::award($request->user(), ['freeze_buy'], now());
            if ($awarded->isNotEmpty()) {
                $request->session()->flash('badges_awarded', BadgeService::summarize($awarded));
            }

            return back()->with('toast', [
                'message' => 'Streak Restore added to your safety net!',
            ]);
        }

        if ($result['reason'] === 'cap') {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'You already hold the maximum number of Streak Restores.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'error',
            'message' => 'Not enough keys for a Streak Restore.',
        ]);
    }

    /**
     * Answer the Streak Restore prompt shown after a single missed day:
     * "accept" spends one restore now (backfilling the missed day),
     * "decline"/"unavailable" record the choice so the prompt never fires
     * again for this break and stampDay() honours it.
     */
    public function restoreStreak(Request $request): RedirectResponse
    {
        $answer = $request->input('answer');

        if (! in_array($answer, ['accept', 'decline', 'unavailable'], true)) {
            return back();
        }

        $user = $request->user();

        if ($answer !== 'accept') {
            StreakRestore::recordAnswer($user, $answer === 'decline' ? 'declined' : $answer);

            if ($answer === 'decline') {
                return back()->with('toast', [
                    'message' => 'No Streak Restore used. Your streak will start fresh on your next stamp.',
                ]);
            }

            return back();
        }

        $result = RewardService::useStreakRestore($user, now());

        if (! $result['ok']) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        $awarded = BadgeService::award($user, ['freeze'], now());
        if ($awarded->isNotEmpty()) {
            $request->session()->flash('badges_awarded', BadgeService::summarize($awarded));
        }

        return back()->with('toast', [
            'message' => 'Streak restored! You are back on track.',
        ]);
    }
}
