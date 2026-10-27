/**
 * Word Search — 10x10 grid, 8-direction placement, touch + mouse selection.
 * Words provided by server (10 items with word + definition).
 * Base time 120s * difficulty multiplier. Score: 10pts per word + time bonus.
 */

import { play } from '../game/sfx.js';
import { shake, flash, scorePopup } from '../game/juice.js';
import { burst, pips, clearPips, computeFinalScore } from '../game/arcade.js';
import { openModal, closeModal } from '../components/modal.js';
import { initMascot, mascotReact } from '../game/mascot.js';

const GRID_SIZE = 10;
const POINTS_PER_WORD = 10;
let baseTime = 120;

const DIRECTIONS = [
    [0, 1],   // right
    [0, -1],  // left
    [1, 0],   // down
    [-1, 0],  // up
    [1, 1],   // down-right
    [-1, -1], // up-left
    [1, -1],  // down-left
    [-1, 1],  // up-right
];

let root, csrf, words, difficulty, source, timeMultiplier;
let gridLetters, foundWords, wordPositions;
let score, timeLeft, timerInterval, started, finished, roundId;
let hintCount;
let keysLeft = null;
let combo, maxCombo, wrongAttempts;

// Selection state
let selecting = false;
let startCell = null;
let lockDir = null;
let selectedCells = [];
let cellElements = [];

export default function init() {
    root = document.querySelector('[data-game-words]');
    if (!root) return;

    csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    words = JSON.parse(root.dataset.gameWords || '[]');
    timeMultiplier = parseFloat(root.dataset.timeMultiplier) || 1;
    baseTime = parseInt(root.dataset.baseTime) || 120;
    difficulty = root.dataset.difficulty || 'medium';
    source = root.dataset.source || 'open';

    if (words.length < 1) return;

    roundId = crypto.randomUUID?.() || Date.now().toString(36) + Math.random().toString(36).slice(2);
    setupGame();
    wireEvents();

    // First-time gate: pop the How to Play modal before play begins. The
    // timer starts on the first swipe, so no countdown needs pausing here.
    try {
        if (localStorage.getItem('alice-wordsearch-help-seen') !== '1') {
            const helpModal = document.getElementById('game-help-modal');
            if (helpModal) {
                openModal(helpModal);
                helpModal.querySelector('[data-modal-close]')?.addEventListener('click', () => {
                    try { localStorage.setItem('alice-wordsearch-help-seen', '1'); } catch { /* storage off */ }
                }, { once: true });
            }
        }
    } catch { /* storage off */ }

    initMascot();
}

function setupGame() {
    gridLetters = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(''));
    foundWords = [];
    wordPositions = new Map();
    score = 0;
    timeLeft = Math.round(baseTime * timeMultiplier);
    started = false;
    finished = false;
    hintCount = 0;
    combo = 0;
    maxCombo = 0;
    wrongAttempts = 0;
    clearPips(document.getElementById('combo-pips'));
    selecting = false;
    startCell = null;
    lockDir = null;
    selectedCells = [];

    placeWords();
    fillEmpty();
    renderGrid();
    renderWordList();
    updateScore();
    updateTimer();
    updateProgress();

    document.getElementById('result-screen').classList.add('hidden');
    document.getElementById('game-status').classList.remove('hidden');
    document.getElementById('game-status').textContent = 'Swipe across letters to find words';

    if (timerInterval) clearInterval(timerInterval);
    document.getElementById('timer-bar').style.width = '100%';
    document.getElementById('timer-bar').className = 'game-timer-bar h-full rounded-full';
}

function placeWords() {
    const sorted = [...words].sort((a, b) => b.word.length - a.word.length);

    for (let i = 0; i < sorted.length; i++) {
        const w = sorted[i];
        const letters = w.word.toUpperCase().split('');

        // Prefer a distinct direction per word (rotating through all 8) so the
        // board spreads across horizontal/vertical/diagonals instead of
        // occasionally clustering into a single direction.
        const preferred = DIRECTIONS[i % DIRECTIONS.length];

        if (tryPlace(letters, w, [preferred])) {
            continue;
        }

        // Fall back to any direction so nothing valid is ever dropped.
        tryPlace(letters, w, DIRECTIONS);
    }
}

function tryPlace(letters, wordObj, directions) {
    const positions = [];
    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            for (let d = 0; d < directions.length; d++) {
                positions.push([r, c, directions[d]]);
            }
        }
    }
    shuffle(positions);

    for (const [r, c, dir] of positions) {
        const [dr, dc] = dir;
        if (canPlace(letters, r, c, dr, dc)) {
            placeWord(letters, wordObj, r, c, dr, dc);
            return true;
        }
    }
    return false;
}

function canPlace(letters, row, col, dr, dc) {
    for (let i = 0; i < letters.length; i++) {
        const r = row + dr * i;
        const c = col + dc * i;
        if (r < 0 || r >= GRID_SIZE || c < 0 || c >= GRID_SIZE) return false;
        if (gridLetters[r][c] !== '' && gridLetters[r][c] !== letters[i]) return false;
    }
    return true;
}

function placeWord(letters, wordObj, row, col, dr, dc) {
    const positions = [];
    for (let i = 0; i < letters.length; i++) {
        const r = row + dr * i;
        const c = col + dc * i;
        gridLetters[r][c] = letters[i];
        positions.push([r, c]);
    }
    wordPositions.set(wordObj.word.toUpperCase(), positions);
}

function fillEmpty() {
    const allLetters = [];
    for (const w of words) {
        for (const ch of w.word.toUpperCase()) {
            allLetters.push(ch);
        }
    }

    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            if (gridLetters[r][c] === '') {
                if (allLetters.length > 0 && Math.random() < 0.6) {
                    gridLetters[r][c] = allLetters[Math.floor(Math.random() * allLetters.length)];
                } else {
                    gridLetters[r][c] = String.fromCharCode(65 + Math.floor(Math.random() * 26));
                }
            }
        }
    }
}

function renderGrid() {
    const container = document.getElementById('ws-grid');
    container.innerHTML = '';
    cellElements = [];

    for (let r = 0; r < GRID_SIZE; r++) {
        for (let c = 0; c < GRID_SIZE; c++) {
            const el = document.createElement('div');
            el.className = 'ws-cell';
            el.textContent = gridLetters[r][c];
            el.dataset.row = r;
            el.dataset.col = c;
            container.appendChild(el);
            cellElements.push(el);
        }
    }
}

function renderWordList() {
    const container = document.getElementById('word-list');
    container.innerHTML = '';

    for (const w of words) {
const badge = document.createElement('span');
badge.className = 'inline-flex items-center justify-center font-pixel text-[10px] px-3 py-1 border-2 border-[#2D231E]/25 rounded-lg text-[#2D231E]';
        badge.dataset.word = w.word.toUpperCase();
        badge.textContent = w.word.toUpperCase();
        container.appendChild(badge);
    }
}

function wireEvents() {
    const gridEl = document.getElementById('ws-grid');

    // Touch
    gridEl.addEventListener('touchstart', onPointerDown, { passive: false });
    gridEl.addEventListener('touchmove', onPointerMove, { passive: false });
    gridEl.addEventListener('touchend', onPointerUp, { passive: false });

    // Mouse
    gridEl.addEventListener('mousedown', onPointerDown);
    gridEl.addEventListener('mousemove', onPointerMove);
    gridEl.addEventListener('mouseup', onPointerUp);
    gridEl.addEventListener('mouseleave', onPointerUp);

    document.addEventListener('mouseup', onPointerUp);

    document.getElementById('btn-replay')?.addEventListener('click', () => {
        // Reload so the server picks a fresh, non-repeating word batch.
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
    if (finished) return;
    syncHintModal();
    openModal(document.getElementById('hint-modal'));
}

async function revealHint() {
    if (finished) return;

    const unfound = words.filter((w) => !foundWords.includes(w));
    if (unfound.length === 0) return;

    try {
        const res = await fetch('/game/hint', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ game_type: 'wordsearch' }),
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

    const target = unfound[0];
    const positions = wordPositions.get(target.word.toUpperCase());
    if (!positions) return;

    hintCount++;
    play('pop');

    // Highlight the word's cells briefly
    positions.forEach(([r, c]) => {
        const el = cellElements[r * GRID_SIZE + c];
        el.classList.add('missed');
    });
    document.getElementById('game-status').textContent =
        `Hint: "${target.word.toUpperCase()}" is highlighted in red`;

    setTimeout(() => {
        positions.forEach(([r, c]) => {
            const el = cellElements[r * GRID_SIZE + c];
            if (!el.classList.contains('found')) {
                el.classList.remove('missed');
            }
        });
    }, 1200);
}

function getCellFromEvent(e) {
    let target;
    if (e.touches && e.touches.length > 0) {
        const touch = e.touches[0];
        target = document.elementFromPoint(touch.clientX, touch.clientY);
    } else if (e.changedTouches && e.changedTouches.length > 0) {
        const touch = e.changedTouches[0];
        target = document.elementFromPoint(touch.clientX, touch.clientY);
    } else {
        target = e.target;
    }

    if (!target || !target.classList.contains('ws-cell')) return null;
    return { row: parseInt(target.dataset.row), col: parseInt(target.dataset.col), el: target };
}

function onPointerDown(e) {
    if (finished) return;
    if (e.cancelable) e.preventDefault();

    if (!started) {
        started = true;
        startTimer();
    }

    const cell = getCellFromEvent(e);
    if (!cell) return;

    selecting = true;
    startCell = cell;
    lockDir = null;
    selectedCells = [cell];
    updateSelectionVisual();
}

function onPointerMove(e) {
    if (!selecting || finished) return;
    if (e.cancelable) e.preventDefault();

    const cell = getCellFromEvent(e);
    if (!cell) return;

    const last = selectedCells[selectedCells.length - 1];
    if (cell.row === last.row && cell.col === last.col) return;

    if (selectedCells.length === 1) {
        const dr = cell.row - startCell.row;
        const dc = cell.col - startCell.col;
        lockDir = normalizeDirection(dr, dc);
        if (!lockDir) return;
        selectedCells.push(cell);
    } else {
        const [ldr, ldc] = lockDir;
        const dr = cell.row - startCell.row;
        const dc = cell.col - startCell.col;

        // Must be same axis and same sign from start
        if (dr === 0 && dc === 0) return;

        let aligned = false;
        if (ldr === 0 && ldc !== 0) {
            aligned = dr === 0 && Math.sign(dc) === Math.sign(ldc);
        } else if (ldc === 0 && ldr !== 0) {
            aligned = dc === 0 && Math.sign(dr) === Math.sign(ldr);
        } else {
            aligned = Math.abs(dr) === Math.abs(dc)
                && Math.sign(dr) === Math.sign(ldr)
                && Math.sign(dc) === Math.sign(ldc);
        }
        if (!aligned) return;

        const dist = Math.max(Math.abs(dr), Math.abs(dc));
        const newCells = [];
        for (let i = 0; i <= dist; i++) {
            const r = startCell.row + ldr * i;
            const c = startCell.col + ldc * i;
            if (r < 0 || r >= GRID_SIZE || c < 0 || c >= GRID_SIZE) break;
            const el = cellElements[r * GRID_SIZE + c];
            newCells.push({ row: r, col: c, el });
        }

        if (newCells.length > 0) {
            selectedCells = newCells;
            updateSelectionVisual();
        }
    }
}

function onPointerUp(e) {
    if (!selecting || finished) return;
    if (e.cancelable) e.preventDefault();
    selecting = false;

    if (selectedCells.length >= 2) {
        checkSelection();
    }

    clearSelectionVisual();
    startCell = null;
    lockDir = null;
    selectedCells = [];
}

function normalizeDirection(dr, dc) {
    if (dr === 0 && dc === 0) return null;
    const sr = dr === 0 ? 0 : Math.sign(dr);
    const sc = dc === 0 ? 0 : Math.sign(dc);
    return [sr, sc];
}

function updateSelectionVisual() {
    document.querySelectorAll('.ws-cell.selected').forEach(c => c.classList.remove('selected'));

    for (const cell of selectedCells) {
        cell.el.classList.add('selected');
    }

    drawSelectionLine();
}

function drawSelectionLine() {
    const svg = document.getElementById('ws-line-svg');
    svg.innerHTML = '';
    if (selectedCells.length < 2) return;

    const gridEl = document.getElementById('ws-grid');
    const gridRect = gridEl.getBoundingClientRect();
    const first = selectedCells[0];
    const last = selectedCells[selectedCells.length - 1];

    const firstRect = first.el.getBoundingClientRect();
    const lastRect = last.el.getBoundingClientRect();

    const x1 = firstRect.left + firstRect.width / 2 - gridRect.left;
    const y1 = firstRect.top + firstRect.height / 2 - gridRect.top;
    const x2 = lastRect.left + lastRect.width / 2 - gridRect.left;
    const y2 = lastRect.top + lastRect.height / 2 - gridRect.top;

    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', x1);
    line.setAttribute('y1', y1);
    line.setAttribute('x2', x2);
    line.setAttribute('y2', y2);
    line.setAttribute('stroke', '#F59E0B');
    line.setAttribute('stroke-width', '3');
    line.setAttribute('stroke-linecap', 'round');
    line.setAttribute('opacity', '0.6');
    svg.appendChild(line);
}

function clearSelectionVisual() {
    document.querySelectorAll('.ws-cell.selected').forEach(c => c.classList.remove('selected'));
    const svg = document.getElementById('ws-line-svg');
    svg.innerHTML = '';
}

function checkSelection() {
    const selected = selectedCells.map(c => gridLetters[c.row][c.col]).join('');
    const reversed = selected.split('').reverse().join('');

    let matched = null;
    for (const w of words) {
        const upper = w.word.toUpperCase();
        if (foundWords.includes(w)) continue;
        if (selected === upper || reversed === upper) {
            matched = w;
            break;
        }
    }

    if (matched) {
        foundWords.push(matched);
        combo++;
        maxCombo = Math.max(maxCombo, combo);
        score += POINTS_PER_WORD;
        updateScore();
        updateProgress();
        pips(document.getElementById('combo-pips'), combo, 5);

        // Mark cells green
        for (const cell of selectedCells) {
            cell.el.classList.add('found');
            cell.el.classList.remove('selected', 'missed');
        }

        // Update word badge
        const badge = document.querySelector(`#word-list [data-word="${matched.word.toUpperCase()}"]`);
        if (badge) {
            badge.classList.add('bg-emerald-100', 'line-through', 'text-emerald-600');
            badge.classList.remove('text-[#2D231E]');
        }

        // Score popup
        const lastCell = selectedCells[selectedCells.length - 1];
        scorePopup(lastCell.el, `+${POINTS_PER_WORD}`, '#10B981');
        play('match');
        burst(lastCell.el, 'star', 6);
        burst(document.getElementById('ws-grid'), 'coin', 3);
        mascotReact('attack');

        flash(document.getElementById('ws-grid'), 'emerald');

        document.getElementById('game-status').textContent =
            `Found "${matched.word.toUpperCase()}"! ${words.length - foundWords.length} left`;

        if (foundWords.length === words.length) {
            endGame(true);
        }
    } else {
        combo = 0;
        wrongAttempts++;
        clearPips(document.getElementById('combo-pips'));
        play('wrong');
        mascotReact('walk');
        for (const cell of selectedCells) {
            if (!cell.el.classList.contains('found')) {
                shake(cell.el);
            }
        }
        burst(selectedCells[0]?.el || document.getElementById('ws-grid'), 'cross', 3);
    }
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
    const min = Math.floor(Math.max(0, timeLeft) / 60);
    const sec = Math.max(0, timeLeft) % 60;
    document.getElementById('timer-display').textContent = `${min}:${sec.toString().padStart(2, '0')}`;

    const pct = (Math.max(0, timeLeft) / (baseTime * timeMultiplier)) * 100;
    const bar = document.getElementById('timer-bar');
    bar.style.width = `${pct}%`;
    bar.className = 'game-timer-bar h-full rounded-full';
    if (pct < 20) bar.classList.add('danger');
    else if (pct < 50) bar.classList.add('warning');
}

function updateScore() {
    document.getElementById('score-display').textContent = score;
}

function updateProgress() {
    document.getElementById('word-progress').textContent = `${foundWords.length} / ${words.length}`;
}

function endGame(won) {
    finished = true;
    if (timerInterval) clearInterval(timerInterval);

    const finalScore = computeFinalScore({
        accuracy: foundWords.length / words.length,
        timeFraction: Math.max(0, timeLeft) / (baseTime * timeMultiplier),
        maxCombo,
        wrongFraction: Math.min(1, wrongAttempts / words.length),
    });

    document.getElementById('ws-grid').style.pointerEvents = 'none';
    document.getElementById('game-status').classList.add('hidden');

    const pct = Math.round((foundWords.length / words.length) * 100);
    let title;
    if (pct === 100) { title = 'Perfect!'; }
    else if (pct >= 60) { title = 'Great Job!'; }
    else { title = 'Keep Trying!'; }

    document.getElementById('result-title').textContent = title;
    document.getElementById('result-subtitle').textContent = won
        ? `You found all ${words.length} words!`
        : `You found ${foundWords.length} of ${words.length} words`;

    document.getElementById('result-stats').innerHTML = `
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-[#2D231E]">${foundWords.length}/${words.length}</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Words Found</div>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-[#2D231E]">${Math.max(0, timeLeft)}s</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Time Left</div>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-amber-600">${score}</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Points</div>
        </div>
        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3 text-center">
            <div class="font-pixel text-lg font-bold text-emerald-600">${maxCombo}x</div>
            <div class="text-[10px] text-[#2D231E] font-semibold mt-1">Best Combo</div>
        </div>
    `;

    // Reveal on-timeout missed cells in the grid
    if (!won) {
        revealMissedWords();
    }

    // List any unfound words on the summary screen
    const missedWords = words.filter((w) => !foundWords.includes(w));
    const missedEl = document.getElementById('result-missed');
    if (missedEl) {
        if (missedWords.length > 0) {
            document.getElementById('result-missed-list').innerHTML = missedWords
                .map((w) => `<span class="px-2 py-1 bg-rose-100 border border-rose-300 text-rose-700 rounded-lg text-xs font-bold uppercase">${w.word}</span>`)
                .join('');
            missedEl.classList.remove('hidden');
        } else {
            missedEl.classList.add('hidden');
        }
    }

    const resultScreen = document.getElementById('result-screen');
    resultScreen.classList.remove('hidden');
    resultScreen.classList.add('flex');
    play(won ? 'fanfare' : 'wrong');
    if (won) {
        window.Celebrate?.('win', resultScreen);
    }
    submitRound(finalScore);
}

function revealMissedWords() {
    for (const w of words) {
        if (foundWords.includes(w)) continue;
        const positions = wordPositions.get(w.word.toUpperCase());
        if (!positions) continue;
        positions.forEach(([r, c]) => {
            cellElements[r * GRID_SIZE + c].classList.add('missed');
        });
    }
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
            game_type: 'wordsearch',
            score: finalScore,
            round_id: roundId,
            words_count: words.length,
            correct_count: foundWords.length,
            difficulty,
            source,
        }),
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.xp > 0) {
                scorePopup(document.getElementById('result-title'), `+${data.xp} points`, '#10B981');
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

