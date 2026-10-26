/**
 * No-reload pagination for server-rendered lists.
 *
 * Wrap a results region (list + pager) in a root container and call
 * initAjaxPager(selector). Clicks on `.pagination a[href]` inside the root are
 * re-fetched with fetch() and swapped in place - no full page load, no scroll
 * jump - while the URL advances via history.pushState so Back/Forward re-fetch
 * the previous/next page. The links stay real `<a href>`: no-JS, modified/new-
 * tab clicks and fetch failures all fall back to a normal navigation.
 *
 * Contract: the endpoint returns the same full HTML page; the new subtree is
 * located by the same root selector and swapped (server contract unchanged -
 * this is pure progressive enhancement on top of existing views).
 */
export function initAjaxPager(rootSelector) {
    const root = document.querySelector(rootSelector);

    if (!root) {
        return;
    }

    let controller = null;
    let inFlight = false;

    const turnTo = async (url) => {
        if (inFlight || url === window.location.href) {
            return;
        }

        inFlight = true;
        root.setAttribute('aria-busy', 'true');
        root.classList.add('alice-busy');

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Pager fetch failed');
            }

            const html = await response.text();
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const nextRoot = nextDocument.querySelector(rootSelector);

            if (!nextRoot) {
                throw new Error('Pager response missing results region');
            }

            root.replaceChildren(...nextRoot.childNodes);
            window.history.pushState(null, '', url);
        } catch (error) {
            // Aborts (rapid double-clicks) are intentional - stay on the page.
            if (error.name !== 'AbortError') {
                window.location.assign(url);
            }
        } finally {
            inFlight = false;
            root.removeAttribute('aria-busy');
            root.classList.remove('alice-busy');
        }
    };

    root.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || !link.closest('.pagination')) {
            return;
        }

        // Modified / new-tab clicks behave natively.
        if (event.defaultPrevented || event.button === 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        turnTo(link.href);
    });

    window.addEventListener('popstate', () => {
        if (document.querySelector(rootSelector)) {
            turnTo(window.location.href);
        }
    });
}