import { initPronounceChecks } from '../components/speech.js';
import { bindEntry } from './study-entry.js';

/**
 * Dictionary browser: debounced auto-search that fetch-swaps the entry
 * region (no page refresh) plus the shared entry behaviors.
 */
export default function studyDictionary() {
    bindEntry(document);

    const form = document.querySelector('[data-dictionary-search]');
    const input = form?.querySelector('input[name="word"]');
    let region = document.querySelector('[data-entry-region]');

    if (!form || !input) {
        return;
    }

    let timer = null;
    let inFlight = false;

    const swap = async () => {
        const value = input.value.trim();
        const url = new URL(window.location.href);

        if (value === '') {
            url.searchParams.delete('word');
        } else {
            url.searchParams.set('word', value);
        }

        if (inFlight) {
            return;
        }

        inFlight = true;

        try {
            const response = await fetch(url.toString(), {
                headers: { Accept: 'text/html' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector('[data-entry-region]');

            if (fresh && region) {
                region.replaceWith(fresh);
                region = fresh;
                // Fresh markup on every lookup: rebind say/save and re-init the
                // dictation widget (it arrives hidden and unbound otherwise).
                bindEntry(document);
                initPronounceChecks(region);
                window.history.replaceState({}, '', url.toString());
            }
        } catch (error) {
            // Graceful fallback: full navigation.
            window.location.assign(url.toString());
        } finally {
            inFlight = false;
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(swap, 600);
    });

    // Enter should not double-submit a full form - the swap already ran.
    form.addEventListener('submit', (event) => event.preventDefault());
}
