const WIDTHS = { mobile: 'max-w-[380px]', tablet: 'max-w-[540px]' };

const CARD_SECTION = 'p-5 space-y-4';
const GENRE_PILL = 'inline-block px-2 py-0.5 border border-[#E6DCCF] bg-[#FAF6EE] rounded text-xs text-[#2D231E]';
const META_TEXT = 'text-xs text-[#2D231E]';
const COVER_BOX = 'mx-auto w-full max-w-[240px] aspect-[3/4] rounded-lg border border-[#E6DCCF] bg-[#F3ECE0] overflow-hidden';
const ACTIVE_WIDTH = 'bg-[#F3ECE0] font-semibold text-[#2D231E]';

let root = null;
let frame = null;
let body = null;
let isOpen = false;

const h = (tag, className, children = []) => {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    [].concat(children).forEach((child) => {
        if (child === null || child === undefined) {
            return;
        }

        node.append(child.nodeType ? child : document.createTextNode(child));
    });

    return node;
};

const cleanContent = (raw) => raw
    .replace(/\r\n/g, '\n')
    .replace(/[ \t]+$/gm, '')
    .replace(/[ \t]{2,}/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.trim())
    .filter((paragraph) => paragraph !== '');

const collectSnapshot = () => {
    const value = (selector) => (document.querySelector(selector)?.value || '').trim();
    const licenseSelect = document.querySelector('#license');
    const genres = [...document.querySelectorAll('input[name="genres[]"]:checked')]
        .map((input) => input.closest('label')?.querySelector('span span')?.textContent.trim())
        .filter(Boolean);
    const coverPreview = document.querySelector('[data-cover-preview]');
    const coverSrc = coverPreview?.src || '';

    return {
        title: value('#title') || 'Untitled story',
        author: value('#author'),
        license: licenseSelect ? licenseSelect.selectedOptions[0]?.textContent.trim() : '',
        sourceUrl: value('#source_url'),
        synopsis: value('#synopsis'),
        paragraphs: cleanContent(value('#content')),
        level: root.dataset.readerSavedLevel || '',
        genres,
        cover: coverSrc && !coverSrc.includes('placeholder-cover.svg') && !coverPreview?.classList.contains('opacity-40') ? coverSrc : '',
    };
};

const coverBox = (snapshot) => {
    const box = h('div', COVER_BOX);

    if (snapshot.cover) {
        box.classList.add('bg-cover', 'bg-center');
        box.style.backgroundImage = 'url("' + snapshot.cover + '")';
    } else {
        box.classList.add('flex', 'items-center', 'justify-center');
        box.append(h('span', 'text-xs font-semibold uppercase tracking-wider text-[#B5A89C]', ['No cover']));
    }

    return h('div', 'p-5', [box]);
};

const metaLine = (snapshot) => {
    const parts = [];

    if (snapshot.level) {
        parts.push('Level ' + snapshot.level);
    }

    if (snapshot.license && snapshot.license.toLowerCase() !== '—') {
        parts.push(snapshot.license);
    }

    return parts.join(' · ');
};

const render = () => {
    const snapshot = collectSnapshot();

    frame.innerHTML = '';
    frame.append(coverBox(snapshot));

    const card = h('div', CARD_SECTION);

    if (snapshot.genres.length > 0) {
        card.append(h('div', 'flex flex-wrap gap-1.5',
            snapshot.genres.map((genre) => h('span', GENRE_PILL, [genre])),
        ));
    }

    card.append(h('h1', 'text-xl sm:text-2xl text-[#2D231E] leading-snug', [snapshot.title]));

    if (snapshot.author) {
        card.append(h('p', 'text-sm text-[#2D231E]', ['by ' + snapshot.author]));
    }

    const meta = metaLine(snapshot);

    if (meta) {
        card.append(h('p', META_TEXT, [meta]));

        if (snapshot.sourceUrl) {
            card.append(h('p', META_TEXT + ' break-all', ['Source: ' + snapshot.sourceUrl]));
        }
    } else if (snapshot.sourceUrl) {
        card.append(h('p', META_TEXT + ' break-all', ['Source: ' + snapshot.sourceUrl]));
    }

    if (snapshot.synopsis) {
        card.append(h('div', 'p-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-md space-y-2',
            snapshot.synopsis.split(/\n+/).map((line) => h('p', 'text-sm text-[#2D231E] leading-relaxed', [line])),
        ));
    }

    frame.append(card);
    frame.append(h('div', 'border-t border-[#E6DCCF]'));

    body = h('div', 'p-5 space-y-4');

    if (snapshot.paragraphs.length === 0) {
        body.append(h('p', 'text-sm text-[#B5A89C] italic', ['Add your story text to see it here.']));
    } else {
        snapshot.paragraphs.forEach((paragraph) => {
            body.append(h('p', 'text-base text-[#2D231E] leading-loose', [paragraph]));
        });
    }

    frame.append(body);
};

const applyWidth = (width) => {
    Object.values(WIDTHS).forEach((cls) => frame.classList.remove(cls));
    frame.classList.add(WIDTHS[width] || WIDTHS.mobile);

    root.querySelectorAll('[data-reader-width]').forEach((button) => {
        const active = button.dataset.readerWidth === width;
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        ACTIVE_WIDTH.split(' ').forEach((cls) => button.classList.toggle(cls, active));
        button.classList.toggle('text-[#2D231E]', !active);
    });
};

function openReader() {
    root = document.querySelector('[data-story-reader]');
    frame = root ? root.querySelector('[data-reader-frame]') : null;

    if (!frame) {
        return;
    }

    isOpen = true;

    root.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    render();
    applyWidth('mobile');
}

function closeReader() {
    if (!isOpen || !root) {
        return;
    }

    isOpen = false;
    root.classList.add('hidden');
    frame.innerHTML = '';
    body = null;
    document.body.style.overflow = '';
}

export function initStoryReaderPreview() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-preview-story]')) {
            event.preventDefault();
            openReader();

            return;
        }

        if (!isOpen) {
            return;
        }

        if (event.target.closest('[data-reader-close]')) {
            closeReader();

            return;
        }

        const widthButton = event.target.closest('[data-reader-width]');

        if (widthButton) {
            applyWidth(widthButton.dataset.readerWidth);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            closeReader();
        }
    });
}
