<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Too many requests | ALICE</title>
    <meta name="description" content="ALICE - Too many requests were sent in a short time.">

    @vite(['resources/css/app.css', 'resources/css/web-fonts.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Quicksand', sans-serif;
        }
        .font-pixel {
            font-family: 'Press Start 2P', monospace;
            image-rendering: pixelated;
        }
    </style>
</head>
<body class="bg-[#FAF6EE] text-[#2D231E] min-h-screen antialiased flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white border border-[#E6DCCF] rounded-xl p-8 text-center space-y-4">
        <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase tracking-wider">Error 429</p>
        <h1 class="text-lg font-extrabold text-[#2D231E]">Too many requests</h1>
        <p class="text-sm font-semibold text-[#8C7B70] leading-relaxed">
            You sent requests a little too quickly. Wait a moment, then try again — the action is safe to re-run.
        </p>
        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a
                href="{{ url()->previous() ?: route('home') }}"
                class="inline-block min-h-[44px] bg-[#2D231E] text-[#FAF6EE] rounded-lg px-6 py-2.5 text-xs font-bold hover:bg-[#3D322C] transition-colors"
            >
                GO BACK
            </a>
            <button
                type="button"
                onclick="window.location.reload()"
                class="inline-block min-h-[44px] bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg px-6 py-2.5 text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
            >
                TRY AGAIN
            </button>
        </div>
    </div>

</body>
</html>