/**
 * Global busy overlay (ALICE admin + teacher side).
 *
 * Opt-in full-page feedback for actions that visibly wait: any
 * `form[data-busy]` gets a fixed overlay (spinner + message) the moment it
 * submits, and every other submit-guarded button on the page is disabled so a
 * second action can't queue mid-run. These forms are all full-page POST →
 * redirect, so the overlay normally vanishes when the browser loads the
 * response. You can dismiss it early instead by clicking outside the message
 * card (or pressing Escape) — that only hides the mask and re-enables the
 * buttons; the request that is already in flight keeps running, and the early
 * repeat actions are harmless: the maintenance trio is single-flight (a
 * duplicate click gets the "already running" toast). Configure the copy with
 * `data-busy-text="…"` (defaults below); leave forms bare if they open a
 * download or do AJAX (no page reload → the overlay would dead-stick).
 */

const DEFAULT_TEXT =
    "Working \u2014 the page reloads when it\u2019s done.";

let disabledButtons = [];

function markup(text) {
    return `
        <div data-busy-overlay class="fixed inset-0 z-[70] flex items-center justify-center bg-[#FAF6EE]/90" role="status" aria-live="polite">
            <div data-busy-card class="mx-4 flex max-w-sm flex-col items-center gap-3 rounded-xl border border-[#E6DCCF] bg-white p-6 text-center shadow-xl">
                <svg class="h-8 w-8 animate-spin text-[#2D231E]" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <p class="text-xs font-bold leading-relaxed text-[#2D231E]">${text}</p>
                <p class="text-[10px] font-semibold text-[#2D231E]">You're back on this page when it finishes. Click anywhere outside to leave it running in the background.</p>
            </div>
        </div>`;
}

function current() {
    return document.querySelector('[data-busy-overlay]');
}

function releaseDisabled() {
    disabledButtons.forEach((button) => {
        button.disabled = false;
    });
    disabledButtons = [];
}

export function showBusyOverlay(text = DEFAULT_TEXT) {
    hideBusyOverlay();
    const overlay = document.createElement('div');
    overlay.innerHTML = markup(text);
    document.body.appendChild(overlay);
    document.body.setAttribute('aria-busy', 'true');
}

export function hideBusyOverlay() {
    current()?.remove();
    document.body.removeAttribute('aria-busy');
}

function dismiss() {
    hideBusyOverlay();
    releaseDisabled();
}

export function initBusyOverlay() {
    document.addEventListener('submit', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form[data-busy]') : null;

        if (!form) {
            return;
        }

        // A second action must not queue while the first is still running.
        document.querySelectorAll('form[data-submit-guard] button[type="submit"]').forEach((button) => {
            if (button.form !== form) {
                button.disabled = true;
                disabledButtons.push(button);
            }
        });

        showBusyOverlay(form.dataset.busyText || DEFAULT_TEXT);
    });

    document.addEventListener('click', (event) => {
        if (!current()) {
            return;
        }

        if (event.target instanceof Element && event.target.closest('[data-busy-card]')) {
            return;
        }

        dismiss();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && current()) {
            dismiss();
        }
    });
}