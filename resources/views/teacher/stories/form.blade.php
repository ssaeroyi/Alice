@extends('layouts.teacher')

@section('title', $story ? $story->title : 'Add story')
@section('eyebrow', 'Story center')

@section('content')
    <div class="space-y-5">

        <a href="{{ route('teacher.stories.index', request()->query()) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2D231E] transition-colors">
            <x-icons.core.arrow-left class="w-3.5 h-3.5" />
            Back to stories
        </a>

        <div class="flex flex-col items-start sm:flex-row sm:items-center justify-start sm:justify-between gap-3">
            <div>
                <h1 class="text-base font-extrabold text-[#2D231E]">
                    {{ $story ? 'Edit story' : 'Add a story' }}
                </h1>
                <p class="text-xs font-semibold text-[#2D231E] mt-0.5">
                    @if ($story)
                        {{ $story->title }} &middot;
                    @endif
                    Drafts are fine. The meter shows what publishing still needs.
                </p>
            </div>
            @if ($story)
                <x-admin.pill :tone="$story->status === \App\Enums\StoryStatus::Published ? 'ok' : ($story->status === \App\Enums\StoryStatus::Archived ? 'neutral' : 'warn')">
                    {{ $story->status->label() }}
                </x-admin.pill>
            @endif
        </div>

        @if (session('story_title_duplicate'))
            <div class="p-3 bg-amber-50 border border-amber-300 rounded-lg">
                <p class="text-xs font-bold text-amber-800">
                    A story with this title already exists in the center. You may want to check for a duplicate.
                </p>
            </div>
        @endif

        <div
            data-story-tabs
            data-tab-status="{{ json_encode($tabStatus ?? [], JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
            class="space-y-5"
        >

            <div data-toolbar-sentinel aria-hidden="true" class="h-px"></div>

            <div class="sticky top-[var(--topbar-h)] z-20">
                <div data-story-toolbar class="flex items-center justify-between gap-2 p-2 bg-[#FAF6EE]/95 backdrop-blur-md border border-[#E6DCCF] rounded-xl transition-shadow">
                    <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar flex-nowrap md:overflow-visible md:flex-wrap" role="tablist" aria-label="Story sections">
                        <button
                            type="button"
                            data-story-tab="story"
                            role="tab"
                            aria-selected="true"
                            class="inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg text-xs font-bold transition-colors cursor-pointer shrink-0 whitespace-nowrap bg-[#2D231E] text-white"
                        >
                            Story
                            <span data-tab-dot="story" aria-label="Attention needed in the story details" class="ml-1 inline-block h-2 w-2 rounded-full hidden"></span>
                        </button>
                        <button
                            type="button"
                            data-story-tab="quiz"
                            role="tab"
                            aria-selected="false"
                            class="inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg text-xs font-bold transition-colors cursor-pointer shrink-0 whitespace-nowrap bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E]"
                        >
                            Quiz
                            @if ($story)
                                <span data-quiz-count class="ml-1.5 min-w-[20px] h-5 px-1 inline-flex items-center justify-center rounded-full bg-white border border-[#E6DCCF] text-[#2D231E] text-[10px] font-extrabold tabular-nums">
                                    {{ $questions->count() }}
                                </span>
                            @endif
                            <span data-tab-dot="quiz" aria-label="Attention needed for the quiz" class="ml-1 inline-block h-2 w-2 rounded-full hidden"></span>
                        </button>
                        <button
                            type="button"
                            data-story-tab="journal"
                            role="tab"
                            aria-selected="false"
                            class="inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg text-xs font-bold transition-colors cursor-pointer shrink-0 whitespace-nowrap bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E]"
                        >
                            Journal
                            @if ($story)
                                <span data-mission-count class="ml-1.5 min-w-[20px] h-5 px-1 inline-flex items-center justify-center rounded-full bg-white border border-[#E6DCCF] text-[#2D231E] text-[10px] font-extrabold tabular-nums">
                                    {{ $missionActions->count() }}
                                </span>
                            @endif
                            <span data-tab-dot="journal" aria-label="Attention needed for journal moves" class="ml-1 inline-block h-2 w-2 rounded-full hidden"></span>
                        </button>
                        <button
                            type="button"
                            data-story-tab="publish"
                            role="tab"
                            aria-selected="false"
                            class="inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg text-xs font-bold transition-colors cursor-pointer shrink-0 whitespace-nowrap bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E]"
                        >
                            Publish
                            @if ($completeness)
                                <span class="ml-1.5 min-w-[20px] h-5 px-1 inline-flex items-center justify-center rounded-full bg-white border border-[#E6DCCF] text-[#2D231E] text-[10px] font-extrabold tabular-nums">
                                    {{ $completeness->percent() }}%
                                </span>
                            @endif
                            <span data-tab-dot="publish" aria-label="Attention needed before publishing" class="ml-1 inline-block h-2 w-2 rounded-full hidden"></span>
                        </button>
                    </div>
                    <div class="flex items-center justify-end gap-1.5 shrink-0">
                        @if ($story)
                            <span data-autosave-badge class="hidden sm:inline self-center whitespace-nowrap text-[11px] font-bold text-[#2D231E]"></span>
                            <div class="relative">
                                <button
                                    type="button"
                                    data-dropdown-toggle="story-toolbar-menu"
                                    aria-label="More story actions"
                                    class="shrink-0 min-h-[44px] min-w-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-base font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                >⋯</button>
                                <div
                                    id="story-toolbar-menu"
                                    data-dropdown
                                    class="hidden absolute right-0 mt-2 w-56 bg-white border border-[#E6DCCF] rounded-xl p-2 z-50 space-y-1 shadow-[0_4px_12px_rgba(45,35,30,0.08)]"
                                >
                                    @if ($story->status !== \App\Enums\StoryStatus::Archived)
                                        <form method="POST" action="{{ route('teacher.stories.archive', $story) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                data-confirm-open="archive-story-modal"
                                                data-submit-guard
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-[#2D231E] hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                                            >
                                                <span data-submit-idle>Archive story</span>
                                                <span data-submit-active class="hidden"><x-spinner label="Archiving..." /></span>
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('teacher.stories.duplicate', $story) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            data-submit-guard
                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-[#2D231E] hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                                        >
                                            <span data-submit-idle>Duplicate as draft</span>
                                            <span data-submit-active class="hidden"><x-spinner label="Duplicating..." /></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                        <button
                            type="submit"
                            form="story-form"
                            data-submit-guard
class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                        >
                            <span data-submit-idle><span class="sm:hidden">{{ $story ? 'Save' : 'Create' }}</span><span class="hidden sm:inline">{{ $story ? 'Save changes' : 'Create story' }}</span></span>
                            <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                        </button>
                        @if (! $story)
                            <button
                                type="submit"
                                form="story-form"
                                name="intent"
                                value="add_another"
                                data-submit-guard
                                class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                            >
                                <span data-submit-idle><span class="sm:hidden">Save another</span><span class="hidden sm:inline">Save &amp; add another</span></span>
                                <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <form id="story-form" method="POST" action="{{ $story ? route('teacher.stories.update', $story) : route('teacher.stories.store') }}" enctype="multipart/form-data" class="space-y-5" data-story-autosave>
            @csrf
            @if ($story)
                @method('PUT')
            @endif
            <button type="submit" class="hidden" tabindex="-1" aria-hidden="true"></button>

            <div data-story-tab-panel="story" class="space-y-5">

                <x-admin.panel title="Story identity">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="title" class="block text-xs font-bold text-[#2D231E] mb-1.5">Title <span class="text-rose-500">*</span></label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                value="{{ old('title', $story?->title) }}"
                                maxlength="200"
                                class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('title') border-rose-400 @enderror"
                            >
                            @error('title')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="author" class="block text-xs font-bold text-[#2D231E] mb-1.5">Author</label>
                            <input
                                id="author"
                                type="text"
                                name="author"
                                value="{{ old('author', $story?->author) }}"
                                maxlength="120"
                                placeholder="e.g. Mary Shelley"
                                class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('author') border-rose-400 @enderror"
                            >
                            @error('author')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="license" class="flex items-center gap-1.5 text-xs font-bold text-[#2D231E] mb-1.5">
                                License
                                <x-admin.info label="About licenses">
                                    <p class="font-extrabold mb-1">What each license means:</p>
                                    <p><strong class="text-amber-300">Original work</strong>: written by you or your students; you own it.</p>
                                    <p><strong class="text-amber-300">Public domain</strong>: copyright expired; anyone can use it (classic tales, old books).</p>
                                    <p><strong class="text-amber-300">CC BY</strong>: free to use with credit to the author.</p>
                                    <p><strong class="text-amber-300">CC BY-SA</strong>: free to use with credit; derivative works must stay share-alike.</p>
                                    <p><strong class="text-amber-300">CC BY-NC</strong>: free for non-commercial use with credit.</p>
                                    <p><strong class="text-amber-300">CC BY-NC-SA</strong>: non-commercial and share-alike.</p>
                                    <p class="mt-1 text-[#C9BCAD]">When in doubt, "Original work" is always safe.</p>
                                </x-admin.info>
                            </label>
                            <select
                                id="license"
                                name="license"
                                class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('license') border-rose-400 @enderror cursor-pointer"
                            >
                                <option value="">Choose a license...</option>
                                @foreach (config('stories.licenses') as $licenseOption)
                                    <option value="{{ $licenseOption }}" @selected(old('license', $story?->license) === $licenseOption)>
                                        {{ $licenseOption }}
                                    </option>
                                @endforeach
                                @if ($story?->license && ! in_array($story->license, config('stories.licenses'), true))
                                    <option value="{{ $story->license }}" selected>Custom: {{ $story->license }}</option>
                                @endif
                            </select>
                            @error('license')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="source_url" class="block text-xs font-bold text-[#2D231E] mb-1.5">Source URL <span class="font-semibold text-[#2D231E]">(optional)</span></label>
                            <input
                                id="source_url"
                                type="url"
                                name="source_url"
                                value="{{ old('source_url', $story?->source_url) }}"
                                maxlength="255"
                                placeholder="https://"
                                class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('source_url') border-rose-400 @enderror"
                            >
                            <p class="mt-1 text-[11px] font-semibold text-[#2D231E]">Original story? Leave this empty.</p>
                            @error('source_url')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2" data-jump-target="reading_level">
                            <label class="block text-xs font-bold text-[#2D231E] mb-1.5">Reading level <span class="font-semibold text-[#2D231E]">(auto)</span></label>
                            @if ($story?->reading_level)
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    <span class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-full text-xs font-extrabold {{ $story->difficulty_label ? \App\Support\AliceLibrary::difficultyClasses($story->difficulty_label) : 'bg-sky-100 text-sky-700' }}">
                                        {{ $story->difficulty_label ?: 'Grade '.$story->reading_level }}
                                    </span>
                                    @if ($story->difficulty_label)
                                        <span class="text-[11px] font-semibold text-[#2D231E]">
                                            Band {{ $story->difficulty_label }} = app grade {{ $story->reading_level }} (7 Easy · 8 Intermediate · 9 Hard · 10 Very Hard, mapped from the text's readability score). The band is computed from the story text; length and quiz load are shown separately.
                                        </span>
                                    @else
                                        <span class="text-[11px] font-semibold text-[#2D231E]">
                                            Computed by the readability analyzer from the story text. Teachers don't set it.
                                        </span>
                                    @endif
                                </div>
                            @else
                                <p class="text-[11px] font-semibold text-[#2D231E]">
                                    Set automatically from the story text. Type at least
                                    {{ \App\Support\ReadabilityAnalyzer::MIN_WORDS }} words and the check runs itself.
                                </p>
                            @endif
                        </div>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Cover">
                    <input type="hidden" name="cover_mode" value="{{ old('cover_mode', $story?->cover_image && ! str_starts_with($story->cover_image, 'http') ? 'upload' : 'url') }}" data-cover-mode-input>

                    <div class="flex items-start gap-4 mb-4">
                        <img
                            data-cover-preview
                            src="{{ $story?->cover_image ? $story->coverUrl() : asset('images/placeholder-cover.svg') }}"
                            alt="Cover preview"
                            class="w-24 h-32 object-cover rounded-lg border border-[#E6DCCF] {{ $story?->cover_image ? '' : 'opacity-40' }}"
                        >
                        <div class="min-w-0 space-y-2 pt-1">
                            <p class="text-xs font-bold text-[#2D231E]">{{ $story?->cover_image ? 'Current cover' : 'No cover yet' }}</p>
                            <p class="text-[11px] font-semibold text-[#2D231E]">Shown to students on the library shelf and the story page.</p>
                            @if ($story?->cover_image)
                                <label class="flex items-center gap-2 pt-1 text-[11px] font-bold text-[#2D231E] cursor-pointer">
                                    <input type="checkbox" name="remove_cover" value="1" class="w-4 h-4 accent-rose-500 cursor-pointer">
                                    Remove the current cover
                                </label>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 p-1 bg-[#F3ECE0] border border-[#E6DCCF] rounded-xl mb-4" role="group" aria-label="Cover source" data-jump-target="cover_image">
                        <button type="button" data-cover-mode-button="upload" aria-pressed="false"
                            class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors cursor-pointer">
                            Upload
                        </button>
                        <button type="button" data-cover-mode-button="search" aria-pressed="false"
                            class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors cursor-pointer">
                            Find on web
                        </button>
                        <button type="button" data-cover-mode-button="url" aria-pressed="false"
                            class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors cursor-pointer">
                            Paste URL
                        </button>
                    </div>

                    <div data-cover-mode="upload">
                        <label
                            for="cover_image"
                            data-cover-dropzone
                            class="block cursor-pointer rounded-xl border-2 border-dashed border-[#D8CCBC] bg-[#FAF6EE] hover:border-[#2D231E] transition-colors px-4 py-6 text-center"
                        >
                            <svg class="mx-auto w-6 h-6 text-[#B5A89C]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <span class="mt-2 block text-xs font-bold text-[#2D231E]">Drop an image here, or click to browse</span>
                            <span class="mt-0.5 block text-[11px] font-semibold text-[#2D231E]">JPEG, PNG or WebP &middot; up to 2 MB</span>
                        </label>
                        <input id="cover_image" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-cover-file class="hidden">

                        @error('cover_image')
                            <div class="mt-3 p-3 bg-rose-50 border border-rose-300 rounded-lg">
                                <p class="text-xs font-bold text-rose-800">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>

                    <div data-cover-mode="search" class="hidden">
                        <button
                            type="button"
                            data-cover-search
                            data-cover-search-url="{{ route('teacher.stories.cover-search') }}"
                            class="inline-flex items-center justify-center min-h-[44px] w-full px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">
                            Search Open Library &amp; Wikimedia
                        </button>
                        <p class="mt-1.5 text-[11px] font-semibold text-[#2D231E]">Uses the story title and author to find real book covers. Picking one pastes it into the URL field.</p>
                        <p data-cover-search-error class="mt-3 p-3 bg-rose-50 border border-rose-300 rounded-lg text-xs font-bold text-rose-800 hidden"></p>
                        <div data-cover-search-results class="hidden mt-3"></div>
                    </div>

                    <div data-cover-mode="url" class="hidden">
                        <input
                            id="cover_url"
                            type="url"
                            name="cover_url"
                            value="{{ old('cover_url', $story?->cover_image && str_starts_with($story->cover_image, 'http') ? $story->cover_image : '') }}"
                            maxlength="255"
                            placeholder="https://example.com/cover.jpg"
                            class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                        >

                        @error('cover_url')
                            <div class="mt-3 p-3 bg-rose-50 border border-rose-300 rounded-lg">
                                <p class="text-xs font-bold text-rose-800">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Synopsis">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <p class="text-[11px] font-semibold text-[#2D231E]">A few sentences students see before opening the story.</p>
                        <button
                            type="button"
                            data-synopsis-fill="content"
                            class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-3 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-[11px] font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                        >
                            Fill from opening
                        </button>
                    </div>
                    <textarea
                        id="synopsis"
                        name="synopsis"
                        rows="3"
                        maxlength="2000"
                        data-synopsis-target="content"
                        placeholder="A few sentences students see before opening the story."
                        class="w-full px-3 py-2 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('synopsis') border-rose-400 @enderror"
                    >{{ old('synopsis', $story?->synopsis) }}</textarea>
                    @error('synopsis')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </x-admin.panel>

                <x-admin.panel title="Story text" subtitle="Paste the story here. Word count is computed automatically. Leave a blank line between paragraphs; the reader pages by paragraph.">
                    <textarea
                        id="content"
                        name="content"
                        rows="14"
                        spellcheck="false"
                        data-word-count-target="content"
                        data-word-limit="{{ config('stories.content.max_words') }}"
                        data-char-limit="{{ config('stories.content.max_chars') }}"
                        data-readability-analyze-text="content"
                        data-readability-url="{{ route('teacher.stories.analyze') }}"
                        data-readability-min-words="{{ \App\Support\ReadabilityAnalyzer::MIN_WORDS }}"
                        data-grammar-text="content"
                        placeholder="Once upon a time..."
                        class="w-full px-3 py-2 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] resize-y @error('content') border-rose-400 @enderror"
                    >{{ old('content', $story?->content) }}</textarea>
                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-[#2D231E]">
                            <span data-word-count="content">0 words</span> &middot;
                            <span data-char-count="content">0 characters</span>
                            <span data-read-time="content" class="hidden"></span>
                        </p>
                        <p data-word-limit-warning="content" class="hidden mt-1 text-[11px] font-bold text-rose-600">
                            Over the {{ number_format(config('stories.content.max_words')) }} word / {{ number_format(config('stories.content.max_chars')) }} character limit. The story won't save until it fits.
                        </p>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                data-grammar-check="content"
                                data-grammar-binary="{{ config('stories.grammar.binary') }}"
                                data-grammar-max-chars="{{ config('stories.grammar.max_chars') }}"
                                class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3.5 bg-emerald-50 border border-emerald-200 rounded-lg text-xs font-bold text-emerald-800 hover:bg-emerald-100 hover:border-emerald-300 transition-colors cursor-pointer disabled:opacity-50"
                            >
                                <span data-grammar-label="content">Check grammar</span>
                                <span data-grammar-busy="content" class="hidden"><x-spinner label="Checking…" /></span>
                            </button>
                            <x-admin.info label="About the grammar check">
                                <p>The check runs entirely in your browser with Harper. The text never leaves this device, and nothing is stored anywhere.</p>
                                <p class="mt-2">The check is a tool, not a gate: it never blocks publishing, and if something goes wrong you can keep writing and try again later.</p>
                            </x-admin.info>
                            <input data-content-import type="file" accept=".txt,.md,text/plain,text/markdown" class="hidden">
                            <button
                                type="button"
                                data-content-import-button="content"
                                class="inline-flex items-center justify-center min-h-[40px] px-3.5 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                            >
                                Import .txt
                            </button>
                            <button
                                type="button"
                                data-clean-format="content"
                                class="inline-flex items-center justify-center min-h-[40px] px-3.5 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                            >
                                Clean format
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                data-preview-story
                                class="inline-flex items-center justify-center min-h-[40px] px-3.5 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                            >
                                Preview
                            </button>
                            <button
                                type="button"
                                data-insights-toggle
                                aria-expanded="false"
                                aria-controls="story-insights-panel"
                                class="inline-flex items-center justify-center gap-1.5 min-h-[40px] px-3.5 bg-white border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                            >
                                Insights
                                <span data-rl-level-badge="content" class="hidden inline-flex items-center px-1.5 py-0.5 bg-sky-100 text-sky-700 rounded-full text-[10px] font-extrabold"></span>
                            </button>
                        </div>
                    </div>
                    <p data-readability-note="content" class="hidden mt-2 text-[11px] font-semibold text-[#2D231E]"></p>
                    <p data-grammar-error="content" class="hidden mt-2 text-[11px] font-semibold text-rose-600"></p>
                    @error('content')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </x-admin.panel>

                

                <x-admin.panel title="Genres">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" data-jump-target="genres">
                        @foreach ($genres as $genre)
                            @php $checked = $story?->genres->contains('id', $genre->id) ?? in_array($genre->id, old('genres', []), true); @endphp
                            <label class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $checked ? 'border-sky-300 bg-sky-50' : 'border-[#E6DCCF] bg-white' }} cursor-pointer transition-colors">
                                <input
                                    type="checkbox"
                                    name="genres[]"
                                    value="{{ $genre->id }}"
                                    @checked($checked)
                                    class="mt-0.5 w-4 h-4 accent-sky-500 cursor-pointer"
                                >
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold text-[#2D231E]">{{ $genre->name }}</span>
                                    <span class="block text-[11px] font-semibold text-[#2D231E] leading-snug">{{ $genre->description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('genres')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </x-admin.panel>

                </div>

            </form>

            <div data-story-tab-panel="publish" class="hidden">
                <x-admin.panel title="Publishing checklist">
                    @if ($completeness)
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-3 bg-[#F3ECE0] rounded-full overflow-hidden">
                                <div class="h-full {{ $completeness->percent() === 100 ? 'bg-emerald-300' : 'bg-sky-300' }} rounded-full transition-all" style="width: {{ $completeness->percent() }}%"></div>
                            </div>
                            <span class="shrink-0 text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $completeness->percent() }}%</span>
                        </div>
                        <p class="text-[11px] font-semibold text-[#2D231E] mt-1">
                            {{ $completeness->done() }} of {{ $completeness->total() }} fields filled.
                        </p>

                        <ul class="mt-4 space-y-1">
                            @foreach (\App\Support\StoryCompleteness::FIELDS as $key => $label)
                                @php
                                    $done = $completeness->fields[$key];
                                    $core = in_array($key, \App\Support\StoryCompleteness::CORE, true);
                                @endphp
                                <li>
                                    <button
                                        type="button"
                                        data-jump-field="{{ $key }}"
                                        class="w-full flex items-center gap-2 text-left px-2 py-1.5 rounded-lg text-[11px] font-bold transition-colors cursor-pointer {{ $done ? 'text-emerald-700 hover:bg-[#FAF6EE]' : ($core ? 'text-[#2D231E] hover:bg-[#FAF6EE]' : 'text-[#2D231E] hover:bg-[#FAF6EE]') }}"
                                    >
                                        @if ($done)
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @else
                                            <span class="w-3.5 h-3.5 shrink-0 rounded-full border-2 {{ $core ? 'border-amber-400' : 'border-[#D8CCBE]' }}"></span>
                                        @endif
                                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                                        @if (! $done)
                                            <span class="shrink-0 text-[9px] font-extrabold uppercase tracking-wider {{ $core ? 'text-amber-600' : 'text-[#2D231E]' }}">{{ $core ? 'needed' : 'optional' }}</span>
                                        @elseif ($core)
                                            <span class="shrink-0 text-[9px] font-extrabold uppercase tracking-wider text-emerald-500">required</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        @if ($story)
                            @if ($completeness->publishable() && $story->status !== \App\Enums\StoryStatus::Published)
                                <form method="POST" action="{{ route('teacher.stories.publish', $story) }}" class="mt-4">
                                    @csrf
                                    <button
                                        type="submit"
                                        data-submit-guard
                                        class="w-full inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                                    >
                                        <span data-submit-idle>Publish story</span>
                                        <span data-submit-active class="hidden"><x-spinner label="Publishing..." /></span>
                                    </button>
                                </form>
                            @elseif (! $completeness->publishable())
                                <div class="mt-4 p-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg">
                                    <p class="text-[11px] font-bold text-[#2D231E]">Publishing needs:</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-[#2D231E]">
                                        {{ implode(', ', $completeness->missingCore()) ?: '—' }}
                                    </p>
                                </div>
                            @else
                                <p class="mt-4 text-[11px] font-bold text-emerald-700">This story is published.</p>
@endif

                        <div
                            data-narration
                            data-narration-slug="{{ $story->slug }}"
                            data-narration-url="{{ route('teacher.stories.narration.status', $story) }}"
                            data-narration-request-url="{{ route('teacher.stories.narration.request', $story) }}"
                            class="mt-4 pt-4 border-t border-[#E6DCCF]"
                        >
                            @php
                                $narrationLabel = \App\Support\Narration::label($narration);
                                $canNarrate = $story->status === \App\Enums\StoryStatus::Published;
                                $requestable = $canNarrate && in_array($narration['state'], ['missing', 'stale', 'partial', 'stalled'], true);
                            @endphp
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Narrated audio</p>
                                    <p class="text-[11px] font-semibold text-[#2D231E] mt-0.5">
                                        Students listen along in the reader. Audio is generated on the authoring machine and follows the ordinary deploy step.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider {{ \App\Support\Narration::classes($narration['state']) }}" data-narration-pill>{{ $narrationLabel }}</span>
                            </div>

                            <div class="mt-3 hidden" data-narration-progress-wrap>
                                <div class="h-2 bg-[#F3ECE0] rounded-full overflow-hidden">
                                    <div class="h-full bg-sky-300 rounded-full transition-all duration-500" data-narration-progress style="width:0%"></div>
                                </div>
                                <p class="mt-1 text-[10px] font-semibold text-[#2D231E]" data-narration-progress-text></p>
                            </div>

                            <div class="mt-3">
                                <button
                                    type="button"
                                    data-narration-request
                                    @if (! $requestable) disabled @endif
                                    class="inline-flex items-center justify-center min-h-[40px] px-4 py-2 bg-sky-600 text-white rounded-lg text-[11px] font-bold hover:bg-sky-700 transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span data-narration-request-idle>{{ $requestable ? 'Generate narration' : 'Generation not available' }}</span>
                                    <span data-narration-request-active class="hidden"><x-spinner label="Generating..." /></span>
                                </button>
                                @if (! $canNarrate)
                                    <p class="mt-2 text-[10px] font-semibold text-[#2D231E]">Publishing the story unlocks narration.</p>
                                @endif
                                <p class="mt-2 text-[10px] font-semibold text-rose-600 hidden" data-narration-error></p>
                            </div>

                            @if (! empty($narrationSegments))
                                @php
                                    $hearTracks = array_map(
                                        static fn (array $segment): array => [
                                            'url' => $segment['url'],
                                            'page' => (int) $segment['page'],
                                            'seconds' => (float) $segment['seconds'],
                                        ],
                                        $narrationSegments,
                                    );
                                    $hearSeconds = (int) round(array_sum(array_column($narrationSegments, 'seconds')));
                                @endphp
                                <div
                                    class="mt-3"
                                    data-hear-narration
                                    data-hear-tracks="{{ json_encode($hearTracks, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                >
                                    <div class="rounded-lg border border-[#E6DCCF] bg-white px-2.5 py-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Hear narration · saved text</p>
                                            <span class="shrink-0 text-[10px] font-semibold text-[#2D231E] tabular-nums" data-hear-meta>Page {{ $hearTracks[0]['page'] ?? 1 }} of {{ count($hearTracks) }} · {{ sprintf('%d:%02d', intdiv($hearSeconds, 60), $hearSeconds % 60) }} total</span>
                                        </div>
                                        <audio controls preload="none" class="h-9 w-full mt-2" data-hear-audio src="{{ $hearTracks[0]['url'] ?? '' }}"></audio>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 pt-4 border-t border-[#E6DCCF]">
                            <button
                                type="button"
                                data-modal-open="delete-story-modal"
                                class="inline-flex items-center justify-center min-h-[40px] px-4 py-2 bg-white border border-rose-300 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                            >
                                Delete story
                            </button>
                        </div>
                        @endif
                    @else
                        <p class="text-sm font-semibold text-[#2D231E]">
                            Save the story first. The checklist then shows exactly what publishing needs.
                        </p>
                    @endif
                </x-admin.panel>
            </div>

            <div data-story-tab-panel="quiz" class="hidden">

@php $questionErrors = $errors->hasAny(['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'correct_options', 'acceptable_answers', 'match_pairs', 'sequence_items', 'section_id', 'type']); @endphp

                <x-admin.panel
                    title="Story quiz"
                    info="Questions are optional. A story without them simply has no quiz on the student side. 5+ recommended: the student quiz draws a random balanced set so every attempt feels fresh."
                >
                    @if (! $story)
                        <p class="text-sm font-semibold text-[#2D231E]">
                            Save the story first. Questions attach to a saved story.
                        </p>
                    @else
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="w-full sm:w-auto sm:flex-1 h-3 bg-[#F3ECE0] rounded-full overflow-hidden">
                                <div class="h-full {{ $questions->count() >= config('stories.quiz_recommended') ? 'bg-emerald-300' : 'bg-amber-300' }} rounded-full transition-all" style="width: {{ min(100, round($questions->count() / config('stories.quiz_recommended') * 100)) }}%"></div>
                            </div>
                            <span class="shrink-0 text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $questions->count() }} of {{ config('stories.quiz_recommended') }}+ recommended</span>
                            <x-admin.info label="How quizzes work">
                                @if ($questions->count() < config('stories.quiz_recommended'))
                                    @php $gap = config('stories.quiz_recommended') - $questions->count(); @endphp
                                    <p>Add {{ $gap }} more question{{ $gap === 1 ? '' : 's' }} to reach the recommended length ({{ config('stories.quiz_recommended') }}+).</p>
                                @else
                                    <p>This quiz meets the recommended length of {{ config('stories.quiz_recommended') }}+ questions.</p>
                                @endif
                                <p class="mt-1">With {{ config('stories.quiz_recommended') }}+ questions, the student quiz draws a random balanced set (mix of multiple choice, true/false, multi-select and identification) so every attempt feels fresh.</p>
                            </x-admin.info>
                            @if ($questions->count() > 0)
                                <button
                                    type="button"
                                    data-preview-quiz
                                    class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                >
                                    Test run
                                </button>
                            @endif
                        </div>

                        <details class="mt-4" data-section-create>
                            <summary class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer list-none">
                                + Add section
                            </summary>
                            <form method="POST" action="{{ route('teacher.stories.sections.store', $story) }}" class="mt-2 flex flex-wrap items-start gap-2">
                                @csrf
                                <div class="flex-1 min-w-[220px]">
                                    <input
                                        type="text"
                                        name="name"
                                        maxlength="60"
                                        placeholder="e.g. Vocabulary"
                                        value="{{ old('name') }}"
                                        class="w-full h-11 px-3 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                    >
                                    @error('name')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button
                                    type="submit"
                                    data-submit-guard
                                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                                >Add section</button>
                            </form>
                        </details>

                        <div data-quiz-card class="{{ $questionErrors ? '' : 'hidden' }} mt-4 p-5 bg-white border border-[#E6DCCF] rounded-lg ring-2 ring-amber-200">
                            <form
                                method="POST"
                                action="{{ route('teacher.stories.questions.store', $story) }}"
                                data-quiz-form
                                data-quiz-url-store="{{ route('teacher.stories.questions.store', $story) }}"
                                data-quiz-url-update="{{ route('teacher.stories.questions.update', ['question' => '__ID__']) }}"
                            >
                                @csrf
                                <input type="hidden" name="_method" value="POST" data-quiz-method>
                                <input type="hidden" name="question_id" value="{{ old('question_id') }}" data-quiz-id>

                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <p class="text-xs font-extrabold text-[#2D231E]">Question editor</p>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            data-quiz-undo
                                            aria-label="Undo the last change to this question"
                                            title="Undo (Ctrl+Z)"
                                            class="min-h-[36px] min-w-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-sm font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer opacity-40 pointer-events-none"
                                        >&#8617;</button>
                                        <button
                                            type="button"
                                            data-quiz-redo
                                            aria-label="Redo the last undone change to this question"
                                            title="Redo (Ctrl+Y)"
                                            class="min-h-[36px] min-w-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-sm font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer opacity-40 pointer-events-none"
                                        >&#8618;</button>
                                    </div>
                                </div>

                                <div>
                                    <label for="quiz-text" class="block text-xs font-bold text-[#2D231E] mb-1.5">Question</label>
                                    <textarea
                                        id="quiz-text"
                                        name="question_text"
                                        rows="3"
                                        maxlength="2000"
                                        data-quiz-text
                                        placeholder="Type the question here…"
                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('question_text') border-rose-400 @enderror"
                                    >{{ old('question_text') }}</textarea>
                                    @error('question_text')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3" data-quiz-type-grid>
                                    <div>
                                        <label for="quiz-type" class="block text-xs font-bold text-[#2D231E] mb-1.5">Type</label>
                                        <select
                                            id="quiz-type"
                                            name="type"
                                            data-quiz-type
                                            class="w-full h-11 px-3 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer"
                                        >
                                            @foreach (\App\Enums\QuestionType::cases() as $typeOption)
                                                <option value="{{ $typeOption->value }}" @selected(old('type', 'mcq') === $typeOption->value)>
                                                    {{ $typeOption->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div data-quiz-section-wrap>
                                        <label for="quiz-section" class="block text-xs font-bold text-[#2D231E] mb-1.5">Section</label>
                                        <select
                                            id="quiz-section"
                                            name="section_id"
                                            data-quiz-section
                                            class="w-full h-11 px-3 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer"
                                        >
                                            @foreach ($sections as $sectionOption)
                                                <option value="{{ $sectionOption->id }}" @selected((string) old('section_id', $sections->first()?->id) === (string) $sectionOption->id)>
                                                    {{ $sectionOption->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div data-quiz-options class="mt-4">
                                    <p class="text-xs font-bold text-[#2D231E]">Options</p>
                                    <div class="mt-1.5 space-y-2">
                                        @foreach (['a', 'b', 'c', 'd'] as $letter)
                                            <div class="flex items-center gap-3">
                                                <span class="shrink-0 w-7 h-7 flex items-center justify-center bg-[#F3ECE0] rounded-md text-[11px] font-extrabold text-[#2D231E] uppercase">{{ $letter }}</span>
                                                <input
                                                    type="text"
                                                    name="option_{{ $letter }}"
                                                    maxlength="255"
                                                    data-quiz-option="{{ $letter }}"
                                                    placeholder="Option {{ strtoupper($letter) }}"
                                                    value="{{ old('option_'.$letter) }}"
                                                    class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('option_'.$letter) border-rose-400 @enderror"
                                                >
                                                <span class="hidden shrink-0" data-quiz-mcq-option="{{ $letter }}">
                                                    <label class="inline-flex items-center gap-1.5 pl-1 text-[10px] font-bold text-[#2D231E] cursor-pointer">
                                                        <input type="radio" name="correct_option" value="{{ $letter }}" data-quiz-correct-radio="{{ $letter }}" @checked(old('correct_option') === $letter) class="w-4 h-4 accent-sky-500 cursor-pointer">
                                                        Correct
                                                    </label>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div data-quiz-answers class="hidden mt-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-[#2D231E]">Accepted answers</p>
                                        <p class="text-[10px] font-semibold text-[#2D231E]">
                                            Checked case-insensitively. "sherlock" and "Sherlock Holmes" both count.
                                        </p>
                                    </div>
                                    <div class="mt-1.5 space-y-2" data-quiz-answers-rows>
                                        @if ($questionErrors && old('type') === 'identification')
                                            @foreach (old('acceptable_answers', ['']) as $oldAnswer)
                                                <div class="flex items-center gap-3" data-quiz-answer-row>
                                                    <span class="shrink-0 w-7 h-7 flex items-center justify-center bg-[#F3ECE0] rounded-md text-[11px] font-extrabold text-[#2D231E] uppercase" data-quiz-answer-index></span>
                                                    <input
                                                        type="text"
                                                        name="acceptable_answers[]"
                                                        maxlength="255"
                                                        data-quiz-answer
                                                        placeholder="e.g. sherlock"
                                                        value="{{ $oldAnswer }}"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('acceptable_answers') border-rose-400 @enderror"
                                                    >
                                                    <button
                                                        type="button"
                                                        data-quiz-answer-remove
                                                        aria-label="Remove answer"
                                                        class="shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                    >×</button>
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="flex items-center gap-3" data-quiz-answer-row>
                                                <span class="shrink-0 w-7 h-7 flex items-center justify-center bg-[#F3ECE0] rounded-md text-[11px] font-extrabold text-[#2D231E] uppercase" data-quiz-answer-index>1</span>
                                                <input
                                                    type="text"
                                                    name="acceptable_answers[]"
                                                    maxlength="255"
                                                    data-quiz-answer
                                                    placeholder="e.g. sherlock"
                                                    class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E] @error('acceptable_answers') border-rose-400 @enderror"
                                                >
                                                <button
                                                    type="button"
                                                    data-quiz-answer-remove
                                                    aria-label="Remove answer"
                                                    class="shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                >×</button>
                                            </div>
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        data-quiz-answer-add
                                        class="mt-2 inline-flex items-center justify-center min-h-[44px] px-3 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                    >+ Add another answer</button>
                                    @error('acceptable_answers')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div data-quiz-pairs class="hidden mt-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-[#2D231E]">Matched pairs</p>
                                        <p class="text-[10px] font-semibold text-[#2D231E]">Students match each prompt to its pair. 2 to 4 pairs.</p>
                                    </div>
                                    <div class="mt-1.5 space-y-2" data-quiz-pairs-rows>
                                        @if ($questionErrors && old('type') === 'matching')
                                            @php $oldLefts = old('match_pairs.left', []); $oldRights = old('match_pairs.right', []); @endphp
                                            @foreach ($oldLefts as $oldPairIndex => $oldLeft)
                                                <div class="flex items-center gap-2" data-quiz-pair-row>
                                                    <input
                                                        type="text"
                                                        name="match_pairs[left][]"
                                                        maxlength="255"
                                                        data-quiz-pair-left
                                                        placeholder="Prompt e.g. London"
                                                        value="{{ $oldLeft }}"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                                    >
                                                    <span class="shrink-0 text-xs font-extrabold text-[#2D231E]" aria-hidden="true">→</span>
                                                    <input
                                                        type="text"
                                                        name="match_pairs[right][]"
                                                        maxlength="255"
                                                        data-quiz-pair-right
                                                        placeholder="Match e.g. Thames"
                                                        value="{{ $oldRights[$oldPairIndex] ?? '' }}"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                                    >
                                                    <button
                                                        type="button"
                                                        data-quiz-pair-remove
                                                        aria-label="Remove pair"
                                                        class="shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                    >×</button>
                                                </div>
                                            @endforeach
                                        @else
                                            @foreach ([1, 2] as $pairRow)
                                                <div class="flex items-center gap-2" data-quiz-pair-row>
                                                    <input
                                                        type="text"
                                                        name="match_pairs[left][]"
                                                        maxlength="255"
                                                        data-quiz-pair-left
                                                        placeholder="Prompt e.g. London"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                                    >
                                                    <span class="shrink-0 text-xs font-extrabold text-[#2D231E]" aria-hidden="true">→</span>
                                                    <input
                                                        type="text"
                                                        name="match_pairs[right][]"
                                                        maxlength="255"
                                                        data-quiz-pair-right
                                                        placeholder="Match e.g. Thames"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                                    >
                                                    <button
                                                        type="button"
                                                        data-quiz-pair-remove
                                                        aria-label="Remove pair"
                                                        class="shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                    >×</button>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        data-quiz-pair-add
                                        class="mt-2 inline-flex items-center justify-center min-h-[44px] px-3 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                    >+ Add another pair</button>
                                    @error('match_pairs')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div data-quiz-sequence class="hidden mt-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-[#2D231E]">Steps in the correct order</p>
                                        <p class="text-[10px] font-semibold text-[#2D231E]">List them top to bottom in the right order. 3 to 5 steps.</p>
                                    </div>
                                    <div class="mt-1.5 space-y-2" data-quiz-sequence-rows>
                                        @if ($questionErrors && old('type') === 'sequence')
                                            @foreach (old('sequence_items', ['']) as $oldStep)
                                                <div class="flex items-center gap-3" data-quiz-sequence-row>
                                                    <span class="shrink-0 w-7 h-7 flex items-center justify-center bg-[#F3ECE0] rounded-md text-[11px] font-extrabold text-[#2D231E] uppercase" data-quiz-sequence-index></span>
                                                    <input
                                                        type="text"
                                                        name="sequence_items[]"
                                                        maxlength="255"
                                                        data-quiz-sequence-item
                                                        placeholder="e.g. She packs the basket"
                                                        value="{{ $oldStep }}"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                                    >
                                                    <button
                                                        type="button"
                                                        data-quiz-sequence-remove
                                                        aria-label="Remove step"
                                                        class="shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                    >×</button>
                                                </div>
                                            @endforeach
                                        @else
                                            @foreach ([1, 2, 3] as $stepRow)
                                                <div class="flex items-center gap-3" data-quiz-sequence-row>
                                                    <span class="shrink-0 w-7 h-7 flex items-center justify-center bg-[#F3ECE0] rounded-md text-[11px] font-extrabold text-[#2D231E] uppercase" data-quiz-sequence-index>{{ $stepRow }}</span>
                                                    <input
                                                        type="text"
                                                        name="sequence_items[]"
                                                        maxlength="255"
                                                        data-quiz-sequence-item
                                                        placeholder="e.g. She packs the basket"
                                                        class="w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                                    >
                                                    <button
                                                        type="button"
                                                        data-quiz-sequence-remove
                                                        aria-label="Remove step"
                                                        class="shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                    >×</button>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        data-quiz-sequence-add
                                        class="mt-2 inline-flex items-center justify-center min-h-[44px] px-3 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                    >+ Add another step</button>
                                    @error('sequence_items')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mt-4" data-quiz-correct-block>
                                    <div class="grid grid-cols-2 gap-3" data-quiz-tf-grid>
                                            <label class="flex items-center justify-center gap-2.5 min-h-[56px] bg-white border-2 border-[#E6DCCF] rounded-lg text-sm font-extrabold text-[#2D231E] cursor-pointer transition-colors hover:border-[#8C7B70] has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60 has-[:checked]:text-emerald-700">
                                                <input type="radio" name="correct_option" value="a" data-quiz-correct-radio-tf="a" @checked(old('correct_option') === 'a') class="peer sr-only">
                                                <span class="w-4 h-4 rounded-full border-2 border-[#B5A89C] bg-white transition-colors peer-checked:border-emerald-500 peer-checked:bg-emerald-500" aria-hidden="true"></span>
                                                True
                                            </label>
                                            <label class="flex items-center justify-center gap-2.5 min-h-[56px] bg-white border-2 border-[#E6DCCF] rounded-lg text-sm font-extrabold text-[#2D231E] cursor-pointer transition-colors hover:border-[#8C7B70] has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/60 has-[:checked]:text-emerald-700">
                                                <input type="radio" name="correct_option" value="b" data-quiz-correct-radio-tf="b" @checked(old('correct_option') === 'b') class="peer sr-only">
                                                <span class="w-4 h-4 rounded-full border-2 border-[#B5A89C] bg-white transition-colors peer-checked:border-emerald-500 peer-checked:bg-emerald-500" aria-hidden="true"></span>
                                                False
                                            </label>
                                        </div>
                                    @error('correct_option')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <button
                                        type="submit"
                                        data-submit-guard
                                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                                    >
                                        <span data-submit-idle>Save question</span>
                                        <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                                    </button>
                                    <button
                                        type="button"
                                        data-quiz-cancel
                                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                    >Cancel</button>
                                </div>
                            </form>
                        </div>

                        @php
                            $units = $sections->map(fn ($section) => [
                                'section' => $section,
                                'questions' => $questions->where('section_id', $section->id)->values(),
                            ])->values();
                            $questionNumber = 0;
                        @endphp

                        <div class="mt-4 space-y-4">
                            @foreach ($units as $unit)
                                @php $unitSection = $unit['section']; @endphp
                                <div class="p-4 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg">
                                    <div class="flex flex-wrap items-center gap-2 pb-3 border-b border-[#F3ECE0]">
                                        <h3 class="text-sm font-extrabold text-[#2D231E]">{{ $unitSection->name }}</h3>
                                        <span class="inline-flex items-center px-2 py-0.5 bg-white border border-[#E6DCCF] rounded-full text-[10px] font-extrabold text-[#2D231E] tabular-nums">
                                            {{ $unit['questions']->count() }} {{ $unit['questions']->count() === 1 ? 'question' : 'questions' }}
                                        </span>

                                        @if ($unitSection)
                                            <div class="ml-auto flex flex-wrap items-center gap-1">
                                                <details class="relative" data-section-rename>
                                                    <summary class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold text-[#2D231E] hover:text-[#3D3029] hover:underline cursor-pointer list-none">Rename</summary>
                                                    <form method="POST" action="{{ route('teacher.stories.sections.update', $unitSection) }}" class="absolute right-0 top-full z-20 mt-1 w-60 p-3 bg-white border border-[#E6DCCF] rounded-lg shadow-[0_8px_24px_rgba(45,35,30,0.15)] space-y-2">
                                                        @csrf
                                                        @method('PUT')
                                                        <input
                                                            type="text"
                                                            name="name"
                                                            maxlength="60"
                                                            value="{{ old('name', $unitSection->name) }}"
                                                            class="w-full h-10 px-3 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E]"
                                                        >
                                                        @error('name')
                                                            <p class="text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                                        @enderror
                                                        <button
                                                            type="submit"
                                                            data-submit-guard
                                                            class="w-full inline-flex items-center justify-center min-h-[40px] px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                                                        >Save name</button>
                                                    </form>
                                                </details>
                                                <form method="POST" action="{{ route('teacher.stories.sections.move', $unitSection) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button
                                                        type="submit"
                                                        aria-label="Move section up"
                                                        @disabled($loop->first)
                                                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                                                    >↑</button>
                                                </form>
                                                <form method="POST" action="{{ route('teacher.stories.sections.move', $unitSection) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button
                                                        type="submit"
                                                        aria-label="Move section down"
                                                        @disabled($loop->last)
                                                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                                                    >↓</button>
                                                </form>
                                                <form method="POST" action="{{ route('teacher.stories.sections.destroy', $unitSection) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="section_name" value="{{ $unitSection->name }}">
                                                    <button
                                                        type="button"
                                                        data-confirm-open="section-delete-modal"
                                                        class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold bg-rose-50 border border-rose-200 text-rose-700 rounded-lg hover:bg-rose-600 hover:border-rose-600 hover:text-white transition-colors cursor-pointer"
                                                    >Delete</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="relative mt-3 space-y-2" data-question-list data-reorder-url="{{ route('teacher.stories.questions.reorder', $story) }}">
                                        @forelse ($unit['questions'] as $question)
                                            @php
                                                $questionNumber++;
                                                $answerLine = match ($question->type) {
                                                    \App\Enums\QuestionType::TrueFalse => $question->correct_option?->value === 'a' ? 'True' : 'False',
                                                    \App\Enums\QuestionType::Identification => collect($question->acceptable_answers)->map(fn ($a) => '"'.$a.'"')->implode(' · '),
                                                    \App\Enums\QuestionType::Matching => collect($question->match_pairs)->map(fn ($p) => $p['left'].' → '.$p['right'])->implode(' · '),
                                                    \App\Enums\QuestionType::Sequence => collect($question->sequence_items)->implode(' → '),
                                                    default => $question->correct_option?->letter().' · '.$question->{'option_'.$question->correct_option?->value},
                                                };
                                            @endphp
                                            <div class="group p-4 bg-white border border-[#E6DCCF] rounded-lg" data-question-card="{{ $question->id }}">
                                                <div class="flex items-start gap-2">
                                                    <button
                                                        type="button"
                                                        data-drag-handle
                                                        aria-label="Reorder this question. Drag, or focus and press Arrow Up or Arrow Down"
                                                        class="shrink-0 min-h-[44px] min-w-[28px] inline-flex items-center justify-center text-[#2D231E] cursor-grab active:cursor-grabbing touch-none"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="w-4 h-4">
                                                            <circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/>
                                                            <circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/>
                                                            <circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/>
                                                        </svg>
                                                    </button>
                                                    <span data-question-number class="shrink-0 w-8 h-8 flex items-center justify-center bg-[#F3ECE0] rounded-lg text-[11px] font-extrabold text-[#2D231E] tabular-nums">
                                                        {{ str_pad((string) $questionNumber, 2, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-xs font-bold text-[#2D231E]">{{ $question->question_text }}</p>
                                                        <p class="mt-0.5 text-[10px] font-bold text-emerald-700">
                                                            ✓ {{ $answerLine }}
                                                        </p>
                                                    </div>
                                                    <x-admin.pill>{{ $question->type->shortLabel() }}</x-admin.pill>
                                                </div>
                                                <div class="mt-3 flex flex-wrap items-center justify-end gap-1 border-t border-[#F3ECE0] pt-3 opacity-100 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-focus-within:opacity-100">
                                                    <span class="mx-1 w-px h-6 bg-[#F3ECE0]"></span>
                                                    <button
                                                        type="button"
                                                        data-quiz-edit
                                                        data-question-id="{{ $question->id }}"
                                                        data-question-type="{{ $question->type->value }}"
                                                        data-question-text="{{ $question->question_text }}"
                                                        data-question-a="{{ $question->option_a }}"
                                                        data-question-b="{{ $question->option_b }}"
                                                        data-question-c="{{ $question->option_c }}"
                                                        data-question-d="{{ $question->option_d }}"
                                                        data-question-correct="{{ $question->correct_option?->value }}"
                                                        data-question-answers="{{ json_encode($question->acceptable_answers ?? []) }}"
                                                        data-question-pairs="{{ json_encode($question->match_pairs ?? []) }}"
                                                        data-question-sequence="{{ json_encode($question->sequence_items ?? []) }}"
                                                        data-question-section="{{ $question->section_id ?? '' }}"
                                                        class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold text-[#2D231E] hover:text-[#3D3029] hover:underline cursor-pointer"
                                                    >Edit</button>
                                                    <form method="POST" action="{{ route('teacher.stories.questions.duplicate', $question) }}">
                                                        @csrf
                                                        <button
                                                            type="submit"
                                                            data-submit-guard
                                                            class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold text-[#2D231E] hover:text-[#3D3029] hover:underline cursor-pointer"
                                                        >Duplicate</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('teacher.stories.questions.destroy', $question) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="question_preview" value="{{ $question->question_text }}">
                                                        <button
                                                            type="button"
                                                            data-confirm-open="question-delete-modal"
                                                            class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold bg-rose-50 border border-rose-200 text-rose-700 rounded-lg hover:bg-rose-600 hover:border-rose-600 hover:text-white transition-colors cursor-pointer"
                                                        >Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-sm font-semibold text-[#2D231E]">
                                                No questions in this section yet.
                                            </p>
                                        @endforelse
                                    </div>

                                    <button
                                        type="button"
                                        data-quiz-add
                                        data-quiz-add-section="{{ $unitSection->id }}"
                                        class="mt-3 inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                    >
                                        + Add question to {{ $unitSection->name }}
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        @if ($sections->isEmpty())
                            <div class="mt-4 p-5 bg-[#FAF6EE] border border-dashed border-[#E6DCCF] rounded-lg text-center">
                                <p class="text-sm font-bold text-[#2D231E]">Organize your quiz with sections</p>
                                <p class="mt-1 text-xs font-semibold text-[#2D231E]">Add a section above, for example Vocabulary or Comprehension, then add your questions into it.</p>
                            </div>
                        @endif

                    @endif
                </x-admin.panel>
            </div>

            <div data-story-tab-panel="journal" class="hidden">
                <x-admin.panel
                    title="Field Journal actions"
                    info="Panel shows the move pool students draw from. Action runs 3 random picks per battle, and Finish unlocks when they win."
                >
                    @if (! $story)
                        <p class="text-sm font-semibold text-[#2D231E]">
                            Save the story first. Journal actions attach to a saved story.
                        </p>
                    @else
                        <div data-journal-panel class="space-y-4">
                            @include('teacher.partials.journal-pool')
                        </div>
                    @endif
                </x-admin.panel>
            </div>
        </div>
    </div>

    @if ($story)
    <!-- QUESTION DELETE MODAL -->
    <x-modal id="question-delete-modal" title="Remove this question from the quiz?">
        <p class="text-xs font-semibold text-[#2D231E]">
            "<span data-confirm-field="question_preview" class="text-[#2D231E] font-bold"></span>"
            will be removed from the story quiz. Students just get fewer questions to draw from. Nothing else changes.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button
                type="button"
                data-modal-close
                class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="button"
                data-confirm-approve
                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
            >Remove question</button>
        </div>
    </x-modal>

    <!-- SECTION DELETE MODAL -->
    <x-modal id="section-delete-modal" title="Remove this section?">
        <p class="text-xs font-semibold text-[#2D231E]">
            "<span data-confirm-field="section_name" class="text-[#2D231E] font-bold"></span>"
            will be removed from the quiz. Its questions are not deleted; they move to Uncategorized.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button
                type="button"
                data-modal-close
                class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="button"
                data-confirm-approve
                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
            >Remove section</button>
        </div>
    </x-modal>

    <!-- JOURNAL ACTION DELETE MODAL -->
    <x-modal id="mission-action-delete-modal" title="Remove this journal move?">
        <p class="text-xs font-semibold text-[#2D231E]">
            "<span data-confirm-field="action_preview" class="text-[#2D231E] font-bold"></span>"
            will be removed from the story's move pool. The battle still runs on auto moves until the pool is empty, and nothing else changes.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button
                type="button"
                data-modal-close
                class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="button"
                data-confirm-approve
                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
            >Remove move</button>
        </div>
    </x-modal>

    <!-- JOURNAL ACTION ADD/EDIT MODAL (step 1 gallery, step 2 config) -->
    <x-modal
        id="mission-action-modal"
        title="Journal move"
        wide
        :open="$errors->hasAny(['rule_type', 'need'])"
    >
        <div data-mission-step="gallery" {{ $errors->hasAny(['rule_type', 'need']) ? 'hidden' : '' }}>
            @include('teacher.partials.journal-gallery')
        </div>

        <form
            data-mission-step="config"
            {{ $errors->hasAny(['rule_type', 'need']) ? '' : 'hidden' }}
            data-mission-form
            data-journal-mutate
            data-submit-guard
            method="POST"
            action="{{ $story ? route('teacher.stories.mission-actions.store', $story) : '#' }}"
            data-url-store="{{ $story ? route('teacher.stories.mission-actions.store', $story) : '' }}"
            data-url-update="{{ $story ? route('teacher.stories.mission-actions.update', ['missionAction' => '__ID__']) : '' }}"
            class="space-y-4"
        >
            @csrf
            <input type="hidden" name="_method" value="POST" data-mission-method>
            <input type="hidden" name="mission_action_id" value="{{ old('mission_action_id') }}" data-mission-id>
            <input type="hidden" name="rule_type" value="{{ old('rule_type') }}" data-mission-type>
            <input type="hidden" name="rule_value" value="{{ old('rule_value') }}" data-mission-value>

            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Move type</p>
                    <p data-mission-label class="text-sm font-extrabold text-[#2D231E]">Pick a move type</p>
                </div>
                <button
                    type="button"
                    data-mission-back
                    class="shrink-0 min-h-[44px] px-3 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline cursor-pointer"
                >Other types</button>
            </div>

            <div data-mission-need-row>
                <div class="flex items-center justify-between gap-3">
                    <label for="mission-need" class="text-xs font-bold text-[#2D231E]">Words to find</label>
                    <output data-mission-need-out class="min-w-[2.5rem] text-right text-xs font-extrabold text-[#2D231E] tabular-nums">{{ old('need', 1) }}</output>
                </div>
                <input
                    id="mission-need"
                    type="range"
                    name="need"
                    min="1"
                    max="5"
                    value="{{ old('need', 1) }}"
                    data-mission-need
                    class="mt-2 w-full accent-emerald-600 cursor-pointer"
                >
            </div>

            @php $missionInitialRow = collect($missionCatalog)->firstWhere('type', 'initial'); @endphp
            <div data-mission-letter-grid hidden>
                <p class="text-xs font-bold text-[#2D231E]">First letter</p>
                <div class="mt-1.5 grid grid-cols-7 gap-1.5">
                    @foreach (($missionInitialRow['letters'] ?? []) as $missionLetter => $missionLetterCount)
                        <button
                            type="button"
                            data-mission-letter="{{ $missionLetter }}"
                            data-letter-count="{{ $missionLetterCount }}"
                            class="min-h-[44px] flex flex-col items-center justify-center bg-white border border-[#E6DCCF] rounded-lg text-xs font-extrabold text-[#2D231E] hover:border-emerald-600 transition-colors cursor-pointer"
                        >
                            {{ strtoupper($missionLetter) }}
                            <span class="text-[10px] font-bold text-current tabular-nums">{{ $missionLetterCount }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <p data-mission-error="rule_type" class="mt-1 text-[11px] font-semibold text-rose-600" @unless($errors->has('rule_type')) hidden @endunless>{{ $errors->first('rule_type') }}</p>
            <p data-mission-error="need" class="mt-1 text-[11px] font-semibold text-rose-600" @unless($errors->has('need')) hidden @endunless>{{ $errors->first('need') }}</p>

            <div class="p-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg">
                <p class="text-[10px] font-extrabold uppercase tracking-wide text-emerald-700">Student preview</p>
                <div class="mt-1.5 flex items-start gap-2">
                    <span class="shrink-0 text-[11px] font-extrabold text-[#2D231E] tabular-nums">MOVE 01</span>
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold text-[#2D231E]" data-mission-flavor>SPELL STRIKE</p>
                        <p class="text-xs font-semibold text-[#2D231E]" data-mission-preview>Find 1 matching words in this story</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button
                    type="button"
                    data-mission-cancel
                    class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                >Cancel</button>
                <button
                    type="submit"
                    data-submit-guard
                    class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                >
                    <span data-submit-idle data-mission-submit>Add move</span>
                    <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                </button>
            </div>
        </form>
    </x-modal>

    <x-modal id="archive-story-modal" title="Archive this story?">
        <p class="text-xs font-semibold text-[#2D231E]">
            "{{ $story->title }}" is hidden from students but kept for later. You can restore it any time.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Archive story</button>
        </div>
    </x-modal>
    @endif

    <!-- STORY TEXT INSIGHTS SLIDE-OVER (readability + grammar details) -->
    <div data-insights-drawer class="fixed inset-0 z-40 pointer-events-none">
        <div data-insights-backdrop class="absolute inset-0 bg-[#2D231E]/40 opacity-0 transition-opacity duration-300 pointer-events-none"></div>
        <aside
            id="story-insights-panel"
            data-insights-panel
            tabindex="-1"
            aria-label="Text insights"
            class="absolute inset-y-0 right-0 w-full max-w-md bg-[#FFFDF9] shadow-2xl transform translate-x-full transition-transform duration-300 pointer-events-auto overflow-y-auto"
        >
            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 px-5 py-3 bg-[#FFFDF9] border-b border-[#E6DCCF]">
                <h3 class="text-xs font-extrabold uppercase tracking-wide text-[#2D231E]">Text insights</h3>
                <button
                    type="button"
                    data-insights-close
                    aria-label="Close text insights"
                    class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg text-[#2D231E] hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-5 py-5 space-y-4">
                <div data-readability-panel="content" class="hidden p-4 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-extrabold text-[#2D231E]">Readability analysis (Flesch-Kincaid grade level)</h4>
                        <span data-rl-level="content" class="inline-flex items-center px-2.5 py-1 bg-sky-100 text-sky-700 rounded-full text-[10px] font-extrabold"></span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 pb-3 border-b border-[#E6DCCF]/70">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Score</p>
                            <p data-rl-score="content" class="text-lg font-extrabold text-[#2D231E]"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Reading tier</p>
                            <p data-rl-tier="content" class="text-sm font-bold text-[#2D231E] capitalize"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Grade level</p>
                            <p data-rl-grade="content" class="text-xs font-bold text-[#2D231E]"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Age range</p>
                            <p data-rl-age="content" class="text-xs font-bold text-[#2D231E]"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-2 pt-3 text-center">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Words</p>
                            <p data-rl-words="content" class="text-xs font-extrabold text-[#2D231E]"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Sentences</p>
                            <p data-rl-sentences="content" class="text-xs font-extrabold text-[#2D231E]"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Syllables</p>
                            <p data-rl-syllables="content" class="text-xs font-extrabold text-[#2D231E]"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Letters</p>
                            <p data-rl-letters="content" class="text-xs font-extrabold text-[#2D231E]"></p>
                        </div>
                    </div>
                    <p class="mt-3 text-[10px] font-semibold text-[#2D231E]">
                        The reading level above is what gets saved automatically. Teachers don't set it by hand.
                    </p>
                </div>

                <div data-grammar-panel="content" class="hidden p-4 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-extrabold text-[#2D231E]">Grammar &amp; spelling check (Harper)</h4>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                data-grammar-apply-all="content"
                                class="hidden inline-flex items-center justify-center min-h-[40px] px-3 bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] rounded-lg text-[11px] font-bold hover:border-[#2D231E] transition-colors cursor-pointer disabled:opacity-40"
                            >
                                Apply all fixes
                            </button>
                            <span data-grammar-summary="content" class="text-[11px] font-bold text-right"></span>
                        </div>
                    </div>
                    <div data-grammar-filters="content" class="hidden flex flex-wrap items-center gap-1.5 mb-3"></div>
                    <div data-grammar-issues="content" class="space-y-2"></div>
                </div>
            </div>
        </aside>
    </div>

    <!-- QUIZ TEST RUN PREVIEW (student register, saved questions only) -->
    <div
        data-quiz-preview
        class="hidden fixed inset-0 z-50 bg-[#FAF6EE] overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-label="Quiz test run preview"
    >
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 px-4 py-2.5 bg-white border-b border-[#E6DCCF]">
            <p class="text-[11px] font-semibold text-[#2D231E]">Test run — saved questions</p>
            <button
                type="button"
                data-preview-close
                aria-label="Close test run"
                class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-lg leading-none font-bold text-[#2D231E] transition-colors cursor-pointer"
            >×</button>
        </div>
        <div data-preview-stage class="mx-auto w-full max-w-2xl px-4 py-6 sm:py-10"></div>
    </div>

    <!-- STORY READER PREVIEW (student register, snapshot of current draft) -->
    <div
        data-story-reader
        data-reader-saved-level="{{ $story?->reading_level }}"
        class="hidden fixed inset-0 z-50 bg-[#FAF6EE] overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-label="Story reader preview"
    >
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 px-4 py-2.5 bg-white border-b border-[#E6DCCF]">
            <p class="text-[11px] font-semibold text-[#2D231E]">Preview — snapshot of the current draft</p>
            <button
                type="button"
                data-reader-close
                aria-label="Close reader preview"
                class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-lg leading-none font-bold text-[#2D231E] transition-colors cursor-pointer"
            >×</button>
        </div>
        <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:py-10 space-y-4">
            <div class="flex items-center justify-center">
                <div class="grid grid-cols-2 gap-1 p-1 bg-white border border-[#E6DCCF] rounded-lg" role="group" aria-label="Preview width">
                    <button type="button" data-reader-width="mobile" aria-pressed="true"
                        class="inline-flex items-center justify-center min-h-[36px] px-4 py-1.5 rounded-md text-sm font-semibold bg-[#F3ECE0] text-[#2D231E] cursor-pointer">Phone</button>
                    <button type="button" data-reader-width="tablet" aria-pressed="false"
                        class="inline-flex items-center justify-center min-h-[36px] px-4 py-1.5 rounded-md text-sm text-[#2D231E] cursor-pointer">Tablet</button>
                </div>
            </div>
            <article data-reader-frame class="mx-auto w-full max-w-[380px] bg-white border border-[#E6DCCF] rounded-lg overflow-hidden transition-all duration-200"></article>
        </div>
    </div>

    @if ($story)
    @php($deleteStoryTitle = 'Delete "'.$story->title.'" permanently?')
    <!-- DELETE STORY MODAL -->
    <x-modal id="delete-story-modal" title="{{ $deleteStoryTitle }}">
        <p class="text-xs font-semibold text-[#2D231E]">
            The story is removed permanently. It disappears from the center and the student library. This is
            different from archiving, which keeps the story for later. This action is recorded in the audit log.
        </p>
        <form method="POST" action="{{ route('teacher.stories.delete', $story) }}" class="space-y-4">
            @csrf
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" data-double-confirm class="mt-0.5 w-4 h-4 accent-rose-600 cursor-pointer">
                <span class="text-xs font-semibold text-[#2D231E]">
                    I understand. "{{ $story->title }}" will be deleted permanently and cannot be restored.
                </span>
            </label>
            <div class="flex items-center justify-end gap-2">
                <button
                    type="button"
                    data-modal-close
                    class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    data-submit-guard
                    data-double-confirm-submit
                    class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
                >
                    <span data-submit-idle>Delete story</span>
                    <span data-submit-active class="hidden"><x-spinner label="Deleting..." /></span>
                </button>
            </div>
        </form>
    </x-modal>
    @endif
@endsection