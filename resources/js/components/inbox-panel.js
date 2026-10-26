/**
 * No-reload filter-tab + pager swaps for the notification inboxes
 * (student / teacher / admin — all three share the [data-inbox-panel]
 * contract in their blade views).
 *
 * Click a filter link (or a pagination link), fetch the same full-page URL,
 * extract the [data-inbox-panel] subtree via DOMParser and swap it in place —
 * no browser refresh. Clicks push the URL via history.pushState; popstate
 * re-fetches without pushing, so Back/Forward steps through tabs.
 *
 * Contract mirrors components/ajax-pager.js: the server keeps returning the
 * full HTML page (inbox controllers are untouched); the links stay real
 * <a href> so no-JS, modified/new-tab clicks and fetch failures all fall
 * back to a normal navigation. Rows are forms/buttons, not links — they
 * POST + redirect natively and are never intercepted here.
 *
 * The skeleton loader is components/skeleton.js busy(): it appears only
 * after its 150ms debounce, and every settle path calls settle(token).
 */
export function initInboxPanel() {
    const panelSelector = '[data-inbox-panel]';

    let controller = null;

    const swapTo = async (url, push) => {
        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        const token = Date.now();
        const panel = document.querySelector(panelSelector);
        const { busy } = await import('./skeleton.js');
        const settle = busy(panel, token);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error('Inbox fetch failed');
            }

            const html = await response.text();
            const next = new DOMParser().parseFromString(html, 'text/html').querySelector(panelSelector);

            if (!next) {
                throw new Error('Inbox response missing panel');
            }

            settle(token);

            const focusHref = document.activeElement?.href;
            panel.replaceWith(next);

            if (push) {
                window.history.pushState(null, '', url);
            }

            // The swap detaches the clicked link (focus would drop to <body>):
            // refocus its counterpart in the new panel when one exists.
            if (focusHref) {
                const restored = Array.from(next.querySelectorAll('a[href]'))
                    .find((link) => link.href === focusHref);
                restored?.focus({ preventScroll: true });
            }
        } catch (error) {
            // Aborts (rapid re-clicks) are intentional — the newer request
            // owns the skeleton; only real failures fall back to a full
            // navigation.
            if (error.name !== 'AbortError') {
                settle(token);
                window.location.assign(url);
            }
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest(`${panelSelector} a[href]`);

        if (!link) {
            return;
        }

        // Modified / new-tab clicks behave natively.
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        // Active tab / current page: swallow the click instead of reloading.
        if (link.href.split('#')[0] === window.location.href.split('#')[0]) {
            return;
        }

        swapTo(link.href.split('#')[0], true);
    });

    window.addEventListener('popstate', () => {
        if (document.querySelector(panelSelector)) {
            swapTo(window.location.href, false);
        }
    });
}
