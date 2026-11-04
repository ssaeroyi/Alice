@props([
    'student',
    'size' => 36,
])

@php
    // Teacher-context identity chip: neutral, flat, monochrome. The student
    // sprites AND the color lottery both live on the student side only.
    $name = trim(($student->first_name ?? '').' '.($student->last_name ?? ''))
        ?: ($student->nickname ?? strtok($student->email, '@'));
    $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? $name : '?', 0, 1));
    $px = 36 + $size;
@endphp

<span
    class="inline-flex shrink-0 items-center justify-center rounded-full border-2 border-[#2D231E]/20 bg-[#F3ECE0] font-extrabold text-[#2D231E]"
    style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ (int) max(11, $size * 0.4) }}px"
    aria-hidden="true"
>{{ $initial }}</span>
