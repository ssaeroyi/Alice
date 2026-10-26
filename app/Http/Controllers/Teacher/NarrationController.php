<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Support\Audit;
use App\Support\DetachedCommandLauncher;
use App\Support\Narration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Teacher narration status + request endpoints (TTS_AND_DICTIONARY.md §3.6).
 *
 * The button writes wanted.json + runs `alice:narration:export`, then hands
 * the story to the generator. Two transport options, mutually exclusive by
 * config:
 *
 *  - Authoring machine (`stories.narration.local_generator` set): the request
 *    spawns `tools/narration/generate_narration.py --only <slug>` as a detached
 *    background process so the pill flows queued → generating → narrated by
 *    itself. Config stays empty on hosted sites, so nothing ever spawns.
 *  - Hosted site (config unset): pure queue + feed pickup — the authoring
 *    machine pulls the wanted story over `/tools/narration` and pushes MP3s
 *    back. This is the exact pre-existing behavior.
 */
class NarrationController extends Controller
{
    public function __construct(private DetachedCommandLauncher $launcher) {}

    public function status(Story $story): JsonResponse
    {
        $this->authorize('view', $story);

        return response()->json(Narration::runtime($story, fresh: true));
    }

    public function request(Story $story): JsonResponse
    {
        $this->authorize('update', $story);

        if ($story->status !== StoryStatus::Published) {
            return response()->json([
                'success' => false,
                'message' => 'Narration is generated once the story is published.',
            ], 422);
        }

        if (trim((string) $story->content) === '') {
            return response()->json([
                'success' => false,
                'message' => 'The story has no text to read aloud.',
            ], 422);
        }

        // Snapshot the active-run state BEFORE request() re-arms the heartbeat
        // (it deletes the progress file), so a live run can't be double-spawned.
        $hasActiveRun = Narration::hasActiveRun($story->slug);

        Narration::request($story);

        Artisan::call('alice:narration:export', ['--story' => $story->slug]);

        Audit::log('narration.requested', $story);

        $spawned = false;

        if ($this->interpreterPath() !== null && ! $hasActiveRun) {
            $spawned = $this->spawnLocalGenerator($story);
        }

        return response()->json([
            'success' => true,
            'spawned' => $spawned,
            'status' => Narration::runtime($story, fresh: true),
        ]);
    }

    /**
     * The configured interpreter, only when it is an existing file. Windows
     * `start` shows an error dialog for a missing target and would hang a
     * headless box, so a bogus NARRATION_LOCAL_GENERATOR must never reach it.
     */
    private function interpreterPath(): ?string
    {
        $python = trim((string) config('stories.narration.local_generator'));

        return $python === '' || ! is_file($python)
            ? null
            : $python;
    }

    private function spawnLocalGenerator(Story $story): bool
    {
        $python = $this->interpreterPath();

        if ($python === null) {
            return false;
        }

        $script = base_path('tools/narration/generate_narration.py');
        $log = (string) config('stories.narration.local_generator_log');

        if ($log === '') {
            $log = Storage::disk('local')->path("narration/generator/{$story->slug}.log");
        }

        $logKey = "narration/generator/{$story->slug}.log";

        Storage::disk('local')->makeDirectory('narration/generator');

        // tail -f this file to watch the run; failures land here too.
        Storage::disk('local')->put($logKey, '');

        $command = sprintf(
            '"%s" "%s" --only %s > "%s" 2>&1',
            $python,
            $script,
            $story->slug,
            $log,
        );

        // Detached background spawn so the PHP request returns immediately and
        // the child survives the request. cmd.exe's `start /B` on Windows;
        // nohup + `&` on unix.
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'start /B "" '.$command;
        } else {
            $command = 'nohup '.$command.' &';
        }

        return $this->launcher->launch($command, $logKey);
    }
}
