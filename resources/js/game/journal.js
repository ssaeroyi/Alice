/**
 * Field Journal boss battle client (guidelines/JOURNAL_BATTLES.md, items 43-46).
 *
 * Mission stories only (the root only exists when missions_enabled). The
 * journal popup shows the arena + ACTIONS list; GO switches to game mode on
 * the reading page. Find actions use the story text itself as the surface;
 * fill/choice actions get a parchment panel. Completion is graded server-side
 * (answers never ship to the client before the round). Timeout/quit = one
 * heart; at zero hearts the server regenerates the whole battle.
 */

import { play } from './sfx.js';

/* Equipped-skin sheets: hydrated from data-skins (config/skins.php) at init
   and hot-swapped on equip; null until initJournal runs. */
let BOSS_SHEETS = null;
let ALICE_SHEETS = null;

let HEARTS_URL = '/assets/ui/hearts.png';
let HEARTS_TOTAL = 3;
let HP_TOTAL = 3;

let root = null;
let csrf = '';
let urls = { state: '', start: '', complete: '', fail: '', reset: '' };
let battle = null;
let action = null;

/* Wardrobe: { catalog: {alice, boss, defaults}, state: {keys, owned, equipped} } */
let skins = null;
let skinsBaseUrl = '';
let skinTab = 'battle';
let skinsSection = 'alice';
const preview = { type: null, key: null, player: null };
// Buy confirmation: first click arms ("are you sure"), second click posts.
let confirmArmed = false;
let confirmTimer = 0;
let buying = false;

let boss = null;
let alice = null;

let timerInt = 0;
let deadline = 0;
let failing = false;

const findHits = new Set();
let findRule = null;
let findNeed = 0;

const els = {};

/* ------------------------------ sprite player --------------------------- */

function sprite(el, sheets) {
    let anim = 'idle';
    let frame = 0;
    let lastTick = 0;
    let holdUntil = 0;
    let running = false;

    function paint() {
        const sheet = sheets[anim];
        if (!sheet) return;
        el.style.backgroundImage = `url(${sheet.src})`;
        el.style.backgroundSize = `${sheet.frames * 100}% 100%`;
        const pct = sheet.frames > 1 ? (frame / (sheet.frames - 1)) * 100 : 0;
        el.style.backgroundPosition = `${pct}% bottom`;
    }

    function tick(now) {
        if (!running) return;
        requestAnimationFrame(tick);
        const sheet = sheets[anim];
        const ms = 1000 / sheet.fps;
        if (now - lastTick < ms) return;
        lastTick = now;

        if (sheet.loop) {
            frame = (frame + 1) % sheet.frames;
            paint();
            return;
        }

        if (frame < sheet.frames - 1) {
            frame += 1;
            paint();
            return;
        }

        if (sheet.hold) return; // death: stay on the last frame
        if (now >= holdUntil) {
            anim = 'idle';
            frame = 0;
            paint();
        }
    }

    return {
        start() {
            if (running) return;
            running = true;
            paint();
            lastTick = performance.now();
            requestAnimationFrame(tick);
        },
        stop() {
            running = false;
        },
        play(name) {
            if (!sheets[name] || sheets[name].loop) return;
            anim = name;
            frame = 0;
            holdUntil = performance.now() + (sheets[name].frames / sheets[name].fps) * 1000 + 160;
            paint();
        },
        reset() {
            anim = 'idle';
            frame = 0;
            paint();
        },
    };
}

/* --------------------------------- utils -------------------------------- */

function normalize(word) {
    return String(word ?? '')
        .replace(/[\u{2019}\u{2018}]/gu, "'")
        .trim()
        .toLowerCase();
}

/** Mirror of FindGenerator::wordMatches (PHP). */
function wordMatches(rule, word) {
    if (!rule || !word || !/\p{L}/u.test(word)) return false;
    if (rule.type === 'suffix') return word.endsWith(rule.value) && word.length > rule.value.length;
    if (rule.type === 'doubles') return /(.)\1/u.test(word);
    if (rule.type === 'short') return word.length <= Number(rule.value);
    if (rule.type === 'contains') return word.includes(rule.value);
    if (rule.type === 'length') return word.length === Number(rule.value);
    if (rule.type === 'prefix') return word.length >= 2 && word.startsWith(String(rule.value).toLowerCase());
    if (rule.type === 'set') return Array.isArray(rule.tokens) && rule.tokens.includes(word);
    return false;
}

function fmt(ms) {
    const s = Math.max(0, Math.ceil(ms / 1000));
    const m = Math.floor(s / 60);
    return `${m}:${String(s % 60).padStart(2, '0')}`;
}

async function post(url, body) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok && data.ok === undefined) {
            data.ok = false;
            data.error = data.message || data.error || 'Something went wrong.';
        }
        return data;
    } catch {
        return { ok: false, error: 'Connection hiccup. Try again.' };
    }
}

/* ------------------------------- rendering ------------------------------ */

function renderHearts(container, hearts, max = null, burst = false) {
    if (!container) return;
    const total = max ?? battle?.max_hearts ?? HEARTS_TOTAL;
    container.innerHTML = '';
    for (let i = 0; i < total; i++) {
        const span = document.createElement('span');
        const lost = i >= hearts;
        span.className = `journal-heart ${lost ? 'is-empty' : 'is-full'}${burst && lost && i === hearts ? ' arena-heart-burst' : ''}`;
        // Inline so the sprite always loads, independent of the CSS pipeline.
        span.style.backgroundImage = `url(${HEARTS_URL})`;
        container.appendChild(span);
    }
}

function renderList(wonOverride) {
    const list = els.actionList;
    if (!list || !battle) return;
    list.innerHTML = '';

    const anyActive = battle.actions.some((a) => a.status === 'active');
    const won = wonOverride ?? battle.status === 'won';

    battle.actions.forEach((a, i) => {
        const li = document.createElement('li');
        li.className = 'journal-action-row';
        if (a.status === 'done') li.classList.add('is-done');
        if (a.status === 'locked') li.classList.add('is-locked');

        // Find moves show their target as pips; fill/choice moves have none.
        const pub = a.public_payload ?? {};
        const need = Number(pub.need) || 0;
        const got = a.id === action?.id ? findHits.size : 0;
        const pips = need > 0
            ? `<span class="journal-row-pips">${
                Array.from({ length: need }, (_, j) => `<span class="journal-row-pip${j < got ? ' is-on' : ''}"></span>`).join('')
            }<span class="journal-row-progress font-pixel">${got}/${need}</span></span>`
            : '';

        const info = document.createElement('div');
        info.className = 'journal-action-info';
        info.innerHTML = `
            <span class="journal-move-no font-pixel">MOVE ${String(i + 1).padStart(2, '0')}</span>
            <span class="journal-action-flavor">${a.flavor}</span>
            <span class="journal-action-prompt">${a.prompt}</span>
            ${pips}
        `;
        li.appendChild(info);

        if (a.status === 'done') {
            const mark = document.createElement('span');
            mark.className = 'journal-done-mark';
            mark.textContent = 'OK';
            li.appendChild(mark);
        } else if (a.status === 'active') {
            // Resuming an already-started action: server start is idempotent,
            // so it just reopens game mode with the original deadline. The
            // row offers RESUME only (timer and quit live on the top strip).
            li.classList.add('is-active');
            const go = document.createElement('button');
            go.type = 'button';
            go.className = 'journal-go journal-go--active';
            go.dataset.go = String(a.id);
            go.textContent = 'RESUME';
            li.appendChild(go);
        } else if (!won) {
            const go = document.createElement('button');
            go.type = 'button';
            go.className = 'journal-go';
            go.dataset.go = String(a.id);
            go.textContent = 'GO';
            go.disabled = anyActive;
            li.appendChild(go);
        }

        list.appendChild(li);
    });

    if (els.winBlock) els.winBlock.hidden = !won;
}

/** `fx` overrides paint-only values ({hearts, bossHp, won}) so the journal
 *  can show pre-hit state while the punch is still pending; rows/status
 *  always come from the real battle. */
function render(burst = false, fx = {}) {
    if (!battle) return;
    const hearts = fx.hearts ?? battle.hearts;
    const bossHp = fx.bossHp ?? battle.boss_hp;
    const won = fx.won ?? battle.status === 'won';
    renderHearts(els.journalHearts, hearts);
    renderHearts(els.arenaHearts, hearts);
    renderHearts(els.gmHearts, hearts);
    renderHearts(els.arenaBossHearts, bossHp, battle.max_hp ?? HP_TOTAL, burst);
    renderList(won);
    renderMissionsState();
    startRowWatch();

    const openBtn = document.querySelector('[data-journal-open]');
    if (openBtn) {
        openBtn.classList.toggle('journal-pulse', !won);
    }
}

/* ------------------------------ journal open ---------------------------- */

function openJournal(silent = false) {
    els.backdrop.hidden = false;
    els.panel.hidden = false;
    reconcileMission();
    if (!silent) play('open');
}

function closeJournal() {
    els.backdrop.hidden = true;
    els.panel.hidden = true;
    closeRestartConfirm();
    play('close');
}

/** Reopen on the BATTLE tab so a hit/loss lands where the result is. */
function reopenBattle() {
    openJournal(true);
    setTab('battle');
}

/* ------------------------------ game mode ------------------------------- */

/** The MISSIONS pane mirrors the live move: sheet while answering, a resume
 *  hint when a row is active outside game mode, empty state otherwise. When
 *  nothing is running the sheet is wiped too, so a settled move can never
 *  linger on screen. */
function renderMissionsState() {
    if (!els.missionsPane) return;
    const answering = !!action;
    const resume = !answering && !!battle?.actions?.some((a) => a.status === 'active');
    els.missionsSheet.hidden = !answering;
    if (!answering) els.missionsSheet.innerHTML = '';
    els.missionsEmpty.hidden = answering || resume;
    els.missionsResume.hidden = !resume;
}

/** Server-truth check for the move this client thinks it is playing. If the
 *  row was settled server-side (restart, a second GO, a timer race), drop the
 *  sheet and refresh instead of showing a task that no longer exists. Runs
 *  only mid-move, so idle opens cost nothing. */
async function reconcileMission() {
    const id = action?.id;
    if (!id) return;
    const fresh = await fetchState();
    if (!fresh || action?.id !== id) return;
    const row = fresh.actions.find((a) => a.id === id);
    if (row?.status === 'active') return;
    battle = fresh;
    exitGameMode();
    render();
}

function enterGameMode(started) {
    action = started;
    failing = false;
    clearInterval(rowWatchInt);

    document.body.dataset.gameMode = 'on';
    els.missionsSheet.innerHTML = '';
    setTab('missions');

    const pub = action.public_payload ?? {};
    els.gmTask.textContent = action.kind === 'find'
        ? `FOUND WORDS 0/${pub.need ?? ''}`.trim()
        : action.flavor;
    renderHearts(els.gmHearts, battle.hearts);

    if (action.kind === 'find') {
        findRule = pub.rule ?? null;
        findNeed = pub.need ?? 5;
        findHits.clear();
        clearFindMarks();
        renderFindCard(pub);
    } else {
        renderFillPanelDispatch(pub);
    }
    renderMissionsState();

    startTimer(action.deadline_at);
    play('select');
}

function exitGameMode() {
    delete document.body.dataset.gameMode;
    els.missionsSheet.hidden = true;
    els.missionsSheet.innerHTML = '';
    clearFindMarks();
    clearInterval(timerInt);
    timerInt = 0;
    action = null;
    failing = false;
    renderMissionsState();
}

function startTimer(deadlineIso) {
    clearInterval(timerInt);
    deadline = new Date(deadlineIso).getTime();
    tickTimer();
    timerInt = setInterval(tickTimer, 250);
}

function tickTimer() {
    if (!action) return;
    const left = deadline - Date.now();
    els.gmTimer.textContent = fmt(left);
    els.gmTimer.classList.toggle('is-low', left < 10000);

    if (left <= 0 && !failing) {
        failing = true;
        clearInterval(timerInt);
        failAction();
    }
}

/* ------------------------------ fill / choice --------------------------- */

function renderFillPanel(pub) {
    const items = pub.items ?? [];
    const isLadder = action.key === 'ladder';

    const panel = els.missionsSheet;
    panel.hidden = false;
    panel.innerHTML = `
        <p class="gm-flavor">${action.flavor}</p>
        <p class="gm-prompt">${action.prompt}</p>
        <p class="gm-hint">One word per blank. Spelling counts.</p>
        <div class="gm-rows"></div>
        <button type="button" class="gm-submit">CHECK</button>
        <p class="gm-note" data-gm-note></p>
    `;

    const rows = panel.querySelector('.gm-rows');
    items.forEach((item, i) => {
        const row = document.createElement('div');
        row.className = 'gm-row';

        const no = document.createElement('span');
        no.className = 'gm-row-no';
        no.textContent = String(i + 1);
        row.appendChild(no);

        const makeInput = () => {
            const input = document.createElement('input');
            input.className = 'gm-input';
            input.dataset.gmInput = String(i);
            input.maxLength = 16;
            input.autocomplete = 'off';
            input.autocapitalize = 'off';
            input.spellcheck = false;
            input.setAttribute('aria-label', `Answer ${i + 1}`);
            return input;
        };

        // Sentblank rows carry a "___" blank: the input lives INSIDE one text
        // span, so the sentence wraps around it like a word on any width
        // (flex siblings forced ugly line breaks on mobile).
        // Ladder rows keep the original [shown] [hint] [input] layout.
        if (!isLadder && item.shown.includes('___')) {
            const [before, ...rest] = item.shown.split('___');
            row.classList.add('gm-row--sentence');
            const sentence = document.createElement('span');
            sentence.className = 'gm-row-shown gm-row-sentence';
            sentence.append(
                document.createTextNode(before),
                makeInput(),
                document.createTextNode(rest.join('___')),
            );
            row.appendChild(sentence);
        } else {
            // textContent, not innerHTML: shown text comes from story content.
            const shown = document.createElement('span');
            shown.className = 'gm-row-shown';
            shown.textContent = `${item.shown}${isLadder ? ' + ?' : ''}`;
            row.appendChild(shown);
            if (isLadder && item.hint) {
                const hint = document.createElement('span');
                hint.className = 'gm-row-hint';
                hint.textContent = `(${item.hint})`;
                row.appendChild(hint);
            }
            row.appendChild(makeInput());
        }
        rows.appendChild(row);
    });

    panel.querySelector('.gm-submit').addEventListener('click', () => {
        const answers = items.map((_, i) => normalize(panel.querySelector(`[data-gm-input="${i}"]`)?.value));
        if (answers.some((a) => a === '')) {
            shakePanel();
            note('Fill every blank first.');
            return;
        }
        submitAnswers({ items: answers });
    });

    panel.querySelector('[data-gm-input="0"]')?.focus();
}

/** Choice moves paginate one item per page (placement-pager style): BACK and
 *  dots always, NEXT unlocks once the page is answered, CHECK on the last. */
function renderChoicePanel(pub) {
    const items = pub.items ?? [];
    const panel = els.missionsSheet;
    panel.hidden = false;
    panel.innerHTML = `
        <p class="gm-flavor">${action.flavor}</p>
        <p class="gm-prompt">${action.prompt}</p>
        <div class="gm-rows"></div>
        <div class="missions-pager">
            <button type="button" class="missions-pager-btn font-pixel" data-pager-back>BACK</button>
            <span class="missions-pager-dots" data-pager-dots></span>
            <button type="button" class="missions-pager-btn missions-pager-btn--next font-pixel" data-pager-next>NEXT</button>
        </div>
        <p class="gm-note" data-gm-note></p>
    `;

    const rows = panel.querySelector('.gm-rows');
    const blocks = items.map((item, i) => {
        const block = document.createElement('div');
        // textContent, not innerHTML: q can be a story-vocab definition.
        const q = document.createElement('p');
        q.className = 'gm-choice-q';
        q.textContent = `${i + 1}. ${item.q}`;
        block.appendChild(q);
        const list = document.createElement('div');
        list.className = 'gm-choice-list';
        (item.choices ?? []).forEach((choice) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gm-choice-btn';
            btn.textContent = choice;
            btn.addEventListener('click', () => {
                list.querySelectorAll('.gm-choice-btn').forEach((b) => b.classList.remove('is-picked'));
                btn.classList.add('is-picked');
                play('tick');
                sync();
            });
            list.appendChild(btn);
        });
        block.appendChild(list);
        block.dataset.choiceGroup = String(i);
        rows.appendChild(block);
        return block;
    });

    const back = panel.querySelector('[data-pager-back]');
    const next = panel.querySelector('[data-pager-next]');
    const dots = panel.querySelector('[data-pager-dots]');
    const last = Math.max(items.length - 1, 0);
    let page = 0;

    const answered = (i) => !!rows.querySelector(`[data-choice-group="${i}"] .gm-choice-btn.is-picked`);

    function sync() {
        blocks.forEach((b, i) => { b.hidden = i !== page; });
        back.disabled = page === 0;
        const isLast = page === last;
        next.textContent = isLast ? 'CHECK' : 'NEXT';
        next.classList.toggle('missions-pager-btn--check', isLast);
        next.disabled = !isLast && !answered(page);
        dots.innerHTML = items.map((_, i) => (
            `<span class="missions-pager-dot${i === page ? ' is-on' : (answered(i) ? ' is-done' : '')}"></span>`
        )).join('');
        panel.querySelector('[data-gm-note]').textContent = '';
    }

    back.addEventListener('click', () => {
        if (page === 0) return;
        page -= 1;
        sync();
        play('tick');
    });

    next.addEventListener('click', () => {
        if (page !== last) {
            page += 1;
            sync();
            play('tick');
            return;
        }
        const answers = items.map((_, i) => {
            const picked = rows.querySelector(`[data-choice-group="${i}"] .gm-choice-btn.is-picked`);
            return picked ? normalize(picked.textContent) : '';
        });
        if (answers.some((a) => a === '')) {
            shakePanel();
            note('Pick an answer for every riddle.');
            return;
        }
        submitAnswers({ items: answers });
    });

    sync();
}

/** Tiled arrange: tap shuffled letter tiles into each word's slots in order;
 *  tap a placed tile to return it. Every word shows its full slot row (empty
 *  boxes = letters still needed) plus a live WORD n / n-of-total header, so
 *  the word length itself is the hint. Tiles are built with textContent. */
function renderArrangePanel(pub) {
    const items = pub.items ?? [];
    const panel = els.missionsSheet;
    panel.hidden = false;
    panel.innerHTML = `
        <p class="gm-flavor">${action.flavor}</p>
        <p class="gm-prompt">${action.prompt}</p>
        <p class="gm-hint">Tap tiles into the boxes, left to right. Tap a placed tile to take it back.</p>
        <div class="gm-rows"></div>
        <button type="button" class="gm-submit">CHECK</button>
        <p class="gm-note" data-gm-note></p>
    `;

    const rows = panel.querySelector('.gm-rows');
    // placed[i] = tile indices for item i, in the order the student tapped them.
    const placed = items.map(() => []);

    items.forEach((item, i) => {
        const tiles = item.tiles ?? [];
        const row = document.createElement('div');
        row.className = 'gm-arrange-row';

        const head = document.createElement('div');
        head.className = 'gm-arrange-head';
        const no = document.createElement('span');
        no.className = 'gm-arrange-no';
        no.textContent = `WORD ${i + 1}`;
        const progress = document.createElement('span');
        progress.className = 'gm-arrange-progress';
        head.append(no, progress);

        const slots = document.createElement('div');
        slots.className = 'gm-arrange-slots';
        const trayWrap = document.createElement('div');
        trayWrap.className = 'gm-arrange-tray-wrap';
        const trayLabel = document.createElement('span');
        trayLabel.className = 'gm-arrange-tray-label';
        trayLabel.textContent = 'TILES';
        const tray = document.createElement('div');
        tray.className = 'gm-arrange-tray';
        trayWrap.append(trayLabel, tray);

        const paint = () => {
            progress.textContent = `${placed[i].length}/${tiles.length}`;
            slots.innerHTML = '';
            // Filled boxes come first (taps fill left to right), then dashed
            // empty boxes so the word's length is visible before any tap.
            for (let s = 0; s < tiles.length; s++) {
                const tileIndex = placed[i][s];
                if (tileIndex === undefined) {
                    const empty = document.createElement('span');
                    empty.className = 'gm-slot gm-slot--empty';
                    empty.setAttribute('aria-hidden', 'true');
                    slots.appendChild(empty);
                    continue;
                }
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'gm-tile gm-tile--slot';
                btn.textContent = tiles[tileIndex];
                btn.setAttribute('aria-label', `Remove letter from slot ${s + 1}`);
                btn.addEventListener('click', () => {
                    placed[i].splice(s, 1);
                    paint();
                    play('tick');
                });
                slots.appendChild(btn);
            }

            tray.querySelectorAll('.gm-tile').forEach((btn) => {
                const used = placed[i].includes(Number(btn.dataset.tile));
                btn.classList.toggle('is-used', used);
                btn.disabled = used;
            });
        };

        tiles.forEach((letter, t) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gm-tile';
            btn.dataset.tile = String(t);
            btn.textContent = letter;
            btn.addEventListener('click', () => {
                if (placed[i].includes(t)) return;
                placed[i].push(t);
                paint();
                play('tick');
            });
            tray.appendChild(btn);
        });

        row.append(head, slots, trayWrap);
        rows.appendChild(row);
        paint();
    });

    panel.querySelector('.gm-submit').addEventListener('click', () => {
        const complete = items.every((item, i) => placed[i].length === (item.tiles ?? []).length);
        if (!complete) {
            shakePanel();
            note('Use every letter in each word.');
            return;
        }
        const answers = items.map((item, i) => placed[i].map((t) => item.tiles[t]).join(''));
        submitAnswers({ items: answers });
    });
}

function renderFillPanelDispatch(pub) {
    if (action.kind === 'choice') {
        renderChoicePanel(pub);
    } else if (action.kind === 'arrange') {
        renderArrangePanel(pub);
    } else {
        renderFillPanel(pub);
    }
}

function shakePanel() {
    const panel = els.missionsSheet;
    panel.classList.remove('is-shake');
    void panel.offsetWidth;
    panel.classList.add('is-shake');
    setTimeout(() => panel.classList.remove('is-shake'), 260);
}

function note(text) {
    const n = els.missionsSheet.querySelector('[data-gm-note]');
    if (n) n.textContent = text;
}

/* --------------------------------- find --------------------------------- */

/** Find moves answer in the story text; the MISSIONS sheet only mirrors the
 *  rule and live count so a student can peek back without losing the move. */
function renderFindCard(pub) {
    const sheet = els.missionsSheet;
    sheet.hidden = false;
    sheet.innerHTML = `
        <p class="gm-flavor">${action.flavor}</p>
        <p class="gm-prompt">${action.prompt}</p>
        <p class="missions-find-progress font-pixel" data-missions-progress>0/${findNeed}</p>
        <p class="gm-note missions-hint">Tap the matching words in the story to strike.</p>
    `;
}

function clearFindMarks() {
    document.querySelectorAll('.tap-word.gm-hit, .tap-word.gm-miss').forEach((el) => {
        el.classList.remove('gm-hit', 'gm-miss');
    });
}

function onFindTap(el) {
    if (!action || action.kind !== 'find' || !findRule) return;

    const norm = normalize(el.dataset.w ?? el.textContent);
    if (!norm || findHits.has(norm)) return;

    if (wordMatches(findRule, norm)) {
        findHits.add(norm);
        el.classList.add('gm-hit');
        play('pop');
        els.gmTask.textContent = `FOUND WORDS ${findHits.size}/${findNeed}`;
        const counter = els.missionsSheet.querySelector('[data-missions-progress]');
        if (counter) counter.textContent = `${findHits.size}/${findNeed}`;
        if (findHits.size >= findNeed) {
            submitAnswers({ words: [...findHits].slice(0, 40) });
        }
    } else {
        el.classList.add('gm-miss');
        play('wrong');
        setTimeout(() => el.classList.remove('gm-miss'), 320);
    }
}

/* ------------------------------ server calls ---------------------------- */

async function startAction(id) {
    const res = await post(urls.start, { action_id: Number(id) });
    if (!res.ok) {
        play('wrong');
        const row = document.querySelector(`[data-go="${id}"]`)?.closest('.journal-action-row');
        if (row) {
            row.classList.add('is-shake');
            setTimeout(() => row.classList.remove('is-shake'), 300);
        }
        return;
    }
    // Mark the started row active locally so the journal grays the other
    // GOs and offers RESUME the moment it reopens. Render immediately: a
    // stale list would keep GOs clickable and let a second start reset the
    // row server-side while this sheet stays up.
    if (battle?.actions) {
        const row = battle.actions.find((a) => a.id === res.action.id);
        if (row) {
            row.status = res.action.status ?? 'active';
            row.deadline_at = res.action.deadline_at ?? null;
        }
        render();
    }
    // Find moves answer in the story, so the journal gets out of the way;
    // the other kinds keep it open on the MISSIONS answer sheet.
    enterGameMode(res.action);
    if (res.action.kind === 'find') closeJournal();
}

async function submitAnswers(answers) {
    if (!action || failing) return;
    const id = action.id;

    const res = await post(urls.complete, { action_id: id, answers });
    if (!res.ok) {
        shakePanel();
        note(res.error ?? 'Something went wrong.');
        play('wrong');
        return;
    }

    if (res.correct === false) {
        shakePanel();
        note('Not quite. Try again.');
        play('wrong');
        return;
    }

    const prevBoss = battle?.boss_hp;
    if (res.battle) battle = res.battle;

    // Straight back to the BATTLE tab showing PRE-hit state — the boss heart
    // (and the win banner) land with the punch a beat later.
    play('go');
    exitGameMode();
    render(false, { bossHp: prevBoss, won: false });
    reopenBattle();
    setTimeout(() => playDamage(res.event), 1000);
}

// One beat of stillness after the journal snaps back, THEN the boss punch
// lands — the same rhythm Alice's attack gets on the other side. The heart
// drops on this same tick so the crack plays on a real loss, not a preview.
function scheduleBossPunch(delay = 1000) {
    setTimeout(() => {
        render();
        play('journalLose');
        boss?.play('attack');
        alice?.play('hurt');
        damageFloat(els.arenaAlice);
        const hearts = els.arenaHearts?.children;
        if (hearts && hearts[battle.hearts]) {
            hearts[battle.hearts].classList.add('is-crack');
        }
    }, delay);
}

async function failAction() {
    if (!action) return;
    const id = action.id;
    const prevHearts = battle?.hearts;

    const res = await post(urls.fail, { action_id: id });
    if (res.battle) battle = res.battle;
    exitGameMode();

    if (res.event === 'noop') {
        render();
        reopenBattle();
        return;
    }

    if (res.event === 'regenerated') {
        defeatScene(prevHearts);
        return;
    }

    render(false, { hearts: prevHearts });
    reopenBattle();
    scheduleBossPunch();
}

/* --------------------------- passive row watchdog ------------------------ */
// An action can be active while game mode is closed (quit the story, closed
// the journal). If it expires with nobody tapping, pull fresh state so the
// server settles it (heart down) instead of leaving the journal wedged.

let rowWatchInt = 0;

function startRowWatch() {
    clearInterval(rowWatchInt);
    const active = battle?.actions?.find((a) => a.status === 'active');
    if (!active?.deadline_at || document.body.dataset.gameMode === 'on') return;

    rowWatchInt = setInterval(async () => {
        const left = new Date(active.deadline_at).getTime() - Date.now();
        if (left > 0) return;

        clearInterval(rowWatchInt);
        const prevHearts = battle?.hearts;
        const fresh = await fetchState();
        if (fresh) {
            // Hearts only ever go down mid-battle — a higher count means the
            // server regenerated (last heart expired), so play the death beat.
            if (prevHearts != null && fresh.hearts > prevHearts) {
                battle = fresh;
                defeatScene(prevHearts);
                return;
            }
            battle = fresh;
            render(false, { hearts: prevHearts });
            reopenBattle();
            scheduleBossPunch();
        }
    }, 500);
}

async function fetchState() {
    try {
        const res = await fetch(urls.state, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        return res.ok ? await res.json() : null;
    } catch {
        return null;
    }
}

/* ------------------------------- scenes --------------------------------- */

function playDamage(event) {
    render(event === 'damaged');
    reopenBattle();

    if (event === 'won') {
        winSequence();
        return;
    }

    // Pure sprite drama, no positional lunge: Alice attack frames +
    // Bruno hurt frames + flash + knockback + burst + shake + floating -1.
    play('journalHit');
    alice?.play('attack');
    boss?.play('hurt');
    els.bossEl?.classList.add('is-flash', 'is-hit');
    els.arenaScene?.classList.add('is-shake');
    hitStars();
    damageFloat();
    setTimeout(() => {
        els.bossEl?.classList.remove('is-flash', 'is-hit');
        els.arenaScene?.classList.remove('is-shake');
    }, 420);
}

function hitStars() {
    const scene = els.arenaScene;
    if (!scene) return;
    const rect = scene.getBoundingClientRect();
    const bossRect = els.bossEl?.getBoundingClientRect();
    const cx = bossRect ? bossRect.left - rect.left + bossRect.width * 0.55 : rect.width * 0.65;
    const cy = bossRect ? bossRect.top - rect.top + bossRect.height * 0.4 : rect.height * 0.4;
    const colors = ['#FDE68A', '#F59E0B', '#FFFFFF', '#FCA5A5'];
    for (let i = 0; i < 8; i++) {
        const star = document.createElement('span');
        star.className = 'hit-star';
        star.style.left = `${cx}px`;
        star.style.top = `${cy}px`;
        star.style.background = colors[i % colors.length];
        star.style.setProperty('--dx', `${(Math.random() - 0.5) * 90}px`);
        star.style.setProperty('--dy', `${(Math.random() - 0.7) * 80}px`);
        star.style.animationDelay = `${i * 18}ms`;
        scene.appendChild(star);
        setTimeout(() => star.remove(), 700);
    }
}

/** Floating "-1" anchored clear above whichever fighter just got hit. */
function damageFloat(target = els.bossEl) {
    const scene = els.arenaScene;
    if (!scene) return;
    const targetRect = target?.getBoundingClientRect();
    const rect = scene.getBoundingClientRect();
    const el = document.createElement('span');
    el.className = 'dmg-float';
    el.textContent = '-1';
    el.style.left = `${targetRect ? targetRect.left - rect.left + targetRect.width * 0.5 : rect.width * 0.6}px`;
    el.style.top = `${targetRect ? targetRect.top - rect.top - 26 : 0}px`;
    scene.appendChild(el);
    setTimeout(() => el.remove(), 1500);
}

function winSequence() {
    play('journalVictory');
    boss?.play('death');
    alice?.play('attack');
    els.arenaAlice?.classList.add('is-bounce');
    setTimeout(() => els.arenaAlice?.classList.remove('is-bounce'), 1100);

    confettiBurst();

    // Reader-page sync: the reader listens for this to reveal the bottom-bar
    // FINISH live (it only used to appear after a reload).
    document.dispatchEvent(new CustomEvent('alice:battle-won'));

    const openBtn = document.querySelector('[data-journal-open]');
    if (openBtn) openBtn.classList.remove('journal-pulse');
}

/* ------------------- header restart (confirm dialog) -------------------- */

function openRestartConfirm() {
    if (!els.restartConfirm || !els.restartConfirm.hidden) return;
    els.restartConfirm.hidden = false;
    play('open');
}

function closeRestartConfirm() {
    if (!els.restartConfirm || els.restartConfirm.hidden) return;
    els.restartConfirm.hidden = true;
    play('close');
}

/** Second step of the header button: confirmed — snap back to full. */
function confirmRestart() {
    closeRestartConfirm();
    // Never leave the story strip wedged on a dead action.
    if (document.body.dataset.gameMode === 'on') exitGameMode();
    // Instant snap back to full: no overlay (that screen is death-only).
    replayBattle();
}

async function replayBattle() {
    const res = await fetch(urls.reset, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).then((r) => r.json()).catch(() => null);

    if (!res || res.status !== 'active') {
        play('wrong');
        return;
    }

    battle = res;
    boss?.reset();
    alice?.reset();
    render();
    play('whoosh');
}

/* Pixel "RESTARTING" screen over the battle stage while the regenerated
   battle loads behind it — hides the sprite snap, sells the reset. */
function showRestartOverlay() {
    const arena = els.arenaPage;
    if (!arena || arena.querySelector('.arena-restart')) return;
    const letters = 'RESTARTING'.split('')
        .map((ch, i) => `<span style="animation-delay:${i * 60}ms">${ch}</span>`)
        .join('');
    const ov = document.createElement('div');
    ov.className = 'arena-restart';
    ov.innerHTML = `
        <div class="arena-restart-card">
            <span class="arena-restart-text font-pixel">${letters}</span>
            <span class="arena-restart-bar"><span></span></span>
        </div>`;
    arena.appendChild(ov);
}

function hideRestartOverlay() {
    els.arenaPage?.querySelector('.arena-restart')?.remove();
}

function defeatScene(prevHearts) {
    // The arena restart overlay lives on the BATTLE page, so land there.
    reopenBattle();
    render(false, { hearts: prevHearts });

    // Same beat as every other hit: journal first, punch a second later —
    // hearts drop to empty ON the punch, not before it.
    setTimeout(() => {
        render(false, { hearts: 0 });
        play('journalLose');
        boss?.play('attack');
        alice?.play('dead');
        damageFloat(els.arenaAlice);

        setTimeout(() => {
            boss?.reset();
            alice?.reset();
            render();
            showRestartOverlay();
            // Fire mid-loading so the clip lands before the bar ends and
            // carries through the reveal.
            setTimeout(() => play('journalRestart'), 1000);
            setTimeout(() => {
                hideRestartOverlay();
            }, 1800);
        }, 1400);
    }, 1000);
}

function confettiBurst() {
    const colors = ['#F59E0B', '#10B981', '#DC2626', '#3B82F6', '#A855F7'];
    const panel = els.panel;
    const rect = panel.getBoundingClientRect();
    for (let i = 0; i < 14; i++) {
        const bit = document.createElement('span');
        bit.className = 'journal-confetti';
        bit.style.left = `${rect.left + rect.width * (0.25 + Math.random() * 0.5)}px`;
        bit.style.top = `${rect.top + rect.height * 0.2}px`;
        bit.style.background = colors[i % colors.length];
        bit.style.animationDelay = `${Math.random() * 200}ms`;
        document.body.appendChild(bit);
        setTimeout(() => bit.remove(), 1400);
    }
}

/* --------------------------------- skins --------------------------------- */

/** Clamp a box into a max footprint (aspect preserved) and ship it as the
    slot's CSS vars. Also scales the skin's idle-art pivot (--sox/--soy) so
    the IDLE pose - not the union canvas - lands on the slot anchor. The
    boss slot is mirrored (scaleX(-1)), which flips the horizontal shift. */
function setSlotBox(el, skin, maxW, maxH, mirrored = false) {
    const box = skin.box;
    let w = box.w;
    let h = box.h;
    if (w > maxW || h > maxH) {
        const s = Math.min(maxW / w, maxH / h);
        w = Math.round(w * s);
        h = Math.round(h * s);
    }
    el.style.setProperty('--sw', w + 'px');
    el.style.setProperty('--sh', h + 'px');

    const p = skin.pivot;
    if (p && skin.frame?.w) {
        const sx = w / skin.frame.w;
        const sy = h / skin.frame.h;
        el.style.setProperty('--sox', `${(mirrored ? p.x : -p.x) * sx}px`);
        el.style.setProperty('--soy', `${p.y * sy}px`);
    } else {
        el.style.setProperty('--sox', '0px');
        el.style.setProperty('--soy', '0px');
    }
}

/** Swap an arena slot to a skin: sheets, box, sprite player, banner. */
function applySkinToSlot(slot, skin) {
    if (!skin) return;

    if (slot === 'boss') {
        BOSS_SHEETS = skin.anims;
        if (els.bossEl) {
            setSlotBox(els.bossEl, skin, 190, 205, true);
            if (boss) boss.stop();
            boss = sprite(els.bossEl, BOSS_SHEETS);
            boss.start();
        }
        if (els.winBanner) els.winBanner.textContent = `${skin.name.toUpperCase()} DEFEATED!`;
    } else {
        ALICE_SHEETS = skin.anims;
        if (els.arenaAlice) {
            setSlotBox(els.arenaAlice, skin, 125, 150);
            if (alice) alice.stop();
            alice = sprite(els.arenaAlice, ALICE_SHEETS);
            alice.start();
        }
    }
}

/** Merge a buy/equip JSON response into local state + chips. */
function applySkinsState(data) {
    if (!skins || !data) return;
    if (typeof data.keys === 'number') skins.state.keys = data.keys;
    if (Array.isArray(data.owned)) skins.state.owned = data.owned;
    if (data.equipped) {
        for (const slot of ['alice', 'boss', 'background']) {
            if (data.equipped[slot]) skins.state.equipped[slot] = data.equipped[slot];
        }
    }
    updateKeysChips();
}

/** Resolve an app-root config path against the install base (subdir safe). */
function assetUrl(path) {
    const base = (skinsBaseUrl || '').replace(/skins\/?$/, '');
    return base + String(path || '').replace(/^\//, '');
}

/** Hot-swap the arena battle background (equip flow, same as skins). */
function applyBackground(skin) {
    if (!skin?.file || !els.arenaPage) return;
    els.arenaPage.style.backgroundImage = `url('${assetUrl(skin.file)}')`;
}

function updateKeysChips() {
    const n = Number(skins?.state?.keys ?? 0);
    root.querySelectorAll('[data-keys-count]').forEach((el) => {
        el.textContent = n.toLocaleString();
    });
}

/** Fit a frame (native w/h) inside a box, keeping aspect. */
function fitSize(frame, maxW, maxH) {
    let w = (maxH * frame.w) / frame.h;
    if (w <= maxW) return { w: Math.round(w), h: maxH };
    return { w: maxW, h: Math.round((maxW * frame.h) / frame.w) };
}

/** The key glyph markup already rendered in the journal preview chip. */
function keyGlyph() {
    return root.querySelector('.journal-keys-glyph')?.innerHTML ?? '';
}

/* Tiny padlock for locked wardrobe cards (currentColor = ink-gray). */
const LOCK_SVG = '<svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">'
    + '<rect x="2.2" y="4.6" width="5.6" height="4.2" fill="currentColor" stroke="none"/>'
    + '<path d="M3.3 4.6V3.3a1.7 1.7 0 0 1 3.4 0v1.3"/></svg>';

function setTab(tab) {
    skinTab = tab;
    root.querySelectorAll('[data-journal-tab]').forEach((btn) => {
        const on = btn.dataset.journalTab === tab;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (els.spread) els.spread.hidden = tab !== 'battle';
    if (els.missionsPane) els.missionsPane.hidden = tab !== 'missions';
    if (els.skinsPane) els.skinsPane.hidden = tab !== 'skins';
    if (tab === 'missions') reconcileMission();
    // Restart is a battle control — hide it while browsing the wardrobe.
    if (els.restartBtn) {
        closeRestartConfirm();
        els.restartBtn.hidden = tab !== 'battle';
    }
    if (tab === 'skins') renderGrid();
}

function setSection(section) {
    skinsSection = section;
    root.querySelectorAll('[data-skins-section]').forEach((btn) => {
        const on = btn.dataset.skinsSection === section;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    renderGrid();
}

function renderGrid() {
    if (!skins || !els.skinsGrid) return;
    els.skinsGrid.innerHTML = '';

    const type = skinsSection;
    const owned = new Set(skins.state.owned);
    const equipped = skins.state.equipped[type];

    Object.entries(skins.catalog[type]).forEach(([key, skin]) => {
        const isOwned = owned.has(key);
        const isEquipped = key === equipped;

        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'skin-card'
            + (isEquipped ? ' is-equipped' : '')
            + (isOwned ? '' : ' is-locked');
        card.dataset.skinKey = key;
        card.dataset.skinType = type;

        // Static close-up (thumb.png): art fills the sheet, centered in a
        // fixed slot via CSS — every card gets identical treatment.
        const art = document.createElement('span');
        art.className = type === 'background' ? 'skin-card-art skin-card-art--bg' : 'skin-card-art';
        if (skin.thumb) art.style.backgroundImage = `url('${skin.thumb}')`;
        card.appendChild(art);

        const name = document.createElement('span');
        name.className = 'skin-card-name font-pixel';
        name.textContent = skin.name.toUpperCase();
        card.appendChild(name);

        // Locked: dimmed art (CSS) + tiny corner padlock (+ price if any).
        // Equipped: green border + green corner dot (CSS only, no label).
        if (!isOwned) {
            const lock = document.createElement('span');
            lock.className = 'skin-card-lock';
            lock.title = 'Locked';
            lock.innerHTML = LOCK_SVG;
            card.appendChild(lock);

            if (skin.price != null) {
                const chip = document.createElement('span');
                chip.className = 'skin-card-state font-pixel';
                chip.innerHTML = `${keyGlyph()}<span>${skin.price}</span>`;
                card.appendChild(chip);
            }
        }

        els.skinsGrid.appendChild(card);
    });
}

function openPreview(type, key) {
    const skin = skins?.catalog?.[type]?.[key];
    if (!skin || !els.preview) return;

    preview.type = type;
    preview.key = key;
    if (preview.player) {
        preview.player.stop();
        preview.player = null;
    }

    els.previewStage.innerHTML = '';
    if (type === 'background') {
        // The stage itself is the scene surface (cover) - show the candidate.
        els.previewStage.style.backgroundImage = `url('${assetUrl(skin.file)}')`;
    } else {
        // Restore the equipped scene backdrop behind the sprite.
        const bg = skins?.catalog?.background?.[skins.state.equipped?.background];
        if (bg) els.previewStage.style.backgroundImage = `url('${assetUrl(bg.file)}')`;
        const art = document.createElement('span');
        art.className = 'skin-preview-art';
        const size = fitSize(skin.frame, 220, 200);
        art.style.width = `${size.w}px`;
        art.style.height = `${size.h}px`;
        // Pivot: flex centers the union frame; shift so the IDLE art centers.
        // The preview is not mirrored, so the horizontal sign stays negative.
        if (skin.pivot) {
            art.style.setProperty('--sox', `${-skin.pivot.x * (size.w / skin.frame.w)}px`);
            art.style.setProperty('--soy', `${skin.pivot.y * (size.h / skin.frame.h)}px`);
        }
        els.previewStage.appendChild(art);

        preview.player = sprite(art, { idle: skin.anims.idle });
        preview.player.start();
    }

    els.previewName.textContent = skin.name.toUpperCase();
    renderPreviewAction(skin);
    els.preview.hidden = false;
    play('open');
}

function closePreview() {
    if (!els.preview || els.preview.hidden) return;
    els.preview.hidden = true;
    if (preview.player) {
        preview.player.stop();
        preview.player = null;
    }
    preview.type = null;
    preview.key = null;
    play('close');
}

function disarmConfirm() {
    confirmArmed = false;
    if (confirmTimer) {
        clearTimeout(confirmTimer);
        confirmTimer = 0;
    }
}

/** Single source of truth for the preview button + sub line. */
function renderPreviewAction(skin) {
    const btn = els.previewAction;
    if (!btn) return;
    disarmConfirm();
    const owned = skins.state.owned.includes(preview.key);
    const equipped = skins.state.equipped[preview.type] === preview.key;
    btn.classList.remove('is-equip', 'is-equipped', 'is-locked', 'is-confirm');
    btn.disabled = false;

    els.previewSub.textContent = preview.type === 'alice' ? 'ALICE SKIN'
        : preview.type === 'boss' ? 'BOSS SKIN' : 'BATTLE BACKGROUND';

    if (equipped) {
        btn.textContent = 'EQUIPPED';
        btn.classList.add('is-equipped');
        btn.disabled = true;
    } else if (owned) {
        btn.textContent = 'EQUIP';
        btn.classList.add('is-equip');
    } else if (skin.price != null) {
        btn.innerHTML = `UNLOCK ${keyGlyph()}<span>${skin.price}</span>`;
    } else {
        btn.textContent = 'LOCKED';
        btn.classList.add('is-locked');
        btn.disabled = true;
    }
}

function shakePreview(msg) {
    const btn = els.previewAction;
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

/** Preview button: equip when owned; locked = arm confirm, then buy (AJAX). */
async function previewAction() {
    if (!preview.key || buying) return;
    const skin = skins.catalog[preview.type][preview.key];
    const owned = skins.state.owned.includes(preview.key);

    // Step 1: arm the purchase ("are you sure?").
    if (!owned && skin.price != null && !confirmArmed) {
        confirmArmed = true;
        const btn = els.previewAction;
        btn.classList.add('is-confirm');
        btn.innerHTML = `YES, UNLOCK ${keyGlyph()}<span>${skin.price}</span>`;
        els.previewSub.textContent = 'ARE YOU SURE YOU WANT TO UNLOCK THIS?';
        confirmTimer = setTimeout(() => renderPreviewAction(skin), 4000);
        play('select');
        return;
    }

    // Step 2 (armed buy) / equip: single POST, disabled while in flight.
    buying = true;
    els.previewAction.disabled = true;
    const data = await post(`${skinsBaseUrl}/${preview.key}/${owned ? 'equip' : 'buy'}`, {});
    buying = false;
    if (els.previewAction) els.previewAction.disabled = false;

    if (!data.ok) {
        // Back to the base button first, then shake it (restores base HTML).
        renderPreviewAction(skin);
        if (data.reason === 'keys') shakePreview('NOT ENOUGH KEYS');
        return;
    }

    disarmConfirm();
    applySkinsState(data);

    if (owned) {
        if (preview.type === 'background') {
            applyBackground(skin);
        } else {
            applySkinToSlot(preview.type, skin);
            if (preview.type === 'alice') {
                document.dispatchEvent(new CustomEvent('alice:equip', { detail: { skin } }));
            }
        }
    }

    renderGrid();
    renderPreviewAction(skin);
    play('select');
}

/* --------------------------------- init --------------------------------- */

export function initJournal() {
    root = document.getElementById('journal-root');
    if (!root) return;

    csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    urls = {
        state: root.dataset.stateUrl,
        start: root.dataset.startUrl,
        complete: root.dataset.completeUrl,
        fail: root.dataset.failUrl,
        reset: root.dataset.resetUrl,
    };
    if (root.dataset.heartsSrc) HEARTS_URL = root.dataset.heartsSrc;

    try {
        skins = JSON.parse(root.dataset.skins ?? 'null');
    } catch {
        skins = null;
    }
    skinsBaseUrl = root.dataset.skinsBaseUrl ?? '';

    try {
        battle = JSON.parse(root.dataset.battle ?? 'null');
    } catch {
        battle = null;
    }
    if (!battle) return;

    els.backdrop = document.getElementById('journal-backdrop');
    els.panel = document.getElementById('journal-panel');
    // The strip lives inside #reader-topbar, outside #journal-root.
    els.gmTask = document.querySelector('[data-gm-task]');
    els.gmTimer = document.querySelector('[data-gm-timer]');
    els.gmHearts = document.querySelector('[data-gm-hearts]');
    els.gmQuit = document.querySelector('[data-gm-quit]');
    els.missionsPane = root.querySelector('[data-missions-pane]');
    els.missionsSheet = root.querySelector('[data-missions-sheet]');
    els.missionsEmpty = root.querySelector('[data-missions-empty]');
    els.missionsResume = root.querySelector('[data-missions-resume]');
    els.journalHearts = root.querySelector('[data-journal-hearts]');
    els.arenaHearts = root.querySelector('[data-arena-hearts]');
    els.arenaBossHearts = root.querySelector('[data-arena-boss-hearts]');
    els.arenaScene = root.querySelector('.arena-scene');
    els.actionList = root.querySelector('[data-action-list]');
    els.winBlock = root.querySelector('[data-journal-win]');
    els.winBanner = root.querySelector('[data-win-banner]');
    els.bossEl = root.querySelector('[data-arena-boss]');
    els.arenaAlice = root.querySelector('[data-arena-alice]');
    els.spread = root.querySelector('.journal-spread');
    els.restartBtn = root.querySelector('[data-journal-restart]');
    els.restartConfirm = root.querySelector('#restart-confirm');
    els.arenaPage = root.querySelector('.journal-arena');
    els.skinsPane = root.querySelector('[data-skins-pane]');
    els.skinsGrid = root.querySelector('[data-skins-grid]');
    els.preview = document.getElementById('skin-preview');
    els.previewStage = root.querySelector('[data-preview-stage]');
    els.previewName = root.querySelector('[data-preview-name]');
    els.previewSub = root.querySelector('[data-preview-sub]');
    els.previewAction = root.querySelector('[data-preview-action]');

    if (skins) {
        applySkinToSlot('boss', skins.catalog.boss[skins.state.equipped.boss]);
        applySkinToSlot('alice', skins.catalog.alice[skins.state.equipped.alice]);
        applyBackground(skins.catalog?.background?.[skins.state.equipped?.background]);
        updateKeysChips();
    } else {
        // Data-path failure: fall back to the shipped stock sheets.
        applySkinToSlot('boss', {
            name: root.dataset.bossName || 'Bruno',
            box: { w: 190, h: 190, feet: -51 },
            anims: {
                idle: { src: '/assets/boss/idle.png', frames: 3, fps: 4, loop: true },
                attack: { src: '/assets/boss/attack.png', frames: 4, fps: 12, loop: false },
                hurt: { src: '/assets/boss/hurt.png', frames: 2, fps: 4, loop: false },
                death: { src: '/assets/boss/death.png', frames: 6, fps: 10, loop: false, hold: true },
            },
        });
        applySkinToSlot('alice', {
            box: { w: 120, h: 120, feet: 0 },
            anims: {
                idle: { src: '/assets/mascot/idle.png', frames: 6, fps: 8, loop: true },
                attack: { src: '/assets/mascot/attack.png', frames: 4, fps: 12, loop: false },
                hurt: { src: '/assets/mascot/hurt.png', frames: 2, fps: 6, loop: false },
                dead: { src: '/assets/mascot/dead.png', frames: 2, fps: 4, loop: false, hold: true },
            },
        });
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-journal-open]')) {
            render();
            openJournal();
            return;
        }
        const tabBtn = event.target.closest('[data-journal-tab]');
        if (tabBtn) {
            setTab(tabBtn.dataset.journalTab);
            return;
        }
        const secBtn = event.target.closest('[data-skins-section]');
        if (secBtn) {
            setSection(secBtn.dataset.skinsSection);
            return;
        }
        if (
            event.target.closest('[data-preview-close]')
            || (els.preview && event.target === els.preview)
        ) {
            closePreview();
            return;
        }
        const skinAction = event.target.closest('[data-preview-action]');
        if (skinAction && !skinAction.disabled) {
            previewAction();
            return;
        }
        const skinCard = event.target.closest('[data-skin-key]');
        if (skinCard) {
            openPreview(skinCard.dataset.skinType, skinCard.dataset.skinKey);
            return;
        }
        if (event.target.closest('[data-journal-close]') || event.target === els.backdrop) {
            closeJournal();
            return;
        }
        if (event.target.closest('[data-journal-replay]')) {
            replayBattle();
            return;
        }
        if (event.target.closest('[data-journal-restart]')) {
            openRestartConfirm();
            return;
        }
        if (event.target.closest('[data-restart-confirm]')) {
            confirmRestart();
            return;
        }
        if (
            event.target.closest('[data-restart-cancel]')
            || (els.restartConfirm && event.target === els.restartConfirm)
        ) {
            closeRestartConfirm();
            return;
        }
        const go = event.target.closest('[data-go]');
        if (go && !go.disabled) {
            startAction(go.dataset.go);
            return;
        }
        if (event.target.closest('[data-gm-quit]')) {
            failAction();
        }
    });

    // Find mode: story words are the game surface.
    document.addEventListener('click', (event) => {
        if (document.body.dataset.gameMode !== 'on') return;
        if (!action || action.kind !== 'find') return;
        const wordEl = event.target.closest?.('.tap-word');
        if (!wordEl) return;
        event.preventDefault();
        event.stopPropagation();
        onFindTap(wordEl);
    }, true);

    // Capture phase: this runs BEFORE modal.js's bubble-phase Escape handler
    // on document, so the shared-modal guard still sees the modal open and
    // leaves the key press to modal.js (which then closes only that modal).
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        // A shared [data-modal] (the journal's own HOW TO FINISH? help) sits
        // above the panel: modal.js closes it, and the journal must stay put.
        if (document.querySelector('[data-modal]:not(.hidden)')) return;
        // Confirm dialog on top: dismiss it, keep the journal open.
        if (els.restartConfirm && !els.restartConfirm.hidden) {
            closeRestartConfirm();
            return;
        }
        if (els.preview && !els.preview.hidden) {
            closePreview();
            return;
        }
        if (els.panel && !els.panel.hidden) closeJournal();
    }, true);

    render();

    // Escape closes the journal; game mode is quit via the strip's X.
}
