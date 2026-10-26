const GAP = 4;
const VIEWPORT_MARGIN = 16;

const openMenus = new Set();

/**
 * Top/left/flip math only — no DOM moves, no visibility changes. Split out of
 * placeMenu() so a menu that is ALREADY open can be re-measured after its
 * content changes size (the bell panel fetches its rows after the first open,
 * so it is initially measured at skeleton height).
 */
function positionMenu(menu, trigger) {
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;
    const rect = trigger.getBoundingClientRect();

    let top = rect.bottom + GAP;
    if (top + height > window.innerHeight - VIEWPORT_MARGIN) {
        top = Math.max(VIEWPORT_MARGIN, rect.top - GAP - height);
    }

    let left;
    if (menu.classList.contains('right-0')) {
        left = rect.right - width;
    } else {
        left = rect.left;
    }

    if (width > 0) {
        left = Math.min(
            Math.max(VIEWPORT_MARGIN, left),
            Math.max(VIEWPORT_MARGIN, window.innerWidth - width - VIEWPORT_MARGIN)
        );
    }

    menu.style.top = `${Math.round(top)}px`;
    menu.style.left = `${Math.round(left)}px`;
    menu.style.marginTop = '0px';
}

function setExpanded(trigger, expanded) {
    if (trigger) {
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
}

function closeMenu(menu) {
    openMenus.delete(menu);

    const trigger = menu.__dropdownTrigger;
    const parent = menu.__dropdownParent;
    delete menu.__dropdownTrigger;
    delete menu.__dropdownParent;

    setExpanded(trigger, false);

    menu.classList.add('hidden');
    menu.style.position = '';
    menu.style.top = '';
    menu.style.left = '';
    menu.style.marginTop = '';
    menu.style.visibility = '';

    if (parent && parent.isConnected) {
        parent.appendChild(menu);
    }
}

function placeMenu(menu, trigger) {
    if (!menu.classList.contains('hidden')) {
        return;
    }

    if (!menu.__dropdownParent) {
        menu.__dropdownParent = menu.parentNode;
    }
    document.body.appendChild(menu);

    menu.classList.remove('hidden');
    menu.style.visibility = 'hidden';
    menu.style.position = 'fixed';
    menu.style.top = '0px';
    menu.style.left = '0px';

    menu.__dropdownTrigger = trigger;
    setExpanded(trigger, true);

    positionMenu(menu, trigger);
    menu.style.visibility = 'visible';
}

/**
 * Re-place a menu that is already open — call after its content grows or
 * shrinks, or it stays pinned at the height measured when it first opened.
 */
export function repositionDropdown(menu) {
    if (!menu || menu.classList.contains('hidden')) {
        return;
    }

    const trigger = menu.__dropdownTrigger;

    if (!trigger || !trigger.isConnected) {
        return;
    }

    positionMenu(menu, trigger);
}

export function closeAllDropdowns() {
    [...openMenus].forEach(closeMenu);
}

export function initDropdowns(root = document) {
    root.querySelectorAll('[data-dropdown-toggle]').forEach((trigger) => {
        const menu = root.getElementById(trigger.getAttribute('data-dropdown-toggle'));

        if (!menu) {
            return;
        }

        if (!trigger.hasAttribute('aria-expanded')) {
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            if (openMenus.has(menu)) {
                closeMenu(menu);
                return;
            }
            // One menu at a time: the trigger's stopPropagation above means the
            // document-level "close everything else" handler never sees this
            // click, so close the others here or two menus sit on screen at once.
            [...openMenus].forEach((other) => {
                if (other !== menu) {
                    closeMenu(other);
                }
            });
            openMenus.add(menu);
            placeMenu(menu, trigger);        });
    });

    document.addEventListener('click', (event) => {
        openMenus.forEach((menu) => {
            const trigger = root.querySelector(`[data-dropdown-toggle="${menu.id}"]`);

            if (!menu.contains(event.target)
                && (!trigger || !trigger.contains(event.target))) {
                closeMenu(menu);
            }
        });

        // Guarded submits (logout etc.) keep their menu open so the
        // pressed-state spinner swap is actually visible until navigate.
        // A control can also opt out outright with data-dropdown-keep-open
        // (the bell panel's "Mark all as read" re-renders in place).
        if (event.target.closest('[data-dropdown] button')
            && !event.target.closest('[data-dropdown-keep-open]')
            && !event.target.closest('[data-dropdown] form[data-submit-guard] button[type="submit"]')) {
            closeAllDropdowns();
        }
    });

    const closeAll = () => [...openMenus].forEach(closeMenu);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });

    // Scroll (capture) closes every open menu — EXCEPT a scroll that originates
    // inside one. The bell panel's row list scrolls, and without this guard it
    // would close itself on the first flick.
    document.addEventListener('scroll', (event) => {
        if ([...openMenus].some((menu) => menu.contains(event.target))) {
            return;
        }
        closeAll();
    }, true);
    window.addEventListener('resize', closeAll);
}
