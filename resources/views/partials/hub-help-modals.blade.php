{{-- Standalone hub help modals: one modal per topic, opened by an x-info ? with a modal prop. Each header is the topic's own name. --}}
<x-modal id="hub-help-streak" title="STREAK" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>Your streak is the result of your continual activity here in Alice, tracked through stamping each day. So what is stamping?</p>
        <p class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-xs">
            <span class="font-pixel text-[10px] font-bold uppercase">Stamp</span><br>
            Stamping is done automatically by finishing reading a story, taking a quiz, playing mini-games, and doing a study session with the flashcards.
        </p>
    </div>
</x-modal>

<x-modal id="hub-help-keys" title="KEYS" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>Keys are Alice's main economy. Use them to get hints while taking quizzes or playing mini-games.</p>
        <p class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-xs">
            <span class="font-pixel text-[10px] font-bold uppercase">How to get keys</span><br>
            Earn keys by reading stories, finishing quizzes, playing mini-games and completing daily tasks.
        </p>
    </div>
</x-modal>

<x-modal id="hub-help-saved-words" title="SAVED WORDS" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>Saving a word here is also the same as adding it to your personal dictionary. You can review these words on the study page.</p>
    </div>
</x-modal>

<x-modal id="hub-help-reading-goal" title="READING GOAL" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>This is your daily reading goal. Depending on your preference you can set it to whatever suits you, up to 60 minutes a day.</p>
    </div>
</x-modal>

<x-modal id="hub-help-study-goal" title="STUDY GOAL" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>This is your daily goal of words to study in a study session with the flashcards. You can modify it up to 100 words a day depending on your preference.</p>
    </div>
</x-modal>

<x-modal id="hub-help-daily-tasks" title="DAILY TASKS" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>These are your daily tasks. There are always three of them, and finishing one earns keys and points.</p>
        <p class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-xs">
            <span class="font-pixel text-[10px] font-bold uppercase">Reroll</span><br>
            Use your reroll chance, one per day, to shuffle the current tasks and get new ones. The replacement is random. Each reroll costs 2 keys.
        </p>
    </div>
</x-modal>

<x-modal id="hub-help-reader-ranks" title="READER RANKS" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>These are the milestone ranks you can achieve by reading and earning points. Earning enough points grants you a new title.</p>
    </div>
</x-modal>

<x-modal id="hub-help-activity" title="ACTIVITY HISTORY" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>This graph visually shows your tracked progress. Use the cycle button in the corner to switch between the chart and your history.</p>
        <p class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-xs">
            <span class="font-pixel text-[10px] font-bold uppercase">Activity chart</span><br>
            Pick a range. Today, this week, last week or all time. Each day is one stacked bar (reading, quiz, game and drill added together). All Time shows your activity month by month. Hover or tap a bar for the breakdown. The dot under each day lights up when you kept your streak going.
        </p>
        <p class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-xs">
            <span class="font-pixel text-[10px] font-bold uppercase">History</span><br>
            A long-run line graph of your activity, month by month, from your first day to now. Pick a type to focus on reading, quizzes, games or drills, or Overall to see them combined. Handy for spotting how a month or a whole year compared.
        </p>
    </div>
</x-modal>

<x-modal id="hub-help-high-scores" title="HIGH SCORES" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>These show your current best high score for each mini-game.</p>
    </div>
</x-modal>

<x-modal id="hub-help-stamp-calendar" title="STAMP CALENDAR" :pixel="true">
    <div class="space-y-3 text-sm text-[#2D231E] font-medium leading-relaxed">
        <p>This is your visual heatmap of the days you were active in Alice. Tap a green or yellow day to see exactly what you did.</p>
        <ul class="space-y-1.5 text-xs">
            <li class="flex items-start gap-2"><span class="mt-0.5 w-3 h-3 shrink-0 rounded border-2 border-[#2D231E] bg-emerald-500"></span><span><strong>Stamped:</strong> green, you stamped that day and kept your streak. The greener the day, the more you did: light green = 1 action, deep green = 7 or more.</span></li>
            <li class="flex items-start gap-2"><span class="mt-0.5 w-3 h-3 shrink-0 rounded border-2 border-[#2D231E] bg-amber-100"></span><span><strong>Active:</strong> yellow, you were active that day but didn't do any triggering activity to stamp it.</span></li>
            <li class="flex items-start gap-2"><span class="mt-0.5 w-3 h-3 shrink-0 rounded border-2 border-[#2D231E] ring-2 ring-amber-400 bg-white"></span><span><strong>Today:</strong> white with an amber ring, nothing stamped yet.</span></li>
            <li class="flex items-start gap-2"><span class="mt-0.5 w-3 h-3 shrink-0 rounded border border-[#E6DCCF] bg-white"></span><span><strong>Plain:</strong> nothing was logged that day.</span></li>
        </ul>
    </div>
</x-modal>
