import { initSubmitGuards } from './submit-guard.js';

let drawer = null;
let backdrop = null;
let panel = null;
let content = null;

const ensureInit = () => {
    if (content) {
        return true;
    }

    drawer = document.querySelector('[data-story-drawer]');

    if (!drawer) {
        return false;
    }

    backdrop = drawer.querySelector('[data-story-drawer-backdrop]');
    panel = drawer.querySelector('[data-story-drawer-panel]');
    content = drawer.querySelector('[data-story-drawer-body]');

    if (panel) {
        panel.tabIndex = -1;
    }

    return content !== null;
};

export function openStoryDrawer(storyId) {
    if (!ensureInit()) {
        return;
    }

    const template = document.querySelector(`template[data-story-drawer-template="${storyId}"]`);

    if (!template) {
        return;
    }

    content.innerHTML = '';
    content.appendChild(template.content.cloneNode(true));
    initSubmitGuards(content);

    drawer.classList.remove('pointer-events-none');
    backdrop.classList.add('opacity-100', 'pointer-events-auto');
    panel.classList.remove('translate-x-full');
    document.body.style.overflow = 'hidden';
    panel.focus({ preventScroll: true });
}

export function closeStoryDrawer() {
    if (!ensureInit()) {
        return;
    }

    drawer.classList.add('pointer-events-none');
    backdrop.classList.remove('opacity-100', 'pointer-events-auto');
    panel.classList.add('translate-x-full');
    document.body.style.overflow = '';
}

export function initStoryDrawer(root = document) {
    drawer = root.querySelector('[data-story-drawer]');

    if (!drawer) {
        return;
    }

    backdrop = drawer.querySelector('[data-story-drawer-backdrop]');
    panel = drawer.querySelector('[data-story-drawer-panel]');
    content = drawer.querySelector('[data-story-drawer-body]');

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-story-drawer-open]');

        if (trigger) {
            event.preventDefault();
            openStoryDrawer(trigger.dataset.storyDrawerOpen);

            return;
        }

        if (event.target.closest('[data-story-drawer-close]') || event.target === backdrop) {
            closeStoryDrawer();
        }
    });

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeStoryDrawer();
        }
    });
}