/**
 * Student Hub tab control (HUB redesign). A lightweight, no-fetch tab toggle:
 * every panel already lives in the DOM, so switching only moves the "active"
 * classes. Supports deep-linking via the URL hash (#today/#tasks/#stats/
 * #badges) so a refresh or a shared link keeps the student's place, and
 * respects `prefers-reduced-motion`.
 *
 * Markup contract:
 *   <div data-hub-tabs>
 *     <div role="tablist">
 *       <button data-hub-tab="today">Home</button>
 *       ...
 *     </div>
 *     <section role="tabpanel" data-hub-panel="today">...</section>
 *     ...
 *   </div>
 */
export function initHubTabs() {
    const root = document.querySelector('[data-hub-tabs]');
    if (!root) return;

    const tabs = Array.from(root.querySelectorAll('[data-hub-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-hub-panel]'));
    if (!tabs.length || !panels.length) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const show = (name, updateHash = true, scroll = false) => {
        const target = tabs.find((t) => t.dataset.hubTab === name);

        if (!target) return;

        tabs.forEach((t) => {
            const active = t === target;
            t.classList.toggle('bg-amber-400', active);
            t.classList.toggle('bg-white', !active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
            t.setAttribute('tabindex', active ? '0' : '-1');
        });

        panels.forEach((p) => {
            const active = p.dataset.hubPanel === name;
            p.hidden = !active;
            if (active) p.removeAttribute('hidden');
            p.setAttribute('aria-hidden', active ? 'false' : 'true');
        });

        if (updateHash && window.location.hash !== `#${name}`) {
            history.replaceState(null, '', `#${name}`);
        }

        // Tasteful audio feedback on user-initiated tab switches (not the
        // initial deep-link). Best-effort: SFX.play already guards mute and
        // a missing audio context.
        if (updateHash && !reduceMotion && window.SFX && typeof window.SFX.play === 'function') {
            window.SFX.play('nav');
        }

        // Scroll the active tab into view for deep links (arriving on the
        // page at #classwork from an assignment) and for reduced-motion
        // users, who don't get the animation that would otherwise reveal it.
        if (reduceMotion || scroll) {
            target.scrollIntoView({ block: 'nearest' });
        }

        document.dispatchEvent(new CustomEvent('hub:tab', { detail: name }));
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => show(tab.dataset.hubTab));
        tab.addEventListener('keydown', (event) => {
            // Arrow-key navigation within the tablist; Home/End jump to ends.
            const keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End'];
            if (!keys.includes(event.key)) return;

            event.preventDefault();
            const index = tabs.indexOf(document.activeElement) >= 0
                ? tabs.indexOf(document.activeElement)
                : tabs.indexOf(tab);

            let next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;

            tabs[next].focus();
            show(tabs[next].dataset.hubTab);
        });
    });

    const initial = window.location.hash.replace('#', '');
    const hasDeepLink = tabs.some((t) => t.dataset.hubTab === initial);
    show(hasDeepLink ? initial : tabs[0].dataset.hubTab, false, hasDeepLink);

    // In-page anchors (e.g. the Home tab's "View all" -> #classwork, or
    // returning from an assignment detail page) change the hash while the
    // page stays put; respond so the deep link works mid-session, not just
    // on load, and bring the tab into view.
    window.addEventListener('hashchange', () => {
        const name = window.location.hash.replace('#', '');
        if (tabs.some((t) => t.dataset.hubTab === name)) {
            show(name, true, true);
        }
    });
}
