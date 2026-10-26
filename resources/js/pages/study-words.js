/**
 * All Saved Words management. AJAX-first: search/sort fetch-swaps the
 * results region (library pattern), bulk actions mutate rows in place.
 * The EDIT toggle is JS-only - without scripts rows stay plain links.
 */
export default function studyWords() {
    let resultsRegion = document.querySelector('[data-search-results]');
    const searchInput = document.querySelector('[data-word-search]');
    const sortButtons = [...document.querySelectorAll('[data-word-sort]')];
    const editToggle = document.querySelector('[data-edit-toggle]');
    const editLabel = document.querySelector('[data-edit-label]');
    const bulkBar = document.querySelector('[data-bulk-bar]');
    const confirmTrigger = document.querySelector('[data-bulk-confirm-trigger]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let editMode = false;
    let timer = null;
    let inFlight = false;

    // ------------------------------------------------------------------
    // Fetch-swap for search / sort / filter
    // ------------------------------------------------------------------

    const currentUrl = () => {
        const url = new URL(window.location.href);
        const q = searchInput?.value.trim() ?? '';

        if (q === '') {
            url.searchParams.delete('q');
        } else {
            url.searchParams.set('q', q);
        }

        const activeSort = sortButtons.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.wordSort ?? 'date';

        if (activeSort !== 'date') {
            url.searchParams.set('sort', activeSort);
        } else {
            url.searchParams.delete('sort');
        }

        return url.toString();
    };

    const swapResults = async () => {
        if (!resultsRegion || inFlight) {
            return;
        }

        inFlight = true;

        let settle = () => {};
        import('../components/skeleton.js').then(({ busy }) => { settle = busy(resultsRegion, resultsRegion.dataset.aliceToken ?? '0'); });

        try {
            const response = await fetch(currentUrl(), { headers: { Accept: 'text/html' } });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.querySelector('[data-search-results]');

            if (fresh) {
                resultsRegion.replaceWith(fresh);
                resultsRegion = fresh;
                clearSelection();
                applyEditMode(document);
                window.history.replaceState({}, '', currentUrl());
            }
        } catch (error) {
            // Graceful fallback: full navigation.
            window.location.assign(currentUrl());
        } finally {
            inFlight = false;
            resultsRegion.removeAttribute?.('aria-busy');
            settle();
        }
    };

    searchInput?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(swapResults, 500);
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.getAttribute('aria-pressed') === 'true') {
                return;
            }

            sortButtons.forEach((other) => {
                const on = other === button;
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
                other.classList.toggle('bg-amber-400', on);
                other.classList.toggle('bg-white', !on);
            });

            swapResults();
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-clear-search]')) {
            if (searchInput) {
                searchInput.value = '';
            }
            swapResults();
        }
    });

    // ------------------------------------------------------------------
    // Edit mode
    // ------------------------------------------------------------------

    const applyEditMode = (root = document) => {
        root.querySelectorAll('.edit-only').forEach((el) => {
            el.classList.toggle('hidden', !editMode);
        });

        root.querySelectorAll('[data-select-all-group]').forEach((button) => {
            button.classList.toggle('hidden', !editMode);
            button.classList.toggle('inline-block', editMode);
        });

        if (editLabel) {
            editLabel.textContent = editMode ? 'Done' : 'Edit';
        }
        editToggle?.setAttribute('aria-pressed', editMode ? 'true' : 'false');

        if (!editMode) {
            updateBulkBar();
        }
    };

    editToggle?.addEventListener('click', () => {
        editMode = !editMode;
        applyEditMode();

        if (!editMode) {
            clearSelection();
        }
    });

    // Row click: navigate normally; toggle selection while editing.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-word-link]');

        if (!link || !editMode) {
            return;
        }

        event.preventDefault();
        const checkbox = link.closest('[data-word-row]')?.querySelector('[data-word-check]');

        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateBulkBar();
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches?.('[data-word-check]')) {
            updateBulkBar();
        }
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-select-all-group]');

        if (!button || !editMode) {
            return;
        }

        button.closest('[data-group]')?.querySelectorAll('[data-word-check]').forEach((box) => {
            box.checked = true;
        });
        updateBulkBar();
    });

    // ------------------------------------------------------------------
    // Selection + bulk bar
    // ------------------------------------------------------------------

    const selectedIds = () => [...document.querySelectorAll('[data-word-check]:checked')]
        .map((box) => Number(box.value));

    const updateBulkBar = () => {
        const count = selectedIds().length;
        const counter = document.querySelector('[data-selected-count]');

        if (counter) {
            counter.textContent = count;
        }

        bulkBar?.classList.toggle('hidden', !editMode || count === 0);

        document.querySelectorAll('[data-bulk-action]').forEach((button) => {
            button.disabled = count === 0;
        });
    };

    const clearSelection = () => {
        document.querySelectorAll('[data-word-check]').forEach((box) => {
            box.checked = false;
        });
        updateBulkBar();
    };

    document.querySelector('[data-select-all]')?.addEventListener('click', () => {
        document.querySelectorAll('[data-word-check]').forEach((box) => {
            box.checked = true;
        });
        updateBulkBar();
    });

    document.querySelector('[data-select-none]')?.addEventListener('click', () => {
        clearSelection();
    });

    // ------------------------------------------------------------------
    // Bulk actions
    // ------------------------------------------------------------------

    let pendingAction = null;

    const confirmCopy = {
        delete: 'Delete the selected words?',
        reset: 'Reset progress for the selected words?',
        master: 'Mark the selected words as mastered?',
    };

    document.querySelectorAll('[data-bulk-action]').forEach((button) => {
        button.addEventListener('click', () => {
            pendingAction = button.dataset.bulkAction;

            const copy = document.querySelector('[data-bulk-confirm-copy]');

            if (copy) {
                copy.textContent = confirmCopy[pendingAction];
            }

            // Destructive action confirms first; the rest run immediately.
            if (pendingAction === 'delete') {
                confirmTrigger?.click();
            } else {
                runBulk();
            }
        });
    });

    document.querySelector('[data-bulk-confirm-go]')?.addEventListener('click', async () => {
        document.querySelector('#bulk-confirm-modal [data-modal-close]')?.click();
        await runBulk();
    });

    const runBulk = async () => {
        const ids = selectedIds();

        if (!pendingAction || ids.length === 0) {
            return;
        }

        try {
            const response = await fetch('/study/words/bulk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ action: pendingAction, ids }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (pendingAction === 'delete') {
                ids.forEach((id) => {
                    document.querySelector(`[data-word-row][data-word-id="${id}"]`)?.remove();
                });

                const total = document.querySelector('[data-total-words]');

                if (total) {
                    total.textContent = Math.max(0, Number(total.textContent) - data.affected);
                }

                clearSelection();

                // Last rows gone - reload so the server renders the
                // proper empty state.
                if (!resultsRegion?.querySelector('[data-word-row]')) {
                    window.location.reload();
                }
            } else {
                // Progress changed on remaining rows - a fresh swap is the
                // cheapest correct state.
                await swapResults();
            }
        } catch (error) {
            window.alert('Something went wrong. Please try again.');
        }
    };
}
