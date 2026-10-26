import { openModal } from './modal.js';

let bound = false;

export function initBadgeDetail() {
    if (bound) {
        return;
    }

    bound = true;

    // Mouse: the delegated data-modal-open listener in modal.js opens the
    // dialog first, this only paints it. Both run in the same tick, so the
    // panel never flashes empty content.
    document.addEventListener('click', (event) => {
        const card = event.target instanceof Element ? event.target.closest('[data-badge-detail]') : null;

        if (card) {
            fillBadgeDetail(card);
        }
    });

    // Keyboard: a div does not fire click on Enter/Space, so activate it here
    // exactly like a button.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const card = event.target instanceof Element ? event.target.closest('[data-badge-detail]') : null;

        if (!card) {
            return;
        }

        const modal = document.getElementById('badge-detail-modal');

        if (!modal) {
            return;
        }

        event.preventDefault();
        fillBadgeDetail(card);
        openModal(modal);
    });
}

function fillBadgeDetail(card) {
    const modal = document.getElementById('badge-detail-modal');

    if (!modal) {
        return;
    }

    let data;

    try {
        data = JSON.parse(card.getAttribute('data-badge-detail') || '{}');
    } catch (error) {
        return;
    }

    const icon = modal.querySelector('[data-badge-icon]');
    const cardIcon = card.querySelector('span.w-16');

    if (icon && cardIcon) {
        // Reuse the card's own glyph and rarity colours, minus the card-only
        // bottom margin.
        icon.className = cardIcon.className.replace(/\bmb-3\b/, '').trim();
        icon.innerHTML = cardIcon.innerHTML;
    }

    setText(modal, '[data-badge-name]', data.name);
    setText(modal, '[data-badge-rarity]', data.rarity);
    setText(modal, '[data-badge-description]', data.description);

    const label = modal.querySelector('[data-badge-status-label]');
    const status = modal.querySelector('[data-badge-status-text]');
    const bar = modal.querySelector('[data-badge-status-bar]');
    const fill = modal.querySelector('[data-badge-status-fill]');

    if (!label || !status || !bar || !fill) {
        return;
    }

    if (data.earned) {
        label.textContent = 'Unlocked at';
        status.textContent = data.unlockedAt || '';
        bar.classList.add('hidden');
    } else if (data.hasProgress) {
        label.textContent = 'Your progress';
        status.textContent = `${count(data.current)} / ${count(data.target)}${data.unit ? ' ' + data.unit : ''}`;
        bar.classList.remove('hidden');
        fill.style.width = `${data.pct || 0}%`;
    } else {
        label.textContent = 'Status';
        status.textContent = 'Not unlocked yet';
        bar.classList.add('hidden');
    }

    const reach = modal.querySelector('[data-badge-reach]');
    const reachText = modal.querySelector('[data-badge-reach-text]');
    const reachFill = modal.querySelector('[data-badge-reach-fill]');

    if (!reach || !reachText || !reachFill) {
        return;
    }

    if (data.students > 0) {
        reach.classList.remove('hidden');
        reachText.textContent = `${data.percent}% of students got this achievement`;
        reachFill.style.width = `${data.percent || 0}%`;
    } else {
        reach.classList.add('hidden');
    }
}

function setText(root, selector, value) {
    const node = root.querySelector(selector);

    if (node) {
        node.textContent = value ?? '';
    }
}

function count(value) {
    return Number(value || 0).toLocaleString('en-US');
}
