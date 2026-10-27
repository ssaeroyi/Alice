/**
 * Memory game — memorize the pairs, then match.
 * 10 pairs (20 cards) in a 4-column grid. Round starts with every card
 * face-up for a memorize countdown; on START the cards flip down and the
 * game timer begins. Timer: base time * difficulty multiplier.
 */

import { play } from '../game/sfx.js';
import { shake, flash, comboFlash, scorePopup } from '../game/juice.js';
import { burst, pips, clearPips, computeFinalScore } from '../game/arcade.js';
import { openModal, closeModal } from '../components/modal.js';

let baseTime = 100;
const COMBO_THRESHOLD = 2;
const FINAL_COUNTDOWN = 3;

let root, csrf, pairs, cards, flipped, matchedPairIds, wrongFlips, combo, maxCombo;
let score, timeLeft, totalTime, timerInterval, started, finished, roundId;
let memorizing, memorizeInterval, memorizeSeconds;
let keysLeft = null;
let difficulty, source, timeMultiplier;

export default function init() {
    root = document.querySelector('[data-game-pairs]');
    if (!root) return;

    csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    pairs = JSON.parse(root.dataset.gamePairs || '[]');
    timeMultiplier = parseFloat(root.dataset.timeMultiplier) || 1;
    baseTime = parseInt(root.dataset.baseTime) || 100;
    memorizeSeconds = parseInt(root.dataset.memorizeSeconds) || 10;
    difficulty = root.dataset.difficulty || 'medium';
    source = root.dataset.source || 'open';

    if (pairs.length < 2) return; // need at least one card to play

    roundId = crypto.randomUUID?.() || Date.now().toString(36) + Math.random().toString(36).slice(2);
    setupBoard();

    // First-time gate: show the How to Play modal BEFORE the memorize
    // countdown starts; the timer only runs once they click GOT IT. Repeat
    // players (flag set) go straight into the memorize phase.
    const HELP_FLAG = 'alice-match-help-seen';
    let helpSeen = false;
    try { helpSeen = localStorage.getItem(HELP_FLAG) === '1'; } catch { /* storage off */ }

    if (helpSeen) {
        startMemorize();
    } else {
        const helpModal = document.getElementById('game-help-modal');
        const gotIt = helpModal?.querySelector('[data-modal-close]');
        openModal(helpModal);
        gotIt?.addEventListener('click', () => {
            try { localStorage.setItem(HELP_FLAG, '1'); } catch { /* storage off */ }
            if (!memorizing && !started && !finished) {
                startMemorize();
            }
        }, { once: true });
    }

    wireEvents();
}

function setupBoard() {
    const board = document.getElementById('game-board');
    board.innerHTML = '';

    // Build 20 cards from 10 synonym pairs
    cards = [];
    pairs.forEach((pair, i) => {
        cards.push({ id: cards.length, text: pair.a, pairId: i });
        cards.push({ id: cards.length, text: pair.b, pairId: i });
    });
    shuffle(cards);

flipped = [];
    matchedPairIds = [];
    wrongFlips = 0;
    combo = 0;
    maxCombo = 0;
    score = 0;
    totalTime = Math.round(baseTime * timeMultiplier);
    timeLeft = totalTime;
    started = false;
    finished = false;
    memorizing = false;
    if (memorizeInterval) clearInterval(memorizeInterval);

    updateScore();
    updateTimer();
    clearPips(document.getElementById('combo-pips'));
    document.getElementById('result-screen').classList.add('hidden');
    document.getElementById('result-screen').classList.remove('flex');
    document.getElementById('game-board').parentElement.classList.remove('hidden');
    document.getElementById('game-status').classList.remove('hidden');
    document.getElementById('game-status').textContent = 'Flip cards to find matching pairs';

    cards.forEach((c) => {
        const el = document.createElement('div');
        el.className = 'game-card w-[72px] h-[88px] sm:w-[84px] sm:h-[100px] cursor-pointer';
        el.dataset.index = c.id;
        el.innerHTML = `
            <div class="game-card-inner relative w-full h-full">
                <div class="game-card-back absolute inset-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <span class="font-pixel text-[10px] text-[#2D231E]">?</span>
                </div>
                <div class="game-card-front absolute inset-0 bg-white border-2 border-[#2D231E] rounded-xl flex items-center justify-center p-1.5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <span class="text-[11px] font-bold text-[#2D231E] text-center leading-tight break-words overflow-hidden px-1">${escapeHtml(c.text)}</span>
                </div>
            </div>
        `;
        board.appendChild(el);
    });

    if (timerInterval) clearInterval(timerInterval);
    document.getElementById('timer-bar').style.width = '100%';
    document.getElementById('timer-bar').className = 'game-timer-bar h-full rounded-full';
}

function wireEvents() {
    const board = document.getElementById('game-board');
    board.addEventListener('click', onCardClick);

    document.getElementById('btn-replay')?.addEventListener('click', () => {
        window.location.reload();
    });

document.getElementById('btn-help')?.addEventListener('click', () => openModal(document.getElementById('game-help-modal')));

    document.getElementById('btn-hint')?.addEventListener('click', purchaseHint);

    // Hint confirm modal
    const hintModal = document.getElementById('hint-modal');
    document.getElementById('btn-hint-confirm')?.addEventListener('click', () => {
        const balance = Number(keysLeft ?? hintModal?.dataset.hintBalance ?? 0);
        const cost = Number(hintModal?.dataset.hintCost || 5);

        if (balance < cost) {
            shakeConfirm('NOT ENOUGH KEYS');

            return;
        }

        closeModal(hintModal);
        revealHint();
    });

    // Quit confirmation
    const backBtn = document.getElementById('btn-back');
    const quitModal = document.getElementById('quit-modal');
    if (backBtn && quitModal) {
        backBtn.addEventListener('click', (e) => {
            if (!finished) {
                e.preventDefault();
                openModal(quitModal);
            }
        });
    }
}

function shakeConfirm(msg) {
    const btn = document.getElementById('btn-hint-confirm');
    if (!btn) return;
    btn.classList.remove('is-shake');
    void btn.offsetWidth;
    btn.classList.add('is-shake');
    if (msg) {
        const prev = btn.innerHTML;
        btn.textContent = msg;
        setTimeout(() => {
            btn.innerHTML = prev;
            btn.classList.remove('is-shake');
        }, 1200);
    }
}

function syncHintModal() {
    const modal = document.getElementById('hint-modal');
    if (!modal) return;

    const balance = Number(keysLeft ?? modal.dataset.hintBalance ?? 0);
    const balanceEl = modal.querySelector('[data-slot="hint-balance"]');
    const confirmBtn = document.getElementById('btn-hint-confirm');

    if (balanceEl) {
        balanceEl.textContent = String(balance);
    }

    if (confirmBtn) {
        confirmBtn.dataset.baseLabel ||= confirmBtn.innerHTML;
        confirmBtn.innerHTML = confirmBtn.dataset.baseLabel;
    }
}

function purchaseHint() {
    if (finished || memorizing) return;
    syncHintModal();
    openModal(document.getElementById('hint-modal'));
}

async function revealHint() {
    if (finished) return;

    try {
        const res = await fetch('/game/hint', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ game_type: 'match' }),
        });

        const data = await res.json();

if (!res.ok) {
            document.getElementById('game-status').textContent = data.message || 'Not enough keys.';
            return;
        }

        keysLeft = Number(data.balance ?? keysLeft);
    } catch {
        document.getElementById('game-status').textContent = 'Hint failed. Try again.';
        return;
    }

    // Find one unmatched pair and briefly flip both cards
    const unmatchedPairIds = pairs
        .map((_, i) => i)
        .filter((i) => !matchedPairIds.includes(i));

    if (unmatchedPairIds.length === 0) return;

    const targetPairId = unmatchedPairIds[0];
    const pairCards = cards.filter((c) => c.pairId === targetPairId);

    play('pop');
    pairCards.forEach((c) => {
        const el = boardCardEl(c.id);
        if (el && !el.classList.contains('flipped')) {
            el.classList.add('flipped');
        }
    });

    document.getElementById('game-status').textContent =
        `Hint: "${pairCards[0].text}" and "${pairCards[1].text}" go together`;

    setTimeout(() => {
        pairCards.forEach((c) => {
            const el = boardCardEl(c.id);
            if (el && !el.classList.contains('matched')) {
                el.classList.remove('flipped');
            }
        });
    }, 1200);
}

function startMemorize() {
    const top = document.getElementById('memorize-top');
    const topCount = document.getElementById('memorize-top-count');
    const overlay = document.getElementById('memorize-overlay');
    const count = document.getElementById('memorize-count');
    const label = document.getElementById('memorize-label');
    const hintBtn = document.getElementById('btn-hint');

    memorizing = true;
    top.classList.remove('hidden');
    top.classList.add('flex');
    topCount.textContent = String(memorizeSeconds);
    document.getElementById('game-status').textContent = 'Memorize the pairs!';

    hintBtn.disabled = true;
    hintBtn.classList.add('opacity-40', 'pointer-events-none');

    // Show every card face-up so the round starts on memory, not luck. The
    // grace countdown stays in the top pill so it never hides the cards.
    cards.forEach((c) => {
        const el = boardCardEl(c.id);
        if (el) el.classList.add('flipped');
    });

    let left = memorizeSeconds;
    memorizeInterval = setInterval(() => {
        left--;
        if (left > 0) {
            play('tick');
            topCount.textContent = String(left);
            return;
        }
        clearInterval(memorizeInterval);
        startFinalCountdown(top, topCount, overlay, count, label, hintBtn);
    }, 1000);
}

function startFinalCountdown(top, topCount, overlay, count, label, hintBtn) {
    // Grace period is over: swap in the center overlay for the 3-2-1 cue.
    top.classList.add('hidden');
    top.classList.remove('flex');
    label.textContent = 'Get ready';
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    count.textContent = String(FINAL_COUNTDOWN);

    let left = FINAL_COUNTDOWN;
    memorizeInterval = setInterval(() => {
        left--;
        if (left > 0) {
            play('tick');
            count.textContent = String(left);
            return;
        }
        clearInterval(memorizeInterval);
        startPlay(overlay, count, hintBtn);
    }, 1000);
}

function startPlay(overlay, count, hintBtn) {
    play('go');
    count.textContent = 'START!';
    document.getElementById('game-status').textContent = 'GO!';

    setTimeout(() => {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        memorizing = false;

        // Flip the cards down — only now can the game begin.
        cards.forEach((c) => {
            const el = boardCardEl(c.id);
            if (el && !el.classList.contains('matched')) el.classList.remove('flipped');
        });

        hintBtn.disabled = false;
        hintBtn.classList.remove('opacity-40', 'pointer-events-none');
        document.getElementById('game-status').textContent = 'Flip cards to find matching pairs';
    }, 350);
}

function boardCardEl(cardId) {
    return document.querySelector(`.game-card[data-index="${cardId}"]`);
}

function onCardClick(e) {
    if (finished || memorizing) return;

    const cardEl = e.target.closest('.game-card');
    if (!cardEl) return;

    const idx = parseInt(cardEl.dataset.index, 10);
    const card = cards.find((c) => c.id === idx);
    if (!card) return;
    if (cardEl.classList.contains('flipped') || cardEl.classList.contains('matched')) return;
    if (flipped.length >= 2) return;

    if (!started) {
        started = true;
        startTimer();
    }

    cardEl.classList.add('flipped');
    flipped.push({ el: cardEl, card });
    play('flip');

    if (flipped.length === 2) {
        checkMatch();
    }
}

function checkMatch() {
    const [a, b] = flipped;
    const isMatch = a.card.pairId === b.card.pairId;

    if (isMatch) {
        combo++;
        maxCombo = Math.max(maxCombo, combo);
        matchedPairIds.push(a.card.pairId);

        a.el.classList.add('matched');
        b.el.classList.add('matched');
        a.el.style.opacity = '0.4';
        b.el.style.opacity = '0.4';
        a.el.style.pointerEvents = 'none';
        b.el.style.pointerEvents = 'none';

        score += 10 + (combo >= COMBO_THRESHOLD ? combo * 5 : 0);
        updateScore();
        play('match');
        flash(document.getElementById('game-board'), 'emerald');
        burst(a.el, 'star', 6);
        burst(b.el, 'coin', 4);

        if (combo >= COMBO_THRESHOLD) {
            comboFlash(document.getElementById('score-display'), combo);
            pips(document.getElementById('combo-pips'), combo, 5);
        } else {
            pips(document.getElementById('combo-pips'), combo, 5);
        }

        if (matchedPairIds.length === pairs.length) {
            endGame(true);
        }
    } else {
        combo = 0;
        wrongFlips++;
        clearPips(document.getElementById('combo-pips'));

        shake(a.el);
        shake(b.el);
        burst(a.el, 'cross', 4);
        burst(b.el, 'cross', 4);

        setTimeout(() => {
            a.el.classList.remove('flipped');
            b.el.classList.remove('flipped');
        }, 800);

        play('wrong');
    }

    flipped = [];
}

function startTimer() {
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimer();

        if (timeLeft <= 0) {
            endGame(false);
        }
    }, 1000);
}

function updateTimer() {
    const t = Math.max(0, timeLeft);
    const min = Math.floor(t / 60);
    const sec = t % 60;
    document.getElementById('timer-display').textContent = `${min}:${sec.toString().padStart(2, '0')}`;

    const pct = (t / totalTime) * 100;
    const bar = document.getElementById('timer-bar');
    bar.style.width = `${pct}%`;
    bar.className = 'game-timer-bar h-full rounded-full';
    if (pct < 20) bar.classList.add('danger');
    else if (pct < 50) bar.classList.add('warning');
}

function updateScore() {
    document.getElementById('score-display').textContent = `${score} points`;
}

function endGame(won) {
    finished = true;
    if (timerInterval) clearInterval(timerInterval);

    const finalScore = computeFinalScore({
        accuracy: matchedPairIds.length / pairs.length,
        timeFraction: Math.max(0, timeLeft) / totalTime,
        maxCombo,
        wrongFraction: Math.min(1, wrongFlips / (pairs.length * 2)),
    });

    document.getElementById('game-board').parentElement.classList.add('hidden');
    document.getElementById('game-status').classList.add('hidden');

    const resultScreen = document.getElementById('result-screen');
    resultScreen.classList.remove('hidden');
    resultScreen.classList.add('flex');

    if (won) {
        window.Celebrate?.('win', resultScreen);
    }


    document.getElementById('result-title').textContent = won
        ? 'Excellent!'
        : matchedPairIds.length > 0
            ? 'Good Try!'
            : 'Time\'s Up!';
    document.getElementById('result-subtitle').textContent = won
        ? 'You matched all the synonym pairs!'
        : `You matched ${matchedPairIds.length} of ${pairs.length} pairs`;

    document.getElementById('result-stats').innerHTML = `
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <p class="font-pixel text-[10px] text-[#2D231E] uppercase mb-1">Matched</p>
            <p class="font-pixel text-sm font-bold text-[#2D231E]">${matchedPairIds.length}/${pairs.length}</p>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <p class="font-pixel text-[10px] text-[#2D231E] uppercase mb-1">Wrong</p>
            <p class="font-pixel text-sm font-bold text-[#2D231E]">${wrongFlips}</p>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <p class="font-pixel text-[10px] text-[#2D231E] uppercase mb-1">Best Combo</p>
            <p class="font-pixel text-sm font-bold text-[#2D231E]">${maxCombo}x</p>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <p class="font-pixel text-[10px] text-[#2D231E] uppercase mb-1">Score</p>
            <p class="font-pixel text-sm font-bold text-[#2D231E]">${finalScore}%</p>
        </div>
    `;

    play(won ? 'fanfare' : 'wrong');
    submitRound(finalScore);
}

function submitRound(finalScore) {
    fetch('/game/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            game_type: 'match',
            score: finalScore,
            round_id: roundId,
            words_count: pairs.length * 2,
            correct_count: matchedPairIds.length,
            difficulty,
            source,
        }),
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.xp > 0) {
                const title = document.getElementById('result-title');
                scorePopup(title, `+${data.xp} points`, '#10B981');
            }
        })
        .catch(() => {});
}

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


