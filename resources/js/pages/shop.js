import { play } from '../game/sfx.js';

/**
 * Shop wardrobe: a port of the Field Journal SKINS tab (game/journal.js).
 * Same markup classes, same grid, same preview dialog, same button states
 * and the same two-step unlock. journal.js is intentionally untouched, so
 * the battle page carries no change from this module.
 */

const SECTION_ON = 'font-pixel text-[10px] font-bold uppercase px-4 min-h-[44px] border-2 border-[#2D231E] rounded-xl cursor-pointer transition-all bg-amber-400 text-[#2D231E] translate-x-[2px] translate-y-[2px]';
const SECTION_OFF = 'font-pixel text-[10px] font-bold uppercase px-4 min-h-[44px] border-2 border-[#2D231E] rounded-xl cursor-pointer transition-all bg-white text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FAF6EE] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none';

const LOCK_SVG = '<svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">'
    + '<rect x="2.2" y="4.6" width="5.6" height="4.2" fill="currentColor" stroke="none"/>'
    + '<path d="M3.3 4.6V3.3a1.7 1.7 0 0 1 3.4 0v1.3"/></svg>';

/* ------------------------------ sprite player --------------------------- */

function sprite(el, sheets) {
    let anim = 'idle';
    let frame = 0;
    let lastTick = 0;
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
        frame = (frame + 1) % sheet.frames;
        paint();
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
    };
}

/* --------------------------------- utils -------------------------------- */

function fitSize(frame, maxW, maxH) {
    const w = (maxH * frame.w) / frame.h;
    if (w <= maxW) return { w: Math.round(w), h: maxH };
    return { w: maxW, h: Math.round((maxW * frame.h) / frame.w) };
}

export default function initShop() {
    const root = document.querySelector('[data-shop]');

    if (!root) {
        return;
    }

    const skins = JSON.parse(root.dataset.skins || '{}');
    const skinsBaseUrl = root.dataset.skinsBaseUrl || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const grid = root.querySelector('[data-skins-grid]');
    const dialog = root.querySelector('#skin-preview');
    const stage = root.querySelector('[data-preview-stage]');
    const nameEl = root.querySelector('[data-preview-name]');
    const subEl = root.querySelector('[data-preview-sub]');
    const actionBtn = root.querySelector('[data-preview-action]');
    const sectionButtons = Array.from(root.querySelectorAll('[data-skins-section]'));

    let section = 'alice';
    let previewType = null;
    let previewKey = null;
    let previewPlayer = null;
    let confirmArmed = false;
    let confirmTimer = 0;
    let buying = false;

    const assetUrl = (path) => {
        const base = skinsBaseUrl.replace(/skins\/?$/, '');
        return base + String(path || '').replace(/^\//, '');
    };

    // The key glyph lives in the preview chip; reused for price chips.
    const keyGlyph = () => root.querySelector('.journal-keys-glyph')?.innerHTML ?? '';

    const updateKeysChips = () => {
        const n = Number(skins.state.keys ?? 0).toLocaleString('en-US');
        // Preview counter plus every header/dropdown total on the page.
        root.querySelectorAll('[data-keys-count]').forEach((el) => {
            el.textContent = n;
        });
        document.querySelectorAll('[data-keys-total]').forEach((el) => {
            el.textContent = n;
        });
    };

    const post = async (url) => {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '{}',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok && data.ok === undefined) {
                data.ok = false;
            }
            return data;
        } catch {
            return { ok: false };
        }
    };

    const applyState = (data) => {
        if (typeof data.keys === 'number') skins.state.keys = data.keys;
        if (Array.isArray(data.owned)) skins.state.owned = data.owned;
        if (data.equipped) {
            ['alice', 'boss', 'background'].forEach((slot) => {
                if (data.equipped[slot]) skins.state.equipped[slot] = data.equipped[slot];
            });
        }
        updateKeysChips();
    };

    /* ------------------------------- grid -------------------------------- */

    const renderGrid = () => {
        grid.innerHTML = '';
        const owned = new Set(skins.state.owned);
        const equipped = skins.state.equipped[section];

        Object.entries(skins.catalog[section]).forEach(([key, skin]) => {
            const isOwned = owned.has(key);
            const isEquipped = key === equipped;

            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'skin-card'
                + (isEquipped ? ' is-equipped' : '')
                + (isOwned ? '' : ' is-locked');
            card.dataset.skinKey = key;
            card.dataset.skinType = section;

            const art = document.createElement('span');
            art.className = section === 'background' ? 'skin-card-art skin-card-art--bg' : 'skin-card-art';
            if (skin.thumb) art.style.backgroundImage = `url('${skin.thumb}')`;
            card.appendChild(art);

            const name = document.createElement('span');
            name.className = 'skin-card-name font-pixel';
            name.textContent = skin.name.toUpperCase();
            card.appendChild(name);

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

            grid.appendChild(card);
        });
    };

    const setSection = (next) => {
        section = next;
        sectionButtons.forEach((btn) => {
            const on = btn.dataset.skinsSection === next;
            btn.className = on ? SECTION_ON : SECTION_OFF;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        renderGrid();
    };

    /* ------------------------------ preview ------------------------------ */

    const disarmConfirm = () => {
        confirmArmed = false;
        if (confirmTimer) {
            clearTimeout(confirmTimer);
            confirmTimer = 0;
        }
    };

    // Single source of truth for the preview button and sub line.
    const renderPreviewAction = (skin) => {
        disarmConfirm();
        const owned = skins.state.owned.includes(previewKey);
        const equipped = skins.state.equipped[previewType] === previewKey;

        actionBtn.classList.remove('is-equip', 'is-equipped', 'is-locked', 'is-confirm', 'is-shake');
        actionBtn.disabled = false;

        subEl.textContent = previewType === 'alice' ? 'ALICE SKIN'
            : previewType === 'boss' ? 'BOSS SKIN' : 'BATTLE BACKGROUND';

        if (equipped) {
            actionBtn.textContent = 'EQUIPPED';
            actionBtn.classList.add('is-equipped');
            actionBtn.disabled = true;
        } else if (owned) {
            actionBtn.textContent = 'EQUIP';
            actionBtn.classList.add('is-equip');
        } else if (skin.price != null) {
            actionBtn.innerHTML = `UNLOCK ${keyGlyph()}<span>${skin.price}</span>`;
        } else {
            actionBtn.textContent = 'LOCKED';
            actionBtn.classList.add('is-locked');
            actionBtn.disabled = true;
        }
    };

    const shakeAction = (message) => {
        actionBtn.classList.remove('is-shake');
        void actionBtn.offsetWidth;
        actionBtn.classList.add('is-shake');

        if (message) {
            const previous = actionBtn.innerHTML;
            actionBtn.textContent = message;
            setTimeout(() => {
                actionBtn.innerHTML = previous;
                actionBtn.classList.remove('is-shake');
            }, 1200);
        }
    };

    const closePreview = () => {
        if (dialog.hidden) return;
        dialog.hidden = true;
        if (previewPlayer) {
            previewPlayer.stop();
            previewPlayer = null;
        }
        previewType = null;
        previewKey = null;
        disarmConfirm();
        play('close');
    };

    const openPreview = (type, key) => {
        const skin = skins.catalog?.[type]?.[key];

        if (!skin) return;

        previewType = type;
        previewKey = key;

        if (previewPlayer) {
            previewPlayer.stop();
            previewPlayer = null;
        }

        stage.innerHTML = '';

        if (type === 'background') {
            stage.style.backgroundImage = `url('${assetUrl(skin.file)}')`;
        } else {
            // Equipped scene stays behind the sprite, like the battle page.
            const bg = skins.catalog?.background?.[skins.state.equipped?.background];

            if (bg) {
                stage.style.backgroundImage = `url('${assetUrl(bg.file)}')`;
            }

            const art = document.createElement('span');
            art.className = 'skin-preview-art';
            const size = fitSize(skin.frame, 220, 200);
            art.style.width = `${size.w}px`;
            art.style.height = `${size.h}px`;

            if (skin.pivot) {
                art.style.setProperty('--sox', `${-skin.pivot.x * (size.w / skin.frame.w)}px`);
                art.style.setProperty('--soy', `${skin.pivot.y * (size.h / skin.frame.h)}px`);
            }

            stage.appendChild(art);
            previewPlayer = sprite(art, { idle: skin.anims.idle });
            previewPlayer.start();
        }

        nameEl.textContent = skin.name.toUpperCase();
        renderPreviewAction(skin);
        dialog.hidden = false;
        play('open');
    };

    // Step 1 arms the unlock ("are you sure?"); step 2 posts the purchase.
    // Equip posts straight away. One request in flight at a time.
    const previewAction = async () => {
        if (!previewKey || buying) return;

        const skin = skins.catalog[previewType][previewKey];
        const owned = skins.state.owned.includes(previewKey);

        if (!owned && skin.price != null && !confirmArmed) {
            confirmArmed = true;
            actionBtn.classList.add('is-confirm');
            actionBtn.innerHTML = `YES, UNLOCK ${keyGlyph()}<span>${skin.price}</span>`;
            subEl.textContent = 'ARE YOU SURE YOU WANT TO UNLOCK THIS?';
            confirmTimer = setTimeout(() => renderPreviewAction(skin), 4000);
            play('select');

            return;
        }

        buying = true;
        actionBtn.disabled = true;
        const data = await post(`${skinsBaseUrl}/${previewKey}/${owned ? 'equip' : 'buy'}`);
        buying = false;
        actionBtn.disabled = false;

        if (!data.ok) {
            renderPreviewAction(skin);

            if (data.reason === 'keys') {
                shakeAction('NOT ENOUGH KEYS');
            }

            return;
        }

        disarmConfirm();
        applyState(data);
        renderGrid();
        renderPreviewAction(skin);
        play('select');
    };

    /* ------------------------------ wiring ------------------------------- */

    root.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) return;

        const sectionBtn = target.closest('[data-skins-section]');

        if (sectionBtn) {
            setSection(sectionBtn.dataset.skinsSection);

            return;
        }

        if (target.closest('[data-preview-close]') || target === dialog) {
            closePreview();

            return;
        }

        if (target.closest('[data-preview-action]') && !actionBtn.disabled) {
            previewAction();

            return;
        }

        const card = target.closest('[data-skin-key]');

        if (card) {
            openPreview(card.dataset.skinType, card.dataset.skinKey);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.hidden) {
            closePreview();
        }
    });

    setSection(section);
    updateKeysChips();
}
