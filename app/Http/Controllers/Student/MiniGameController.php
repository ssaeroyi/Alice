<?php

namespace App\Http\Controllers\Student;

use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\GameRound;
use App\Models\LeaderboardSnapshot;
use App\Models\SavedWord;
use App\Models\Story;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\DailyTaskService;
use App\Services\RewardService;
use App\Services\StoryVocabService;
use App\Services\SynonymService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MiniGameController extends Controller
{
    private const VALID_GAMES = ['wordsearch', 'match', 'sentence'];

    private const VALID_SOURCES = ['open', 'saved'];

    private const VALID_DIFFICULTIES = ['easy', 'medium', 'hard'];

    public function hub(Request $request): View
    {
        $user = $request->user();
        $savedCounts = $user->savedWords()
            ->whereNotNull('definition')
            ->selectRaw('
                COUNT(*) as `all`,
                SUM(CASE WHEN mastery_level <= 1 THEN 1 ELSE 0 END) as easy,
                SUM(CASE WHEN mastery_level = 2 THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN mastery_level >= 3 THEN 1 ELSE 0 END) as hard
            ')
            ->first();
        $savedCounts = [
            'all' => (int) ($savedCounts->all ?? 0),
            'easy' => (int) ($savedCounts->easy ?? 0),
            'medium' => (int) ($savedCounts->medium ?? 0),
            'hard' => (int) ($savedCounts->hard ?? 0),
        ];

        $openCounts = StoryVocabService::wordCounts();

        $bestScores = GameRound::query()
            ->where('user_id', $user->id)
            ->whereIn('game_type', self::VALID_GAMES)
            ->selectRaw('game_type, MAX(score) as best')
            ->groupBy('game_type')
            ->pluck('best', 'game_type');

        $leaderboard = [];
        $weekStart = Carbon::now()->startOfWeek();
        $lastWeekStart = $weekStart->copy()->subWeek();

        // One grouped pass per window spans all three game types instead of
        // three separate scans: current-week scores feed each game's podium
        // entries, last-week scores both freeze the snapshot (first time only)
        // and render the "last week" ranks.
        $currentScores = $this->rankedScores(self::VALID_GAMES, $weekStart);
        $lastWeekScores = $this->rankedScores(self::VALID_GAMES, $lastWeekStart);

        $this->recordWeeklyPodiums($lastWeekScores, $lastWeekStart);
        $lastWeekPodiums = $this->weeklyPodiums($lastWeekStart);

        foreach (self::VALID_GAMES as $gameType) {
            $leaderboard[$gameType] = [
                'current' => $this->gameLeaderboard($user, $gameType, 10, $currentScores->get($gameType, collect())),
                'lastWeek' => $lastWeekPodiums[$gameType] ?? [],
            ];
        }

        return view('student.mini-game', [
            'savedCounts' => $savedCounts,
            'openCounts' => $openCounts,
            'bestScores' => $bestScores,
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Global Top-N leaderboard for a single game type, built from one
     * window's already-grouped, per-user bests (see rankedScores()): each
     * user's personal best (MAX score) with ties broken by the earliest round
     * that achieved that best, so a screen is never flooded by one player.
     * Ranks are 1-based and assembled in PHP so the signed-in student's own
     * standing is always included even outside the podium.
     *
     * @param  Collection<int, array{user_id:int,best:int,first_at:mixed,name:string}>  $scores
     * @return array{entries: array<int, array{rank:int,name:string,score:int,isSelf:bool}>, selfRank:?int, selfBest:?int}
     */
    private function gameLeaderboard(User $user, string $gameType, int $limit = 10, Collection $scores = new Collection): array
    {
        $scores = $scores->values()->all();

        $entries = [];
        $rank = 1;
        foreach ($scores as $i => $score) {
            // Stable rank: equal scores share no ties (the earliest first_at
            // wins), so we just walk 1..N.
            if ($i > 0 && $score['best'] !== $scores[$i - 1]['best']) {
                $rank = $i + 1;
            }
            if (count($entries) < $limit) {
                $entries[] = [
                    'rank' => $rank,
                    'name' => $score['name'],
                    'score' => $score['best'],
                    'avatar' => $score['avatar'] ?? null,
                    'isSelf' => $score['user_id'] === (int) $user->id,
                ];
            }
        }

        $selfBest = null;
        $selfRank = null;
        foreach ($scores as $i => $score) {
            if ($score['user_id'] === (int) $user->id) {
                $selfBest = $score['best'];
                $selfRank = $i + 1;
                break;
            }
        }

        return [
            'entries' => $entries,
            'selfRank' => $selfRank,
            'selfBest' => $selfBest,
        ];
    }

    /**
     * Ranked per-user bests across a set of game types within a window: a
     * single grouped pass per game type for each user's best score plus the
     * first round that recorded it (the tie-break for equal bests), then name
     * lookups in one bulk query. Keyed by game type so every mini-game hub
     * section reads from the same two scans (current + previous week).
     *
     * @param  array<int, string>  $gameTypes
     * @return Collection<string, Collection<int, array{user_id:int,best:int,first_at:mixed,name:string}>>
     */
    private function rankedScores(array $gameTypes, ?Carbon $from): Collection
    {
        $rows = GameRound::query()
            ->whereHas('user', fn ($q) => $q->realAccounts())
            ->whereIn('game_type', $gameTypes)
            ->when($from !== null, fn ($q) => $q->whereBetween('created_at', [$from, $from->copy()->addWeek()]))
            ->selectRaw('game_type, user_id, MAX(score) as best, MIN(created_at) as first_at')
            ->groupBy('game_type', 'user_id')
            ->get();

        $byGame = $rows->groupBy('game_type')->map(fn (Collection $gameRows) => $gameRows
            ->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'best' => (int) $r->best,
                'first_at' => $r->first_at,
            ])
            ->sortByDesc('best')
            ->values()
            ->sortBy('first_at')
            ->values());

        $userIds = $byGame->flatten(1)->pluck('user_id')->unique()->values();
        $users = $userIds->isNotEmpty()
            ? User::realAccounts()->whereIn('id', $userIds)->get(['id', 'nickname', 'avatar'])->keyBy('id')
            : collect();

        return $byGame->map(function (Collection $entries) use ($users) {
            return $entries
                ->map(function (array $score) use ($users) {
                    $u = $users->get($score['user_id']);

                    return [...$score, 'name' => $u?->nickname ?? 'Reader', 'avatar' => $u?->avatar];
                })
                ->values();
        });
    }

    /**
     * Frozen top-3 podiums for a past week read from the snapshot table in
     * one pass, keyed by game type.
     *
     * @return array<string, array<int, array{rank:int,name:string,score:int,avatar:?string}>>
     */
    private function weeklyPodiums(Carbon $weekStart): array
    {
        $podiums = array_fill_keys(self::VALID_GAMES, []);

        $snaps = LeaderboardSnapshot::query()
            ->whereHas('user', fn ($q) => $q->realAccounts())
            ->where('week_start', $weekStart->toDateString())
            ->whereIn('game_type', self::VALID_GAMES)
            ->orderBy('rank')
            ->get();

        foreach ($snaps as $snap) {
            $podiums[$snap->game_type][] = [
                'rank' => $snap->rank,
                'name' => $snap->display_name,
                'score' => $snap->score,
                'avatar' => $snap->avatar,
            ];
        }

        return $podiums;
    }

    /**
     * Freeze last week's podiums once per game type. Runs while last week's
     * rounds are still visible; per-game existence is checked in one query,
     * and the podium rows are written from the already-computed scores (bounded
     * to the top 3). Later weeks return early because the unique
     * (game_type, week_start, rank) rows already exist.
     */
    private function recordWeeklyPodiums(Collection $scoresByGame, Carbon $weekStart): void
    {
        $date = $weekStart->toDateString();

        $frozen = LeaderboardSnapshot::query()
            ->where('week_start', $date)
            ->whereIn('game_type', self::VALID_GAMES)
            ->pluck('game_type')
            ->flip();

        foreach (self::VALID_GAMES as $gameType) {
            if ($frozen->has($gameType)) {
                continue;
            }

            foreach ($scoresByGame->get($gameType, collect())->take(3)->values() as $i => $score) {
                LeaderboardSnapshot::firstOrCreate(
                    ['game_type' => $gameType, 'week_start' => $date, 'rank' => $i + 1],
                    [
                        'user_id' => $score['user_id'],
                        'display_name' => $score['name'],
                        'score' => $score['best'],
                        'avatar' => $score['avatar'] ?? null,
                    ],
                );
            }
        }
    }

    public function setup(Request $request): View
    {
        $user = $request->user();
        $savedCount = $user->savedWords()->whereNotNull('definition')->count();
        $openCounts = StoryVocabService::wordCounts();

        $game = $request->query('game', 'wordsearch');

        if (! in_array($game, self::VALID_GAMES)) {
            $game = 'wordsearch';
        }

        return view('student.game-setup', [
            'savedCount' => $savedCount,
            'openCounts' => $openCounts,
            'game' => $game,
            'pickableStories' => $game === 'wordsearch' ? StoryVocabService::pickableStories() : [],
        ]);
    }

    public function wordsearch(Request $request): View|RedirectResponse
    {
        return $this->gameView($request, 'student.game-wordsearch', 'wordsearch');
    }

    public function match(Request $request): View|RedirectResponse
    {
        return $this->gameView($request, 'student.game-match', 'match');
    }

    public function sentence(Request $request): View|RedirectResponse
    {
        return $this->gameView($request, 'student.game-sentence', 'sentence');
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_type' => ['required', 'string', 'in:'.implode(',', self::VALID_GAMES)],
            'score' => ['required', 'integer', 'min:0', 'max:999'],
            'round_id' => ['required', 'uuid'],
            'words_count' => ['required', 'integer', 'min:1', 'max:24'],
            'correct_count' => ['required', 'integer', 'min:0', 'max:12'],
            'difficulty' => ['required', 'string', 'in:easy,medium,hard'],
            'source' => ['required', 'string', 'in:open,saved'],
        ]);

        $user = $request->user();

        if (GameRound::where('round_id', $data['round_id'])->exists()) {
            return response()->json(['message' => 'Round already submitted.'], 409);
        }

        GameRound::create([
            'user_id' => $user->id,
            'game_type' => $data['game_type'],
            'score' => $data['score'],
            'words_count' => $data['words_count'],
            'round_id' => $data['round_id'],
        ]);

        $result = RewardService::settleGameRound(
            $user,
            $data['game_type'],
            $data['score'],
            $data['round_id'],
            $data['words_count'],
            now()
        );

        $awarded = BadgeService::award($user, ['stamp', 'game', 'freeze'], now());
        if ($awarded->isNotEmpty()) {
            $request->session()->flash('badges_awarded', BadgeService::summarize($awarded));
        }

        $phrases = config('gamification.game_phrases', []);
        $tier = $data['score'] >= 80 ? 'excellent' : ($data['score'] >= 50 ? 'good' : 'poor');
        $phrase = $phrases[$tier][array_rand($phrases[$tier] ?? ['Keep going!'])] ?? 'Keep going!';

        // Games award points only (S1). The "xp" field returned by
        // settleGameRound is the points credited — the client renders it as
        // points, never as keys.
        return response()->json([
            ...$result,
            'score' => $data['score'],
            'phrase' => $phrase,
            ...app(DailyTaskService::class)->syncSeen($user, now(), $request),
        ]);
    }

    /**
     * Charge a key for a hint. Boards are generated client-side, so the
     * server only authorizes and deducts the cost; the client performs the
     * actual reveal once it gets a 200. Mirrors the quiz hint sink.
     */
    public function hint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_type' => ['required', 'string', 'in:'.implode(',', self::VALID_GAMES)],
        ]);

        $user = $request->user();
        $cost = (int) config('gamification.sinks.hint_token');

        $spent = RewardService::spend($user, $cost, 'hint_token', 'game_round', null);

        if (! $spent) {
            return response()->json([
                'message' => 'Not enough keys for a hint. Earn more by finishing quizzes.',
            ], 422);
        }

        return response()->json([
            'message' => 'Hint purchased.',
            'spent' => $cost,
            'balance' => (int) $user->fresh()->total_keys,
        ]);
    }

    private function gameView(Request $request, string $view, string $gameType): View|RedirectResponse
    {
        $user = $request->user();
        $keys = (int) $user->total_keys;
        $hintCost = (int) config('gamification.sinks.hint_token');
        $source = $request->query('source', session('game_config.source', 'open'));
        $difficulty = $request->query('difficulty', session('game_config.difficulty', 'medium'));

        // Equipped Alice skin for the game-page mascot (same slice the reader uses).
        $skinsCatalog = config('skins');
        $skinsState = SkinsController::state($user);
        $equippedAlice = $skinsCatalog['alice'][$skinsState['equipped']['alice']];

        if (! in_array($source, self::VALID_SOURCES)) {
            $source = 'open';
        }

        if (! in_array($difficulty, self::VALID_DIFFICULTIES)) {
            $difficulty = 'medium';
        }

        session(['game_config' => ['source' => $source, 'difficulty' => $difficulty]]);

        $timeMultiplier = config("gamification.game_time_multiplier.{$difficulty}", 1.0);
        $baseTime = (int) config("gamification.game_base_time.{$gameType}", 60);

        if ($gameType === 'match') {
            $pairs = SynonymService::pickPairs(10, $this->recentWords($request, 'match'));

            $this->rememberWords(
                $request,
                'match',
                collect($pairs)->flatMap(fn ($p) => [$p['a'], $p['b']])->all(),
            );

            return view($view, [
                'pairs' => $pairs,
                'source' => $source,
                'difficulty' => $difficulty,
                'timeMultiplier' => $timeMultiplier,
                'baseTime' => $baseTime,
                'memorizeSeconds' => (int) config('gamification.match_memorize_seconds', 10),
                'keys' => $keys,
                'hintCost' => $hintCost,
                'equippedAlice' => $equippedAlice,
            ]);
        }

        if ($gameType === 'sentence') {
            $words = StoryVocabService::pickSentences(5, $difficulty);

            if (count($words) < 3) {
                return redirect()->route('game.setup', ['game' => $gameType])
                    ->with('error', 'Not enough sentences available. Try a different source or difficulty.');
            }

            return view($view, [
                'words' => $words,
                'source' => $source,
                'difficulty' => $difficulty,
                'timeMultiplier' => $timeMultiplier,
                'baseTime' => $baseTime,
                'savedWords' => $user->savedWords()->pluck('word'),
                'keys' => $keys,
                'hintCost' => $hintCost,
                'equippedAlice' => $equippedAlice,
            ]);
        }

        $recent = $this->recentWords($request, 'wordsearch');

        $storyTitle = null;

        // Open source draws from a single story so every round's words share
        // a common theme. The setup picker sends ?story= for a chosen story;
        // without one we fall back to a random published story. If that story
        // is short (or its difficulty tier is thin), top up from the global
        // pool so a round always gets 10 words.
        if ($source === 'open') {
            if ($request->filled('story')) {
                $story = $this->findStory((int) $request->query('story'));

                if ($story === null) {
                    return redirect()->route('game.setup', ['game' => $gameType])
                        ->with('error', 'That story is not available for a word hunt. Pick another one below.');
                }
            } else {
                $story = StoryVocabService::randomStory();
            }

            if ($story !== null) {
                $storyWords = StoryVocabService::pickWordsFromStory(
                    $story->id, 10, $difficulty, $recent, true,
                );

                $fromStory = count($storyWords);

                if ($fromStory < 10) {
                    $usedWords = array_merge(
                        array_map(fn ($w) => $w['word'], $storyWords),
                        $recent,
                    );
                    $topUp = StoryVocabService::pickWords(10 - $fromStory, $difficulty, $usedWords, true);
                    $words = array_merge($storyWords, $topUp);
                } else {
                    $words = $storyWords;
                }

                if ($words !== []) {
                    $storyTitle = $story->title;
                }
            } else {
                $words = StoryVocabService::pickWords(10, $difficulty, $recent, true);
            }
        } else {
            $words = $this->pickWords($user, 10, $source, $difficulty, $recent);
        }

        if (count($words) < 5) {
            return redirect()->route('game.setup', ['game' => $gameType])
                ->with('error', "Not enough {$difficulty} words available. Try a different source or difficulty.");
        }

        $this->rememberWords(
            $request,
            'wordsearch',
            array_map(fn ($w) => $w['word'], $words),
        );

        return view($view, [
            'words' => $words,
            'source' => $source,
            'difficulty' => $difficulty,
            'timeMultiplier' => $timeMultiplier,
            'baseTime' => $baseTime,
            'storyTitle' => $storyTitle,
            'keys' => $keys,
            'hintCost' => $hintCost,
                'equippedAlice' => $equippedAlice,
        ]);
    }

    /**
     * A published story that has at least one extracted vocabulary entry,
     * used to honour a ?story= pick from the setup story picker. Returns null
     * for anything missing, unpublished or vocab-less so callers can redirect
     * back to setup.
     */
    private function findStory(int $storyId): ?Story
    {
        return Story::query()
            ->where('id', $storyId)
            ->where('status', StoryStatus::Published)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('story_vocab')
                    ->whereColumn('story_vocab.story_id', 'stories.id')
                    ->whereNotNull('definition')
                    ->where('definition', '!=', '');
            })
            ->first();
    }

    /**
     * Words the user already saw in a recent round of this game type, so
     * retries avoid repeating the same batch. Session-scoped, survives a
     * refresh, and is bounded so it never grows unbounded.
     *
     * @return array<int, string>
     */
    private function recentWords(Request $request, string $gameType): array
    {
        $list = $request->session()->get('game_recent_words.'.$gameType, []);

        if (count($list) > 40) {
            $list = array_slice($list, -40);
        }

        return $list;
    }

    /**
     * Record the words served this round so the next draw avoids them.
     *
     * @param  array<int, string>  $words
     */
    private function rememberWords(Request $request, string $gameType, array $words): void
    {
        $list = $request->session()->get('game_recent_words.'.$gameType, []);
        $list = array_values(array_unique(array_merge($list, $words)));

        if (count($list) > 40) {
            $list = array_slice($list, -40);
        }

        $request->session()->put('game_recent_words.'.$gameType, $list);
    }

    private function pickWords($user, int $count, string $source, string $difficulty, array $exclude = []): array
    {
        if ($source === 'saved') {
            $query = SavedWord::query()
                ->where('user_id', $user->id)
                ->whereNotNull('definition');

            $masteryMap = [
                'easy' => fn ($q) => $q->where('mastery_level', '<=', 1),
                'medium' => fn ($q) => $q->where('mastery_level', 2),
                'hard' => fn ($q) => $q->where('mastery_level', '>=', 3),
            ];

            if (isset($masteryMap[$difficulty])) {
                $masteryMap[$difficulty]($query);
            }

            if ($exclude !== []) {
                $query->whereNotIn('word', $exclude);
            }

            return $query->inRandomOrder()
                ->limit($count)
                ->get()
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'word' => $w->word,
                    'definition' => $w->definition ?? '',
                    'difficulty' => $difficulty,
                ])
                ->toArray();
        }

        return StoryVocabService::pickWords($count, $difficulty, $exclude);
    }
}
