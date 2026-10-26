{{-- Nav-bell preview body (Admin\InboxController::preview). Compact version of
     the inbox page: pill, title, time, clamped body. Rows post straight to
     admin.inbox.read (mark + follow), the footer re-clears unread in place. --}}
@if ($notifications->isEmpty())
    <div class="px-3 py-7 text-center">
        <p class="text-xs font-extrabold text-[#2D231E]">No notifications</p>
        <p class="text-xs font-semibold text-[#2D231E] mt-2">Student reports and system alerts will land here.</p>
    </div>
@else
    <div class="space-y-1">
        @foreach ($notifications as $notification)
            @php
                $pill = \App\Http\Controllers\Admin\InboxController::pillFor($notification->type);
                $pillTone = $notification->type === 'report.new'
                    ? 'bg-amber-100 text-amber-800'
                    : 'bg-sky-100 text-sky-800';
                $isUnread = $notification->read_at === null;
            @endphp
            <form method="POST" action="{{ route('admin.inbox.read', $notification) }}">
                @csrf
                <button
                    type="submit"
                    class="w-full min-h-[44px] text-left flex items-start gap-2.5 px-3 py-2.5 rounded-xl border {{ $isUnread ? 'bg-amber-50 border-[#2D231E]' : 'bg-white border-transparent' }} transition-colors hover:bg-[#FAF6EE] cursor-pointer"
                >
                    <span class="mt-1.5 w-2.5 h-2.5 shrink-0 rounded-full {{ $isUnread ? 'bg-amber-400' : 'bg-[#E6DCCF]' }}"></span>
                    <span class="flex-1 min-w-0">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="inline-flex flex-wrap items-center gap-1.5 min-w-0">
                                @if ($pill)
                                    <span class="text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full {{ $pillTone }} shrink-0">{{ $pill }}</span>
                                @endif
                                <span class="text-xs font-extrabold text-[#2D231E] truncate">{{ $notification->title }}</span>
                            </span>
                            <time class="text-[10px] font-bold text-[#2D231E] shrink-0" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                        </span>
                        @if ($notification->body)
                            <span class="block text-xs font-semibold text-[#2D231E] mt-0.5 line-clamp-2">{{ $notification->body }}</span>
                        @endif
                    </span>
                </button>
            </form>
        @endforeach
    </div>

    @if ($unreadCount > 0)
        <div class="mt-2 pt-2 border-t-2 border-[#E6DCCF]">
            <form method="POST" action="{{ route('admin.inbox.read-all') }}" data-bell-mark-all>
                @csrf
                <button type="submit" data-dropdown-keep-open class="w-full min-h-[40px] px-2 text-xs font-bold text-amber-700 hover:text-amber-800 transition-colors cursor-pointer">
                    Mark all as read
                </button>
            </form>
        </div>
    @endif
@endif
