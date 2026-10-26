@extends('layouts.teacher')

@section('title', $assignment ? 'Edit Assignment' : 'New Assignment')
@section('eyebrow', 'Teaching')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <a href="{{ $assignment ? route('teacher.assignments.show', $assignment) : (isset($prefillClassId) && $prefillClassId ? route('teacher.classes.show', $prefillClassId) : route('teacher.classes.index')) }}" class="inline-flex items-center gap-1 text-sm font-bold text-[#2D231E]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
            </svg>
            @if ($assignment)
                Back to assignment
            @elseif (isset($prefillClassId) && $prefillClassId)
                Back to class
            @else
                Back to classes
            @endif
        </a>

        <form method="POST" action="{{ $assignment ? route('teacher.assignments.update', $assignment) : route('teacher.assignments.store') }}" class="bg-white border border-[#E6DCCF] rounded-xl p-4 sm:p-6 space-y-6">
            @csrf
            @if ($assignment)
                @method('PUT')
            @endif

            <div>
                <label class="block text-sm font-extrabold text-[#2D231E] mb-1">Title</label>
                <input
                    type="text"
                    name="title"
                    value="{{ old('title', $assignment?->title) }}"
                    required
                    maxlength="200"
                    placeholder="e.g. Read 'The Lost Coin' by Friday"
                    class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600"
                >
                @error('title')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-extrabold text-[#2D231E] mb-1">Story</label>
                <div data-story-picker>
                    <input type="hidden" name="story_id" data-story-value value="{{ old('story_id', $assignment?->story_id ?? $prefillStoryId) }}">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div data-story-chosen class="hidden flex-1 min-w-0 items-center gap-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg px-3 py-2">
                            <span class="shrink-0 w-10 h-[53px] rounded border border-[#E6DCCF] bg-[#F3ECE0] overflow-hidden flex items-center justify-center">
                                <img data-story-chosen-cover src="" alt="" class="hidden w-full h-full object-cover">
                                <svg data-story-chosen-placeholder class="w-4 h-4 text-[#B5A89C]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p data-story-chosen-title class="text-sm font-bold text-[#2D231E] truncate">—</p>
                                <p data-story-chosen-status class="mt-0.5 text-[10px] font-bold uppercase tracking-wide">—</p>
                            </div>
                            <button type="button" data-story-clear aria-label="Clear story choice" class="shrink-0 min-w-[36px] min-h-[36px] flex items-center justify-center bg-white border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#B5A89C] hover:border-[#2D231E] hover:text-[#2D231E] transition-colors cursor-pointer">&times;</button>
                        </div>
                        <div data-story-empty class="flex-1 min-w-0 flex items-center px-3 py-2.5 border border-dashed border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#B5A89C]">
                            No story chosen yet.
                        </div>
                        <button
                            type="button"
                            data-modal-open="story-picker-modal"
                            class="shrink-0 inline-flex items-center justify-center gap-2 min-h-[44px] px-4 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-sm font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path stroke-linecap="round" d="M21 21l-4.35-4.35"></path>
                            </svg>
                            Choose story
                        </button>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold text-[#2D231E] flex-1 min-w-0">Want something you don't see? Use Stories to add a story first.</p>
                        <a href="#" data-edit-story class="hidden text-xs font-bold text-sky-700 hover:underline shrink-0">Edit story</a>
                    </div>
                </div>
                @error('story_id')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-extrabold text-[#2D231E] mb-1">Instructions</label>
                <textarea
                    name="instructions"
                    rows="4"
                    maxlength="4000"
                    placeholder="Optional instructions for your students..."
                    class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600"
                >{{ old('instructions', $assignment?->instructions) }}</textarea>
                @error('instructions')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-extrabold text-[#2D231E] mb-1">Due date</label>
                    <div data-date-picker class="relative">
                        <input
                            type="text"
                            data-date-display
                            readonly
                            placeholder="Pick a date"
                            value="{{ old('due_at') ? \Carbon\Carbon::parse(old('due_at'))->format('j M Y') : ($assignment?->due_at?->format('j M Y') ?? '') }}"
                            class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] bg-white focus:outline-none focus:border-sky-600 cursor-pointer"
                        >
                        <input type="hidden" name="due_at" data-date-value value="{{ old('due_at', $assignment?->due_at?->format('Y-m-d')) }}">
                        <div data-date-popover class="hidden absolute top-full mt-2 z-30"></div>
                    </div>
                    @error('due_at')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-extrabold text-[#2D231E] mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600">
                        <option value="draft" {{ old('status', $assignment?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ old('status', $assignment?->status ?? 'draft') === 'active' ? 'selected' : '' }}>Active now</option>
                    </select>
                </div>
            </div>

            <div>
                <input type="hidden" name="scope" value="group">
                @php
                    $selectedGroup = old('group_id', $assignment?->group_id ?? ($prefillClassId ?? null));
                @endphp

                <label class="block text-sm font-extrabold text-[#2D231E] mb-1">Class</label>
                @if ($classes->isEmpty())
                    <div class="border border-[#E6DCCF] rounded-lg p-4">
                        <p class="text-sm font-semibold text-[#2D231E]">You don't have any classes yet.</p>
                        <a href="{{ route('teacher.classes.create') }}" class="inline-flex items-center gap-1 mt-2 text-sm font-bold text-sky-700 hover:text-sky-800">Create a class first →</a>
                    </div>
                @else
                    <select name="group_id" class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600">
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" {{ (int) $selectedGroup === $class->id ? 'selected' : '' }}>
                                {{ $class->name }} ({{ $class->members_count }} student{{ $class->members_count === 1 ? '' : 's' }})
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-center sm:justify-end gap-2 sm:gap-3 pt-2">
                <a href="{{ $assignment ? route('teacher.assignments.show', $assignment) : ($selectedGroup ?? false ? route('teacher.classes.show', $selectedGroup) : route('teacher.classes.index')) }}" class="flex items-center justify-center w-full sm:w-auto px-4 py-2.5 rounded-lg text-sm font-bold text-[#2D231E]">Cancel</a>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                    {{ $assignment ? 'Save changes' : 'Create assignment' }}
                </button>
            </div>
        </form>

        <x-modal id="story-picker-modal" title="Choose a story" :wide="true">
            <input
                data-story-modal-search
                type="search"
                placeholder="Search stories by title..."
                autocomplete="off"
                class="w-full min-h-[44px] px-3.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-sky-500"
            >
            <div data-story-modal-grid class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[55vh] overflow-y-auto pr-1"></div>
            <p data-story-modal-empty class="hidden text-center text-sm font-semibold text-[#2D231E] py-6">No matching stories. Add one in Stories first.</p>
        </x-modal>
    </div>
@endsection

@push('scripts')
    @php
        $storyOptions = $stories->map(fn ($s) => [
            'id' => (int) $s->id,
            'title' => $s->title,
            'status' => $s->status instanceof \BackedEnum ? $s->status->value : (string) $s->status,
            'cover' => $s->cover_image ? $s->coverUrl() : null,
        ])->values();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Story picker: cover-card modal, Word Search style (teacher flat).
            const picker = document.querySelector('[data-story-picker]');
            if (picker) {
                const hidden = picker.querySelector('[data-story-value]');
                const chosen = picker.querySelector('[data-story-chosen]');
                const chosenTitle = picker.querySelector('[data-story-chosen-title]');
                const chosenStatus = picker.querySelector('[data-story-chosen-status]');
                const chosenCover = picker.querySelector('[data-story-chosen-cover]');
                const chosenPlaceholder = picker.querySelector('[data-story-chosen-placeholder]');
                const empty = picker.querySelector('[data-story-empty]');
                const clearBtn = picker.querySelector('[data-story-clear]');
                const editLink = picker.querySelector('[data-edit-story]');
                const modalSearch = document.querySelector('[data-story-modal-search]');
                const grid = document.querySelector('[data-story-modal-grid]');
                const gridEmpty = document.querySelector('[data-story-modal-empty]');
                const stories = @json($storyOptions);

                const editUrl = '{{ route("teacher.stories.edit", ["story" => "__ID__"]) }}';
                const bookIcon = '<svg class="w-5 h-5 text-[#B5A89C]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>';

                function statusPill(status) {
                    return 'text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full ' +
                        (status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-[#F3ECE0] text-[#2D231E]');
                }

                function showChosen(s) {
                    chosenTitle.textContent = s.title;
                    chosenStatus.textContent = s.status;
                    chosenStatus.className = 'mt-0.5 ' + statusPill(s.status);
                    if (s.cover) {
                        chosenCover.src = s.cover;
                        chosenCover.classList.remove('hidden');
                        chosenPlaceholder.classList.add('hidden');
                    } else {
                        chosenCover.classList.add('hidden');
                        chosenPlaceholder.classList.remove('hidden');
                    }
                    chosen.classList.remove('hidden');
                    chosen.classList.add('flex');
                    empty.classList.add('hidden');
                    editLink.href = editUrl.replace('__ID__', s.id);
                    editLink.classList.remove('hidden');
                }

                function clearChosen() {
                    hidden.value = '';
                    chosen.classList.add('hidden');
                    chosen.classList.remove('flex');
                    empty.classList.remove('hidden');
                    editLink.classList.add('hidden');
                }

                function selectStory(s) {
                    hidden.value = s.id;
                    showChosen(s);
                    document.querySelector('#story-picker-modal [data-modal-close]')?.click();
                }

                function renderGrid(filter) {
                    const q = (filter || '').toLowerCase();
                    const matched = stories.filter((s) => !q || s.title.toLowerCase().includes(q));
                    grid.innerHTML = '';
                    gridEmpty.classList.toggle('hidden', matched.length !== 0);
                    matched.forEach(function (s) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'group text-left bg-white border border-[#E6DCCF] rounded-lg p-2 hover:border-sky-500 hover:bg-[#FAF6EE] transition-colors cursor-pointer';

                        const cover = document.createElement('div');
                        cover.className = 'relative w-full aspect-[3/4] rounded border border-[#E6DCCF] bg-[#F3ECE0] overflow-hidden flex items-center justify-center';
                        if (s.cover) {
                            const img = document.createElement('img');
                            img.src = s.cover;
                            img.alt = '';
                            img.loading = 'lazy';
                            img.decoding = 'async';
                            img.className = 'absolute inset-0 w-full h-full object-cover';
                            img.onerror = function () { this.remove(); };
                            cover.appendChild(img);
                        } else {
                            cover.innerHTML = bookIcon;
                        }

                        const title = document.createElement('p');
                        title.className = 'mt-1.5 text-xs font-bold text-[#2D231E] leading-tight line-clamp-2';
                        title.textContent = s.title;

                        const status = document.createElement('span');
                        status.className = 'mt-1 inline-block ' + statusPill(s.status);
                        status.textContent = s.status;

                        btn.appendChild(cover);
                        btn.appendChild(title);
                        btn.appendChild(status);
                        btn.addEventListener('click', function () { selectStory(s); });
                        grid.appendChild(btn);
                    });
                }

                clearBtn.addEventListener('click', clearChosen);
                modalSearch.addEventListener('input', function () { renderGrid(modalSearch.value.trim()); });
                document.querySelector('[data-modal-open="story-picker-modal"]').addEventListener('click', function () {
                    modalSearch.value = '';
                    renderGrid('');
                    modalSearch.focus();
                });

                const initial = stories.find((s) => s.id === Number(hidden.value));
                if (initial) {
                    showChosen(initial);
                } else {
                    empty.classList.remove('hidden');
                }
            }
        });
    </script>
@endpush
