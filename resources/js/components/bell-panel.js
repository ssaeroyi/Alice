/**
 * Nav-bell dropdown preview (student layout only).
 *
 * The bell is a real <a href="/inbox"> in the markup. This module prevents the
 * default and opens the dropdown instead, so with JS off the bell still
 * navigates to the inbox — same progressive-enhancement contract as
 * components/ajax-pager.js.
 *
 * Open/close itself belongs to components/dropdown.js (data-dropdown-toggle):
 * this module only (1) fetches the panel body when the panel ends up open,
 * (2) re-places the panel after the content changes its height, and (3) clears
 * the shared unread badge after "Mark all as read".
 *
 * The read is deferred to a macrotask on purpose: dropdown.js toggles the menu
 * in its own click listener, and reading the state afterwards means we never
 * depend on which of the two listeners was registered first.
 *
 * Fetch failures fall back to a normal navigation (the trigger's href IS the
 * inbox), matching how every other fetch-enhanced surface degrades.
 */
import { repositionDropdown } from './dropdown.js';

export function initBellPanel() {
    const trigger = document.querySelector('[data-bell-toggle]');
    const menuId = trigger?.getAttribute('data-dropdown-toggle');
    const menu = menuId ? document.getElementById(menuId) : null;
    const body = menu?.querySelector('[data-bell-body]');

    if (!trigger || !menu || !body || !trigger.dataset.url) {
        return;
    }

    const inboxUrl = trigger.getAttribute('href');
    let controller = null;

    const clearUnreadBadges = () => {
        document.querySelectorAll('[data-unread-badge]').forEach((badge) => badge.remove());
    };

    const refresh = async () => {
        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        const token = Date.now();

        const { busy } = await import('./skeleton.js');
        const settle = busy(body, token);

        try {
            const response = await fetch(trigger.dataset.url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error('Bell preview fetch failed');
            }

            const data = await response.json();

            settle(token);
            body.innerHTML = data.html;
            repositionDropdown(menu);

            if (typeof data.unread === 'number' && data.unread <= 0) {
                clearUnreadBadges();
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            settle(token);
            window.location.assign(inboxUrl);
        }
    };

    const markAllRead = async (form) => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Mark-all failed');
            }

            clearUnreadBadges();
            await refresh();
        } catch {
            // Native POST (bypasses the submit event, so no recursion).
            form.submit();
        }
    };

    trigger.addEventListener('click', (event) => {
        // Modified / middle clicks keep their native meaning (new tab, etc).
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        window.setTimeout(() => {
            if (!menu.classList.contains('hidden')) {
                refresh();
            }
        }, 0);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-bell-mark-all]');

        if (!form || !menu.contains(form)) {
            return;
        }

        event.preventDefault();
        markAllRead(form);
    });
}
