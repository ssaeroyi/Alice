@props(['field' => ''])

@error($field)
    <p {{ $attributes->merge(['class' => 'mt-1.5 flex items-center gap-1.5 text-xs font-bold text-rose-700']) }}>
        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        {{ $message }}
    </p>
@enderror