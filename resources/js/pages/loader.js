/**
 * Per-page code splitting (FRONTEND_GUIDELINES §10.6).
 *
 * Pages that need JS declare the module on the body tag:
 *   <body data-page="library">
 * which loads resources/js/pages/library.js (default export = init fn).
 * Vite emits one chunk per page module, so each student page ships only
 * its own enhancement code. Content stays server-rendered; JS only
 * enhances — a failed chunk never blocks the page.
 */

const RETRY_FLAG_PREFIX = 'alice-page-chunk-retry:';

export function initPageLoader() {
    const page = document.body?.dataset?.page;

    if (!page) {
        return;
    }

    import(`./${page}.js`)
        .then((module) => {
            if (typeof module.default === 'function') {
                module.default();
            } else if (import.meta.env.DEV) {
                console.warn(`[alice] pages/${page}.js has no default export function`);
            }
        })
        .catch((error) => {
            // Chunk-error contract: retry once via reload, then surface.
            //
            // Hardened for deployment: a reload is only justified when the
            // module genuinely does not exist (a real wiring bug). Transient
            // failures — offline, a dropped chunk fetch, a stale hashed chunk
            // after a deploy — must NOT nuke the page (which would restart a
            // mini-game or lose form state). Those are logged and the page is
            // left fully server-rendered instead.

            // A dynamic import that fails because the module is missing throws
            // a SyntaxError/plain Error. Network & chunk-hash failures surface
            // as TypeError ("Failed to fetch") or a ChunkLoadError, and we can
            // also detect an offline browser directly.
            const isNetworkFailure =
                navigator.onLine === false ||
                error?.name === 'TypeError' ||
                /failed to fetch|load chunk|chunkload/i.test(error?.message || '');

            if (isNetworkFailure) {
                if (import.meta.env.DEV) {
                    console.warn(`[alice] page module "${page}" chunk fetch failed (no reload)`, error);
                }

                return;
            }

            const flag = RETRY_FLAG_PREFIX + page;

            if (!sessionStorage.getItem(flag)) {
                sessionStorage.setItem(flag, '1');
                window.location.reload();

                return;
            }

            sessionStorage.removeItem(flag);

            if (import.meta.env.DEV) {
                console.error(`[alice] page module "${page}" failed to load`, error);
            }
        });
}
