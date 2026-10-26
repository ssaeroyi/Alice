{{-- Nav-bell preview body (Student\InboxController::preview). Replaces the
     [data-bell-body] innerHTML when the dropdown opens.

     Two row shapes, visually identical:
       - actionable (class invite / starter reward) -> a real link to the inbox.
         It must NOT be marked read here: deciding or claiming only exists on
         the inbox page, and a preview tap would strand it read-but-unfinished.
       - everything else -> the same POST-to-read row the inbox page uses, so a
         tap marks it read and lands on its destination.

     No chevron, tighter padding, clamped body: this is a glance layer, the
     inbox page owns the full detail. --}}
@if ($notifications->isEmpty())
    <div class="px-3 py-7 text-center">
        <p class="font-pixel text-[9px] uppercase text-[#8C7B70]">No notifications</p>
        <p class="text-xs font-semibold text-[#8C7B70] mt-2">All your updates from teachers, classes, rewards, and achievements will land here.</p>
    </div>
@else
    <div class="space-y-1">
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
                $isUnread = $notification->read_at === null;
                $actionable = in_array($notification->type, ['class.invite.teacher', 'reward.welcome_kit'], true);
                $chrome = $isUnread
                    ? 'bg-amber-50 border-[#2D231E]'
                    : 'bg-transparent border-transparent';
            @endphp

            @if ($actionable)
                <a
                    href="{{ route('inbox') }}"
                    class="block w-full min-h-[44px] text-left flex items-start gap-2.5 px-3 py-2.5 rounded-xl border-2 {{ $chrome }} transition-colors hover:bg-[#FAF6EE]"
                >
                    <span class="mt-1 w-2.5 h-2.5 shrink-0 rounded-full {{ $isUnread ? 'bg-amber-400' : 'bg-[#E6DCCF]' }}"></span>
                    <span class="flex-1 min-w-0">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="inline-flex flex-wrap items-center gap-1.5 min-w-0">
                                @if ($pill)
                                    <span class="text-[8px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full {{ $pillClass }} shrink-0">{{ $pill }}</span>
                                @endif
                                <span class="text-xs font-extrabold text-[#2D231E] truncate">{{ $notification->title }}</span>
                            </span>
                            <time class="text-[10px] font-bold text-[#8C7B70] shrink-0" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                        </span>
                        @if ($notification->body)
                            <span class="block text-xs font-semibold text-[#8C7B70] mt-0.5 line-clamp-2">{{ $notification->body }}</span>
                        @endif
                    </span>
                </a>
            @else
                <form method="POST" action="{{ route('inbox.read', $notification) }}">
                    @csrf
                    <button
                        type="submit"
                        class="w-full min-h-[44px] text-left flex items-start gap-2.5 px-3 py-2.5 rounded-xl border-2 {{ $chrome }} transition-colors hover:bg-[#FAF6EE] cursor-pointer"
                    >
                        <span class="mt-1 w-2.5 h-2.5 shrink-0 rounded-full {{ $isUnread ? 'bg-amber-400' : 'bg-[#E6DCCF]' }}"></span>
                        <span class="flex-1 min-w-0">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="inline-flex flex-wrap items-center gap-1.5 min-w-0">
                                    @if ($pill)
                                        <span class="text-[8px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full {{ $pillClass }} shrink-0">{{ $pill }}</span>
                                    @endif
                                    <span class="text-xs font-extrabold text-[#2D231E] truncate">{{ $notification->title }}</span>
                                </span>
                                <time class="text-[10px] font-bold text-[#8C7B70] shrink-0" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                            </span>
                            @if ($notification->body)
                                <span class="block text-xs font-semibold text-[#8C7B70] mt-0.5 line-clamp-2">{{ $notification->body }}</span>
                            @endif
                        </span>
                    </button>
                </form>
            @endif
        @endforeach
    </div>

    @if ($unreadCount > 0)
        <div class="mt-2 pt-2 border-t-2 border-[#E6DCCF]">
            <form method="POST" action="{{ route('inbox.read-all') }}" data-bell-mark-all>
                @csrf
                <button
                    type="submit"
                    data-dropdown-keep-open
                    class="w-full min-h-[40px] px-2 text-xs font-bold text-sky-700 hover:text-sky-800 transition-colors cursor-pointer"
                >
                    Mark all as read
                </button>
            </form>
        </div>
    @endif
@endif
