/**
 * Global scroll preservation: back/forward + form-POST redirect restore, while
 * letting plain refreshes and fresh navigations land at the top.
 *
 * Because the app's authenticated responses carry `Cache-Control: must-revalidate`
 * (Laravel's session listener), browsers do NOT keep these pages in the back/forward
 * cache, so `pageshow.event.persisted` is false even on a real Back/Forward and the
 * original code fell back to a sessionStorage marker to restore on any non-persisted
 * pageshow — which also (wrongly) restored after a manual refresh, teleporting the
 * user back into the page.
 *
 * To tell navigation kinds apart we read Navigation Timing Level 2
 * (`performance.getEntriesByType('navigation')[0].type`), which current browsers
 * report reliably per the HTML DOM spec even when a history traversal requires a
 * network revalidation:
 *
 *   - 'reload'        -> user hit refresh -> start at the top (drop the saved marker)
 *   - 'back_forward'  -> Back/Forward (e.g. story preview -> library) -> restore
 *   - 'navigate'      -> a fresh page load; restore ONLY when it is the target of a
 *                        marked navigation (form submit or same-path filter link),
 *                        else -> top
 *
 * Scrolls are saved on `beforeunload` (so Back has something to restore), on
 * `submit` and on same-path link clicks (so a filter/chip reload keeps the
 * viewport; those change the query string, so they save a path-only fallback
 * key). Registered globally in app.js so every page gets it (see
 * FRONTEND_GUIDELINES §10.12 + the "back-nav scroll restore" note).
 */
const STORE_KEY = 'alice-scroll';
const POST_MARKER = 'alice-scroll-post-redirect';

export function initScrollRestore() {
    const key = `${STORE_KEY}:${window.location.pathname}${window.location.search}`;
    // Same-path navigations (filter forms, preset chips) change the query
    // string, so the exact-URL key above misses; this path-only key is the
    // fallback that keeps the viewport in place across them.
    const pathKey = `${STORE_KEY}-path:${window.location.pathname}`;

    const savePath = () => {
        if (window.scrollY > 0) {
            sessionStorage.setItem(pathKey, String(window.scrollY));
        }
    };

    const save = () => {
        if (window.scrollY > 0) {
            sessionStorage.setItem(key, String(window.scrollY));
        }
    };

    const clear = () => {
        sessionStorage.removeItem(key);
        sessionStorage.removeItem(pathKey);
        sessionStorage.removeItem(POST_MARKER);
    };

    const applySaved = () => {
        const saved = sessionStorage.getItem(key) ?? sessionStorage.getItem(pathKey);

        if (saved !== null) {
            window.scrollTo(0, Number(saved));
        }

        clear();
    };

    const goToTop = () => {
        clear();
        window.scrollTo(0, 0);
    };

    window.addEventListener('pageshow', (event) => {
        const nav = performance.getEntriesByType?.('navigation')?.[0];
        const type = nav ? nav.type : 'navigate';

        if (event.persisted || type === 'back_forward') {
            // Back/Forward (or a browser-restored page): bring the user back to
            // where they were when they navigated away.
            applySaved();

            return;
        }

        if (type === 'reload') {
            // Manual refresh: don't teleport the user back into the page.
            goToTop();

            return;
        }

        // 'navigate': fresh load into this URL. Only a form-POST redirect (the
        // submit handler set the marker right before landing here) restores;
        // any other navigate — first visit or a normal link — starts at the top.
        if (sessionStorage.getItem(POST_MARKER) !== null) {
            applySaved();

            return;
        }

        goToTop();
    });

    // Leaving via a normal link/preview click: record the position so a Back
    // (back_forward) can restore it. A reload also fires this, but the reload
    // branch above discards the marker, so refreshes still start at the top.
    window.addEventListener('beforeunload', () => {
        save();
    });

    // Form GET/POST submit -> same page with a new query string: keep the
    // position under the path key so the reload does not jump to the top.
    document.addEventListener('submit', () => {
        sessionStorage.setItem(POST_MARKER, '1');
        save();
        savePath();
    }, true);

    // Same-path link clicks (filter chips, "See students", pagination) also
    // reload with a new query string; flag them the same way. Modified
    // clicks (new tab) and other targets are skipped so they stay fresh.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const anchor = event.target.closest?.('a[href]');

        if (!anchor || anchor.target || anchor.download) {
            return;
        }

        let url;
        try {
            url = new URL(anchor.href, window.location.origin);
        } catch {
            return;
        }

        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
            return;
        }

        // In-page anchors and JS-driven pushState links (metric tiles) never
        // reload, so flagging them would leave a stale restore marker.
        if ((url.search === window.location.search && url.hash) || anchor.hasAttribute('data-metric-tile')) {
            return;
        }

        sessionStorage.setItem(POST_MARKER, '1');
        savePath();
    }, true);
}