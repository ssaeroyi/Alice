import { getPortal, portalId } from '../utils/portal';

let bound = false;

export function initInfoPopovers() {
    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-info-toggle]');

        if (!toggle) {
            return;
        }

        event.stopPropagation();
        // The toggle can sit inside a clickable pane (dashboard cards) —
        // opening the tooltip must not also follow the pane's link.
        event.preventDefault();

        const wrapper = toggle.closest('[data-info]');
        const popover = wrapper?.querySelector('[data-info-popover]');

        if (!wrapper || !popover) {
            return;
        }

        closeOthers(wrapper);

        const willOpen = popover.classList.contains('hidden');

        popover.classList.toggle('hidden', !willOpen);
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        if (willOpen) {
            openPopover(wrapper, popover);
        } else {
            restorePopover(popover);
        }
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-info]') || event.target.closest('#' + portalId)) {
            return;
        }

        closeAll();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });

    window.addEventListener('scroll', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-info-popover]')) {
            return;
        }

        closeAll();
    }, true);

    window.addEventListener('resize', closeAll);
}

function openPopover(wrapper, popover) {
    popover._infoHost = wrapper;

    getPortal().appendChild(popover);

    popover.classList.remove('absolute', 'top-full', 'bottom-full', 'mt-2', 'mb-2', 'hidden');
    popover.classList.add('fixed', 'pointer-events-auto');

    const rect = wrapper.getBoundingClientRect();

    popover.style.maxWidth = Math.max(0, window.innerWidth - 16) + 'px';

    const width = popover.offsetWidth;
    const height = popover.offsetHeight;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    popover.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';

    if (spaceBelow < 240 && spaceAbove > spaceBelow) {
        popover.style.top = Math.max(8, rect.top - height - 8) + 'px';
    } else {
        popover.style.top = rect.bottom + 8 + 'px';
    }
}

function restorePopover(popover) {
    const host = popover._infoHost;

    popover._infoHost = null;

    popover.classList.add('hidden');
    popover.classList.remove('fixed', 'pointer-events-auto');
    popover.classList.add('absolute');
    popover.style.top = '';
    popover.style.left = '';

    popover.closest('#' + portalId)?.removeChild(popover);

    if (host) {
        host.appendChild(popover);
        host.querySelector('[data-info-toggle]')?.setAttribute('aria-expanded', 'false');
    }
}

function closeOthers(except) {
    document.querySelectorAll('[data-info-popover]:not(.hidden)').forEach((popover) => {
        if (popover._infoHost && popover._infoHost !== except) {
            restorePopover(popover);
        }
    });
}

function closeAll() {
    document.querySelectorAll('[data-info-popover]:not(.hidden)').forEach((popover) => {
        if (popover._infoHost) {
            restorePopover(popover);
        }
    });
}