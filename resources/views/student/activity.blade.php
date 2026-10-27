@extends('layouts.authenticated')

@section('title', 'Activity')

@section('page', 'activity')

@php
    $tabs = [
        'everything' => 'Everything',
        'keys' => 'Keys',
        'reading' => 'Reading',
        'badges' => 'Badges',
        'streak' => 'Streak',
    ];
    $glyph = fn (string $name) => \App\Support\LibraryPresentation::iconGlyph('ui', $name);
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 sm:py-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('hub') }}" aria-label="Back to hub"
           class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <div class="min-w-0">
            <h1 class="font-pixel text-base font-bold text-[#2D231E] uppercase">Activity</h1>
        </div>
    </div>

    <!-- Tabs -->
    <nav aria-label="Activity sections" class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1" style="scrollbar-width:none">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('activity', $key === 'everything' ? [] : ['tab' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif
               class="shrink-0 min-h-[44px] inline-flex items-center px-4 sm:px-5 font-pixel text-[10px] sm:text-xs font-bold uppercase rounded-xl border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all {{ $tab === $key ? 'bg-amber-400 text-[#2D231E]' : 'bg-white text-[#2D231E] hover:bg-[#FAF6EE]' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($tab === 'streak')

        <!-- ============ STREAK DASHBOARD ============ -->
        <div class="space-y-6">

            <!-- Summary cards (hub-snapshot style) -->
            <div class="grid grid-cols-3 gap-2 sm:gap-4">
                <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                    <div class="flex items-start justify-between gap-1">
                        <span class="font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase leading-tight">Streak</span>
                    </div>
                    <div class="mt-2 flex items-end justify-between gap-1">
                        <span class="text-lg sm:text-2xl font-extrabold text-rose-600">{{ $currentStreak }}<span class="ml-0.5 text-[10px] sm:text-xs font-bold text-[#2D231E]">days</span></span>
                        <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('flame') !!}</span>
                    </div>
                </div>
                <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                    <div class="flex items-start justify-between gap-1">
                        <span class="font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase leading-tight">Best</span>
                    </div>
                    <div class="mt-2 flex items-end justify-between gap-1">
                        <span class="text-lg sm:text-2xl font-extrabold text-violet-700">{{ $longestStreak }}<span class="ml-0.5 text-[10px] sm:text-xs font-bold text-[#2D231E]">days</span></span>
                        <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-violet-700 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('crown') !!}</span>
                    </div>
                </div>
                <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                    <div class="flex items-start justify-between gap-1">
                        <span class="font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase leading-tight">Restores</span>
                    </div>
                    <div class="mt-2 flex items-end justify-between gap-1">
                        <span class="text-lg sm:text-2xl font-extrabold text-sky-600">{{ $freezeHeld }}<span class="ml-0.5 text-[10px] sm:text-xs font-bold text-[#2D231E]">/ {{ $freezeCap }}</span></span>
                        <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-sky-600 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('snowflake') !!}</span>
                    </div>
                </div>
            </div>

            @if ($currentStreak === 0)
                <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 text-center space-y-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <span class="w-12 h-12 mx-auto rounded-2xl border-2 border-[#2D231E] bg-rose-500 text-white flex items-center justify-center [&>svg]:h-6 [&>svg]:w-6">{!! $glyph('flame') !!}</span>
                    <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No current streak</p>
                    <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">Read once a day to start your streak. Every stamped day pushes you up the battle board.</p>
                    <a href="{{ route('library') }}" class="inline-flex items-center justify-center min-h-[44px] px-5 bg-amber-400 border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold uppercase text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">Start reading</a>
                </div>
            @endif

            <!-- Freeze shop -->
            <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-10 h-10 shrink-0 rounded-xl border-2 border-[#2D231E] bg-sky-500 text-white flex items-center justify-center [&>svg]:h-5 [&>svg]:w-5">{!! $glyph('snowflake') !!}</span>
                    <div class="text-left min-w-0">
                        <p class="text-xs sm:text-sm font-extrabold text-[#2D231E]">Streak Restore</p>
                        <p class="text-[11px] font-semibold text-[#2D231E] leading-snug">Miss a reading day and a Streak Restore brings your streak back. A new one is granted on the first stamp of each month.</p>
                    </div>
                </div>
                @if ($freezeHeld >= $freezeCap)
                    <span class="shrink-0 self-start sm:self-center min-h-[44px] inline-flex items-center px-4 rounded-xl border-2 border-[#2D231E] bg-[#F4F0EA] text-[#8C7B70] font-pixel text-[10px] font-bold uppercase">Full</span>
                @else
                    <form method="POST" action="{{ route('rewards.freeze.buy') }}" class="shrink-0 self-start sm:self-center">
                        @csrf
                        <button type="submit" class="min-h-[44px] inline-flex items-center gap-2 px-4 rounded-xl border-2 border-[#2D231E] bg-white text-[#2D231E] font-pixel text-[10px] font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                            <span class="w-3.5 h-3.5 text-amber-600 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('key') !!}</span>
                            Buy {{ $freezeCost }} keys
                        </button>
                    </form>
                @endif
            </div>

            <!-- Streak leaderboard (global) -->
            <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-3 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-pixel text-[11px] sm:text-xs font-bold uppercase text-[#2D231E]">Streak leaderboard</span>
                        <x-info label="About the streak leaderboard">This is the leaderboard for the most active students here in Alice. This leaderboard refreshes every week on Mondays.</x-info>
                    </div>
                </div>

                @php
                    $topStreak = max(1, collect($board['entries'])->max('streak'));
                @endphp

                <div class="space-y-2">
                    @forelse ($board['entries'] as $entry)
                        @php
                            $behind = $topStreak - $entry['streak'];
                        @endphp
                        <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all hover:translate-x-[2px] {{ $entry['is_self'] ? 'bg-amber-400/60 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]' : 'bg-[#FAF6EE] border-2 border-[#E9E0D4]' }}">
                            <span class="w-8 h-8 shrink-0 rounded-full border-2 border-[#2D231E] flex items-center justify-center font-pixel text-[11px] font-bold {{ $entry['rank'] === 1 ? 'bg-amber-400 text-[#2D231E]' : ($entry['rank'] === 2 ? 'bg-[#E7E1D8] text-[#2D231E]' : ($entry['rank'] === 3 ? 'bg-[#F0D9B5] text-[#2D231E]' : 'bg-white text-[#2D231E]')) }}">{{ $entry['rank'] }}</span>
                            <x-avatar :name="$entry['name']" :avatar="$entry['avatar'] ?? null" :medal="$entry['rank'] <= 3 ? $entry['rank'] : null" :size="32" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs sm:text-sm font-extrabold text-[#2D231E] truncate">
                                    {{ $entry['name'] }}
                                    @if ($entry['is_self'])
                                        <span class="ml-1 align-middle inline-block font-pixel text-[8px] font-bold px-1.5 py-0.5 rounded bg-[#2D231E] text-white uppercase">You</span>
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 flex items-center gap-2">
                                @if ($behind === 0)
                                    <span class="font-pixel text-[8px] font-bold px-1.5 py-0.5 rounded bg-amber-400 text-[#2D231E] uppercase">&#9650;</span>
                                @elseif ($behind > 0)
                                    <span class="font-pixel text-[8px] font-bold px-1.5 py-0.5 rounded bg-[#F4F0EA] text-[#2D231E] border border-[#E0D6C8] uppercase">&#9660; {{ $behind }}</span>
                                @endif
                                <span class="flex items-center gap-1.5 font-pixel text-[10px] sm:text-xs font-bold {{ $entry['streak'] > 0 ? 'text-rose-600' : 'text-[#B5A89C]' }}">
                                    {{ $entry['streak'] }}
                                    <span class="text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase">{{ $entry['streak'] === 1 ? 'day' : 'days' }}</span>
                                    <span class="w-3 h-3 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('flame') !!}</span>
                                </span>
                            </span>
                        </div>
                    @empty
                        <div class="rounded-xl border-2 border-dashed border-[#E0D6C8] bg-[#F4F0EA] p-6 text-center space-y-1">
                            <p class="font-pixel text-[11px] font-bold uppercase text-[#2D231E]">Be the first to set a streak</p>
                            <p class="text-[11px] sm:text-xs font-semibold text-[#2D231E]">No other student has a streak yet. Today has to be day one for someone.</p>
                        </div>
                    @endforelse
                </div>

                @unless ($board['selfInTop'])
                    @php $selfBehind = $topStreak - $currentStreak; @endphp
                    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 bg-[#FAF6EE] border-2 border-[#E9E0D4] border-l-4 border-l-amber-400">
                        <span class="w-8 h-8 shrink-0 rounded-full border-2 border-[#2D231E] bg-white flex items-center justify-center font-pixel text-[11px] font-bold text-[#2D231E]">{{ $board['selfRank'] }}</span>
                        <x-avatar :user="auth()->user()" alt="Your avatar" :size="32" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-extrabold text-[#2D231E] truncate">{{ (auth()->user()->nickname ?: 'You') }}</p>
                        </div>
                        <span class="shrink-0 flex items-center gap-2">
                            @if ($selfBehind > 0)
                                <span class="font-pixel text-[8px] font-bold px-1.5 py-0.5 rounded bg-[#F4F0EA] text-[#2D231E] border border-[#E0D6C8] uppercase">&#9660; {{ $selfBehind }}</span>
                            @endif
                            <span class="flex items-center gap-1.5 font-pixel text-[10px] sm:text-xs font-bold text-rose-600">
                                {{ $currentStreak }}
                                <span class="text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase">{{ $currentStreak === 1 ? 'day' : 'days' }}</span>
                                <span class="w-3 h-3 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('flame') !!}</span>
                            </span>
                        </span>
                    </div>
                @endunless
            </div>

        </div>

    @else

        <!-- ============ FEED ============ -->
        @if ($events->isEmpty())
            <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-8 text-center space-y-3 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                <span class="w-12 h-12 mx-auto rounded-2xl border-2 border-[#2D231E] bg-amber-400 text-[#2D231E] flex items-center justify-center [&>svg]:h-6 [&>svg]:w-6">
                    {!! $glyph($tab === 'keys' ? 'key' : ($tab === 'reading' ? 'open-book' : ($tab === 'badges' ? 'trophy' : 'open-book'))) !!}
                </span>
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">
                    @if ($tab === 'keys')
                        No keys yet
                    @elseif ($tab === 'reading')
                        Nothing read yet
                    @elseif ($tab === 'badges')
                        No badges yet
                    @else
                        Nothing here yet
                    @endif
                </p>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed mx-auto max-w-md">
                    @if ($tab === 'keys')
                        Finish a daily task or read a story to the end to earn your first keys.
                    @elseif ($tab === 'reading')
                        Pick any story from the library and your progress will show up here.
                    @elseif ($tab === 'badges')
                        Badges unlock as you read, quiz and play your way through ALICE.
                    @else
                        Read a story, earn keys, claim badges — every step lands here.
                    @endif
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                    <a href="{{ route('library') }}" class="inline-flex items-center gap-2 min-h-[44px] px-5 bg-amber-400 border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold uppercase text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                        <span class="w-3.5 h-3.5 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('bookshelf') !!}</span>
                        Open the library
                    </a>
                    @if ($tab !== 'reading')
                        <a href="{{ route('mini-game') }}" class="inline-flex items-center gap-2 min-h-[44px] px-5 bg-white border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold uppercase text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                            <span class="w-3.5 h-3.5 [&>svg]:h-full [&>svg]:w-full">{!! $glyph('cards') !!}</span>
                            Play mini-games
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="space-y-4">
                @php $lastGroup = null; @endphp
                @foreach ($events as $event)
                    @php
                        $at = $event['at'];
                        $group = match (true) {
                            $at->isToday() => 'Today',
                            $at->isYesterday() => 'Yesterday',
                            default => $at->format('M j').($at->year !== now()->year ? ', '.$at->year : ''),
                        };
                    @endphp
                    @if ($group !== $lastGroup)
                        <div class="pt-2 flex items-center gap-3">
                            <h2 class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wide">{{ $group }}</h2>
                            <span class="flex-1 h-px bg-[#E4DCD2]"></span>
                        </div>
                    @endif
                    @php $lastGroup = $group; @endphp

                    @php
                        $eventUrl = null;
                        if ($event['type'] === 'reading' && filled($event['story_slug'])) {
                            $eventUrl = route('story.preview', $event['story_slug']);
                        } elseif ($event['type'] === 'badge') {
                            $eventUrl = route('badges');
                        }
                    @endphp
                    @if ($eventUrl)
                        <a href="{{ $eventUrl }}" class="block bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 flex items-center gap-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] cursor-pointer hover:translate-x-[2px] hover:shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] transition-all">
                    @else
                        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 flex items-center gap-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    @endif
                        <span class="w-10 h-10 shrink-0 rounded-xl border-2 border-[#2D231E] text-white flex items-center justify-center [&>svg]:h-5 [&>svg]:w-5 {{ $event['icon_class'] }}">
                            @php $eventIcon = $glyph($event['icon']); @endphp
                            {!! $eventIcon !!}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-extrabold text-[#2D231E] truncate">{{ $event['title'] }}</p>
                            @if ($event['subtitle'] !== null && $event['subtitle'] !== '')
                                <p class="text-[11px] sm:text-xs font-semibold text-[#2D231E] truncate">{{ $event['subtitle'] }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            @if ($event['delta'] !== null)
                                <p class="font-pixel text-[10px] sm:text-xs font-bold {{ $event['delta'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $event['delta'] >= 0 ? '+'.number_format($event['delta']) : '-'.number_format(abs($event['delta'])) }}
                                </p>
                                @if ($event['balance'] !== null)
                                    <p class="text-[9px] sm:text-[10px] font-semibold text-[#2D231E]">{{ number_format($event['balance']) }} keys</p>
                                @endif
                            @endif
                            <p class="mt-0.5 text-[9px] sm:text-[10px] font-semibold text-[#B5A89C]">{{ $relative($at) }}</p>
                        </div>
                    @if ($eventUrl)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>

            {{ $events->links() }}
        @endif

    @endif

</div>
@endsection