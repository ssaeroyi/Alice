@props([
    'student',
    'size' => 36,
])

@php
    // Teacher-context identity chip: the student SPRITES belong to the
    // student side. Keep the teacher surfaces neutral with initials.
    $name = trim(($student->first_name ?? '').' '.($student->last_name ?? ''))
        ?: ($student->nickname ?? strtok($student->email, '@'));
    $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? $name : '?', 0, 1));
    $palette = ['#F2A007', '#34C3A9', '#7DA8E8', '#E884D4', '#88A9F0'];
    $bg = $palette[$student->id % count($palette)];
@endphp

<span
    class="inline-flex shrink-0 items-center justify-center rounded-full border-2 border-[#2D231E] font-extrabold text-[#2D231E]"
    style="width: {{ $size }}px; height: {{ $size }}px; background: {{ $bg }}; font-size: {{ (int) max(11, $size * 0.4) }}px"
    aria-hidden="true"
>{{ $initial }}</span>
