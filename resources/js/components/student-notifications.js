/**
 * Student live unread badge. Polls the unread count every 30 seconds and
 * updates every [data-student-unread] badge in place. Skipped while the tab
 * is hidden. The bell list itself is fetched fresh each time the panel opens.
 */

const POLL_MS = 30000;
const URL = '/inbox/unread.json';

function paint(number) {
    document.querySelectorAll('[data-student-unread]').forEach((badge) => {
        badge.textContent = number > 99 ? '99+' : String(number);
        badge.classList.toggle('hidden', number <= 0);
    });
}

export function initStudentNotifications() {
    if (!document.querySelector('[data-student-unread]')) {
        return;
    }

    const poll = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const res = await fetch(URL, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();

            if (data && typeof data.unread === 'number') {
                paint(data.unread);
            }
        } catch {
            // Retry on the next interval.
        }
    };

    const timer = setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
        }
    });
    window.addEventListener('beforeunload', () => clearInterval(timer), { once: true });
}
