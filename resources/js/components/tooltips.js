import { getPortal, portalId } from '../utils/portal';

let bound = false;
let openTip = null;

export function initTooltips() {
    if (bound) {
        return;
    }

    bound = true;

    // event.target can be a Text node on mouse/focus events and has no
    // .closest(); guard every handler so each falls back to "no host".
    const hostFrom = (event) => (event.target instanceof Element ? event.target.closest('[data-tip]') : null);

    document.addEventListener('mouseenter', (event) => {
        const host = hostFrom(event);
        if (!host || openTip?.host === host) {
            return;
        }
        open(host);
    }, true);

    document.addEventListener('mouseleave', (event) => {
        if (hostFrom(event)) {
            close();
        }
    }, true);

    document.addEventListener('focusin', (event) => {
        const host = hostFrom(event);
        if (!host) {
            return;
        }
        open(host);
    }, true);

    document.addEventListener('focusout', (event) => {
        if (hostFrom(event)) {
            close();
        }
    }, true);

    document.addEventListener('click', (event) => {
        const host = hostFrom(event);

        if (host) {
            event.stopPropagation();
            if (openTip?.host === host) {
                close();
            } else {
                open(host);
            }
            return;
        }

        close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    window.addEventListener('scroll', () => close(), true);
    window.addEventListener('resize', () => close(), true);
}

function open(host) {
    const title = host.getAttribute('data-tip') || host.textContent.trim();

    close();

    const tip = document.createElement('div');
    tip.className = 'fixed pointer-events-none z-[60] max-w-[220px] sm:max-w-[280px] rounded-lg border-2 border-[#2D231E] bg-[#2D231E] text-white px-2.5 py-1.5 font-pixel text-[10px] leading-relaxed shadow-lg';
    tip.setAttribute('role', 'tooltip');
    tip.textContent = title;

    host.setAttribute('aria-describedby', 'alice-tip');
    tip.id = 'alice-tip';

    getPortal().appendChild(tip);

    const hostRect = host.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();

    tip.style.left = Math.max(8, Math.min(hostRect.left, window.innerWidth - tipRect.width - 8)) + 'px';

    const spaceBelow = window.innerHeight - hostRect.bottom;
    const spaceAbove = hostRect.top;

    if (spaceAbove > tipRect.height + 8 && spaceBelow < spaceAbove) {
        tip.style.top = Math.max(8, hostRect.top - tipRect.height - 8) + 'px';
    } else {
        tip.style.top = Math.min(window.innerHeight - tipRect.height - 8, hostRect.bottom + 8) + 'px';
    }

    openTip = { host, tip };
}

function close() {
    if (!openTip) {
        return;
    }

    const { host, tip } = openTip;
    openTip = null;

    host.removeAttribute('aria-describedby');
    tip.parentNode?.removeChild(tip);
}
