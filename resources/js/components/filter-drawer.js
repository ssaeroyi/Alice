/**
 * Shared library/collection filtering behavior: the filter drawer's
 * open/close + grouped single-select toggles, the debounced auto-search,
 * and scroll-position restore on back navigation. Imported by both the
 * library (hub + landings) and the collection page so every browsing
 * surface shares one implementation.
 */

// Skeleton + stale-response guard for in-place result swaps. One in-flight
// token at a time: starting a new search supersedes the previous request
// (abort + bump the seq) so a late stale response can't clear a newer
// skeleton or overwrite newer results.
let searchSeq = 0;
let searchController = null;

const searchResultsRegion = () => document.querySelector('[data-search-results]');

const beginSwap = () => {
    const region = searchResultsRegion();
    const token = ++searchSeq;

    if (searchController) {
        searchController.abort();
    }
    searchController = new AbortController();
    searchController.signal.addEventListener('abort', () => {
        // Aborted superseded request must not leave a skeleton stuck; if it
        // still owns the region, clear it and let the newer one take over.
        if (region && region.dataset.aliceToken === String(token)) {
            delete region.dataset.aliceToken;
            delete region.dataset.aliceSkel;
            region.removeAttribute('aria-busy');
        }
    });

    return { region, token, signal: searchController.signal };
};

export function initFilterDrawer() {
    const drawer = document.querySelector('[data-filter-drawer]');

    if (!drawer) {
        return;
    }

    const backdrop = drawer.querySelector('[data-filter-drawer-backdrop]');
    const closeBtn = drawer.querySelector('[data-filter-drawer-close]');
    const panel = drawer.querySelector('[data-filter-drawer-panel]');
    const triggers = document.querySelectorAll('[data-filter-drawer-open]');
    const form = drawer.closest('form');
    const gradeInput = form?.querySelector('[data-filter-grade-input]');
    const genreInput = form?.querySelector('[data-filter-genre-input]');
    const difficultyInput = form?.querySelector('[data-filter-difficulty-input]');
    const lengthInput = form?.querySelector('[data-filter-length-input]');
    const sortInput = form?.querySelector('[data-filter-sort-input]');

    // Teacher register: the search lives outside the drawer (always-visible
    // auto-search bar). A hidden `q` carrier inside the drawer form preserves
    // whatever the teacher has typed when they apply a filter. Student drawers
    // have a visible (non-hidden) `q` input instead, so this stays inert there.
    const hiddenQ = form?.querySelector('input[type="hidden"][name="q"]');
    const pageSearch = document.querySelector('form[data-auto-filter] input[type="search"]');

    const syncSearch = () => {
        if (hiddenQ && pageSearch) {
            hiddenQ.value = pageSearch.value;
        }
    };

    let closing = false;

    const open = () => {
        closing = false;
        drawer.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Slide the panel in + fade the backdrop. `hidden` makes the browser
        // skip transitions, so kick off with a double rAF once it's visible.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                drawer.classList.remove('opacity-0');
                drawer.classList.add('opacity-100');
                panel.classList.remove('translate-x-full');
            });
        });
    };

    const close = () => {
        if (closing) return;
        closing = true;

        drawer.classList.remove('opacity-100');
        drawer.classList.add('opacity-0');
        panel.classList.add('translate-x-full');

        // Let the 300ms transition finish before hiding (hidden disables it).
        window.setTimeout(() => {
            drawer.classList.add('hidden');
            document.body.style.overflow = '';
            closing = false;
        }, 300);
    };

    triggers.forEach((trigger) => trigger.addEventListener('click', () => {
        syncSearch();
        open();
    }));
    if (backdrop) backdrop.addEventListener('click', close);
    if (closeBtn) closeBtn.addEventListener('click', close);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !drawer.classList.contains('hidden')) {
            close();
        }
    });

    // Difficulty / genre / length / sort — each is a single-select
    // toggle set. An option with an empty value is the "Any" clear button.
    // Choosing an active option again also clears it; the summary updates.
    // `grade` is kept for any legacy drawer markup that still ships it.
    [
        { attr: 'data-filter-grade', input: gradeInput, summary: '[data-filter-grade-summary]' },
        { attr: 'data-filter-difficulty', input: difficultyInput, summary: '[data-filter-difficulty-summary]' },
        { attr: 'data-filter-genre', input: genreInput, summary: '[data-filter-genre-summary]' },
        { attr: 'data-filter-length', input: lengthInput, summary: '[data-filter-length-summary]' },
        { attr: 'data-filter-sort', input: sortInput, summary: '[data-filter-sort-summary]' },
    ].forEach(({ attr, input, summary }) => {
        const buttons = drawer.querySelectorAll(`[${attr}]`);

        if (!buttons.length) {
            return;
        }

        const summaryEl = drawer.querySelector(summary);

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const val = btn.getAttribute(attr);
                const isSelected = btn.classList.contains('border-amber-400');

                // Clear every button in this set first.
                buttons.forEach((b) => {
                    b.classList.remove('border-amber-400', 'bg-amber-100', 'text-[#2D231E]');
                    b.classList.add('border-[#2D231E]', 'bg-white', 'text-[#8C7B70]');
                });

                if (!isSelected && val !== '') {
                    btn.classList.add('border-amber-400', 'bg-amber-100', 'text-[#2D231E]');
                    btn.classList.remove('border-[#2D231E]', 'bg-white', 'text-[#8C7B70]');
                    if (input) input.value = val;
                    if (summaryEl && val !== '') summaryEl.textContent = btn.textContent.trim();
                } else {
                    if (input) input.value = '';
                    if (summaryEl) summaryEl.textContent = 'Any';
                }
            });
        });
    });

    // AJAX apply on landing pages (no page refresh). Collection pages have
    // no [data-search-results] container, so submitting falls back to a full
    // page navigation (which is what the auto-search already does too).
    if (form && document.querySelector('[data-search-results]')) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            syncSearch();

            const params = new URLSearchParams(new FormData(form));
            const url = `${form.action}?${params}`;

            const { region, token, signal } = beginSwap();
            let settle = () => {};
            import('./skeleton.js').then(({ busy }) => { settle = busy(region, token); });

            fetch(url, { signal })
                .then((res) => (res.ok ? res.text() : Promise.reject(new Error(String(res.status)))))
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newResults = doc.querySelector('[data-search-results]');

                    if (newResults) {
                        document.querySelector('[data-search-results]').replaceWith(newResults);
                    }

                    history.replaceState(null, '', url);
                    close();
                })
                .catch((err) => {
                    if (err.name === 'AbortError') {
                        return;
                    }
                    // Fallback: full page load keeps the page consistent.
                    window.location = url;
                })
                .finally(() => {
                    if (region) region.removeAttribute('aria-busy');
                    settle(token);
                });
        });
    }

    // Teacher register (no [data-search-results]): the drawer applies via a
    // plain GET navigation. Sync the hidden `q` carrier right before the
    // browser submits so a freshly-typed search isn't lost by the filter.
    if (form && hiddenQ && pageSearch && !document.querySelector('[data-search-results]')) {
        form.addEventListener('submit', syncSearch);
    }
}

export function initAutoSearch() {
    const forms = document.querySelectorAll('form[data-auto-filter]');

    forms.forEach((form) => {
        const input = form.querySelector('input[type="search"]');

        if (!input) {
            return;
        }

        let timer;

        input.addEventListener('input', () => {
            clearTimeout(timer);

            timer = setTimeout(() => {
                const params = new URLSearchParams(new FormData(form));
                const url = `${form.action}?${params}`;

                // No results container (hub or collection): navigate outright.
                if (!document.querySelector('[data-search-results]')) {
                    window.location = url;

                    return;
                }

                // Landing page: AJAX swap results without page refresh.
                const { region, token, signal } = beginSwap();
                let settle = () => {};
                import('./skeleton.js').then(({ busy }) => { settle = busy(region, token); });

                fetch(url, { signal })
                    .then((res) => (res.ok ? res.text() : Promise.reject(new Error(String(res.status)))))
                    .then((html) => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const newResults = doc.querySelector('[data-search-results]');

                        if (newResults) {
                            document.querySelector('[data-search-results]').replaceWith(newResults);
                        }

                        // Update URL without reload
                        history.replaceState(null, '', url);
                    })
                    .catch((err) => {
                        if (err.name === 'AbortError') {
                            return;
                        }
                        // Fallback: full page load
                        window.location = url;
                    })
                    .finally(() => {
                        if (region) region.removeAttribute('aria-busy');
                        settle(token);
                    });
            }, 800);
        });
    });
}
