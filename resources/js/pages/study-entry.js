/**
 * Shared behaviors for dictionary-style entry views: speech synthesis for
 * [data-say] triggers and the SAVE toggle. Used by the dictionary browser
 * and the saved-word detail page. A saved word can be removed again by
 * tapping its button, the same way the reader works.
 */
const SAVE_CLASS = 'flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 bg-amber-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase';
const SAVED_CLASS = 'flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 bg-emerald-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase';

export function say(text) {
    if (!text || !('speechSynthesis' in window)) {
        return;
    }

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.9;
    utterance.lang = 'en-US';
    window.speechSynthesis.speak(utterance);
}

function toggleButton(word, saved) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = saved ? SAVED_CLASS : SAVE_CLASS;
    button.textContent = saved ? 'In your words' : 'Save to my words';
    if (saved) {
        button.title = 'Tap to remove from your words';
        button.dataset.unsaveWord = word;
    } else {
        button.dataset.saveWord = word;
    }
    return button;
}

async function toggleSave(root, button) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const errorLine = root.querySelector('[data-save-error]');
    const unsaving = button.hasAttribute('data-unsave-word');
    const word = button.dataset.saveWord ?? button.dataset.unsaveWord;

    button.disabled = true;
    errorLine?.classList.add('hidden');

    try {
        const response = unsaving
            ? await fetch(`/words/${encodeURIComponent(word)}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            })
            : await fetch('/words', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ word }),
            });

        const data = await response.json().catch(() => ({}));
        const ok = unsaving ? data.deleted === true : data.saved === true;

        if (!response.ok || !ok) {
            throw new Error(data.message ?? (unsaving ? 'Could not remove that word.' : 'Could not save that word.'));
        }

        button.replaceWith(toggleButton(word, !unsaving));
        bindSave(root);
    } catch (error) {
        button.disabled = false;

        if (errorLine) {
            errorLine.textContent = error.message;
            errorLine.classList.remove('hidden');
        }
    }
}

function bindSave(root) {
    const button = root.querySelector('[data-save-word]:not([data-save-bound]), [data-unsave-word]:not([data-save-bound])');

    if (!button) {
        return;
    }

    button.dataset.saveBound = 'true';
    button.addEventListener('click', () => toggleSave(root, button));
}

/** Bind say/save behaviors inside a root element. Safe to re-run after swaps. */
export function bindEntry(root = document) {
    root.querySelectorAll('[data-say]').forEach((button) => {
        if (button.dataset.sayBound) {
            return;
        }

        button.dataset.sayBound = 'true';
        button.addEventListener('click', () => say(button.dataset.say));
    });

    bindSave(root);
}

export default function studyEntry() {
    bindEntry(document);
}
