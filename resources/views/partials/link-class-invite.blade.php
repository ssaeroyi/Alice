{{-- Shared-link class invite: shown app-wide for students whenever a
     pending invite sits in the session (arrived via /a/CODE before they
     registered or signed in). Accept reuses the same membership path as
     joining by code; dismissing clears the session flag. --}}
@if (session('pending_class_invite') && auth()->check() && auth()->user()->role === \App\Enums\Role::Student)
    @php $inviteGroup = \App\Models\Group::find((int) session('pending_class_invite')); @endphp
    <x-modal id="link-class-invite" :open="$inviteGroup !== null" title="Class invitation">
        @if ($inviteGroup)
            <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                You were invited to join <strong>{{ $inviteGroup->name }}</strong>. Accept and the class gets added
                to your Classwork, invites included.
            </p>
            <form method="POST" action="{{ route('student.classes.link-decide') }}">
                @csrf
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
                    <button type="submit" name="accept" value="0" class="w-full sm:w-auto min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">
                        Not now
                    </button>
                    <button type="submit" class="w-full sm:w-auto min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                        Accept
                    </button>
                </div>
            </form>
        @else
            <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                That class no longer exists. Ask your teacher for a new link.
            </p>
            <form method="POST" action="{{ route('student.classes.link-decide') }}">
                @csrf
                <div class="flex justify-end">
                    <button type="submit" class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Dismiss</button>
                </div>
            </form>
        @endif
    </x-modal>
@endif
