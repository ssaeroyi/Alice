import { closeModal } from './modal.js';
import { showToast } from './toast.js';

/**
 * Journal tab (story editor): the 2-step add/edit modal for Field
 * Journal battle moves. Step 1 is the feasibility gallery (tiles come
 * from the server with live word counts; infeasible or already-added
 * rules ship disabled), step 2 is the config form with a live student
 * preview of the move row. The form is a plain POST/PUT to the same
 * routes the quiz builder style uses, so validation redirects reopen
 * this modal in the config step with old() values intact.
 */
export function initStoryJournal() {
    const panel = document.querySelector('[data-journal-panel]');
    const modal = document.getElementById('mission-action-modal');

    if (!modal) {
        return;
    }

    const gallery = modal.querySelector('[data-mission-step="gallery"]');
    const config = modal.querySelector('[data-mission-step="config"]');
    const form = modal.querySelector('[data-mission-form]');

    if (!gallery || !config || !form) {
        return;
    }

    const typeInput = form.querySelector('[data-mission-type]');
    const valueInput = form.querySelector('[data-mission-value]');
    const idInput = form.querySelector('[data-mission-id]');
    const methodInput = form.querySelector('[data-mission-method]');
    const labelEl = modal.querySelector('[data-mission-label]');
    const need = modal.querySelector('[data-mission-need]');
    const needOut = modal.querySelector('[data-mission-need-out]');
    const needRow = modal.querySelector('[data-mission-need-row]');
    const letterGrid = modal.querySelector('[data-mission-letter-grid]');
    const letterButtons = Array.from(modal.querySelectorAll('[data-mission-letter]'));
    const flavorEl = modal.querySelector('[data-mission-flavor]');
    const preview = modal.querySelector('[data-mission-preview]');
    const submit = modal.querySelector('[data-mission-submit]');
    // Re-collected after every pool/gallery swap (see bindTiles/bindEdits).
    let tiles = [];

    let current = null;
    let pendingEdit = null;

    const promptText = () => {
        const template = current?.prompt
            ?? `Find {need} ${current?.label ?? 'matching words'} in this story`;

        return template
            .replaceAll('{need}', need.value)
            .replaceAll('{letter}', (valueInput.value || '?').toUpperCase());
    };

    const paintLetters = () => {
        letterButtons.forEach((btn) => {
            const on = btn.dataset.missionLetter === valueInput.value;
            btn.style.background = on ? '#059669' : '';
            btn.style.color = on ? '#FFFFFF' : '';
            btn.style.borderColor = on ? '#059669' : '';
        });
    };

    const renderConfig = () => {
        labelEl.textContent = current?.label ?? 'Pick a move type';

        // defmatch/sentblank are fixed-pick moves: the need slider would
        // not change what the student sees, so it steps out of the form.
        const hasNeed = current?.hasNeed !== false;
        if (needRow) needRow.hidden = !hasNeed;

        // The initial hunt picks a letter in the config step (A-Z grid
        // with live per-word counts); its need caps to that letter's pool.
        const isInitial = current?.type === 'initial';
        if (letterGrid) letterGrid.hidden = !isInitial;
        if (isInitial) paintLetters();

        if (flavorEl) flavorEl.textContent = current?.flavor ?? 'SPELL STRIKE';

        let max = Math.max(1, Math.min(5, Number(current?.count ?? 5)));
        if (isInitial) {
            const letterCount = valueInput.value
                ? Number(current?.letters?.[valueInput.value] ?? 0)
                : 0;
            max = Math.max(1, Math.min(5, letterCount || 5));
        }
        need.max = String(max);

        if (Number(need.value) > max) {
            need.value = String(max);
        }

        needOut.textContent = need.value;
        preview.textContent = promptText();
    };

    const showGallery = () => {
        gallery.hidden = false;
        config.hidden = true;
    };

    const showConfig = (rule, edit = null) => {
        current = rule;
        typeInput.value = rule.type;
        valueInput.value = rule.value ?? '';

        if (edit) {
            idInput.value = String(edit.id);
            form.action = form.dataset.urlUpdate.replace('__ID__', String(edit.id));
            methodInput.value = 'PUT';
            submit.textContent = 'Save move';
            need.value = String(edit.need ?? 1);
            if (rule.type === 'initial') {
                valueInput.value = edit.value ?? '';
            }
        } else {
            idInput.value = '';
            form.action = form.dataset.urlStore;
            methodInput.value = 'POST';
            submit.textContent = 'Add move';
            need.value = String(Math.min(5, Number(rule.count ?? 5)));
        }

        gallery.hidden = true;
        config.hidden = false;
        renderConfig();
    };

    const tileFor = (type, value) => tiles.find(
        (tile) => tile.dataset.ruleType === type
            && (tile.dataset.ruleMulti === '1' || (tile.dataset.ruleValue ?? '') === (value ?? '')),
    );

    const ruleFromTile = (tile) => {
        let letters = null;
        try {
            letters = tile.dataset.ruleLetters ? JSON.parse(tile.dataset.ruleLetters) : null;
        } catch {
            letters = null;
        }

        return {
            type: tile.dataset.ruleType,
            value: tile.dataset.ruleValue || null,
            label: tile.dataset.ruleLabel,
            count: Number(tile.dataset.ruleCount),
            prompt: tile.dataset.rulePrompt || null,
            flavor: tile.dataset.ruleFlavor || 'SPELL STRIKE',
            hasNeed: tile.dataset.ruleNeed !== '0',
            letters,
        };
    };

    const restoreFromForm = () => {
        const tile = tileFor(typeInput.value, valueInput.value);

        if (!tile) {
            showGallery();

            return;
        }

        current = ruleFromTile(tile);

        const editId = idInput.value;
        form.action = editId
            ? form.dataset.urlUpdate.replace('__ID__', editId)
            : form.dataset.urlStore;
        methodInput.value = editId ? 'PUT' : 'POST';
        submit.textContent = editId ? 'Save move' : 'Add move';
        gallery.hidden = true;
        config.hidden = false;
        renderConfig();
    };

    // Tiles and Edit buttons live inside the swapped regions, so both are
    // (re)bound through these two helpers: once at init, then again after
    // every no-reload mutation swaps pool/gallery HTML.
    const bindTiles = () => {
        tiles = Array.from(modal.querySelectorAll('[data-mission-rule]'));

        tiles.forEach((tile) => {
            tile.addEventListener('click', () => {
                if (tile.disabled) {
                    return;
                }

                showConfig(ruleFromTile(tile));
            });
        });
    };

    // Edit buttons open the modal through data-modal-open (initModals'
    // delegated handler runs after this one on the bubble), so the rule
    // is recorded before alice:modal-open fires.
    const bindEdits = () => {
        panel?.querySelectorAll('[data-mission-edit]')?.forEach((button) => {
            button.addEventListener('click', () => {
                try {
                    pendingEdit = JSON.parse(button.dataset.missionEdit);
                } catch {
                    pendingEdit = null;
                }
            });
        });
    };

    bindTiles();
    bindEdits();

    // The letter grid lives in the config form, which no swap touches.
    letterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            valueInput.value = btn.dataset.missionLetter ?? '';
            renderConfig();
        });
    });

    modal.addEventListener('alice:modal-open', () => {
        if (pendingEdit) {
            const edit = pendingEdit;
            pendingEdit = null;
            const tile = tileFor(edit.type, edit.value);

            if (tile) {
                showConfig(ruleFromTile(tile), edit);

                return;
            }
        }

        if (!config.hidden) {
            restoreFromForm();
        } else {
            showGallery();
        }
    });

    modal.addEventListener('alice:modal-close', () => {
        pendingEdit = null;
        showGallery();
    });

    need.addEventListener('input', () => {
        needOut.textContent = need.value;
        preview.textContent = promptText();
    });

    modal.querySelector('[data-mission-back]')?.addEventListener('click', showGallery);
    modal.querySelector('[data-mission-cancel]')?.addEventListener('click', () => closeModal(modal));

    // ---- No-reload mutations (form[data-journal-mutate]) --------------------
    // Reorder, toggle, delete, add and edit all fetch instead of navigating.
    // The server answers { pool, gallery, toast } and both regions swap in
    // place, so the page never reloads. Without JS the same forms POST
    // normally and the server still redirects (progressive enhancement);
    // only XHR requests take the JSON branch.

    const TOAST_TONE = { error: 'story', warn: 'stamp' };

    // submit-guard flips the button to its spinner and never resets itself
    // (it assumes a page navigation). No-reload means we reset it here.
    const resetGuard = (target) => {
        if (!target.matches('[data-submit-guard]')) {
            return;
        }

        target.dataset.submitting = 'false';

        const button = target.querySelector('button[type="submit"]');

        if (!button) {
            return;
        }

        button.disabled = false;
        button.removeAttribute('aria-busy');

        const idle = button.querySelector('[data-submit-idle]');
        const active = button.querySelector('[data-submit-active]');

        if (idle) {
            idle.hidden = false;
        }

        if (active) {
            active.hidden = true;
            active.classList.add('hidden');
        }
    };

    const showErrors = (errors) => {
        ['rule_type', 'need'].forEach((field) => {
            const slot = form.querySelector(`[data-mission-error="${field}"]`);

            if (!slot) {
                return;
            }

            const message = Array.isArray(errors?.[field]) ? errors[field][0] : null;
            slot.textContent = message ?? '';
            slot.hidden = !message;
        });
    };

    document.addEventListener('submit', async (event) => {
        const submitted = event.target instanceof Element ? event.target.closest('form[data-journal-mutate]') : null;

        if (!submitted || event.defaultPrevented) {
            return;
        }

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;

        if (submitter?.disabled) {
            return;
        }

        event.preventDefault();

        const isConfig = submitted === form;
        let response;

        try {
            response = await fetch(submitted.action, {
                method: (submitted.getAttribute('method') || 'POST').toUpperCase(),
                body: new FormData(submitted),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
        } catch {
            // Network failure: fall back to a normal POST navigation.
            submitted.submit();

            return;
        }

        resetGuard(submitted);

        if (response.status === 422) {
            let payload = {};

            try {
                payload = await response.json();
            } catch {
                // Non-JSON 422 — fall through to the generic message.
            }

            showErrors(payload.errors ?? {});

            if (payload.message) {
                showToast(payload.message, { tone: 'story' });
            }

            return;
        }

        if (!response.ok) {
            let message = 'That action could not finish.';

            try {
                const payload = await response.json();

                if (payload?.message) {
                    message = payload.message;
                }
            } catch {
                // Non-JSON error body — keep the generic message.
            }

            showToast(message, { tone: 'story' });

            return;
        }

        let data;

        try {
            data = await response.json();
        } catch {
            showToast('That action finished but the result could not be read.', { tone: 'story' });

            return;
        }

        const swapped = typeof data.pool === 'string';

        if (swapped && panel) {
            panel.innerHTML = data.pool;
            bindEdits();
        }

        if (typeof data.gallery === 'string') {
            gallery.innerHTML = data.gallery;
            bindTiles();
        }

        if (isConfig && swapped) {
            showErrors({});
            pendingEdit = null;
            closeModal(modal);
        }

        if (data.toast?.message) {
            showToast(data.toast.message, { tone: TOAST_TONE[data.toast.type] ?? 'success' });
        }
    });

    // ---- Drag reordering (the quiz question list pattern) ----------------
    // Bound once on the persistent panel so pool swaps never need a rebind
    // (list + rows are re-queried fresh per interaction). The DOM is only
    // committed when the server answers: success swaps in the pool
    // fragment (which renumbers every row), failure leaves the old order
    // and drops the inline error note.
    if (panel) {
        const DRAG_INDICATOR = 'absolute left-0 right-0 h-1 bg-[#2D231E]/40 rounded-full pointer-events-none z-10';
        const ERROR_MS = 4000;
        let drag = null;

        const journalList = () => panel.querySelector('[data-journal-list]');
        const journalCards = () => Array.from(panel.querySelectorAll('[data-journal-card]'));

        const showReorderError = () => {
            const currentList = journalList();

            if (!currentList?.parentElement || currentList.parentElement.querySelector('[data-reorder-error]')) {
                return;
            }

            const note = document.createElement('p');

            note.dataset.reorderError = '';
            note.className = 'mt-2 p-3 bg-rose-50 border border-rose-300 rounded-lg text-xs font-bold text-rose-800';
            note.textContent = "Couldn't save the new order — check your connection and try again.";

            currentList.parentElement.insertBefore(note, currentList);
            setTimeout(() => note.remove(), ERROR_MS);
        };

        const persistOrder = async (orderedIds) => {
            const currentList = journalList();

            if (!currentList?.dataset.reorderUrl) {
                return false;
            }

            try {
                const response = await fetch(currentList.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: orderedIds }),
                });

                if (!response.ok) {
                    return false;
                }

                const data = await response.json().catch(() => null);

                if (typeof data?.pool !== 'string') {
                    return false;
                }

                panel.innerHTML = data.pool;
                bindEdits();

                if (data.toast?.message) {
                    showToast(data.toast.message, { tone: TOAST_TONE[data.toast.type] ?? 'success' });
                }

                return true;
            } catch {
                return false;
            }
        };

        const slotIndexAt = (clientY, exclude) => {
            let index = 0;

            for (const card of journalCards()) {
                if (card === exclude) {
                    continue;
                }

                const rect = card.getBoundingClientRect();

                if (clientY > rect.top + rect.height / 2) {
                    index += 1;
                }
            }

            return index;
        };

        panel.addEventListener('keydown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');

            if (!handle || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
                return;
            }

            event.preventDefault();

            const card = handle.closest('[data-journal-card]');
            const sibling = event.key === 'ArrowUp' ? card.previousElementSibling : card.nextElementSibling;

            if (!sibling || !sibling.matches('[data-journal-card]')) {
                return;
            }

            const ids = journalCards().map((item) => item.dataset.journalCard);
            const from = ids.indexOf(card.dataset.journalCard);
            const to = from + (event.key === 'ArrowUp' ? -1 : 1);

            [ids[from], ids[to]] = [ids[to], ids[from]];

            persistOrder(ids.map(Number)).then((saved) => {
                if (!saved) {
                    showReorderError();
                }
            });
        });

        panel.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');

            if (!handle || drag || (event.pointerType === 'mouse' && event.button !== 0)) {
                return;
            }

            const card = handle.closest('[data-journal-card]');

            if (!card || journalCards().length < 2) {
                return;
            }

            event.preventDefault();
            drag = card;

            const list = journalList();
            const indicator = document.createElement('div');
            let lastY = event.clientY;
            let scrollTimer = null;

            indicator.className = DRAG_INDICATOR;
            list.appendChild(indicator);
            card.classList.add('opacity-50');
            document.body.classList.add('select-none');

            const others = () => journalCards().filter((item) => item !== card);

            const placeIndicator = () => {
                const target = slotIndexAt(lastY, card);
                const rest = others();

                if (rest.length === 0) {
                    list.appendChild(indicator);
                } else if (target >= rest.length) {
                    rest[rest.length - 1].after(indicator);
                } else {
                    rest[target].before(indicator);
                }
            };

            // Stop the scroll-follow + autoscroll early on drop: the swap
            // replaces the whole pool, so live layout fiddling must not run
            // against detached nodes while the request is in flight.
            const settle = () => {
                clearInterval(scrollTimer);
                window.removeEventListener('scroll', onScroll);
            };

            const finish = () => {
                settle();
                indicator.remove();
                card.classList.remove('opacity-50');
                document.body.classList.remove('select-none');
                drag = null;
            };

            const onScroll = () => placeIndicator();

            scrollTimer = setInterval(() => {
                const margin = 72;

                if (lastY < margin) {
                    window.scrollBy(0, -12);
                } else if (lastY > window.innerHeight - margin) {
                    window.scrollBy(0, 12);
                }
            }, 16);

            window.addEventListener('scroll', onScroll);
            handle.setPointerCapture(event.pointerId);

            const onMove = (moveEvent) => {
                if (moveEvent.pointerId !== event.pointerId) {
                    return;
                }

                moveEvent.preventDefault();
                lastY = moveEvent.clientY;
                placeIndicator();
            };

            const onCancel = () => finish();

            const onUp = async (upEvent) => {
                if (upEvent.pointerId !== event.pointerId) {
                    return;
                }

                handle.removeEventListener('pointermove', onMove);
                handle.removeEventListener('pointerup', onUp);
                handle.removeEventListener('pointercancel', onCancel);
                settle();

                const rest = others();
                const target = slotIndexAt(lastY, card);
                const orderedIds = rest.map((item) => item.dataset.journalCard);

                orderedIds.splice(target, 0, card.dataset.journalCard);

                if (JSON.stringify(orderedIds) === JSON.stringify(journalCards().map((item) => item.dataset.journalCard))) {
                    finish();

                    return;
                }

                const saved = await persistOrder(orderedIds.map(Number));

                if (!saved) {
                    showReorderError();
                }

                finish();
            };

            handle.addEventListener('pointermove', onMove);
            handle.addEventListener('pointerup', onUp);
            handle.addEventListener('pointercancel', onCancel);
        });
    }

    // A validation redirect reopens the modal server-side (no event):
    // the config step is already visible with old() values in the form.
    if (!config.hidden) {
        restoreFromForm();
    } else {
        showGallery();
    }
}
