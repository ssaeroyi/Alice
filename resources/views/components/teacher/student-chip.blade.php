@props([
    'student',
    'size' => 36,
])

{{-- Teacher-context identity is the student's REAL avatar (the plain
     avatar component shots, sprite or initial fallback, unchanged from the
     student side). This wrapper only keeps the teacher call sites short. --}}
<x-avatar :user="$student" :size="$size" />
