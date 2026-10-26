<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Access Denied | ALICE</title>
    <meta name="description" content="ALICE - You do not have permission to view this page.">

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
        <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase tracking-wider">Error 403</p>
        <h1 class="text-lg font-extrabold text-[#2D231E]">Access denied</h1>
        <p class="text-sm font-semibold text-[#8C7B70] leading-relaxed">
            You do not have permission to view this area.
        </p>
        <div class="pt-2">
            <a
                href="{{ route('home') }}"
                class="inline-block min-h-[44px] bg-[#2D231E] text-[#FAF6EE] rounded-lg px-6 py-2.5 text-xs font-bold hover:bg-[#3D322C] transition-colors"
            >
                BACK TO HOME
            </a>
        </div>
    </div>

</body>
</html>