const TABS = ['story', 'quiz', 'journal', 'publish'];
const STORE_KEY = 'alice-story-tab';

export function initStoryTabs() {
    document.querySelectorAll('[data-story-tabs]').forEach((root) => {
        const buttons = Array.from(root.querySelectorAll('[data-story-tab]'));
        const panels = Array.from(root.querySelectorAll('[data-story-tab-panel]'));

        if (buttons.length === 0 || panels.length === 0) {
            return;
        }

        const toolbar = root.querySelector('[data-story-toolbar]');
        const sentinel = root.querySelector('[data-toolbar-sentinel]');

        if (toolbar && sentinel && 'IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => {
                const stuck = !entry.isIntersecting;
                toolbar.classList.toggle('bg-[#FAF6EE]', stuck);
                toolbar.classList.toggle('bg-[#FAF6EE]/95', !stuck);
                toolbar.classList.toggle('shadow-[0_8px_24px_rgba(45,35,30,0.12)]', stuck);
            }).observe(sentinel);
        }

        const activate = (name, persist = true) => {
            buttons.forEach((button) => {
                const active = button.dataset.storyTab === name;

                // Active = emerald solid, inactive = neutral chip. Emerald is
                // removed when inactive too (the old code only swapped
                // bg-[#2D231E], leaving bg-emerald-600 stuck on forever).
                button.classList.toggle('bg-emerald-600', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('bg-[#F3ECE0]', !active);
                button.classList.toggle('border', !active);
                button.classList.toggle('border-[#E6DCCF]', !active);
                button.classList.toggle('text-[#2D231E]', !active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.storyTabPanel !== name);
            });

            if (persist) {
                sessionStorage.setItem(STORE_KEY, name);
            }
        };

        const remembered = sessionStorage.getItem(STORE_KEY);
        const initial = TABS.includes(location.hash.slice(1))
            ? location.hash.slice(1)
            : (TABS.includes(remembered) ? remembered : 'story');

        activate(initial, false);

        buttons.forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.storyTab));
        });
    });
}