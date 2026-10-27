@extends('layouts.authenticated')

@section('title', 'Inbox')
@section('page', 'inbox')

@section('content')
    <div class="max-w-2xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-extrabold text-[#2D231E]">Inbox</h1>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('inbox.read-all') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-xs font-bold text-sky-700 hover:text-sky-800 transition-colors cursor-pointer">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @php
            $labels = ['all' => 'All', 'unread' => 'Unread', 'assignments' => 'Assignments', 'invites' => 'Invites'];
            $empty = [
                'all' => ['No notifications', 'All your updates from teachers, classes, rewards, and achievements will land here.'],
                'unread' => ['No unread notifications', 'At least nothing new. You\'ve read everything.'],
                'assignments' => ['No assignments yet', 'New assignments and reminders will land here.'],
                'invites' => ['No class invites', 'When your teacher invites you to a class, it shows up here.'],
            ];
        @endphp

        <div data-inbox-panel>
        @if ($hasAny)
            <nav role="group" aria-label="Filter notifications" class="flex gap-2 overflow-x-auto -mx-1 px-1 pb-1" style="scrollbar-width:none">
                @foreach ($labels as $key => $label)
                    <a
                        href="{{ route('inbox', ['filter' => $key]) }}"
                        class="shrink-0 inline-flex items-center gap-1.5 min-h-[40px] px-3 py-1.5 rounded-xl border-2 border-[#2D231E] font-pixel text-[9px] font-bold uppercase transition-colors {{ $key === $filter ? 'bg-amber-400 text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]' : 'bg-white text-[#8C7B70] hover:text-[#2D231E]' }}"
                        aria-current="{{ $key === $filter ? 'page' : 'false' }}"
                    >
                        {{ $label }}
                        <span class="px-1.5 py-0.5 rounded-full {{ $key === $filter ? 'bg-[#2D231E] text-white' : 'bg-[#F3ECE0] text-[#8C7B70]' }}">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($notifications->isEmpty())
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-10 text-center shadow-[5px_5px_0px_0px_rgba(45,35,30,1)]">
                <p class="font-pixel text-[10px] uppercase text-[#8C7B70]">{{ $empty[$filter][0] }}</p>
                <p class="text-sm font-semibold text-[#8C7B70] mt-2">{{ $empty[$filter][1] }}</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($notifications as $notification)
                    @php
                        $pill = \App\Http\Controllers\Student\InboxController::pillFor($notification->type);
                        $pillClass = match ($pill) {
                            'Overdue' => 'bg-rose-100 text-rose-700',
                            'Due soon', 'Invite', 'Reward' => 'bg-amber-100 text-amber-700',
                            'New' => 'bg-emerald-100 text-emerald-700',
                            'Title' => 'bg-violet-100 text-violet-700',
                            'Badge' => 'bg-sky-100 text-sky-700',
                            default => 'bg-[#E6DCCF] text-[#8C7B70]',
                        };
                    @endphp
                    @if ($notification->type === 'class.invite.teacher')
                        <div class="p-4 rounded-2xl border-2 {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]' }}">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 w-3 h-3 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="inline-flex flex-wrap items-center gap-2">
                                            @if ($pill)
                                                <span class="text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $pillClass }}">{{ $pill }}</span>
                                            @endif
                                            <span class="text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                        </span>
                                        <time class="text-[11px] font-bold text-[#8C7B70] shrink-0">{{ $notification->created_at->diffForHumans() }}</time>
                                    </span>
                                    @if ($notification->body)
                                        <span class="block text-sm font-semibold text-[#8C7B70] mt-1">{{ $notification->body }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2 pl-6">
                                <form method="POST" action="{{ route('student.classes.decide', $notification) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="accept">
                                    <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('student.classes.decide', $notification) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="decline">
                                    <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 rounded-xl bg-white border-2 border-[#E6DCCF] text-sm font-bold text-[#8C7B70] hover:text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Not now</button>
                                </form>
                            </div>
                        </div>
                    @elseif ($notification->type === 'reward.welcome_kit')
                        <div class="p-4 rounded-2xl border-2 {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]' }}">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 w-3 h-3 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                                <span class="w-9 h-9 shrink-0 flex items-center justify-center rounded-xl bg-amber-100 border-2 border-[#2D231E] text-amber-600 [&>svg]:h-full [&>svg]:w-full">
                                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="inline-flex flex-wrap items-center gap-2">
                                            @if ($pill)
                                                <span class="text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $pillClass }}">{{ $pill }}</span>
                                            @endif
                                            <span class="text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                        </span>
                                        <time class="text-[11px] font-bold text-[#8C7B70] shrink-0">{{ $notification->created_at->diffForHumans() }}</time>
                                    </span>
                                    @if ($notification->body)
                                        <span class="block text-sm font-semibold text-[#8C7B70] mt-1">{{ $notification->body }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-end gap-2 pl-6">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                                    +{{ (int) config('placement.welcome_kit.keys') }} keys
                                </span>
                                <form method="POST" action="{{ route('inbox.claim', $notification) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] text-sm font-extrabold hover:bg-amber-500 transition-colors cursor-pointer uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none">Claim</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('inbox.read', $notification) }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full text-left flex items-start gap-3 p-4 rounded-2xl border-2 {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]' }} transition-colors hover:bg-[#FAF6EE] cursor-pointer"
                            >
                                <span class="mt-1 w-3 h-3 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="inline-flex flex-wrap items-center gap-2">
                                            @if ($pill)
                                                <span class="text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $pillClass }}">{{ $pill }}</span>
                                            @endif
                                            <span class="text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                        </span>
                                        <time class="text-[11px] font-bold text-[#8C7B70] shrink-0">{{ $notification->created_at->diffForHumans() }}</time>
                                    </span>
                                    @if ($notification->body)
                                        <span class="block text-sm font-semibold text-[#8C7B70] mt-1">{{ $notification->body }}</span>
                                    @endif
                                </span>
                                <svg class="w-4 h-4 mt-1 shrink-0 text-[#8C7B70]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>

            <div class="pt-1">
                {{ $notifications->links() }}
            </div>
        @endif
        </div>
    </div>
@endsection