@props([
    'user' => null,
    'name' => null,
    'avatar' => null,
    'size' => 36,
    'medal' => null,
    'alt' => 'User avatar',
])

@php
    if ($user !== null) {
        $name = $name ?? ($user->nickname ?? strtok($user->email ?? '', '@'));
        $avatar = $avatar ?? $user->avatar;
    }

    $name = (string) ($name ?? '');
    $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';

    // Shared size map so every roster/header renders the same bubble at the
    // same scale (size = diameter in px).
    $sizeClasses = [
        24 => 'w-6 h-6 text-[9px]',
        28 => 'w-7 h-7 text-[10px]',
        32 => 'w-8 h-8 text-xs',
        36 => 'w-9 h-9 text-xs',
        40 => 'w-10 h-10 text-sm',
        56 => 'w-14 h-14 text-lg',
    ];
    $sizeClass = $sizeClasses[(int) $size] ?? $sizeClasses[36];

    // One neutral bubble for every roster, leaderboard and header: app.css
    // remaps bg-[#FAF6EE] to --c-page (and the text to --c-ink) under .dark,
    // so light mode gets a light circle and dark mode a dark one.
    $bgClass = 'bg-[#FAF6EE]';
    $textClass = 'text-[#2D231E]';
@endphp

<span {{ $attributes->merge(['class' => "shrink-0 $sizeClass rounded-full overflow-hidden border border-[#2D231E] flex items-center justify-center font-pixel font-bold select-none $bgClass $textClass"]) }}>
    @if (filled($avatar ?? null))
        <img src="{{ asset('image/avatar/' . $avatar) }}" alt="{{ $alt }}" class="w-full h-full object-cover">
    @else
        {{ $initial }}
    @endif
</span>
