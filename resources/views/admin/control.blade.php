@extends('layouts.admin')

@section('title', 'Control')
@section('eyebrow', 'Control center')

@section('content')
    <div class="space-y-5">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- CACHE -->
        <x-admin.panel
            title="Application cache"
            info="This is the application cache. Clearing it flushes every cached entry (views, config, routes). The next request rebuilds them all."
        >
            <p class="text-xs font-semibold text-[#2D231E]">
                Driver:
                <span class="font-bold text-[#2D231E]">{{ $snapshot['cache']['driver'] }}</span>
                @if ($snapshot['cache']['entries'] !== null)
                    &middot; {{ $snapshot['cache']['entries'] }} file entr{{ $snapshot['cache']['entries'] === 1 ? 'y' : 'ies' }}
                @endif
            </p>
            <div class="pt-4">
                <form method="POST" action="{{ route('admin.control.cache') }}" data-busy data-busy-text="Clearing the application cache. A moment.">
                    @csrf
                    <x-admin.button tone="safe" type="button" data-confirm-open="cache-modal">Clear cache</x-admin.button>
                </form>
            </div>
        </x-admin.panel>

        <!-- ERROR LOG -->
        <x-admin.panel
            title="Error log"
            info="This is the application error log. Clearing it empties storage/logs/laravel.log so the Status sidebar dot goes away. The app keeps running and future errors are logged as usual."
        >
            <p class="text-xs font-semibold text-[#2D231E]">
                <span class="font-bold text-[#2D231E]">laravel.log</span>
                holds recent errors and warnings. The Status sidebar dot appears while error lines sit in the log tail.
            </p>
            <div class="pt-4">
                <form method="POST" action="{{ route('admin.control.error-log.clear') }}">
                    @csrf
                    <x-admin.button tone="outline" type="button" data-confirm-open="error-log-modal">Clear error log</x-admin.button>
                </form>
            </div>
        </x-admin.panel>

        <!-- MAINTENANCE -->
        <x-admin.panel
            title="Maintenance mode"
            info="This is maintenance mode. Turning it on puts the public site behind a maintenance page. The admin panel stays reachable and nobody is signed out."
        >
            <p class="text-xs font-semibold text-[#2D231E]">
                The site is
                <span class="font-bold {{ $maintenance ? 'text-rose-600' : 'text-emerald-600' }} uppercase">
                    {{ $maintenance ? 'offline' : 'live' }}
                </span>.
                @if ($maintenance)
                    Visitors see the maintenance page.
                @else
                    Visitors can use the app normally.
                @endif
            </p>
            <div class="pt-4">
                <form method="POST" action="{{ route('admin.control.maintenance') }}">
                    @csrf
                    <x-admin.button :tone="$maintenance ? 'safe' : 'caution'" type="button" data-confirm-open="maintenance-modal">
                        {{ $maintenance ? 'Turn maintenance mode off' : 'Turn maintenance mode on' }}
                    </x-admin.button>
                </form>
            </div>
        </x-admin.panel>

        <!-- DATABASE BACKUP -->
        <x-admin.panel
            title="Database backup"
            info="This is the database backup tool. It downloads a full SQL copy of the database to this device. Nothing is stored on the server."
        >
            <p class="text-xs font-semibold text-[#2D231E]">
                Downloads to <span class="font-bold text-[#2D231E]">this device</span> as
                <span class="font-bold text-[#2D231E]">alice-backup-…sql</span>. Nothing is stored on the server.
            </p>
            <p class="text-xs font-semibold text-[#2D231E] mt-2">
                The dump is plain SQL text created directly from the database, with no external tool needed. Open it in any
                text editor, and restore it through phpMyAdmin or HeidiSQL if the database is ever corrupted.
            </p>
            <div class="pt-4">
                <form method="GET" action="{{ route('admin.control.dump') }}">
                    <x-admin.button tone="outline" type="button" data-confirm-open="dump-modal">Download database dump</x-admin.button>
                </form>
            </div>
        </x-admin.panel>

        <!-- SESSIONS -->
        <x-admin.panel
            title="Sessions"
            info="This is the sessions tool. It signs out every signed-in user except you, on every device."
        >
            <p class="text-xs font-semibold text-[#2D231E]">
                Signs out every signed-in user except you, on every device.
            </p>
            <div class="pt-4">
                <form method="POST" action="{{ route('admin.control.sign-out-all') }}">
                    @csrf
                    <x-admin.button tone="danger" type="button" data-confirm-open="signout-all-modal">Sign out all sessions except mine</x-admin.button>
                </form>
            </div>
        </x-admin.panel>

        <!-- MAIL -->
        <x-admin.panel
            title="Mail test"
            info="This is the mail test tool. It sends a short test message to your own address through the configured mail server and measures delivery time."
        >
            <p class="text-xs font-semibold text-[#2D231E]">
                Sends a test message to
                <span class="font-bold text-[#2D231E]">{{ $snapshot['mail']['from'] }}</span>
                via <span class="font-bold text-[#2D231E]">{{ $snapshot['mail']['mailer'] }}</span>
                @if ($snapshot['mail']['host'])
                    ({{ $snapshot['mail']['host'] }}:{{ $snapshot['mail']['port'] }})@endif. The delivery time is measured and shown on the Status page.
            </p>
            <div class="pt-4">
                <form method="POST" action="{{ route('admin.control.mail-test') }}">
                    @csrf
                    <x-admin.button tone="neutral" type="button" data-confirm-open="mail-modal">Send test email</x-admin.button>
                </form>
            </div>
        </x-admin.panel>

        </div>

    </div>

    <!-- CLEAR CACHE CONFIRM -->
    <x-modal id="cache-modal" title="Clear the application cache?">
        <p class="text-xs font-semibold text-[#2D231E]">Every cached entry is flushed and rebuilt on demand. Recorded in the audit log.</p>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="safe" type="button" data-confirm-approve>Clear cache</x-admin.button>
        </div>
    </x-modal>

    <!-- ERROR LOG CONFIRM -->
    <x-modal id="error-log-modal" title="Clear the error log?">
        <p class="text-xs font-semibold text-[#2D231E]">
            laravel.log is emptied and the Status dot goes away. The app keeps running and future errors are logged as usual. Recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="caution" type="button" data-confirm-approve>Clear error log</x-admin.button>
        </div>
    </x-modal>

    <!-- MAINTENANCE CONFIRM -->
    <x-modal id="maintenance-modal" title="{{ $maintenance ? 'Turn maintenance mode off?' : 'Turn maintenance mode on?' }}">
        <p class="text-xs font-semibold text-[#2D231E]">
            @if ($maintenance)
                The site becomes live for everyone again. Recorded in the audit log.
            @else
                Visitors get the maintenance page; the admin panel stays reachable so you can switch it back off.
                Recorded in the audit log.
            @endif
        </p>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button :tone="$maintenance ? 'safe' : 'caution'" type="button" data-confirm-approve>
                {{ $maintenance ? 'Turn off maintenance' : 'Turn on maintenance' }}
            </x-admin.button>
        </div>
    </x-modal>

    <!-- DB DUMP CONFIRM -->
    <x-modal id="dump-modal" title="Download a database dump?">
        <p class="text-xs font-semibold text-[#2D231E]">
            A full SQL copy starts downloading to <span class="font-bold text-[#2D231E]">this device</span> (your
            browser's downloads folder). It contains personal data. Store it somewhere secure and delete it when
            no longer needed. Recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="outline" type="button" data-confirm-approve>Download dump</x-admin.button>
        </div>
    </x-modal>

    <!-- SIGN OUT ALL CONFIRM -->
    <x-modal id="signout-all-modal" title="Sign out everyone except you?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Every other signed-in user is logged out and must sign in again. Your own session is untouched.
            Recorded in the audit log.
        </p>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" data-double-confirm class="mt-0.5 w-4 h-4 accent-rose-600 cursor-pointer">
            <span class="text-xs font-semibold text-[#2D231E]">
                I understand. Everyone except me will be signed out on every device.
            </span>
        </label>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="danger" type="button" data-confirm-approve>Sign everyone out</x-admin.button>
        </div>
    </x-modal>

    <!-- MAIL CONFIRM -->
    <x-modal id="mail-modal" title="Send a test email?">
        <p class="text-xs font-semibold text-[#2D231E]">A short message goes to your account's email address. Recorded in the audit log.</p>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="neutral" type="button" data-confirm-approve>Send test email</x-admin.button>
        </div>
    </x-modal>
@endsection