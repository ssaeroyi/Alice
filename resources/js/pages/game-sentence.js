/**
 * Sentence Builder — complete a broken sentence.
 * The server tags each word with part-of-speech and picks the blanks
 * (a verb, a noun and a connector where present). The player fills the
 * gaps from a shuffled pool of the blanked words.
 */

import { play } from '../game/sfx.js';
import { shake, flash, scorePopup } from '../game/juice.js';
import { burst, pips, clearPips, computeFinalScore } from '../game/arcade.js';
import { openModal, closeModal } from '../components/modal.js';
import { initMascot, mascotReact } from '../game/mascot.js';

let baseTime = 30;
const MAX_WRONG = 3;
let keysLeft = null;

const DICT_HOVER_MS = 250;
const DICT_PRESS_MS = 420;
const DICT_CACHE_KEY = 'alice.words.memo';
const DICT_BREAKER_FAILS = 3;
const DICT_BREAKER_COOLDOWN_MS = 20000;

let dictSavedWords = new Set();
let dictMemo = new Map();
let dictPopupWord = null;
let dictPopupData = null;
let dictOpenedBy = null;
let dictPressTimer = null;
let dictHoverTimer = null;
let dictHoverCloseTimer = null;
let dictAnchorRect = null;
let dictConsecutiveFails = 0;
let dictCooldownUntil = 0;
let dictSuppressClick = false;

let root, csrf;
let sentences, currentIdx;
let tokens, blankIndices, pool, blankFills, correctCount, correctBlanks, totalBlanks;
let timeLeft, timerInterval, started, finished, wrongCount, roundId, roundWrongCount;
let combo, maxCombo;
let difficulty, source;
let getTimeLimit = () => 30;

export default function init() {
    root = document.querySelector('[data-game-words]');
    if (!root) return;

    csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    sentences = JSON.parse(root.dataset.gameWords || '[]');

    if (sentences.length < 1) return;

    difficulty = root.dataset.difficulty || 'medium';
    source = root.dataset.source || 'open';
    const timeMultiplier = parseFloat(root.dataset.timeMultiplier || '1') || 1;
    baseTime = parseInt(root.dataset.baseTime) || 150;

    dictSavedWords = new Set(JSON.parse(root.dataset.savedWords || '[]'));
    dictMemo = new Map(JSON.parse(sessionStorage.getItem(DICT_CACHE_KEY) || '[]'));

    // One continuous countdown shared across the whole round (all sentences),
    // sized to the difficulty. Never reset between sentences.
    getTimeLimit = () => Math.round(baseTime * timeMultiplier);

    roundId = crypto.randomUUID?.() || Date.now().toString(36) + Math.random().toString(36).slice(2);
    correctCount = 0;
    correctBlanks = 0;
    totalBlanks = 0;
    combo = 0;
    maxCombo = 0;
    roundWrongCount = 0;
    currentIdx = 0;
    finished = false;
    started = false;
    timeLeft = getTimeLimit();

    setupSentence();
    wireEvents();
    wireDictionary();

    // First-time gate: pop the How to Play modal before play begins. The
    // timer starts on the first tile tap, so nothing needs pausing here.
    try {
        if (localStorage.getItem('alice-sentence-help-seen') !== '1') {
            const helpModal = document.getElementById('game-help-modal');
            if (helpModal) {
                openModal(helpModal);
                helpModal.querySelector('[data-modal-close]')?.addEventListener('click', () => {
                    try { localStorage.setItem('alice-sentence-help-seen', '1'); } catch { /* storage off */ }
                }, { once: true });
            }
        }
    } catch { /* storage off */ }

    initMascot();
}

function setupSentence() {
    const sentence = sentences[currentIdx];
    tokens = (sentence.words || []).map((w) => (typeof w === 'string' ? { text: w, pos: null } : w));
    blankIndices = (sentence.blanks || []).map(Number);
    totalBlanks += blankIndices.length;

    pool = shuffle(blankIndices.map((idx, i) => ({ id: i, word: tokens[idx].text, used: false })));
    blankFills = {}; // blank word-index -> pool id

    wrongCount = 0;

    document.getElementById('sentence-hint').textContent = sentence.hint || 'Complete the sentence with the word blocks.';
    document.getElementById('word-progress').textContent = `${currentIdx + 1} / ${sentences.length}`;
    document.getElementById('game-status').textContent = 'Fill in the missing words';
    document.getElementById('result-screen').classList.add('hidden');
    document.getElementById('result-screen').classList.remove('flex');
    document.getElementById('btn-clear').classList.add('hidden');
    document.getElementById('btn-submit').classList.add('hidden');

    renderSlots();
    renderPool();
    updateScore();
    updateTimer();
}

function isBlankIndex(idx) {
    return blankIndices.includes(idx);
}

function renderSlots() {
    const container = document.getElementById('answer-slots');
    container.innerHTML = '';

    tokens.forEach((token, idx) => {
        const item = document.createElement('span');
        item.className = 'sentence-item';

        if (isBlankIndex(idx)) {
            const filledWord = blankFills[idx] !== undefined
                ? pool.find((p) => p.id === blankFills[idx])?.word
                : null;

            const el = document.createElement('div');
            el.className = `game-slot sentence-slot ${filledWord !== null ? 'filled' : 'empty'}`;
            el.dataset.wordIndex = idx;
            el.textContent = filledWord !== null ? filledWord : '';
            item.appendChild(el);
        } else {
            const el = document.createElement('div');
            el.className = 'game-slot sentence-fixed';
            el.textContent = token.text;
            item.appendChild(el);
        }

        if (token.post) {
            const punct = document.createElement('span');
            punct.className = 'sentence-punct';
            punct.textContent = token.post;
            item.appendChild(punct);
        }

        container.appendChild(item);
    });
}

function renderPool() {
    const container = document.getElementById('word-blocks');
    container.innerHTML = '';

    pool.forEach((t) => {
        const el = document.createElement('div');
        el.className = `game-tile sentence-tile ${t.used ? 'used' : ''}`;
        el.dataset.poolId = t.id;
        el.textContent = t.word;
        container.appendChild(el);
    });
}

function wireEvents() {
    document.getElementById('word-blocks').addEventListener('click', (e) => {
        if (finished || dictSuppressClick) { dictSuppressClick = false; return; }
        const tileEl = e.target.closest('.game-tile');
        if (!tileEl) return;
        const poolId = parseInt(tileEl.dataset.poolId, 10);
        const tile = pool.find((t) => t.id === poolId);
        if (!tile || tile.used) return;

        if (!started) {
            started = true;
            startTimer();
        }

        const emptyBlank = blankIndices.find((idx) => blankFills[idx] === undefined);
        if (emptyBlank === undefined) return;

        tile.used = true;
        blankFills[emptyBlank] = poolId;

        play('pop');
        renderSlots();
        renderPool();
        updateButtons();
    });

    document.getElementById('answer-slots').addEventListener('click', (e) => {
        if (finished || dictSuppressClick) { dictSuppressClick = false; return; }
        const slotEl = e.target.closest('.game-slot');
        if (!slotEl) return;
        const idx = parseInt(slotEl.dataset.wordIndex, 10);
        if (!isBlankIndex(idx)) return;
        if (blankFills[idx] === undefined) return;

        const poolId = blankFills[idx];
        const tile = pool.find((t) => t.id === poolId);
        if (tile) tile.used = false;
        delete blankFills[idx];

        play('pop');
        renderSlots();
        renderPool();
        updateButtons();
    });

    document.getElementById('btn-clear')?.addEventListener('click', () => {
        if (finished) return;
        pool.forEach((t) => { t.used = false; });
        blankFills = {};
        play('whoosh');
        renderSlots();
        renderPool();
        updateButtons();
    });

    document.getElementById('btn-submit')?.addEventListener('click', () => {
        if (finished) return;
        checkAnswer();
    });

    document.getElementById('btn-replay')?.addEventListener('click', () => {
        window.location.reload();
    });

    document.getElementById('btn-help')?.addEventListener('click', () => openModal(document.getElementById('game-help-modal')));

    document.getElementById('btn-hint')?.addEventListener('click', () => purchaseHint());

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
    if (finished) return;

    syncHintModal();
    openModal(document.getElementById('hint-modal'));
}

async function revealHint() {
    if (finished) return;

    const csrfToken = csrf;

    try {
        const res = await fetch('/game/hint', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ game_type: 'sentence' }),
        });

        const data = await res.json();

        if (!res.ok) {
            document.getElementById('game-status').textContent = data.message || 'Not enough keys.';
            return;
        }

        keysLeft = Number(data.balance ?? keysLeft);

        const emptyBlank = blankIndices.find((idx) => blankFills[idx] === undefined);
        if (emptyBlank === undefined) return;

        const expected = tokens[emptyBlank].text;
        const tile = pool.find((t) => !t.used && t.word.toLowerCase() === expected.toLowerCase());

        if (tile) {
            tile.used = true;
            blankFills[emptyBlank] = tile.id;
            play('pop');
            renderSlots();
            renderPool();
            updateButtons();
        } else {
            document.getElementById('game-status').textContent = `Hint: a missing word starts with "${expected[0]}"`;
        }
    } catch {
        document.getElementById('game-status').textContent = 'Hint failed. Try again.';
    }
}

function updateButtons() {
    const hasWords = Object.keys(blankFills).length > 0;
    const allFilled = blankIndices.every((idx) => blankFills[idx] !== undefined);
    document.getElementById('btn-clear').classList.toggle('hidden', !hasWords);
    document.getElementById('btn-submit').classList.toggle('hidden', !allFilled);
}

// Live 0-100 arcade score, identical to the score submitted at the end.
function currentArcadeScore() {
    const total = getTimeLimit();
    return computeFinalScore({
        accuracy: totalBlanks ? correctBlanks / totalBlanks : 0,
        timeFraction: total ? Math.max(0, timeLeft) / total : 0,
        maxCombo,
        wrongFraction: sentences.length ? Math.min(1, roundWrongCount / sentences.length) : 0,
    });
}

function updateScore() {
    // The live readout stays "0 points" until the student first starts a
    // round (drops the first word block), so no phantom time-bonus shows
    // before any answer. currentArcadeScore() (used for the final grade)
    // is intentionally untouched.
    if (!started) {
        document.getElementById('score-display').textContent = '0 points';
        return;
    }
    document.getElementById('score-display').textContent = `${currentArcadeScore()} points`;
}

function checkAnswer() {
    const allFilled = blankIndices.every((idx) => blankFills[idx] !== undefined);
    if (!allFilled) return;

    const allCorrect = blankIndices.every((idx) => {
        const placed = pool.find((t) => t.id === blankFills[idx])?.word;
        return placed !== undefined && placed.toLowerCase() === tokens[idx].text.toLowerCase();
    });

    if (allCorrect) {
        correctCount++;
        correctBlanks += blankIndices.length;
        combo++;
        maxCombo = Math.max(maxCombo, combo);

        play('match');
        flash(document.getElementById('answer-slots'), 'emerald');
        pips(document.getElementById('combo-pips'), combo, Math.min(5, sentences.length));
        burst(document.getElementById('answer-slots'), 'star', 5);
        mascotReact('attack');
        document.getElementById('game-status').textContent = 'Correct!';
        updateScore();

        setTimeout(() => {
            currentIdx++;
            if (currentIdx >= sentences.length) {
                endGame();
            } else {
                setupSentence();
            }
        }, 800);
    } else {
        wrongCount++;
        shake(document.getElementById('answer-slots'));
        play('wrong');
        burst(document.getElementById('answer-slots'), 'cross', 3);
        mascotReact('walk');

        roundWrongCount++;
        updateScore();

        if (wrongCount >= MAX_WRONG) {
            combo = 0;
            clearPips(document.getElementById('combo-pips'));
            const correct = tokens.map((t) => t.text).join(' ');
            document.getElementById('game-status').textContent = `The sentence was: ${correct}`;
            setTimeout(() => {
                currentIdx++;
                if (currentIdx >= sentences.length) {
                    endGame();
                } else {
                    setupSentence();
                }
            }, 1500);
        } else {
            document.getElementById('game-status').textContent = `Wrong! ${MAX_WRONG - wrongCount} tries left`;
            pool.forEach((t) => { t.used = false; });
            blankFills = {};
            renderSlots();
            renderPool();
            updateButtons();
        }
    }
}

function startTimer() {
    // Idempotent: only ever one continuous countdown for the whole round.
    if (timerInterval) return;

    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimer();
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            const correct = tokens.map((t) => t.text).join(' ');
            document.getElementById('game-status').textContent = `Time's up! The sentence was: ${correct}`;
            play('wrong');
            setTimeout(() => {
                endGame();
            }, 1500);
        }
    }, 1000);
}

function updateTimer() {
    const total = getTimeLimit();
    const min = Math.floor(Math.max(0, timeLeft) / 60);
    const sec = Math.max(0, timeLeft) % 60;
    document.getElementById('timer-display').textContent = `${min}:${sec.toString().padStart(2, '0')}`;

    const pct = (Math.max(0, timeLeft) / total) * 100;
    const bar = document.getElementById('timer-bar');
    bar.style.width = `${pct}%`;
    bar.className = 'game-timer-bar h-full rounded-full';
    if (pct < 20) bar.classList.add('danger');
    else if (pct < 50) bar.classList.add('warning');
}

function endGame() {
    finished = true;
    if (timerInterval) clearInterval(timerInterval);

    document.getElementById('timer-bar').style.width = '0%';
    document.getElementById('btn-clear').classList.add('hidden');
    document.getElementById('btn-submit').classList.add('hidden');
    document.getElementById('game-status').classList.add('hidden');

    const resultScreen = document.getElementById('result-screen');
    resultScreen.classList.remove('hidden');
    resultScreen.classList.add('flex');

    const pct = sentences.length ? Math.round((correctCount / sentences.length) * 100) : 0;
    const blankPct = totalBlanks ? Math.round((correctBlanks / totalBlanks) * 100) : 0;

    if (pct >= 50) {
        window.Celebrate?.('win', resultScreen);
    }

    // Unified 0-100 arcade score (same computeFinalScore as match/wordsearch) —
    // mirrors the live score display and is exactly what gets submitted.
    const finalScore = currentArcadeScore();

    let title, subtitle;
    if (pct >= 80) {
        title = 'Excellent!';
        subtitle = 'You completed every sentence like a pro.';
    } else if (pct >= 50) {
        title = 'Good Job!';
        subtitle = 'Solid work completing those sentences.';
    } else {
        title = 'Keep Practicing!';
        subtitle = 'Every attempt helps you improve.';
    }

    document.getElementById('result-title').textContent = title;
    document.getElementById('result-subtitle').textContent = subtitle;
    document.getElementById('result-stats').innerHTML = `
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-[#2D231E]">${correctCount}/${sentences.length}</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Sentences</div>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-amber-600">${finalScore}</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Score</div>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-emerald-600">${maxCombo}x</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Best Combo</div>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-[#2D231E]">${blankPct}%</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Accuracy</div>
        </div>
    `;

    play(pct >= 50 ? 'fanfare' : 'wrong');
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
            game_type: 'sentence',
            score: finalScore,
            round_id: roundId,
            words_count: sentences.length,
            correct_count: correctCount,
            difficulty,
            source,
        }),
    })
        .then((r) => r.json())
        .then((data) => {
            const xp = data.xp || 0;
            if (xp > 0) {
                const title = document.getElementById('result-title');
                scorePopup(title, `+${xp} points`, '#10B981');
            }
        })
        .catch(() => {});
}

function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

/* ====================== hoverable dictionary popup ====================== */

function wireDictionary() {
    const slots = document.getElementById('answer-slots');
    const popupBox = document.getElementById('word-popup');
    if (!slots || !popupBox) return;

    const wireHold = (container) => {
        container.addEventListener('pointerdown', dictPressStart);
        for (const type of ['pointerup', 'pointercancel', 'pointerleave']) {
            container.addEventListener(type, dictCancelPress);
        }
        container.addEventListener('pointermove', (e) => {
            if (dictPressTimer === null) return;
            if (e.movementX !== undefined && Math.abs(e.movementX) + Math.abs(e.movementY) > 6) {
                dictCancelPress();
            }
        });
        container.addEventListener('contextmenu', (e) => e.preventDefault());
    };

    wireHold(slots);

    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        slots.addEventListener('mouseover', dictHoverStart);
        slots.addEventListener('mouseout', dictHoverEnd);
    }

    popupBox.querySelector('[data-slot="say"]').addEventListener('click', dictSayCurrent);
    popupBox.querySelector('[data-slot="save"]').addEventListener('click', () => {
        const saveButton = popupBox.querySelector('[data-slot="save"]');
        if (saveButton instanceof HTMLButtonElement && !saveButton.disabled) {
            dictSaveWord(saveButton);
        }
    });

    document.addEventListener('click', (e) => {
        if (popupBox.hidden) return;
        if (!e.target.closest('#word-popup') && !e.target.closest('.sentence-fixed')) {
            dictHidePopup();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') dictHidePopup();
    });
    popupBox.addEventListener('mouseenter', () => clearTimeout(dictHoverCloseTimer));
    popupBox.addEventListener('mouseleave', () => {
        if (dictOpenedBy !== 'hover') return;
        clearTimeout(dictHoverCloseTimer);
        dictHoverCloseTimer = setTimeout(() => dictHidePopup(), 180);
    });
}

function dictWordFromEl(el) {
    if (!el) return null;
    const t = (el.textContent || '').trim();
    return t || null;
}

function dictPressStart(event) {
    const el = event.target.closest?.('.sentence-fixed');
    if (!el || event.button !== 0) return;
    clearTimeout(dictHoverCloseTimer);
    dictPressTimer = setTimeout(() => {
        dictPressTimer = null;
        dictSuppressClick = true;
        dictOpenPopup(dictWordFromEl(el), 'press', el);
    }, DICT_PRESS_MS);
}

function dictCancelPress() {
    clearTimeout(dictPressTimer);
    dictPressTimer = null;
}

function dictHoverStart(event) {
    const el = event.target.closest?.('.sentence-fixed');
    if (!el) return;
    clearTimeout(dictHoverTimer);
    dictHoverTimer = setTimeout(() => dictOpenPopup(dictWordFromEl(el), 'hover', el), DICT_HOVER_MS);
}

function dictHoverEnd(event) {
    if (!event.target.closest?.('.sentence-fixed')) return;
    clearTimeout(dictHoverTimer);
    // Closing is owned by the popup's own `mouseleave` (see wireDictionary):
    // leaving the word is not enough to hide, because the anchored popup can
    // open directly under the cursor and immediately trigger a word `mouseout`.
    // The popup stays open while the cursor is over it (or the word), and closes
    // only once the cursor leaves the popup.
}

function dictPopup() {
    return document.getElementById('word-popup');
}

function dictOpenPopup(word, source, anchorEl) {
    if (!word) return;
    dictPopupWord = word;
    dictOpenedBy = source ?? null;
    dictAnchorRect = anchorEl?.getBoundingClientRect() ?? null;
    dictShowShell(word);
    dictPosition();

    const cached = dictMemo.get(word);
    if (cached) {
        dictRenderLookup(cached);
        return;
    }

    if (Date.now() < dictCooldownUntil) {
        dictRenderMiss('The dictionary is catching its breath. Try again in a moment.');
        return;
    }

    dictRenderMiss('Looking it up\u2026');

    fetch(`/words/${encodeURIComponent(word)}`, { headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) throw new Error(String(response.status));
            return response.json();
        })
        .then((data) => {
            dictConsecutiveFails = 0;
            dictRemember(word, data);
            dictRenderLookup(data);
        })
        .catch(() => {
            dictConsecutiveFails++;
            if (dictConsecutiveFails >= DICT_BREAKER_FAILS) {
                dictCooldownUntil = Date.now() + DICT_BREAKER_COOLDOWN_MS;
            }
            dictRenderMiss("We couldn't reach the dictionary. Check the connection.");
        });
}

function dictRemember(key, data) {
    dictMemo.delete(key);
    dictMemo.set(key, data);
    while (dictMemo.size > 40) {
        dictMemo.delete(dictMemo.keys().next().value);
    }
    sessionStorage.setItem(DICT_CACHE_KEY, JSON.stringify([...dictMemo]));
}

function dictShowShell(word) {
    const box = dictPopup();
    if (!box) return;
    box.hidden = false;
    box.querySelector('[data-slot="lemma"]').textContent = word;
    box.querySelector('[data-slot="tapped"]').textContent = '';
    box.querySelector('[data-slot="badge"]').hidden = true;
    box.querySelector('[data-slot="senses"]').innerHTML = '';
    const saveButton = box.querySelector('[data-slot="save"]');
    saveButton.disabled = false;
    saveButton.textContent = dictSavedWords.has(word) ? 'SAVED \u2713' : 'SAVE TO MY WORDS';
}

// Anchor the dictionary popup just above the hovered/pressed word so it never
// covers the sentence being read. Falls below the word if there is no room on
// top, and clamps to the viewport on all sides.
function dictPosition() {
    const box = dictPopup();
    if (!box || !dictAnchorRect) return;
    if (box.hidden) box.hidden = false;

    const pad = 8;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const boxW = box.offsetWidth;
    const boxH = box.offsetHeight;

    let left = dictAnchorRect.left + dictAnchorRect.width / 2 - boxW / 2;
    left = Math.max(pad, Math.min(left, vw - boxW - pad));

    const aboveTop = dictAnchorRect.top - boxH - pad;
    let top;
    if (aboveTop >= pad) {
        top = aboveTop;
    } else {
        top = Math.min(dictAnchorRect.bottom + pad, vh - boxH - pad);
        top = Math.max(pad, top);
    }

    box.style.left = `${left}px`;
    box.style.top = `${top}px`;
}

function dictHidePopup() {
    const box = dictPopup();
    if (box) {
        box.hidden = true;
        box.style.left = '';
        box.style.top = '';
    }
    dictPopupWord = null;
    dictPopupData = null;
    dictOpenedBy = null;
    clearTimeout(dictHoverTimer);
    clearTimeout(dictHoverCloseTimer);
}

function dictRenderLookup(data) {
    const box = dictPopup();
    if (!box || box.hidden) return;

    dictPopupData = data;

    box.querySelector('[data-slot="lemma"]').textContent = data.resolved_to ?? data.word;
    box.querySelector('[data-slot="tapped"]').textContent =
        data.form_label ? `you tapped: ${data.word} \u00b7 ${data.form_label}` : (data.word !== data.resolved_to ? `you tapped: ${data.word}` : '');

    const badge = box.querySelector('[data-slot="badge"]');
    if (data.freq_badge) {
        badge.hidden = false;
        badge.textContent = data.freq_badge;
        badge.className =
            'shrink-0 font-pixel text-[8px] font-bold px-1.5 py-0.5 rounded-md border-2 border-[#2D231E] ' +
            ({ COMMON: 'bg-emerald-100', UNCOMMON: 'bg-amber-100', RARE: 'bg-rose-100' }[data.freq_badge] ?? 'bg-white');
    }

    const sensesBox = box.querySelector('[data-slot="senses"]');
    sensesBox.innerHTML = '';

    if (!data.found) {
        dictRenderMiss(data.looks_like_name ? 'Looks like a name in this story.' : 'No definition found for this one.');
        dictSetSaveDisabled();
        return;
    }

    const POS_LABELS = { noun: 'noun', verb: 'verb', adj: 'adjective', adv: 'adverb', function: 'helper word' };

    for (const group of data.senses ?? []) {
        const heading = document.createElement('p');
        heading.className = 'font-pixel text-[8px] font-bold uppercase text-emerald-700';
        heading.textContent = POS_LABELS[group.pos] ?? group.pos;
        sensesBox.appendChild(heading);

        for (const sense of group.list ?? []) {
            const line = document.createElement('p');
            line.className = 'leading-snug';

            const meaning = document.createElement('span');
            meaning.textContent = sense.definition ?? '';
            line.appendChild(meaning);

            if (sense.example) {
                const example = document.createElement('span');
                example.className = 'block text-xs italic text-[#2D231E]/60 mt-0.5';
                example.textContent = `\u201c${sense.example}\u201d`;
                line.appendChild(example);
            }

            sensesBox.appendChild(line);
        }
    }

    dictSyncSaveButton(data);
    dictPosition();
}

function dictRenderMiss(message) {
    const box = dictPopup();
    if (!box) return;
    const sensesBox = box.querySelector('[data-slot="senses"]');
    sensesBox.innerHTML = '';
    const line = document.createElement('p');
    line.className = 'text-sm italic text-[#2D231E]/70';
    line.textContent = message;
    sensesBox.appendChild(line);
    dictPosition();
}

function dictSetSaveDisabled() {
    const saveButton = dictPopup()?.querySelector('[data-slot="save"]');
    if (saveButton instanceof HTMLButtonElement) {
        saveButton.disabled = true;
        saveButton.textContent = '\u00b7';
    }
}

function dictSyncSaveButton(data) {
    const saveButton = dictPopup()?.querySelector('[data-slot="save"]');
    if (!(saveButton instanceof HTMLButtonElement)) return;
    const lemma = data.resolved_to ?? data.word;
    saveButton.disabled = false;
    saveButton.textContent = dictSavedWords.has(lemma) || dictSavedWords.has(data.word) ? 'SAVED \u2713' : 'SAVE TO MY WORDS';
}

async function dictSaveWord(button) {
    const word = dictPopupData?.resolved_to ?? dictPopupWord;
    if (!word) return;

    button.textContent = 'SAVED \u2713';

    try {
        const response = await fetch('/words', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ word }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.saved !== true) {
            button.textContent = 'SAVE TO MY WORDS';
            dictRenderMiss(data.message ?? 'Saving failed. Try again.');
            return;
        }

        dictSavedWords.add(data.word);
        button.textContent = 'SAVED \u2713';
    } catch {
        button.textContent = 'SAVE TO MY WORDS';
        dictRenderMiss('Saving failed. Check the connection.');
    }
}

function dictSayCurrent() {
    const word = dictPopupData?.resolved_to ?? dictPopupWord;
    if (!word || !('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(word);
    utterance.rate = 0.9;
    utterance.lang = 'en-US';
    window.speechSynthesis.speak(utterance);
}
