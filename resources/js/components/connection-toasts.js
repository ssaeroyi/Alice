/**
 * Connection status toasts for the guest (auth) + placement flows, scoped by
 * <body data-conn-toasts="1">. Covers the two dead ends students actually hit:
 *  - going offline mid-flow (or submitting offline): one clear pixel toast
 *    instead of a browser "no internet" page;
 *  - a network-level fetch failure no component already swallowed: one
 *    "something went wrong" toast, then back online confirmation — all
 *    deduped so bursts stay quiet. Server flash toasts, inline 422s and
 *    component-level fallbacks are untouched; aborted requests never toast.
 */

import { showToast } from './toast.js';

const OFFLINE_COPY = 'No internet connection. Check your connection and try again.';
const ONLINE_COPY = "You're back online.";
const FAILED_COPY = 'Something went wrong. Try again.';

/** A repeat of the same status inside this window stays silent. */
const QUIET_MS = 10_000;

export function initConnectionToasts() {
    if (document.body.dataset.connToasts !== '1') {
        return;
    }

    // Same-kind repetition is suppressed; a kind flip (offline -> online or
    // the reverse) always shows, so status changes never read as stale.
    let lastKind = null;
    let lastShownAt = 0;

    const announce = (kind, message, options = {}) => {
        const now = Date.now();
        if (kind === lastKind && now - lastShownAt < QUIET_MS) {
            return;
        }
        lastKind = kind;
        lastShownAt = now;
        showToast(message, options);
    };

    // Wrapping once: a rerun on an already-wrapped fetch is a no-op.
    if (!window.fetch.__aliceWrapped) {
        const originalFetch = window.fetch.bind(window);
        const wrappedFetch = async (...args) => {
            try {
                return await originalFetch(...args);
            } catch (err) {
                // Abort / cancel is a component's own polite stop, not a fault.
                if (err?.name === 'AbortError' || err?.name === 'CanceledError') {
                    throw err;
                }
                announce(!navigator.onLine ? 'offline' : 'failure', !navigator.onLine ? OFFLINE_COPY : FAILED_COPY);
                throw err;
            }
        };
        wrappedFetch.__aliceWrapped = true;
        window.fetch = wrappedFetch;
    }

    window.addEventListener('offline', () => {
        announce('offline', OFFLINE_COPY);
    });

    window.addEventListener('online', () => {
        announce('online', ONLINE_COPY, { tone: 'success', duration: 3500 });
    });

    // Plain POST flows (login, register, reset, every placement step): refuse
    // to navigate into a dead browser page while offline. Submits a component
    // already prevented (empty search, repeated submit guard) are skipped —
    // they never navigate at all, so they carry no dead-end to warn about.
    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented || navigator.onLine) {
            return;
        }
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        announce('offline', OFFLINE_COPY);
        event.preventDefault();
        releaseBlocked(form);
    });
}

/**
 * A submit-guard form locked itself (dataset + button + label swap) the same
 * event the gate just refused. Nobody navigated, so undo exactly that lock:
 * the page must read "no connection" and stay usable, not dead.
 */
function releaseBlocked(form) {
    delete form.dataset.submitting;
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
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
    });

    // Any generic busy overlay raised for this attempt comes down too.
    document.querySelector('[data-busy-overlay]')?.remove();
    document.body.removeAttribute('aria-busy');
}
