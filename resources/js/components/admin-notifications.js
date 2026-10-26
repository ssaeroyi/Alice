/**
 * Admin live inbox badge. Polls the unread JSON endpoint on admin pages,
 * updates the top-bar bell badge (capped at 99+) in place, and also runs the
 * server-side watchdog transitions each time. Skipped while the tab is hidden
 * or during a "view as" preview.
 */

const POLL_MS = 30000;

function badgeText(number) {
    return number > 99 ? '99+' : String(number);
}

export function initAdminNotifications() {
    const badge = document.querySelector('[data-admin-inbox-badge]');

    if (!badge) {
        return;
    }

    const bell = document.querySelector('[data-admin-inbox-bell]');
    const area = document.body?.dataset?.area ?? '';

    if (area !== 'admin' || document.querySelector('[data-viewing-as]')) {
        return;
    }

    const url = bell?.dataset?.badgeUrl ?? '/admin/inbox/unread.json';

    const apply = (unread) => {
        const n = Number(unread) || 0;
        badge.textContent = badgeText(n);
        badge.classList.toggle('hidden', n <= 0);
        if (bell) {
            bell.setAttribute('aria-label', `Inbox, ${n} unread`);
        }
    };

    const poll = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();
            if (data && typeof data.unread === 'number') {
                apply(data.unread);
            }
        } catch {
            // Quietly retry next interval.
        }
    };

    const onVisible = () => {
        if (!document.hidden) {
            poll();
        }
    };

    poll();

    const timer = setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', onVisible);

    // Teardown hooks (opencode page transitions) — no-op today, harmless.
    window.addEventListener('beforeunload', () => clearInterval(timer), { once: true });
}