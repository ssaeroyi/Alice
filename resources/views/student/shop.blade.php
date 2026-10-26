@extends('layouts.authenticated')

@section('page', 'shop')

@section('content')
{{-- data-shop marks the page for pages/shop.js. The wardrobe is built in JS
     from the data-skins payload (same as the Field Journal SKINS tab). The
     Streak Restore sink is a plain form and works with JS off. --}}
<div
    class="max-w-5xl mx-auto px-4 py-6 space-y-6"
    data-shop
    data-skins='@json($skinsPayload)'
    data-skins-base-url="{{ route('skins.index') }}"
>

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('hub') }}" aria-label="Back to hub"
           class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-base font-bold text-[#2D231E] uppercase">Shop</h1>
        <span class="ml-auto inline-flex items-center gap-2 px-3 py-2 bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
            <span class="inline-flex w-5 h-5 text-amber-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
            <span class="font-pixel text-sm font-bold text-[#2D231E]" data-keys-total>{{ number_format($skinsState['keys']) }}</span>
        </span>
    </div>

    <!-- WARDROBE -->
    <section class="shop-wardrobe bg-white border-2 border-[#2D231E] rounded-2xl p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] space-y-4">
        <h2 class="font-pixel text-sm font-bold text-[#2D231E] uppercase">Wardrobe</h2>

        <div class="flex items-center gap-3 flex-wrap" role="tablist" aria-label="Wardrobe type">
            @foreach (['alice' => 'Alice', 'boss' => 'Boss', 'background' => 'Background'] as $tabKey => $tabLabel)
                <button
                    type="button"
                    role="tab"
                    data-skins-section="{{ $tabKey }}"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    class="font-pixel text-[10px] font-bold uppercase px-4 min-h-[44px] border-2 border-[#2D231E] rounded-xl cursor-pointer transition-all {{ $loop->first ? 'bg-amber-400 text-[#2D231E] translate-x-[2px] translate-y-[2px]' : 'bg-white text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FAF6EE] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none' }}"
                >{{ $tabLabel }}</button>
            @endforeach
        </div>

        <div class="skins-grid" data-skins-grid></div>
    </section>

    <!-- STREAK RESTORES -->
    <section class="bg-white border-2 border-[#2D231E] rounded-2xl p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] space-y-4">
        <h2 class="font-pixel text-sm font-bold text-[#2D231E] uppercase">Streak Restores</h2>

        <div class="flex items-center gap-3 bg-sky-50 border-2 border-[#2D231E] rounded-2xl p-3">
            <span class="w-10 h-10 bg-sky-100 border-2 border-[#2D231E] rounded-xl flex items-center justify-center text-sky-600 shrink-0">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'snowflake') !!}
            </span>
            <div class="flex-1 min-w-0">
                <p class="font-pixel text-[11px] uppercase leading-tight text-[#2D231E]">Buy a Streak Restore</p>
                <p class="text-[10px] font-semibold text-[#2D231E]">
                    {{ number_format($freezeCost) }} keys each. Held {{ $freezeHeld }} of {{ $freezeCap }}.
                </p>
            </div>
            @if ($freezeHeld < $freezeCap)
                <form method="POST" action="{{ route('rewards.freeze.buy') }}" data-submit-guard>
                    @csrf
                    <button
                        type="submit"
                        @disabled($skinsState['keys'] < $freezeCost)
                        class="min-w-[44px] min-h-[44px] px-4 bg-sky-500 text-white font-pixel text-[11px] font-bold rounded-xl border-2 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-sky-600 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all uppercase cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <span data-submit-idle>Buy</span>
                        <span data-submit-active class="hidden"><x-spinner label="Buying..." /></span>
                    </button>
                </form>
            @else
                <span class="font-pixel text-[10px] font-bold uppercase text-sky-700 px-3 py-2 bg-white border-2 border-[#2D231E] rounded-xl">At cap</span>
            @endif
        </div>
    </section>

    <!-- Skin preview dialog (same markup and classes as the Field Journal) -->
    <div id="skin-preview" hidden role="dialog" aria-modal="true" aria-label="Skin preview">
        <div class="skin-preview-frame">
            <div class="skin-preview-top">
                <span class="journal-keys" title="Your keys">
                    <span class="journal-keys-glyph">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                    <span class="journal-keys-count font-pixel" data-keys-count>{{ number_format($skinsState['keys']) }}</span>
                </span>
                <button type="button" data-preview-close class="journal-icon-btn font-pixel" aria-label="Close preview">X</button>
            </div>
            <div class="skin-preview-stage" data-preview-stage></div>
            <p class="skin-preview-name font-pixel" data-preview-name></p>
            <p class="skin-preview-sub" data-preview-sub></p>
            <button type="button" class="skin-preview-action font-pixel" data-preview-action></button>
        </div>
    </div>

</div>
@endsection
