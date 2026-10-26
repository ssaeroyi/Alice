import { say } from './study-entry.js';
import { microphoneSupported, setPronounceTarget, stopPronounceCheck } from '../components/speech.js';
import { openModal } from '../components/modal.js';

/**
 * Flashcard session (Anki-style piles): meaning on front → flip to reveal
 * the word (tactile 3D card) → grade → next.
 *
 * Piles (three counters, same as Anki deck counts):
 *   green  - words left to pass this session (starts at the batch size,
 *            only decrements when a card grades Good/Easy - including
 *            when a replayed card is finally passed).
 *   blue   - cards parked as HARD: they go to the back of the queue,
 *            resurface later in this session, and keep looping until
 *            passed. Mastery is NOT reset by hard (server keeps level).
 *   red    - cards marked FORGOT: they go to the very front of the
 *            queue (immediate replay) and keep looping until passed.
 *            Forgot resets mastery to 0 and stamps a miss server-side.
 *
 * The session ends only when every card has been passed (green = 0),
 * so an unresolved card can never be "left behind" - forced-pass.
 * Grading is currency-free - never touches RewardService.
 *
 * The mic button opens a temporary "say it out loud" modal that hosts
 * the pronunciation check; listening is aborted on card changes, on
 * modal close, and before the speaker (TTS) plays again.
 */
export default function studyFlashcards() {
    const stage = document.querySelector('[data-stage]');

    if (!stage) {
        return;
    }

    let cards;

    try {
        cards = JSON.parse(stage.dataset.cards ?? '[]');
    } catch {
        cards = [];
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const supportsSpeech = 'speechSynthesis' in window;
    const supportsMic = microphoneSupported();

    const els = {
        chipHard: document.querySelector('[data-pile-hard]'),
        chipForgot: document.querySelector('[data-pile-forgot]'),
        chipGreen: document.querySelector('[data-pile-green]'),
        cardSection: stage.querySelector('section'),
        cardGroup: stage.querySelector('[data-flip]'),
        word: stage.querySelector('[data-word]'),
        definition: stage.querySelector('[data-definition]'),
        example: stage.querySelector('[data-example]'),
        pos: stage.querySelector('[data-pos]'),
        reveal: stage.querySelector('[data-reveal]'),
        gradeRow: stage.querySelector('[data-grade-row]'),
        gradeButtons: [...stage.querySelectorAll('[data-grade]')],
        gradeError: stage.querySelector('[data-grade-error]'),
        sayPrompt: stage.querySelector('[data-say-prompt]'),
        sayModalOpen: stage.querySelector('[data-say-modal-open]'),
        sayModal: document.getElementById('say-check-modal'),
        sayModalWord: document.querySelector('[data-say-modal-word]'),
        sayCheck: document.querySelector('#say-check-modal [data-pronounce-check]'),
        endScreen: document.querySelector('[data-session-end]'),
        endRemembered: document.querySelector('[data-end-remembered]'),
        endHard: document.querySelector('[data-end-hard]'),
        endForgot: document.querySelector('[data-end-forgot]'),
        tricky: document.querySelector('[data-tricky]'),
        trickyList: document.querySelector('[data-tricky-list]'),
    };

    const total = cards.length;
    let queue = [...cards];
    const passed = new Set();
    const forgotIds = new Set();
    const hardIds = new Set();
    const attempts = new Map();
    let forgotTally = 0;
    let hardTally = 0;
    let current = null;
    let revealed = false;
    let grading = false;
    let finished = false;
    let keyHandler = null;

    // ------------------------------------------------------------------

    const renderCounters = () => {
        if (els.chipGreen) els.chipGreen.textContent = total - passed.size;
        if (els.chipHard) els.chipHard.textContent = hardIds.size;
        if (els.chipForgot) els.chipForgot.textContent = forgotIds.size;
    };

    const stopActiveCheck = () => {
        if (els.sayCheck) {
            stopPronounceCheck(els.sayCheck);
        }
    };

    const renderCard = (card) => {
        current = card;
        revealed = false;

        // A new card invalidates any running pronunciation check.
        stopActiveCheck();

        els.word.textContent = card.word;
        els.definition.textContent = card.definition ?? '';
        els.pos.textContent = card.part_of_speech ?? '';
        if (els.example) {
            els.example.textContent = card.example_sentence ?? '';
            els.example.classList.toggle('hidden', !(card.example_sentence ?? ''));
        }
        els.cardGroup?.classList.remove('flipped');
        els.gradeRow?.classList.add('hidden');
        els.gradeRow?.classList.remove('grid');
        els.gradeError?.classList.add('hidden');
        els.reveal.disabled = false;
        els.reveal.classList.remove('hidden');

        if (!supportsSpeech) {
            els.sayPrompt?.classList.add('hidden');
        } else {
            els.sayPrompt?.classList.remove('hidden');
        }

        if (!supportsMic) {
            els.sayModalOpen?.classList.add('hidden');
        } else {
            els.sayModalOpen?.classList.remove('hidden');
        }

        if (!reducedMotion) {
            els.cardSection.style.opacity = '0';
            els.cardSection.style.transform = 'translateX(12px)';
            requestAnimationFrame(() => {
                els.cardSection.style.transition = 'opacity 180ms ease, transform 180ms ease';
                els.cardSection.style.opacity = '1';
                els.cardSection.style.transform = 'translateX(0)';
            });
        }
    };

    const nextCard = () => {
        grading = false;
        els.gradeButtons.forEach((b) => { b.disabled = false; });

        if (queue.length > 0) {
            renderCard(queue.shift());
            return;
        }

        finish();
    };

    const finish = () => {
        finished = true;
        stage.classList.add('hidden');
        els.endScreen?.classList.remove('hidden');
        stopActiveCheck();

        if (els.endRemembered) els.endRemembered.textContent = total;
        if (els.endHard) els.endHard.textContent = hardTally;
        if (els.endForgot) els.endForgot.textContent = forgotTally;

        const trickyWords = cards
            .filter((card) => (attempts.get(card.id) ?? 0) >= 2)
            .map((card) => card.word);

        if (trickyWords.length > 0 && els.trickyList) {
            els.trickyList.innerHTML = '';
            for (const word of trickyWords.slice(0, 3)) {
                const item = document.createElement('li');
                item.className = 'inline-flex items-center gap-1.5 bg-[#FAF6EE] border border-[#E6DCCF] rounded-full px-3 py-1 font-pixel text-[9px] font-bold text-[#2D231E]';
                item.textContent = word;
                els.trickyList.appendChild(item);
            }
            els.tricky?.classList.remove('hidden');
        } else {
            els.tricky?.classList.add('hidden');
        }

        if (keyHandler) {
            document.removeEventListener('keydown', keyHandler);
            keyHandler = null;
        }
    };

    const advance = () => {
        if (!reducedMotion) {
            els.cardSection.style.transition = 'opacity 150ms ease, transform 150ms ease';
            els.cardSection.style.opacity = '0';
            els.cardSection.style.transform = 'translateX(-12px)';
            setTimeout(nextCard, 160);
        } else {
            nextCard();
        }
    };

    const recordAttempt = (card) => {
        attempts.set(card.id, (attempts.get(card.id) ?? 0) + 1);
    };

    const grade = (value) => {
        if (!current || grading || !revealed || finished) {
            return;
        }

        const card = current;
        const id = card.id;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const saveGrade = async () => {
            const response = await fetch('/study/grade', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ saved_word_id: id, grade: value }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
        };

        if (value === 'good' || value === 'easy') {
            // Passed: leaves the session immediately (optimistic - the green
            // pill ticks down on tap instead of waiting on the server
            // round-trip). The grade is persisted in the background; on
            // failure the card is rolled back to the front of the queue and
            // its pile membership restored, so it can never be silently
            // counted as passed or lost from the session.
            const wasForgot = forgotIds.has(id);
            const wasHard = hardIds.has(id);

            passed.add(id);
            forgotIds.delete(id);
            hardIds.delete(id);
            grading = true;
            els.gradeButtons.forEach((b) => { b.disabled = true; });
            els.gradeError?.classList.add('hidden');
            renderCounters();
            advance();

            saveGrade().catch(() => {
                passed.delete(id);
                if (wasForgot) forgotIds.add(id);
                if (wasHard) hardIds.add(id);
                queue.unshift(card);
                renderCounters();
                els.gradeError?.classList.remove('hidden');
            });

            return;
        }

        // Hard / Forgot: replayed this session until finally passed, so the
        // server must record the miss before the card resurfaces.
        grading = true;
        els.gradeButtons.forEach((b) => { b.disabled = true; });
        els.gradeError?.classList.add('hidden');

        saveGrade()
            .then(() => {
                if (value === 'again') {
                    forgotIds.add(id);
                    hardIds.delete(id);
                    forgotTally += 1;
                    recordAttempt(card);
                    queue.unshift(card);
                } else {
                    hardIds.add(id);
                    forgotIds.delete(id);
                    hardTally += 1;
                    recordAttempt(card);
                    queue.push(card);
                }

                renderCounters();
                advance();
            })
            .catch(() => {
                grading = false;
                els.gradeButtons.forEach((b) => { b.disabled = false; });
                els.gradeError?.classList.remove('hidden');
            });
    };

    const reveal = () => {
        if (revealed || !current || finished) {
            return;
        }

        revealed = true;
        els.cardGroup?.classList.add('flipped');
        els.gradeRow?.classList.remove('hidden');
        els.gradeRow?.classList.add('grid');
        els.reveal.disabled = true;
    };

    // ------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------

    els.reveal?.addEventListener('click', reveal);
    els.sayPrompt?.addEventListener('click', () => {
        stopActiveCheck();
        if (current) {
            say(current.word);
        }
    });

    // Open the say-it-out-loud modal for THIS card: repoint the widget,
    // abort any stale listening, then show it.
    els.sayModalOpen?.addEventListener('click', () => {
        if (!current) {
            return;
        }

        stopActiveCheck();
        if (els.sayCheck) {
            setPronounceTarget(els.sayCheck, current.word);
        }
        if (els.sayModalWord) {
            els.sayModalWord.textContent = current.word;
        }
        if (els.sayModal) {
            openModal(els.sayModal);
        }
    });

    // Closing the modal mid-listening must stop the recognition.
    els.sayModal?.addEventListener('alice:modal-close', stopActiveCheck);

    // Whole card is the flip target; taps inside interactive controls
    // (speaker, mic) must not flip it.
    els.cardGroup?.addEventListener('click', (event) => {
        if (event.target.closest?.('button')) {
            return;
        }
        reveal();
    });

    els.gradeButtons.forEach((button) => {
        button.addEventListener('click', () => grade(button.dataset.grade));
    });

    keyHandler = (event) => {
        if (finished) {
            return;
        }

        if (event.target.matches?.('input, textarea, select')) {
            return;
        }

        if (event.code === 'Space') {
            event.preventDefault();
            reveal();
        }
    };

    document.addEventListener('keydown', keyHandler);

    // Boot.
    renderCounters();
    stage.classList.remove('hidden');

    if (queue.length > 0) {
        renderCard(queue.shift());
    } else {
        finish();
    }
}