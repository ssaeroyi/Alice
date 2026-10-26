<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SavedWord;
use App\Services\BadgeService;
use App\Services\DailyTaskService;
use App\Support\Dictionary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tap-a-word endpoints (STUDENT_PAGES_PLAN §4.3a). Lookups are read-only
 * and heavily throttled; saving re-resolves the definition SERVER-SIDE -
 * the client never supplies meaning, so a tampered payload can only ever
 * save a word that genuinely exists in our WordNet import.
 */
class WordController extends Controller
{
    public function lookup(Request $request, string $word)
    {
        return response()->json(Dictionary::lookup(urldecode($word)));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:80'],
            'deck_id' => ['nullable', Rule::exists('word_gym_decks', 'id')->where('user_id', $request->user()->id)],
        ]);

        $result = Dictionary::lookup($data['word']);

        if ($result['filtered'] ?? false) {
            return response()->json([
                'saved' => false,
                'message' => "That word isn't available on ALICE.",
            ], 422);
        }

        if (! $result['found']) {
            return response()->json([
                'saved' => false,
                'message' => $result['looks_like_name']
                    ? '"'.$result['word'].'" looks like a name. We only save dictionary words.'
                    : "We couldn't find a definition for that one.",
            ], 422);
        }

        $first = collect($result['senses'])->first();

        // Re-tapping a saved word refreshes its meaning but never touches
        // the deck a student already filed it under.
        $values = [
            'definition' => $first['list'][0]['definition'] ?? null,
            'part_of_speech' => implode(', ', array_column($result['senses'], 'pos')),
            'example_sentence' => $first['list'][0]['example'] ?? null,
        ];

        if (array_key_exists('deck_id', $data)) {
            $values['deck_id'] = $data['deck_id'];
        }

        $saved = SavedWord::updateOrCreate(
            ['user_id' => $request->user()->id, 'word' => $result['resolved_to']],
            $values,
        );

        $wasNew = $saved->wasRecentlyCreated;

        if (! $wasNew) {
            $saved->increment('lookup_count');
        }

        if ($wasNew) {
            $awarded = BadgeService::award($request->user(), ['word'], now());
            if ($awarded->isNotEmpty()) {
                $request->session()->flash('badges_awarded', BadgeService::summarize($awarded));
            }
        }

        // Only a genuinely new word counts toward the learn_words task, so
        // only that path refreshes the live task delta.
        return response()->json([
            'saved' => true,
            'already_saved' => ! $wasNew,
            'word' => $saved->word,
            ...($wasNew ? app(DailyTaskService::class)->syncSeen($request->user(), now(), $request) : []),
        ]);
    }

    /**
     * Unsave a word (reader's save-button toggle). Idempotent: deleting a word
     * that isn't filed reports deleted=false instead of 404ing, so a stale
     * client can always resettle. Points/badges/daily-task credit earned when
     * the word was first saved are deliberately NOT rolled back — the learning
     * event already happened (matches Study bulk-delete semantics).
     */
    public function destroy(Request $request, string $word)
    {
        $word = urldecode($word);

        $deleted = SavedWord::query()
            ->where('user_id', $request->user()->id)
            ->where('word', $word)
            ->delete();

        return response()->json([
            'deleted' => (bool) $deleted,
            'word' => $word,
        ]);
    }
}
