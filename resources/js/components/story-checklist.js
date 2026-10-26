const FLASH_MS = 1600;
const FLASH_CLASS = 'ring-2 ring-[#2D231E]';

const SELECTORS = {
    title: '#title',
    author: '#author',
    license: '#license',
    source_url: '#source_url',
    content: '#content',
    synopsis: '#synopsis',
    reading_level: '[data-jump-target="reading_level"]',
    genres: '[data-jump-target="genres"]',
    cover_image: '[data-jump-target="cover_image"]',
};

export function initStoryChecklist() {
    document.querySelectorAll('[data-jump-field]').forEach((item) => {
        item.addEventListener('click', () => {
            const selector = SELECTORS[item.dataset.jumpField];

            if (!selector) {
                return;
            }

            const tab = document.querySelector('[data-story-tab="story"]');

            if (tab) {
                tab.click();
            }

            const target = document.querySelector(selector);

            if (!target) {
                return;
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });

            if (typeof target.focus === 'function') {
                target.focus({ preventScroll: true });
            }

            target.classList.add(...FLASH_CLASS.split(' '));
            setTimeout(() => target.classList.remove(...FLASH_CLASS.split(' ')), FLASH_MS);
        });
    });
}
