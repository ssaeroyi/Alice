@extends(auth()->check() && auth()->user()->role === \App\Enums\Role::Student ? 'layouts.authenticated' : 'layouts.guest')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 sm:py-12 space-y-6">

    <!-- Companion Dialogue Bubble (ALICE) -->
    <div class="flex flex-row items-center gap-3 sm:gap-4">
        <div class="shrink-0 flex items-center justify-center relative">
            <div class="absolute w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>
            <img
                src="{{ asset('image/Alice/Light mode/alice-lt-talking.png') }}"
                width="1280" height="1280"
                alt="ALICE"
                class="relative z-10 w-16 h-16 sm:w-20 sm:h-20 object-contain scale-110 drop-shadow-[2px_2px_0px_rgba(45,35,30,1)]"
            >
        </div>

        <div class="relative flex-1 min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
            <div class="absolute -left-2.5 sm:-left-3 top-1/2 -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

            <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                Need help or found a bug? Send us a message and we'll get back to you!
            </p>
        </div>
    </div>

    <!-- Contact Form Panel -->
    <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Header -->
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <span class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                CONTACT US
            </span>
        </div>

        @if (session('contact_sent'))
            <div class="bg-emerald-50 border-2 border-emerald-400 text-emerald-800 text-xs sm:text-sm font-bold rounded-2xl p-3 leading-relaxed">
                Thanks! Your message was sent. We will get back to you soon.
            </div>
        @endif

        @if ($errors->has('form'))
            <div class="bg-rose-50 border-2 border-rose-400 text-rose-700 text-xs sm:text-sm font-bold rounded-2xl p-3 leading-relaxed">
                {{ $errors->first('form') }}
            </div>
        @endif

        <form action="{{ route('contact.submit') }}" method="POST" class="space-y-5">
            @csrf

            <!-- Name Field -->
            <div class="space-y-1.5">
                <label for="name" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Name
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Your name"
                    value="{{ old('name') }}"
                    required
                    class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                @error('name')
                    <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email Field -->
            <div class="space-y-1.5">
                <label for="email" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Email Address
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="wanderer@example.com"
                    value="{{ old('email') }}"
                    required
                    class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                @error('email')
                    <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Topic Field -->
            <div class="space-y-1.5">
                <label for="topic" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Topic
                </label>
                <select
                    id="topic"
                    name="topic"
                    required
                    class="w-full min-h-[44px] bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs font-bold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer uppercase transition-all"
                >
                    <option value="general" {{ old('topic') === 'general' ? 'selected' : '' }}>GENERAL QUESTION</option>
                    <option value="bug" {{ old('topic') === 'bug' ? 'selected' : '' }}>REPORT A BUG</option>
                    <option value="story" {{ old('topic') === 'story' ? 'selected' : '' }}>STORY SUGGESTION</option>
                    <option value="other" {{ old('topic') === 'other' ? 'selected' : '' }}>OTHER</option>
                </select>
                @error('topic')
                    <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Message Field -->
            <div class="space-y-1.5">
                <label for="message" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Message
                </label>
                <textarea
                    id="message"
                    name="message"
                    rows="4"
                    placeholder="Type your message here..."
                    required
                    class="w-full px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all resize-y"
                >{{ old('message') }}</textarea>
                @error('message')
                    <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    <span>SEND MESSAGE</span>
                </button>
            </div>
        </form>

    </div>

</div>
@endsection
