/**
 * One-page library browsing: the search bar + chip strip form never
 * navigates. Any state change (typing, chips, sort) runs an in-place swap
 * of [data-library-root]: stale-response guarded fetch, skeleton while the
 * round trip lingers, URL kept honest with replaceState so back/forward
 * and bookmarks all carry the exact filter state.
 */

import { busy } from './skeleton.js';

const DEBOUNCE_MS = 350;

let swapController = null;
let swapSeq = 0;

export function initLibraryBrowse() {
    document.querySelectorAll('form[data-browse]').forEach((form) => {
        if (form.dataset.browseBound) {
            return;
        }
        form.dataset.browseBound = 'true';

        const search = form.querySelector('input[type="search"]');
        const clears = form.querySelectorAll('[data-search-clear]');

        const paintClear = () => clears.forEach((clear) => {
            clear.style.display = search && search.value === '' ? 'none' : 'inline-flex';
        });

        // Native-input state is freeloading off CSS (peer-checked) — JS only
        // enforces the include/exclude conflict and schedules the swap.
        form.addEventListener('change', (event) => {
            const field = event.target;
            if (!field.name) {
                return;
            }

            // The one hard conflict: a genre cannot be welcome AND hidden.
            if (field.name === 'genre[]' && field.checked) {
                form.querySelectorAll('input[name="exclude[]"]').forEach((other) => {
                    if (other.value === field.value) {
                        other.checked = false;
                    }
                });
            } else if (field.name === 'exclude[]' && field.checked) {
                form.querySelectorAll('input[name="genre[]"]').forEach((other) => {
                    if (other.value === field.value) {
                        other.checked = false;
                    }
                });
            }

            schedule();
        });

        const schedule = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(swap, DEBOUNCE_MS);
        };

        let timer = null;

        if (search) {
            search.addEventListener('input', () => {
                paintClear();
                schedule();
            });
            // Enter is inert at the field level (combobox owns dropdown nav);
            // results already follow the debounce.
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
        }

        paintClear();

        // The custom X navigates home: a cleared field must never land on a
        // results page nobody asked for.
        clears.forEach((clear) => clear.addEventListener('click', () => {
            if (search) search.value = '';
            window.location.assign(form.dataset.searchClearUrl || form.action.split('?')[0]);
        }));

        // Form-level block: no typed search may ever navigate — the swap is
        // the only path (also catches Chrome-autofill submit monsters).
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(timer);
            swap();
        });
    });

    // CLEAR ALL anywhere in the body resets in place (same swap, real URL).
    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-browse-reset]');
        if (!link || !(link instanceof HTMLAnchorElement)) {
            return;
        }
        event.preventDefault();

        // Purge every input back to defaults BEFORE posting: the fetched
        // page must render the idle shelves, not re-apply what we're clearing.
        const form = document.querySelector('form[data-browse]');
        if (form) {
            const search = form.querySelector('input[type="search"]');
            if (search) search.value = '';
            form.querySelectorAll('input[type="checkbox"]').forEach((check) => {
                check.checked = false;
            });
            form.querySelectorAll('input[type="radio"]').forEach((radio) => {
                radio.checked = radio.value === '' || radio.value === 'mine';
            });
            const sort = form.querySelector('select[name="sort"]');
            if (sort) sort.value = 'title';
        }

        swap(link.href.split('?')[0]);
    });
}

async function swap(explicitUrl = null) {
    const region = document.querySelector('[data-library-root]');
    const form = document.querySelector('form[data-browse]');
    if (!region || !form) {
        return;
    }

    const url = explicitUrl ?? `${form.action.split('?')[0]}?${new URLSearchParams(new FormData(form))}`;

    swapSeq += 1;
    const token = swapSeq;
    if (swapController) {
        swapController.abort();
    }
    swapController = new AbortController();
    const settle = busy(region, token);

    try {
        const res = await fetch(url, {
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
            signal: swapController.signal,
        });
        if (!res.ok) {
            throw new Error(String(res.status));
        }

        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const next = doc.querySelector('[data-library-root]');
        if (!next) {
            throw new Error('library region missing in response');
        }

        region.replaceWith(next);
        history.replaceState(null, '', url);
        next.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
        if (err.name === 'AbortError') {
            return;
        }
        // Fallback: a fresh full load keeps the page honest.
        window.location.assign(url);
    } finally {
        settle(token);
    }
}
