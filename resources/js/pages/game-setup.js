/**
 * Game Setup — story picker (wordsearch) / word source + difficulty selection.
 * Continue activates only when both source and difficulty are selected.
 * Word Search can either pick a specific story or roll the surprise (random).
 */

import { openModal, closeModal } from '../components/modal.js';

let selectedSource = null;
let selectedDifficulty = null;
let selectedStoryId = null; // null = surprise / random story

export default function init() {
    const root = document.querySelector('[data-game-setup]');
    if (!root) return;

    const game = root.dataset.game || 'wordsearch';
    const usesWordBank = game === 'match' || game === 'sentence';
    const isWordSearch = game === 'wordsearch';

    const sourceCards = root.querySelectorAll('[data-source]');
    const diffCards = root.querySelectorAll('[data-difficulty]');
    const btnContinue = document.getElementById('btn-continue');

    const picker = document.getElementById('story-picker-modal');
    const openCard = root.querySelector('[data-source="open"]');
    const savedCard = root.querySelector('[data-source="saved"]');
    const storySearch = root.querySelector('[data-story-search]');
    const storyGrid = root.querySelector('[data-story-grid]');
    const storyOptions = root.querySelectorAll('[data-story-option]');
    const storyChip = root.querySelector('[data-story-chip]');
    const storyChipTitle = root.querySelector('[data-story-chip-title]');
    const storyClear = root.querySelector('[data-story-clear]');

    if (usesWordBank) {
        selectedSource = 'open';
    }

    function paint(card) {
        sourceCards.forEach((c) => {
            c.classList.remove('bg-[#2D231E]', 'text-white');
            c.style.background = '';
            c.style.color = '';
            c.querySelectorAll('p').forEach((p) => (p.style.color = ''));
            c.querySelectorAll('h3').forEach((h) => (h.style.color = ''));
        });

        if (!card) return;

        card.style.background = '#059669';
        card.style.color = '#fff';
        card.querySelectorAll('p').forEach((p) => (p.style.color = '#D1FAE5'));
        card.querySelectorAll('h3').forEach((h) => (h.style.color = '#fff'));
    }

    function setStory(id, title) {
        selectedSource = 'open';
        selectedStoryId = id;
        if (storyChip && storyChipTitle) {
            storyChipTitle.textContent = title;
            storyChip.classList.remove('hidden');
            storyChip.classList.add('flex');
        }
        paint(openCard);
        updateContinue();
    }

    function clearStory() {
        selectedStoryId = null;
        if (storyChip) {
            storyChip.classList.add('hidden');
            storyChip.classList.remove('flex');
        }
    }

    // Word Search: picking a story opens the picker modal.
    if (isWordSearch && openCard) {
        openCard.addEventListener('click', () => {
            if (picker) openModal(picker);
        });
    } else {
        sourceCards.forEach((card) => {
            card.addEventListener('click', handleSourceCard);
        });
    }

    if (isWordSearch && savedCard) {
        savedCard.addEventListener('click', () => {
            clearStory();
            selectedSource = 'saved';
            paint(savedCard);
            updateContinue();
        });
    }

    function handleSourceCard(e) {
        const card = e.currentTarget;
        selectedSource = card.dataset.source;
        paint(card);
        updateContinue();
    }

    storyOptions.forEach((option) => {
        option.addEventListener('click', () => {
            setStory(option.dataset.storyId, option.dataset.storyTitle || '');
            if (picker) closeModal(picker);
        });
    });

    if (storyClear) {
        storyClear.addEventListener('click', () => {
            clearStory();
            selectedSource = null;
            paint(null);
            updateContinue();
        });
    }

    if (storySearch && storyOptions.length > 0) {
        storySearch.addEventListener('input', () => {
            const q = storySearch.value.trim().toLowerCase();
            storyOptions.forEach((option) => {
                const title = (option.dataset.storyTitle || '').toLowerCase();
                option.style.display = !q || title.includes(q) ? '' : 'none';
            });
        });
    }

    diffCards.forEach((card) => {
        card.addEventListener('click', () => {
            diffCards.forEach((c) => {
                c.style.background = '';
                c.querySelector('h3').style.color = '#2D231E';
                c.querySelector('p').style.color = '';
            });

            selectedDifficulty = card.dataset.difficulty;
            card.style.background = '#059669';
            card.querySelector('h3').style.color = '#fff';
            card.querySelector('p').style.color = '#D1FAE5';

            updateContinue();
        });
    });

    function updateContinue() {
        if (selectedSource && selectedDifficulty) {
            btnContinue.disabled = false;
            btnContinue.className = 'w-full min-h-[52px] font-pixel text-sm bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-6 py-3 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-pointer';
            btnContinue.textContent = 'Continue';
            btnContinue.onclick = () => {
                let url = `/game/${game}?source=${selectedSource}&difficulty=${selectedDifficulty}`;
                if (selectedSource === 'open' && selectedStoryId) {
                    url += `&story=${encodeURIComponent(selectedStoryId)}`;
                }
                window.location.href = url;
            };
        } else {
            btnContinue.disabled = true;
            btnContinue.className = 'w-full min-h-[52px] font-pixel text-sm bg-[#E6DCCF] border-2 border-[#2D231E] text-[#8C7B70] px-6 py-3 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-not-allowed';
            btnContinue.textContent = 'Select options to continue';
            btnContinue.onclick = null;
        }
    }
}