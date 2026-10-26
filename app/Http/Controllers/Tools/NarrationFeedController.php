<?php

namespace App\Http\Controllers\Tools;

use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Support\Narration;
use App\Support\StoryPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Token-guarded feed for the narration generator once Alice is hosted
 * (TTS_AND_DICTIONARY.md §3.6). The dev machine pulls the wanted stories
 * plus their exported page text and pushes progress heartbeats with the
 * same file layout the local pipeline uses — so the teacher status pill is
 * storage-local and mode-agnostic. Routes are inert (404) when
 * NARRATION_FEED_TOKEN is unset.
 */
class NarrationFeedController extends Controller
{
    public function wantedExport(): JsonResponse
    {
        $stories = [];

        foreach (Narration::wantedList() as $entry) {
            $story = Story::query()
                ->where('slug', $entry['slug'])
                ->where('status', StoryStatus::Published->value)
                ->first();

            if ($story === null || trim((string) $story->content) === '') {
                continue;
            }

            $sections = [];

            foreach (array_values(StoryPagination::pages($story->content)['pages']) as $index => $text) {
                $sections[] = ['n' => $index + 1, 'text' => $text];
            }

            $stories[] = [
                'slug' => $story->slug,
                'title' => $story->title,
                'updated_at' => $story->updated_at?->toIso8601String(),
                'sections' => $sections,
            ];
        }

        return response()->json([
            'wanted' => array_column(Narration::wantedList(), 'slug'),
            'export' => [
                'generated_at' => now()->toIso8601String(),
                'voice' => config('stories.narration.voice', 'af_heart'),
                'model' => config('stories.narration.model', 'kokoro-v1.0'),
                'stories' => $stories,
            ],
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:191'],
            'state' => ['required', 'in:running,done,error'],
            'done' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'integer', 'min:0'],
            'current_page' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'string', 'max:64'],
            'updated_at' => ['nullable', 'string', 'max:64'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = $data['slug'];
        $accepted = Narration::wantedFor($slug) !== null
            || Story::where('slug', $slug)->where('status', StoryStatus::Published->value)->exists();

        if (! $accepted) {
            return response()->json([
                'success' => false,
                'message' => 'Not a wanted or published story.',
            ], 422);
        }

        Storage::disk('local')->put(
            "narration/progress/{$slug}.json",
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        // A finished run has no business staying queued.
        if ($data['state'] !== 'running') {
            Narration::removeWanted($slug);
        }

        return response()->json(['success' => true]);
    }
}
