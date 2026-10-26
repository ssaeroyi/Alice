@extends('layouts.teacher')

@section('title', 'Stories')
@section('eyebrow', 'Story center')

@section('content')
    <div class="space-y-5">

        @if ($stories->total() === 0)
            <x-admin.panel>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-sm font-extrabold text-[#2D231E]">Alice is waiting for her first stories</h2>
                        <p class="text-xs font-semibold text-[#2D231E] mt-1 max-w-2xl">
Create your first story. Title, text and a few details are all it takes. Students see it
in the library the moment you publish.
                        </p>
                    </div>
                    <a
                        href="{{ route('teacher.stories.create') }}"
                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors"
                    >
                        Add story
                    </a>
                </div>
            </x-admin.panel>
        @endif

        <div data-sticky-sentinel aria-hidden="true" class="h-px"></div>

        <div class="sticky top-[var(--topbar-h)] z-20">
            @php
                $activeFilterCount = collect([
                    request('status') ? 1 : 0,
                    request('genre') ? 1 : 0,
                    request('sort', 'needs') !== 'needs' ? 1 : 0,
                ])->sum();
            @endphp

            <x-admin.panel>
                <div class="flex flex-wrap items-center gap-2">
                    <x-admin.search-input
                        :action="route('teacher.stories.index')"
                        :value="request('q')"
                        placeholder="Search title or author"
                        label="Search stories"
                        form-class="relative min-w-0 flex-1"
                    />

                    <div class="flex flex-wrap items-center gap-2">
                    <x-admin.filter-drawer
                        label="Filter"
                        :action="route('teacher.stories.index')"
                        :count="$activeFilterCount"
                        search-name=""
                        :reset-url="route('teacher.stories.index')"
                    >
                        <input type="hidden" name="q" value="{{ request('q') }}">

                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Status</span>
                            <select name="status" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                                <option value="">Any status</option>
                                @foreach (\App\Enums\StoryStatus::cases() as $statusOption)
                                    <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>
                                        {{ $statusOption->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Genre</span>
                            <select name="genre" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                                <option value="">Any genre</option>
                                @foreach ($genres as $genreOption)
                                    <option value="{{ $genreOption->id }}" @selected((string) request('genre') === (string) $genreOption->id)>
                                        {{ $genreOption->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Sort by</span>
                            <select name="sort" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                                <option value="needs" @selected(request('sort', 'needs') === 'needs')>Needs attention first</option>
                                <option value="newest" @selected(request('sort') === 'newest')>Newest first</option>
                                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                                <option value="title_asc" @selected(request('sort') === 'title_asc')>Title A to Z</option>
                                <option value="title_desc" @selected(request('sort') === 'title_desc')>Title Z to A</option>
                            </select>
                        </label>
                    </x-admin.filter-drawer>

                    <form method="POST" action="{{ route('teacher.stories.library.import') }}" data-library-import>
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                        >
                            Import Alice Library
                        </button>
                    </form>
                    <a
                        href="{{ route('teacher.stories.create') }}"
                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors"
                    >
                        Add story
                    </a>
                    </div>
                </div>

                <p class="mt-3 text-xs font-semibold text-[#2D231E]">
                    {{ $stories->total() }} stor{{ $stories->total() === 1 ? 'y' : 'ies' }} in the center
                </p>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            @if ($stories->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">No stories here yet. Start with "Add story".</p>
            @else
                <div data-story-batch>

                    <form id="batch-form" method="POST" action="{{ route('teacher.stories.batch') }}" data-batch-form class="hidden">
                        @csrf
                    </form>

                    <div
                        data-batch-bar
                        data-batch-edit-url="{{ route('teacher.stories.edit', ['story' => '__ID__']) }}"
                        class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-3xl"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2 bg-white/95 backdrop-blur-md border border-[#E6DCCF] rounded-xl shadow-[0_8px_24px_rgba(45,35,30,0.18)]">
                            <p class="text-xs font-bold text-[#2D231E] px-2 shrink-0">
                                <span data-batch-count>0</span> selected
                            </p>
                            <div class="flex items-center gap-2 flex-nowrap md:flex-wrap overflow-x-auto no-scrollbar min-w-0">
                                <a
                                    href="#"
                                    data-batch-edit
                                    class="hidden inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors"
                                >
                                    Edit
                                </a>
                                <button
                                    type="submit"
                                    form="batch-form"
                                    name="action"
                                    value="publish"
                                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                                >
                                    Publish
                                </button>
                                <button
                                    type="submit"
                                    form="batch-form"
                                    name="action"
                                    value="archive"
                                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                >
                                    Archive
                                </button>
                                <button
                                    type="submit"
                                    form="batch-form"
                                    name="action"
                                    value="duplicate"
                                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                >
                                    Duplicate
                                </button>
                                <button
                                    type="button"
                                    data-modal-open="batch-delete-modal"
                                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-rose-300 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                >
                                    Delete
                                </button>
                                <button
                                    type="button"
                                    data-batch-clear
                                    class="inline-flex items-center justify-center min-h-[44px] px-3 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                    <th class="px-4 py-3 w-12" data-batch-checkbox-cell>
                                        <label class="sr-only" for="batch-select-all">Select all stories</label>
                                        <input
                                            id="batch-select-all"
                                            type="checkbox"
                                            data-batch-select-all
                                            class="w-4 h-4 accent-sky-500 cursor-pointer"
                                        >
                                    </th>
                                    <th class="px-4 py-3 font-extrabold">Story</th>
                                    <th class="px-4 py-3 font-extrabold hidden md:table-cell">Difficulty</th>
                                    <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Genres</th>
                                    <th class="px-4 py-3 font-extrabold hidden sm:table-cell">Completeness</th>
                                    <th class="px-4 py-3 font-extrabold">Needs</th>
                                    <th class="px-4 py-3 font-extrabold">Status</th>
                                    <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Audio</th>
                                    <th class="px-4 py-3 text-right font-extrabold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#F0E9DD]">
                                @foreach ($stories as $story)
                                    @php
                                        $completeness = \App\Support\StoryCompleteness::evaluate($story);
                                        $needs = \App\Support\StoryNeeds::chips($story, (int) $story->questions_count);
                                        $visibleNeeds = array_slice($needs, 0, 3);
                                        $extraNeeds = array_slice($needs, 3);
                                    @endphp
                                    <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                        <td class="px-4 py-3" data-batch-checkbox-cell>
                                            <label class="sr-only" for="batch-select-{{ $story->id }}">Select {{ $story->title }}</label>
                                            <input
                                                id="batch-select-{{ $story->id }}"
                                                type="checkbox"
                                                data-batch-select
                                                value="{{ $story->id }}"
                                                class="w-4 h-4 accent-sky-500 cursor-pointer"
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <button type="button" data-drawer-row-trigger="{{ $story->id }}" class="group text-left">
                                                <span class="flex items-center gap-1.5">
                                                    <span class="font-bold text-sky-700 group-hover:text-sky-800 group-hover:underline underline-offset-2 transition-colors truncate max-w-[180px] sm:max-w-[260px]">{{ $story->title }}</span>
                                                    <span class="text-[#2D231E] group-hover:text-sky-700 transition-colors text-xs" aria-hidden="true">›</span>
                                                </span>
                                                <span class="block text-[11px] font-semibold text-[#2D231E] truncate max-w-[200px] sm:max-w-[280px]">
                                                    {{ $story->author ?: 'No author' }}
                                                    @if ($story->word_count) &middot; {{ number_format($story->word_count) }} words @endif
                                                    @if ($story->quiz_difficulty) &middot; <span class="text-[#2D231E]">{{ ucfirst($story->quiz_difficulty) }} quiz</span> @endif
                                                </span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 hidden md:table-cell">
                                            @if ($story->difficulty_label)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ \App\Support\AliceLibrary::difficultyClasses($story->difficulty_label) }}">{{ $story->difficulty_label }}</span>
                                                @if ($story->reading_level)
                                                    <span class="block mt-0.5 text-[10px] font-bold text-[#2D231E]">Grade {{ $story->reading_level }}</span>
                                                @endif
                                            @else
                                                <span class="text-xs font-bold text-[#2D231E]">{{ $story->reading_level ? 'Grade '.$story->reading_level : '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 hidden lg:table-cell">
                                            <div class="flex flex-wrap gap-1 max-w-[260px]">
                                                @forelse ($story->genres as $genre)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px] font-bold">{{ $genre->name }}</span>
                                                @empty
                                                    <span class="text-[11px] font-semibold text-[#2D231E]">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 hidden sm:table-cell">
                                            <div class="flex items-center gap-2 max-w-[140px]">
                                                <div class="flex-1 h-2 bg-[#F3ECE0] rounded-full overflow-hidden">
                                                    <div class="h-full {{ $completeness->percent() === 100 ? 'bg-emerald-300' : 'bg-sky-300' }} rounded-full" style="width: {{ $completeness->percent() }}%"></div>
                                                </div>
                                                <span class="text-[11px] font-extrabold text-[#2D231E] tabular-nums">{{ $completeness->percent() }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-1 max-w-[240px]" data-story-needs>
                                                @foreach ($visibleNeeds as $chip)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold cursor-help
                                                            {{ $chip['tone'] === 'red' ? 'bg-rose-100 text-rose-700' : ($chip['tone'] === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}"
                                                        title="{{ $chip['detail'] }}"
                                                    >{{ $chip['label'] }}</span>
                                                @endforeach
                                                @foreach ($extraNeeds as $chip)
                                                    <span
                                                        class="hidden inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold cursor-help
                                                            {{ $chip['tone'] === 'red' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}"
                                                        title="{{ $chip['detail'] }}"
                                                        data-need-chip-extra
                                                    >{{ $chip['label'] }}</span>
                                                @endforeach
                                                @if ($extraNeeds)
                                                    <button
                                                        type="button"
                                                        data-needs-more
                                                        title="Show all {{ count($needs) }} needs"
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#F3ECE0] text-[#2D231E] text-[10px] font-extrabold hover:bg-[#E6DCCF] cursor-pointer"
                                                    >+{{ count($extraNeeds) }}</button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-admin.pill :tone="$story->status === \App\Enums\StoryStatus::Published ? 'ok' : ($story->status === \App\Enums\StoryStatus::Archived ? 'neutral' : 'warn')">
                                                {{ $story->status->label() }}
                                            </x-admin.pill>
                                        </td>
                                        <td class="px-4 py-3 hidden lg:table-cell">
                                            @php
                                                $narr = \App\Support\Narration::runtime($story);
                                                $narrActionable = $story->status === \App\Enums\StoryStatus::Published
                                                    && in_array($narr['state'], ['missing', 'stale', 'partial', 'stalled'], true);
                                                $narrTone = \App\Support\Narration::classes($narr['state']);
                                                $narrLabel = \App\Support\Narration::label($narr);
                                            @endphp
                                            @if (Gate::allows('update', $story))
                                                <a
                                                    href="{{ route('teacher.stories.edit', $story).'#publish' }}"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $narrTone }} hover:underline underline-offset-2 transition-colors"
                                                    title="{{ $narr['text_changed_since_request'] ? 'Story text changed since the narration request' : ($narrActionable ? 'Generate narration from the Publish tab' : 'Narration lives in the Publish tab') }}"
                                                >{{ $narrLabel }}</a>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $narrTone }}">{{ $narrLabel }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <a
                                                    href="{{ route('teacher.stories.edit', $story) }}"
class="inline-flex items-center justify-center min-h-[44px] px-4 py-1.5 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-[11px] font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors">
                                                    Edit
                                                </a>
                                                <div class="relative">
                                                    <button
                                                        type="button"
                                                        data-dropdown-toggle="story-menu-{{ $story->id }}"
                                                        aria-label="More actions for {{ $story->title }}"
                                                        aria-haspopup="true"
                                                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-base font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                    >⋯</button>
                                                    <div id="story-menu-{{ $story->id }}" data-dropdown class="hidden absolute right-0 top-full mt-1 z-30 w-44 p-1.5 bg-white border border-[#E6DCCF] rounded-xl shadow-[0_8px_24px_rgba(45,35,30,0.15)]">
                                                        @if ($story->status !== \App\Enums\StoryStatus::Published && $completeness->publishable())
                                                            <form method="POST" action="{{ route('teacher.stories.publish', $story) }}">
                                                                @csrf
                                                                <button
                                                                    type="submit"
                                                                    data-submit-guard
                                                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-emerald-700 hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                                                                >
                                                                    <span data-submit-idle>Publish</span>
                                                                    <span data-submit-active class="hidden"><x-spinner label="Publishing..." /></span>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <button
                                                            type="button"
                                                            data-story-drawer-open="{{ $story->id }}"
                                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-[#2D231E] hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                                                        >
                                                            <span>Details</span>
                                                        </button>
                                                        <a
                                                            href="{{ route('teacher.assignments.create', ['story_id' => $story->id]) }}"
                                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-sky-700 hover:bg-[#FAF6EE] transition-colors"
                                                        >
                                                            <span>Assign</span>
                                                        </a>
                                                        <div class="my-1 border-t border-[#F0E9DD]"></div>
                                                        @if ($story->status !== \App\Enums\StoryStatus::Archived)
                                                            <form method="POST" action="{{ route('teacher.stories.archive', $story) }}">
                                                                @csrf
                                                                <button
                                                                    type="submit"
                                                                    data-submit-guard
                                                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-[#2D231E] hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                                                                >
                                                                    <span data-submit-idle>Archive</span>
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
                                                                <span data-submit-idle>Duplicate</span>
                                                                <span data-submit-active class="hidden"><x-spinner label="Duplicating..." /></span>
                                                            </button>
                                                        </form>
                                                        <button
                                                            type="button"
                                                            data-modal-open="story-delete-modal"
                                                            data-story-delete="{{ route('teacher.stories.delete', $story) }}"
                                                            data-story-title="{{ $story->title }}"
                                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                                        >
                                                            <span>Delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <template data-story-drawer-template="{{ $story->id }}">
                                            <div class="flex items-start justify-between gap-4 p-5 border-b border-[#E6DCCF]">
                                                <div class="min-w-0">
                                                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Story details</p>
                                                    <h3 class="text-lg font-extrabold text-[#2D231E] mt-1 break-words">{{ $story->title }}</h3>
                                                    <p class="text-xs font-semibold text-[#2D231E] mt-0.5">{{ $story->author ?: 'No author' }}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    data-story-drawer-close
                                                    aria-label="Close story details"
                                                    class="shrink-0 min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-lg leading-none font-bold text-[#2D231E] transition-colors cursor-pointer"
                                                >&times;</button>
                                            </div>
                                            <div class="p-5 space-y-5">
                                                @if ($story->cover_image)
                                                    <img
                                                        src="{{ $story->coverUrl() }}"
                                                        alt="Cover for {{ $story->title }}"
                                                        class="w-full max-h-80 object-cover rounded-xl border border-[#E6DCCF]"
                                                    >
                                                @endif

                                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                                                    <div>
                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Status</dt>
                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ $story->status->label() }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Difficulty</dt>
                                                        <dd class="mt-0.5">
                                                            @if ($story->difficulty_label)
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ \App\Support\AliceLibrary::difficultyClasses($story->difficulty_label) }}">{{ $story->difficulty_label }}</span>
                                                                @if ($story->reading_level)
                                                                    <span class="block mt-0.5 text-[10px] font-bold text-[#2D231E]">Grade {{ $story->reading_level }}</span>
                                                                @endif
                                                            @else
                                                                <span class="font-bold text-[#2D231E]">{{ $story->reading_level ? 'Grade '.$story->reading_level : '—' }}</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Words</dt>
                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ $story->word_count ? number_format($story->word_count) : '—' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Quiz</dt>
                                                        <dd class="font-bold text-[#2D231E] mt-0.5">
                                                            {{ $story->questions_count }} question{{ $story->questions_count === 1 ? '' : 's' }}
                                                            @if ($story->quiz_difficulty)
                                                                <span class="text-[#2D231E]">· {{ ucfirst($story->quiz_difficulty) }}</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">License</dt>
                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ $story->license ?: '—' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Source</dt>
                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ $story->gutenberg_id ? 'Project Gutenberg' : ($story->source_url ? 'Web source' : 'Original') }}</dd>
                                                    </div>
                                                </dl>

                                                @if ($story->synopsis)
                                                    <div>
                                                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-1.5">Synopsis</p>
                                                        <p class="text-xs font-semibold text-[#2D231E] leading-relaxed">{{ $story->synopsis }}</p>
                                                    </div>
                                                @endif

                                                <div>
                                                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-2">What it needs</p>
                                                    <ul class="space-y-1.5">
                                                        @foreach ($needs as $chip)
                                                            <li class="flex items-start gap-2 text-xs font-semibold text-[#2D231E]">
                                                                <span class="mt-1 w-2 h-2 shrink-0 rounded-full {{ $chip['tone'] === 'red' ? 'bg-rose-500' : ($chip['tone'] === 'amber' ? 'bg-amber-400' : 'bg-emerald-500') }}"></span>
                                                                <span><span class="font-extrabold">{{ $chip['label'] }}</span>: {{ $chip['detail'] }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-[#F0E9DD]">
                                                    <a
                                                        href="{{ route('teacher.stories.edit', $story) }}"
class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors">
                                                        Edit story
                                                    </a>
                                                    @if ($story->status !== \App\Enums\StoryStatus::Published && $completeness->publishable())
                                                        <form method="POST" action="{{ route('teacher.stories.publish', $story) }}" data-submit-guard>
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                                                            >
                                                                <span data-submit-idle>Publish</span>
                                                                <span data-submit-active class="hidden"><x-spinner label="Publishing..." /></span>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if ($story->status !== \App\Enums\StoryStatus::Archived)
                                                        <form method="POST" action="{{ route('teacher.stories.archive', $story) }}" data-submit-guard>
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                            >
                                                                <span data-submit-idle>Archive</span>
                                                                <span data-submit-active class="hidden"><x-spinner label="Archiving..." /></span>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('teacher.stories.duplicate', $story) }}" data-submit-guard>
                                                        @csrf
                                                        <button
                                                            type="submit"
class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                                                            >
                                                                <span data-submit-idle>Duplicate</span>
                                                                <span data-submit-active class="hidden"><x-spinner label="Duplicating..." /></span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="pt-4">
                    {{ $stories->links('pagination::teacher') }}
                </div>
            @endif
        </x-admin.panel>

    </div>

    <div data-story-drawer class="fixed inset-0 z-40 pointer-events-none">
        <div data-story-drawer-backdrop class="absolute inset-0 bg-[#2D231E]/40 opacity-0 transition-opacity duration-300 pointer-events-none"></div>
        <div
            data-story-drawer-panel
            role="dialog"
            aria-modal="true"
            aria-label="Story details"
            class="absolute inset-y-0 right-0 w-full max-w-md bg-[#FFFDF9] shadow-2xl transform translate-x-full transition-transform duration-300 pointer-events-auto overflow-y-auto"
        >
            <div data-story-drawer-body></div>
        </div>
    </div>

    <x-modal id="batch-delete-modal" title="Delete selected stories permanently?">
        <form method="POST" action="{{ route('teacher.stories.batch') }}" data-batch-delete-form data-submit-guard class="space-y-4">
            @csrf
            <input type="hidden" name="action" value="delete">
            <p class="text-xs font-semibold text-[#2D231E]">
                <span data-batch-delete-count>0</span> selected will be removed permanently. They disappear from the
                center and the student library. This is different from archiving, which keeps them for later, and every
                deletion is recorded in the audit log.
            </p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" data-double-confirm class="mt-0.5 w-4 h-4 accent-rose-600 cursor-pointer">
                <span class="text-xs font-semibold text-[#2D231E]">
                    I understand. These stories will be deleted permanently and cannot be restored.
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
                    data-double-confirm-submit
                    class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
                >
                    <span data-submit-idle>Delete stories</span>
                    <span data-submit-active class="hidden"><x-spinner label="Deleting..." /></span>
                </button>
            </div>
                </form>
    </x-modal>

    <x-modal id="story-delete-modal" title="Delete this story permanently?">
        <form method="POST" action="#" data-story-delete-form data-submit-guard class="space-y-4">
            @csrf
            <p class="text-xs font-semibold text-[#2D231E]">
                "<span data-story-delete-title class="text-[#2D231E] font-bold"></span>" will be removed permanently. It disappears from the
                center and the student library. This is different from archiving, which keeps it for later,
                and every deletion is recorded in the audit log.
            </p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" data-double-confirm class="mt-0.5 w-4 h-4 accent-rose-600 cursor-pointer">
                <span class="text-xs font-semibold text-[#2D231E]">
                    I understand. This story will be deleted permanently and cannot be restored.
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
                    data-double-confirm-submit
                    class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
                >
                    <span data-submit-idle>Delete story</span>
                    <span data-submit-active class="hidden"><x-spinner label="Deleting..." /></span>
                </button>
            </div>
        </form>
    </x-modal>

    <!-- ALICE LIBRARY IMPORT OVERLAY (the import is one sync request; the message is honest, not staged) -->
    <div data-library-overlay class="hidden fixed inset-0 z-50 bg-[#FAF6EE]/95" role="status" aria-live="polite">
        <div class="flex h-full items-center justify-center px-4">
            <div class="w-full max-w-sm rounded-xl border border-[#E6DCCF] bg-white p-6 text-center shadow-lg">
                <x-spinner label="Importing the Alice Library" />
                <p data-library-stage class="mt-4 text-xs font-bold text-[#2D231E]">Importing the Alice Library... This takes a moment.</p>
            </div>
        </div>
    </div>
@endsection