/**
 * No-reload form actions (ALICE admin status page).
 *
 * Any `form[data-ajax]` submits via fetch instead of a full page POST. The
 * server answers with JSON: `{ html, toast }`. When `html` is present it
 * replaces the innerHTML of the nearest ancestor `[data-swap-region]` so the
 * owning panel updates in place; `toast` raises the existing runtime toast.
 * The busy overlay shows while the request is in flight and is dismissible
 * early (work keeps running server-side).
 *
 * Delegated on `document`, so forms swapped in by a previous response are
 * picked up without re-binding. Progressive enhancement: without JS the
 * forms POST normally and the server still redirects — the JSON branch only
 * fires for XHR requests.
 */
import { showBusyOverlay, hideBusyOverlay } from './busy-overlay.js';
import { showToast } from './toast.js';

const TOAST_TONE = { success: 'success', error: 'story', warn: 'stamp' };

export function initAjaxForms() {
    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof Element ? event.target.closest('form[data-ajax]') : null;

        if (!form || event.defaultPrevented) {
            return;
        }

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;

        if (submitter?.disabled) {
            return;
        }

        event.preventDefault();

        const region = form.closest('[data-swap-region]');

        if (submitter) {
            submitter.disabled = true;
            submitter.setAttribute('aria-busy', 'true');
        }

        showBusyOverlay(form.dataset.busyText || 'Working — the result appears in place.');
        region?.setAttribute('aria-busy', 'true');

        let response;

        try {
            response = await fetch(form.action, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
        } catch {
            // Network failure: fall back to a normal POST navigation.
            form.submit();

            return;
        } finally {
            hideBusyOverlay();

            if (submitter) {
                submitter.disabled = false;
                submitter.removeAttribute('aria-busy');
            }

            region?.removeAttribute('aria-busy');
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

        if (typeof data.html === 'string' && region) {
            region.innerHTML = data.html;
        }

        if (data.toast?.message) {
            showToast(data.toast.message, { tone: TOAST_TONE[data.toast.type] ?? 'freeze' });
        }
    });
}
