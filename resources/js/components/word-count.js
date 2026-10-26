const WORDS_PER_MINUTE = 200;

export function initWordCount() {
    document.querySelectorAll('[data-word-count-target]').forEach((textarea) => {
        const key = textarea.dataset.wordCountTarget;
        const wordOutput = document.querySelector(`[data-word-count="${key}"]`);
        const charOutput = document.querySelector(`[data-char-count="${key}"]`);
        const readOutput = document.querySelector(`[data-read-time="${key}"]`);
        const warning = document.querySelector(`[data-word-limit-warning="${key}"]`);

        const update = () => {
            const text = textarea.value.trim();
            const words = text === '' ? 0 : text.split(/\s+/).length;
            const chars = textarea.value.length;

            if (wordOutput) {
                wordOutput.textContent = words + (words === 1 ? ' word' : ' words');
            }

            if (charOutput) {
                charOutput.textContent = chars + (chars === 1 ? ' character' : ' characters');
            }

            if (readOutput) {
                readOutput.classList.toggle('hidden', words === 0);

                if (words > 0) {
                    const minutes = Math.max(1, Math.round(words / WORDS_PER_MINUTE));
                    readOutput.textContent = ' \u00B7 ~' + minutes + ' min read';
                }
            }

            if (warning) {
                const wordLimit = Number(textarea.dataset.wordLimit);
                const charLimit = Number(textarea.dataset.charLimit);
                const over = (wordLimit > 0 && words > wordLimit) || (charLimit > 0 && chars > charLimit);

                warning.classList.toggle('hidden', !over);
            }
        };

        textarea.addEventListener('input', update);
        update();
    });
}