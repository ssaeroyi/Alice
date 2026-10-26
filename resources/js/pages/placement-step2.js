/**
 * Placement vocabulary pager. Server renders all 10 word panels; this module
 * reveals one per screen (quiz-style) and keeps the radio answer inputs in
 * place so the single classic POST to placement.step2.submit stays untouched.
 *
 * NEXT unlocks when the current word is answered and advances one panel,
 * becoming SUBMIT WORDS on the last word (that hand-in uses the shared
 * confirm-form modal). BACK steps to the previous panel, and on the first
 * panel leaves for the previous step via data-back-url. Saved answers come
 * back prefilled from the server on a re-visit.
 */

const initVocabPager = () => {
    const form = document.getElementById('vocab-quiz');

    if (!form) {
        return;
    }

    const panels = [...form.querySelectorAll('[data-vpanel]')];

    if (panels.length === 0) {
        return;
    }

    const dots = document.getElementById('vocab-dots');
    const next = document.getElementById('vocab-next');
    const back = document.getElementById('vocab-back');
    const submitBtn = document.getElementById('vocab-submit');
    let current = 0;

    const panelAnswered = (panel) => panel.querySelector('input[type="radio"]:checked') !== null;

    const sync = () => {
        const last = current === panels.length - 1;
        const answered = panelAnswered(panels[current]);

        panels.forEach((panel, i) => {
            panel.hidden = i !== current;
        });

        dots?.querySelectorAll('span').forEach((dot, i) => {
            const done = i !== current && panelAnswered(panels[i]);
            dot.classList.toggle('bg-amber-400', i === current);
            dot.classList.toggle('bg-emerald-500', done);
            dot.classList.toggle('bg-white', i !== current && !done);
        });

        if (next) {
            next.toggleAttribute('hidden', last);
            next.disabled = !answered;
        }

        if (submitBtn) {
            submitBtn.toggleAttribute('hidden', !last);
            submitBtn.disabled = !answered;
        }

        panels[current].scrollIntoView({ block: 'nearest' });
    };

    for (const panel of panels) {
        for (const radio of panel.querySelectorAll('input[type="radio"]')) {
            radio.addEventListener('change', sync);
        }
    }

    next?.addEventListener('click', () => {
        if (current < panels.length - 1) {
            current += 1;
            sync();
        }
    });

    back?.addEventListener('click', () => {
        if (current > 0) {
            current -= 1;
            sync();
            return;
        }

        window.location.href = back.dataset.backUrl;
    });

    sync();
};

export { initVocabPager };
