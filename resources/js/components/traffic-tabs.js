/**
 * Traffic panel range chips (ALICE admin status page).
 *
 * The chips are real `<a href="?range=…">` links: no-JS and modified clicks
 * fall back to a normal navigation. With JS, the click is intercepted, the
 * partial is re-fetched (`?partial=1`), and the `[data-traffic-panel]` subtree
 * is swapped in place — no full page load, no tab reset. The chart is
 * re-initialised on the freshly swapped canvas, and the URL keeps the current
 * tab hash so a reload stays on Traffic.
 */
export function initTrafficTabs() {
    document.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-traffic-tab]');

        if (!tab) {
            return;
        }

        event.preventDefault();

        const panel = tab.closest('[data-traffic-panel]');

        if (!panel || tab.getAttribute('aria-current') === 'true') {
            return;
        }

        const url = new URL(tab.href, window.location.origin);
        url.searchParams.set('partial', '1');

        const token = Date.now();
        let settle = () => {};
        import('./skeleton.js').then(({ busy }) => { settle = busy(panel, token); });

        fetch(url.toString(), {
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('bad response');
                }

                return response.text();
            })
            .then((html) => {
                const next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-traffic-panel]');

                if (!next) {
                    throw new Error('missing panel');
                }

                settle(token);
                panel.replaceWith(next);

                // Keep the current tab hash so a reload doesn't drop to Overview.
                const hash = window.location.hash || '#traffic';
                const nextUrl = new URL(tab.href, window.location.origin);
                window.history.replaceState(null, '', `${nextUrl.pathname}${nextUrl.search}${hash}`);

                // Re-init the chart on the swapped canvas.
                import('../pages/admin-traffic-chart.js')
                    .then((m) => m.default?.())
                    .catch(() => {});
            })
            .catch(() => {
                window.location.href = tab.href;
            });
    });
}
