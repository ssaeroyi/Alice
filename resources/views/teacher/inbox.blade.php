@extends('layouts.teacher')

@section('title', 'Inbox')
@section('eyebrow', 'Notifications')
@section('page', 'teacher-inbox')

@section('content')
    <div class="max-w-2xl mx-auto space-y-4">
        <a
            href="{{ route('teacher.dashboard') }}"
            class="inline-flex items-center gap-1 text-sm font-bold text-[#2D231E] mb-4 transition-colors"
        >
            <x-icons.core.arrow-left class="w-4 h-4" />
            Back
        </a>

        @if ($unreadCount > 0)
            <div class="flex justify-end">
                <form method="POST" action="{{ route('teacher.inbox.read-all') }}">
                    @csrf
                    <button type="submit" class="text-[11px] font-bold text-sky-600 hover:text-sky-700 transition-colors cursor-pointer">
                        Mark all as read
                    </button>
                </form>
            </div>
        @endif

        @php
            $labels = ['all' => 'All', 'unread' => 'Unread', 'finished' => 'Finished', 'class' => 'Class'];
            $empty = [
                'all' => ['No notifications', 'When a student joins your class or finishes an assignment, you\'ll see it here.'],
                'unread' => ['No unread notifications', 'At least nothing new. Everything you\'ve seen is already read.'],
                'finished' => ['No finished-assignment alerts', 'When a student finishes an assignment, the alert lands here.'],
                'class' => ['No class activity yet', 'Student joins and accepted invitations will appear here.'],
            ];
        @endphp

        <div data-inbox-panel>
        @if ($hasAny)
            <div class="flex flex-wrap items-center gap-1.5 border-b border-[#E6DCCF] pb-3" role="group" aria-label="Filter notifications">
                @foreach ($labels as $key => $label)
                    <a
                        href="{{ route('teacher.inbox', ['filter' => $key]) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors {{ $key === $filter ? 'bg-emerald-600 text-white' : 'text-[#2D231E]' }}"
                        aria-current="{{ $key === $filter ? 'page' : 'false' }}"
                    >
                        {{ $label }}
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $key === $filter ? 'bg-white/20 text-white' : 'bg-[#F3ECE0] text-[#2D231E]' }}">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($notifications->isEmpty())
            <div class="bg-white border border-[#E6DCCF] rounded-2xl p-10 text-center">
                <p class="text-sm font-bold text-[#2D231E]">{{ $empty[$filter][0] }}</p>
                <p class="text-sm font-semibold text-[#2D231E] mt-1">{{ $empty[$filter][1] }}</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($notifications as $notification)
                    @php
                        $pill = \App\Http\Controllers\Teacher\InboxController::pillFor($notification->type);
                    @endphp
                    <form method="POST" action="{{ route('teacher.inbox.read', $notification) }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full text-left flex items-start gap-3 p-4 rounded-2xl border {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E]' }} transition-colors hover:bg-[#FAF6EE] cursor-pointer"
                        >
                            <span class="mt-1.5 w-2.5 h-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                            <span class="flex-1 min-w-0">
                                <span class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                    <span class="inline-flex flex-wrap items-center gap-2">
                                        @if ($pill)
                                            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $pill === 'Finished' ? 'bg-emerald-100 text-emerald-700' : 'bg-[#F3ECE0] text-[#2D231E]' }}">{{ $pill }}</span>
                                        @endif
                                        <span class="text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                    </span>
                                    <time class="text-[11px] font-bold text-[#2D231E] shrink-0">{{ $notification->created_at->diffForHumans() }}</time>
                                </span>
                                @if ($notification->body)
                                    <span class="block text-sm font-semibold text-[#2D231E] mt-1 line-clamp-2">{{ $notification->body }}</span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 mt-1 shrink-0 text-[#2D231E]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </form>
                @endforeach
            </div>

            <div class="pt-1">
                {{ $notifications->links('pagination::teacher') }}
            </div>
        @endif
        </div>
    </div>
@endsection