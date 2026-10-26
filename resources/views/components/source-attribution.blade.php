@props(['title' => '', 'author' => '', 'url' => ''])

<p {{ $attributes->merge(['class' => 'mt-3 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[10px] font-semibold text-[#8C7B70]']) }}>
    Adapted from
    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1 font-bold text-amber-700 hover:text-[#2D231E] hover:underline transition-colors"
    >
        {{ $title }}
        <x-icons.external-link class="w-3 h-3" />
    </a>
    by {{ $author }}, public domain.
</p>