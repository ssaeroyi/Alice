/**
 * Shared behaviors for dictionary-style entry views: speech synthesis for
 * [data-say] triggers and the optimistic SAVE button. Used by the
 * dictionary browser and the saved-word detail page.
 */
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

/** Bind say/save behaviors inside a root element. Safe to re-run after swaps. */
export function bindEntry(root = document) {
    root.querySelectorAll('[data-say]').forEach((button) => {
        if (button.dataset.sayBound) {
            return;
        }

        button.dataset.sayBound = 'true';
        button.addEventListener('click', () => say(button.dataset.say));
    });

    const saveButton = root.querySelector('[data-save-word]:not([data-save-bound])');

    if (!saveButton) {
        return;
    }

    saveButton.dataset.saveBound = 'true';

    saveButton.addEventListener('click', async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const errorLine = root.querySelector('[data-save-error]');
        const original = saveButton.innerHTML;

        saveButton.disabled = true;
        errorLine?.classList.add('hidden');

        try {
            const response = await fetch('/words', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ word: saveButton.dataset.saveWord }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.saved) {
                throw new Error(data.message ?? 'Could not save that word.');
            }

            // Optimistic flip - becomes the saved chip in place.
            const chip = document.createElement('span');
            chip.className = 'flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 bg-emerald-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] uppercase cursor-default';
            chip.textContent = data.already_saved ? 'Already saved' : 'In your words';
            saveButton.replaceWith(chip);
        } catch (error) {
            saveButton.disabled = false;

            if (errorLine) {
                errorLine.textContent = error.message;
                errorLine.classList.remove('hidden');
            } else {
                saveButton.innerHTML = original;
            }
        }
    });
}

export default function studyEntry() {
    bindEntry(document);
}
