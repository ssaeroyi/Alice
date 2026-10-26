<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\LibraryController;
use App\Http\Requests\StoreStoryRequest;
use App\Models\Genre;
use App\Models\Question;
use App\Models\QuestionSection;
use App\Models\Story;
use App\Models\StoryMissionAction;
use App\Models\User;
use App\Services\StoryVocabService;
use App\Support\AliceLibrary;
use App\Support\Audit;
use App\Support\MissionActionWriter;
use App\Support\MissionFeatures;
use App\Support\Narration;
use App\Support\QuestionWriter;
use App\Support\ReadabilityAnalyzer;
use App\Support\StoryCompleteness;
use App\Support\StoryPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoryController extends Controller
{
    /**
     * The story shelf: everything teachers have added, filterable by
     * status and genre, with the completeness meter and needs chips per
     * row, sortable by priority or title.
     */
    public function index(Request $request): View
    {
        $sort = $request->string('sort')->toString();

        if (! in_array($sort, ['needs', 'newest', 'oldest', 'title_asc', 'title_desc'], true)) {
            $sort = 'needs';
        }

        $stories = Story::with(['genres', 'creator'])
            ->withCount('questions')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q')->toString();

                $query->where(function ($where) use ($q) {
                    $where->where('title', 'like', "%{$q}%")
                        ->orWhere('author', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('genre'), function ($query) use ($request) {
                $query->whereHas('genres', fn ($genre) => $genre->whereKey($request->integer('genre')));
            })
            ->when($sort === 'needs', function ($query) {
                $query
                    ->orderByRaw("CASE WHEN TRIM(COALESCE(content, '')) = '' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN TRIM(COALESCE(author, '')) = '' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN TRIM(COALESCE(license, '')) = '' THEN 0 ELSE 1 END")
                    ->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM questions WHERE questions.story_id = stories.id) = 0 THEN 0 ELSE 1 END')
                    ->orderByRaw("CASE WHEN COALESCE(cover_image, '') = '' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN TRIM(COALESCE(synopsis, '')) = '' THEN 0 ELSE 1 END")
                    ->latest();
            })
            ->when($sort === 'newest', fn ($query) => $query->latest())
            ->when($sort === 'oldest', fn ($query) => $query->oldest())
            ->when($sort === 'title_asc', fn ($query) => $query->orderBy('title')->orderByDesc('id'))
            ->when($sort === 'title_desc', fn ($query) => $query->orderByDesc('title')->orderByDesc('id'))
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString();

        return view('teacher.stories.index', [
            'stories' => $stories,
            'genres' => Genre::orderBy('name')->get(),
            'sort' => $sort,
        ]);
    }

    public function create(): View
    {
        return view('teacher.stories.form', [
            'story' => null,
            'genres' => Genre::orderBy('name')->get(),
            'completeness' => null,
            'missionActions' => collect(),
            'missionCatalog' => [],
            'tabStatus' => [],
        ]);
    }

    public function store(StoreStoryRequest $request): RedirectResponse
    {
        $readingLevel = static::recalibratedLevel(ReadabilityAnalyzer::readingLevelFor($request->input('content')));

        $story = Story::create([
            ...$request->safe()->except(['genres']),
            'reading_level' => $readingLevel,
            'difficulty_label' => $readingLevel !== null ? AliceLibrary::difficultyBand($readingLevel) : null,
            'word_count' => static::wordCount($request->input('content')),
            'cover_image' => $this->resolveCover($request, null),
            'status' => StoryStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        $story->genres()->sync($request->input('genres', []));

        Audit::log('story.created', $story);

        $this->flashDuplicateTitleWarning($request->input('title'), $story->id);

        if ($request->input('intent') === 'add_another') {
            return redirect()
                ->route('teacher.stories.create')
                ->with('toast', ['message' => 'Story created — add another.']);
        }

        return redirect()
            ->route('teacher.stories.edit', $story)
            ->with('toast', ['message' => 'Story created — keep filling the fields, the meter shows what is left.']);
    }

    public function edit(Story $story): View
    {
        $completeness = StoryCompleteness::evaluate($story);
        $narration = Narration::runtime($story);
        $narrationSegments = Narration::forStory($story, StoryPagination::pages($story->content)['pages']);

        return view('teacher.stories.form', [
            'story' => $story,
            'genres' => Genre::orderBy('name')->get(),
            'completeness' => $completeness,
            'questions' => $story->questions()->orderBy('position')->get(),
            'sections' => $story->sections()->get(),
            'narration' => $narration,
            'narrationSegments' => $narrationSegments,
            'missionActions' => $story->missionActions()->get(),
            'missionCatalog' => MissionFeatures::refresh($story),
            'tabStatus' => static::tabPayload($story, $completeness, $narration, $narrationSegments),
        ]);
    }

    /**
     * Server-truth baseline for the form's per-tab attention dots
     * (resources/js/components/story-tab-dots.js). The browser re-derives
     * each dot live (typing, narration polls); this payload is the starting
     * point derived from saved state so a plain reload is already correct.
     *
     * @return array<string, mixed>
     */
    private static function tabPayload(Story $story, StoryCompleteness $completeness, array $narration, array $segments): array
    {
        $narratedText = $segments === [] ? null : implode("\n\n", array_column($segments, 'text'));

        return [
            'published' => $story->status === StoryStatus::Published,
            'archived' => $story->status === StoryStatus::Archived,
            'publishable' => $completeness->publishable(),
            'missingCore' => $completeness->missingCore(),
            'story' => [
                'fields' => [
                    'title' => $completeness->fields['title'],
                    'author' => $completeness->fields['author'],
                    'license' => $completeness->fields['license'],
                    'content' => $completeness->fields['content'],
                ],
            ],
            'quiz' => ['count' => $story->questions()->count()],
            'journal' => [
                'enabled' => (bool) $story->missions_enabled,
                'count' => $story->missionActions()->count(),
            ],
            'narration' => [
                'state' => $narration['state'],
                // Fresh-page text so the browser can diff #content against
                // what the audio actually covers: typing a change lights the
                // Publish narration dot before the page ever reloads.
                'narrated_text' => $narratedText,
            ],
        ];
    }

    public function update(Story $story, StoreStoryRequest $request): RedirectResponse
    {
        $this->authorize('update', $story);

        $readingLevel = static::recalibratedLevel(ReadabilityAnalyzer::readingLevelFor($request->input('content')));

        $story->update([
            ...$request->safe()->except(['genres']),
            'reading_level' => $readingLevel,
            'difficulty_label' => $readingLevel !== null ? AliceLibrary::difficultyBand($readingLevel) : null,
            'word_count' => static::wordCount($request->input('content')),
            'cover_image' => $this->resolveCover($request, $story),
        ]);

        $story->genres()->sync($request->input('genres', []));

        Audit::log('story.updated', $story);

        $this->flashDuplicateTitleWarning($request->input('title'), $story->id);

        $message = 'Story saved.';

        if ($story->status === StoryStatus::Published && ! StoryCompleteness::evaluate($story)->publishable()) {
            $story->update(['status' => StoryStatus::Draft]);
            $message = 'Story saved — it was published but no longer meets the publishing checklist, so it was moved back to draft.';
        }

        return redirect()
            ->route('teacher.stories.edit', $story)
            ->with('toast', ['message' => $message]);
    }

    /**
     * Import the curated Alice Library package synchronously - every book
     * lands published (its publish gates are filled by construction);
     * re-importing self-heals stale drafts. Books a teacher deliberately
     * archived stay archived.
     */
    public function importLibrary(): RedirectResponse
    {
        $summary = AliceLibrary::importAll();

        if ($summary['failed'] !== []) {
            $failed = collect($summary['failed'])
                ->take(3)
                ->pluck('reason')
                ->implode(' | ');

            return redirect()
                ->route('teacher.stories.index')
                ->with('toast', ['type' => 'error', 'message' => 'Alice Library import finished with problems: '.$failed]);
        }

        Audit::log('story.library_import');

        $message = 'Alice Library imported and published: '.$summary['created'].' created, '.$summary['updated'].' updated, '.$summary['skipped'].' already up to date.';

        if (($summary['covers_fetched'] ?? 0) > 0) {
            $message .= ' '.$summary['covers_fetched'].' cover(s) restored.';
        }

        if (($summary['covers_failed'] ?? []) !== []) {
            $message .= ' Cover fetch failed for: '.implode(', ', $summary['covers_failed']).'.';
        }

        return redirect()
            ->route('teacher.stories.index')
            ->with('toast', [
                'type' => 'success',
                'message' => $message,
            ]);
    }

    /**
     * The analyze endpoint behind the "Analyze reading level" button.
     * Requires 50+ words so the result is meaningful.
     */
    public function analyze(Request $request): JsonResponse
    {
        $text = trim((string) $request->input('text'));

        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please enter some story content to analyze.',
            ], 422);
        }

        if (str_word_count($text) < ReadabilityAnalyzer::MIN_WORDS) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter at least '.ReadabilityAnalyzer::MIN_WORDS.' words for accurate analysis.',
            ], 422);
        }

        $result = ReadabilityAnalyzer::analyze($text);

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to analyze the text. Please try again.',
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Publishing is the completeness gate: the core fields must all be
     * filled (title, author, license, story text). Reading level and
     * source URL are not manual requirements — the level is derived from
     * the text and original stories have no source.
     */
    public function publish(Story $story): RedirectResponse
    {
        $this->authorize('publish', $story);

        $completeness = StoryCompleteness::evaluate($story);

        if (! $completeness->publishable()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot publish yet — still missing: '.implode(', ', $completeness->missingCore()).'.',
            ]);
        }

        $story->update(['status' => StoryStatus::Published]);

        StoryVocabService::extractForStory($story);
        LibraryController::forgetGenreCounts();

        Audit::log('story.published', $story);

        return redirect()
            ->route('teacher.stories.index')
            ->with('toast', ['message' => '"'.$story->title.'" is now published — students can read it.']);
    }

    public function archive(Story $story): RedirectResponse
    {
        $this->authorize('archive', $story);

        $story->update(['status' => StoryStatus::Archived]);

        LibraryController::forgetGenreCounts();

        Audit::log('story.archived', $story);

        return redirect()
            ->route('teacher.stories.index')
            ->with('toast', ['message' => '"'.$story->title.'" was archived.']);
    }

    /**
     * Duplicate a story as a fresh draft — same content and metadata,
     * " (copy)" appended to the title, ready to edit. The cover path is
     * shared with the original, so deleting one must never delete the
     * other's file.
     */
    public function duplicate(Story $story, Request $request): RedirectResponse
    {
        $copy = $this->duplicateStory($story, $request->user());

        return redirect()
            ->route('teacher.stories.edit', $copy)
            ->with('toast', ['message' => '"'.$story->title.'" was duplicated — the copy is a draft ready to edit.']);
    }

    private function duplicateStory(Story $story, User $user): Story
    {
        $copy = Story::create([
            'title' => $story->title.' (copy)',
            'author' => $story->author,
            'license' => $story->license,
            'source_url' => $story->source_url,
            'reading_level' => $story->reading_level,
            'synopsis' => $story->synopsis,
            'content' => $story->content,
            'word_count' => $story->word_count,
            'cover_image' => $story->cover_image,
            'status' => StoryStatus::Draft,
            'created_by' => $user->id,
            'copied_from_id' => $story->id,
            'condensed_from_id' => $story->condensed_from_id,
        ]);

        $copy->genres()->sync($story->genres()->pluck('genres.id'));

        $sectionMap = [];

        foreach ($story->sections()->orderBy('position')->get() as $section) {
            $sectionMap[$section->id] = $copy->sections()->create([
                'name' => $section->name,
                'position' => $section->position,
            ])->id;
        }

        $copy->questions()->createMany(
            $story->questions()->orderBy('position')->get()->map(fn (Question $question) => [
                ...$question->only([
                    'question_text',
                    'option_a',
                    'option_b',
                    'option_c',
                    'option_d',
                    'correct_option',
                    'correct_options',
                    'acceptable_answers',
                    'match_pairs',
                    'sequence_items',
                    'type',
                    'position',
                ]),
                'section_id' => $question->section_id === null ? null : $sectionMap[$question->section_id],
            ])->all()
        );

        $copy->missionActions()->createMany(
            $story->missionActions()->get()->map(fn (StoryMissionAction $action) => [
                'action_key' => $action->action_key,
                'kind' => $action->kind,
                'config' => $action->config,
                'enabled' => $action->enabled,
                'position' => $action->position,
            ])->all()
        );

        Audit::log('story.duplicated', $copy, ['from' => $story->id]);

        return $copy;
    }

    /**
     * Permanent deletion (double-confirmed in the UI). The stored cover
     * file is removed only when no other story still references it.
     */
    public function delete(Story $story): RedirectResponse
    {
        $this->authorize('delete', $story);

        $this->deleteStory($story);

        return redirect()
            ->route('teacher.stories.index')
            ->with('toast', ['message' => '"'.$story->title.'" was deleted permanently.']);
    }

    private function deleteStory(Story $story): void
    {
        $cover = $story->cover_image;

        $story->forceDelete();

        if (filled($cover) && ! str_starts_with($cover, 'http') && ! Story::where('cover_image', $cover)->exists()) {
            Storage::disk('public')->delete($cover);
        }

        // Narration files are an archive: a delete keeps manifest + MP3s so a
        // later re-import of the same source is instantly narrated again. Only
        // queue/progress bookkeeping is cleared; sync --purge-audio erases them.
        Narration::archiveOut($story->slug);

        Audit::log('story.deleted', $story);
    }

    /**
     * The catalog batch bar (batch 9, part B): one request acts on up to
     * 100 selected stories — publish (only complete ones), archive,
     * duplicate or delete (the UI double-confirms delete). Every
     * successful action is audit-logged individually, and the toast
     * reports what actually happened.
     */
    public function batch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'archive', 'duplicate', 'delete'])],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'exists:stories,id'],
        ]);

        $stories = Story::whereKey($data['ids'])->get();

        $done = 0;
        $skipped = 0;

        foreach ($stories as $story) {
            $canUpdate = Gate::allows('update', $story);

            if ($data['action'] === 'duplicate') {
                $this->duplicateStory($story, $request->user());
                $done++;

                continue;
            }

            if (! $canUpdate) {
                $skipped++;

                continue;
            }

            match ($data['action']) {
                'publish' => $this->batchPublish($story, $done, $skipped),
                'archive' => $this->batchArchive($story, $done, $skipped),
                'delete' => $this->deleteStory($story),
            };

            if ($data['action'] === 'delete') {
                $done++;
            }
        }

        $message = match ($data['action']) {
            'publish' => $skipped > 0
                ? $done.' published · '.$skipped.' skipped (missing required fields or not yours)'
                : $done.' published.',
            'archive' => $skipped > 0
                ? $done.' archived · '.$skipped.' skipped (already archived or not yours)'
                : $done.' archived.',
            'duplicate' => $skipped > 0
                ? $done.' duplicated · '.$skipped.' skipped (not yours)'
                : $done.' duplicated as drafts.',
            'delete' => $skipped > 0
                ? $done.' deleted · '.$skipped.' skipped (not yours)'
                : $done.' deleted permanently.',
        };

        return back()->with('toast', ['message' => $message]);
    }

    private function batchPublish(Story $story, int &$done, int &$skipped): void
    {
        if ($story->status === StoryStatus::Published || ! StoryCompleteness::evaluate($story)->publishable()) {
            $skipped++;

            return;
        }

        $story->update(['status' => StoryStatus::Published]);
        StoryVocabService::extractForStory($story);
        LibraryController::forgetGenreCounts();
        Audit::log('story.published', $story);
        $done++;
    }

    private function batchArchive(Story $story, int &$done, int &$skipped): void
    {
        if ($story->status === StoryStatus::Archived) {
            $skipped++;

            return;
        }

        $story->update(['status' => StoryStatus::Archived]);
        LibraryController::forgetGenreCounts();
        Audit::log('story.archived', $story);
        $done++;
    }

    /**
     * The story quiz pool — comprehension questions attached to a story.
     * Optional for publishing: a story without questions simply has no
     * quiz on the student side (the action is hidden, never a dead end).
     */
    public function questionsStore(Story $story, Request $request): RedirectResponse
    {
        $this->authorize('manageQuestions', $story);

        if ($story->questions()->count() >= config('stories.questions_max')) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This story already has the maximum of '.config('stories.questions_max').' questions.',
            ]);
        }

        $question = QuestionWriter::create($story, $request->all());

        Audit::log('question.created', $question);

        return back()->with('toast', ['message' => 'Question added to the quiz.']);
    }

    public function questionsUpdate(Question $question, Request $request): RedirectResponse
    {
        $this->authorize('manageQuestions', $question->story);

        QuestionWriter::update($question, $request->all());

        Audit::log('question.updated', $question);

        return back()->with('toast', ['message' => 'Question updated.']);
    }

    /**
     * Quiz sections group questions (Vocabulary, Comprehension…).
     * Deleting a section never deletes questions — they fall back to
     * the story's implicit "General" group.
     */
    public function sectionsStore(Story $story, Request $request): RedirectResponse
    {
        $this->authorize('manageQuestions', $story);

        if ($story->sections()->count() >= config('stories.sections_max')) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This story already has the maximum of '.config('stories.sections_max').' sections.',
            ]);
        }

        $section = $story->sections()->create([
            ...QuestionWriter::validateSection($request->all(), $story),
            'position' => QuestionWriter::nextSectionPosition($story),
        ]);

        Audit::log('question_section.created', $section);

        return back()->with('toast', ['message' => 'Section "'.$section->name.'" added to the quiz.']);
    }

    public function sectionsUpdate(QuestionSection $section, Request $request): RedirectResponse
    {
        $this->authorize('manageQuestions', $section->story);

        $section->update(QuestionWriter::validateSection($request->all(), $section->story, $section->id));

        Audit::log('question_section.updated', $section);

        return back()->with('toast', ['message' => 'Section renamed to "'.$section->name.'".']);
    }

    public function sectionsDestroy(QuestionSection $section): RedirectResponse
    {
        $story = $section->story;
        $this->authorize('manageQuestions', $story);

        $storyTitle = $story->title;

        $target = $story->sections()
            ->whereKeyNot($section->id)
            ->whereRaw('LOWER(name) = ?', ['uncategorized'])
            ->first();

        if (! $target) {
            $target = $story->sections()->create([
                'name' => 'Uncategorized',
                'position' => QuestionWriter::nextSectionPosition($story),
            ]);
        }

        $moved = $section->questions()->update(['section_id' => $target->id]);

        $section->delete();

        Audit::log('question_section.deleted', $section, ['questions_moved_to_uncategorized' => $moved]);

        return back()->with('toast', ['message' => 'Section removed from "'.$storyTitle.'" — its '.$moved.' question'.($moved === 1 ? '' : 's').' moved to Uncategorized.']);
    }

    public function sectionsMove(QuestionSection $section, Request $request): RedirectResponse
    {
        $this->authorize('manageQuestions', $section->story);

        $up = $request->input('direction') === 'up';

        $neighbor = QuestionSection::where('story_id', $section->story_id)
            ->where('position', $up ? '<' : '>', $section->position)
            ->orderBy('position', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbor) {
            [$section->position, $neighbor->position] = [$neighbor->position, $section->position];
            $section->save();
            $neighbor->save();
        }

        return back()->with('toast', ['message' => 'Section moved '.($up ? 'up' : 'down').'.']);
    }

    public function questionsDestroy(Question $question): RedirectResponse
    {
        $this->authorize('manageQuestions', $question->story);

        $storyTitle = $question->story->title;

        $question->delete();

        Audit::log('question.deleted', $question);

        return back()->with('toast', ['message' => 'Question removed from "'.$storyTitle.'".']);
    }

    /**
     * Move a question one step up or down within its own section by
     * swapping positions with the nearest neighbor in that section —
     * gaps left by deletions never matter, and questions never jump
     * across section boundaries.
     */
    public function questionsMove(Question $question, Request $request): RedirectResponse
    {
        $this->authorize('manageQuestions', $question->story);

        $up = $request->input('direction') === 'up';

        $neighbor = Question::where('story_id', $question->story_id)
            ->where('position', $up ? '<' : '>', $question->position)
            ->when(
                $question->section_id,
                fn ($query, $sectionId) => $query->where('section_id', $sectionId),
                fn ($query) => $query->whereNull('section_id'),
            )
            ->orderBy('position', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbor) {
            [$question->position, $neighbor->position] = [$neighbor->position, $question->position];
            $question->save();
            $neighbor->save();
        }

        return back()->with('toast', ['message' => 'Question moved '.($up ? 'up' : 'down').'.']);
    }

    /**
     * Bulk reorder one section's questions after a drag-and-drop or
     * keyboard move: the client posts the full id order for a single
     * section and positions are rewritten 1..n inside a transaction.
     */
    public function questionsReorder(Request $request, Story $story): JsonResponse
    {
        $this->authorize('manageQuestions', $story);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['ids']));

        $questions = Question::where('story_id', $story->id)->whereIn('id', $ids)->get();

        if ($questions->count() !== count($ids)) {
            return response()->json(['success' => false, 'message' => 'Some questions do not belong to this story.'], 422);
        }

        $sectionId = $questions->first()->section_id;

        $inSection = Question::where('story_id', $story->id)
            ->when(
                $sectionId,
                fn ($query) => $query->where('section_id', $sectionId),
                fn ($query) => $query->whereNull('section_id'),
            )
            ->count();

        if ($inSection !== count($ids)) {
            return response()->json(['success' => false, 'message' => 'Send every question in the section, in its new order.'], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                Question::where('id', $id)->update(['position' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Duplicate a question as a new card at the end of the quiz pool —
     * the teacher edits the copy in place afterwards.
     */
    public function questionsDuplicate(Question $question): RedirectResponse
    {
        $story = $question->story;
        $this->authorize('manageQuestions', $story);

        $copy = $story->questions()->create([
            ...$question->only([
                'section_id',
                'question_text',
                'option_a',
                'option_b',
                'option_c',
                'option_d',
                'correct_option',
                'correct_options',
                'acceptable_answers',
                'match_pairs',
                'sequence_items',
                'type',
            ]),
            'position' => ($story->questions()->max('position') ?? 0) + 1,
        ]);

        Audit::log('question.duplicated', $copy);

        return back()->with('toast', ['message' => 'Question duplicated — added to the end of the quiz.']);
    }

    /**
     * Field Journal battle moves (the Journal tab): a story stores up to
     * config('missions.authored_max') authored actions; each battle draws
     * a random 3 from the pool, padded with auto moves when the pool is
     * short. Content is a rule + want-count only, never answer words.
     */
    public function missionActionsStore(Story $story, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageJournal', $story);

        $max = (int) config('missions.authored_max');

        if ($story->missionActions()->count() >= $max) {
            $toast = [
                'type' => 'error',
                'message' => 'This story already has the maximum of '.$max.' journal moves.',
            ];

            if ($request->expectsJson()) {
                return response()->json(['toast' => $toast]);
            }

            return back()->with('toast', $toast);
        }

        $action = MissionActionWriter::create($story, $request->all());

        Audit::log('mission_action.created', $action);

        $toast = ['message' => 'Journal move added: "'.MissionActionWriter::summary($action).'"'];

        if ($request->expectsJson()) {
            return response()->json($this->journalPayload($story, $toast));
        }

        return back()->with('toast', $toast);
    }

    public function missionActionsUpdate(StoryMissionAction $missionAction, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageJournal', $missionAction->story);

        MissionActionWriter::update($missionAction, $request->all());

        Audit::log('mission_action.updated', $missionAction);

        $toast = ['message' => 'Journal move updated: "'.MissionActionWriter::summary($missionAction).'"'];

        if ($request->expectsJson()) {
            return response()->json($this->journalPayload($missionAction->story, $toast));
        }

        return back()->with('toast', $toast);
    }

    public function missionActionsDestroy(StoryMissionAction $missionAction, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageJournal', $missionAction->story);

        $story = $missionAction->story;
        $summary = MissionActionWriter::summary($missionAction);

        $missionAction->delete();

        Audit::log('mission_action.deleted', $missionAction, ['summary' => $summary]);

        $toast = ['message' => 'Journal move "'.$summary.'" removed.'];

        if ($request->expectsJson()) {
            return response()->json($this->journalPayload($story, $toast));
        }

        return back()->with('toast', $toast);
    }

    /**
     * Move a journal action one step up or down by swapping positions
     * with the nearest neighbor — gaps left by deletions never matter.
     */
    public function missionActionsMove(StoryMissionAction $missionAction, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageJournal', $missionAction->story);

        $up = $request->input('direction') === 'up';

        $neighbor = StoryMissionAction::where('story_id', $missionAction->story_id)
            ->where('position', $up ? '<' : '>', $missionAction->position)
            ->orderBy('position', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbor) {
            [$missionAction->position, $neighbor->position] = [$neighbor->position, $missionAction->position];
            $missionAction->save();
            $neighbor->save();
            Audit::log('mission_action.moved', $missionAction, ['direction' => $up ? 'up' : 'down']);
        }

        $toast = ['message' => 'Journal move moved '.($up ? 'up' : 'down').'.'];

        if ($request->expectsJson()) {
            return response()->json($this->journalPayload($missionAction->story, $toast));
        }

        return back()->with('toast', $toast);
    }

    /**
     * Drag-reorder endpoint for the Journal tab's move pool (the quiz
     * question list pattern). The client sends every id in the pool,
     * in its new order; the server rewrites positions 1..n and answers
     * with the usual no-reload payload so the list renumbers in place.
     */
    public function missionActionsReorder(Request $request, Story $story): JsonResponse
    {
        $this->authorize('manageJournal', $story);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['ids']));

        $actions = StoryMissionAction::where('story_id', $story->id)
            ->whereIn('id', $ids)
            ->get();

        if ($actions->count() !== count($ids)) {
            return response()->json(['message' => 'Some moves do not belong to this story.'], 422);
        }

        if (count($ids) !== $story->missionActions()->count()) {
            return response()->json(['message' => 'Send every move in the pool, in its new order.'], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                StoryMissionAction::where('id', $id)->update(['position' => $index + 1]);
            }
        });

        Audit::log('mission_action.reordered', $story, ['ids' => $ids]);

        return response()->json($this->journalPayload($story, ['message' => 'Journal move order saved.']));
    }

    /**
     * Per-story journal toggle (the Journal tab's ON/OFF switch). The
     * published-stories sweep migration flipped everything on; a teacher
     * can opt a story back out (or in) without touching anything else.
     */
    public function missionsToggle(Story $story, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageJournal', $story);

        $on = ! $story->missions_enabled;
        $story->update(['missions_enabled' => $on]);

        Audit::log($on ? 'mission.enabled' : 'mission.disabled', $story);

        $toast = [
            'message' => $on
                ? 'Journal battles are on: students win 3 moves before Finish unlocks.'
                : 'Journal battles are off: this story shows the plain Finish button.',
        ];

        if ($request->expectsJson()) {
            return response()->json($this->journalPayload($story, $toast));
        }

        return back()->with('toast', $toast);
    }

    /**
     * Fragments + toast for the Journal tab's no-reload mutations
     * (story-journal.js swaps both regions in place after a JSON reply).
     *
     * @param  array{message: string, type?: string}  $toast
     * @return array{pool: string, gallery: string, toast: array{message: string, type?: string}}
     */
    private function journalPayload(Story $story, array $toast): array
    {
        $actions = $story->missionActions()->get();

        return [
            'pool' => view('teacher.partials.journal-pool', [
                'story' => $story,
                'missionActions' => $actions,
            ])->render(),
            'gallery' => view('teacher.partials.journal-gallery', [
                'missionCatalog' => MissionFeatures::refresh($story),
                'missionActions' => $actions,
            ])->render(),
            'toast' => $toast,
        ];
    }

    /**
     * Resolve the cover from the request: a new upload (stored on the
     * public disk), a pasted URL, the existing cover, or nothing when
     * the teacher removes it. A replaced or removed stored file is
     * deleted; URLs are never touched.
     */
    private function resolveCover(StoreStoryRequest $request, ?Story $story): ?string
    {
        $previous = $story?->cover_image;

        $resolve = ! $request->boolean('remove_cover');

        $next = $resolve ? $previous : null;

        if ($resolve && $request->hasFile('cover_image')) {
            $next = $request->file('cover_image')->store('covers', 'public');
        } elseif ($resolve && $request->input('cover_mode') === 'url' && $request->filled('cover_url')) {
            $next = $request->input('cover_url');
        }

        if ($next !== $previous && filled($previous) && ! str_starts_with($previous, 'http')) {
            Storage::disk('public')->delete($previous);
        }

        return $next;
    }

    private static function wordCount(?string $content): ?int
    {
        return filled($content) ? str_word_count($content) : null;
    }

    /** Map a raw Flesch-Kincaid level (nullable) onto the 7-10 app scale. */
    private static function recalibratedLevel(?int $flesch): ?int
    {
        return $flesch !== null ? AliceLibrary::gradeForReadingLevel($flesch) : null;
    }

    /**
     * Non-blocking warning when another story already carries the title —
     * the teacher decides; the save goes through either way.
     */
    private function flashDuplicateTitleWarning(string $title, ?int $exceptId): void
    {
        if (Story::where('title', $title)->whereKeyNot($exceptId ?? 0)->exists()) {
            session()->flash('story_title_duplicate', true);
        }
    }
}
