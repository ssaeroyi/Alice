const REQUESTABLE = new Set(['missing', 'stale', 'partial', 'stalled']);

const TONES = {
    amber: 'bg-amber-400',
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
};

const TONE_CLASSES = Object.values(TONES);

const CORE_SELECTORS = {
    title: '#title',
    author: '#author',
    license: '#license',
    content: '#content',
};

const FIELD_LABELS = {
    title: 'Title',
    author: 'Author',
    license: 'License',
    content: 'Story text',
};

function empty(value) {
    return typeof value !== 'string' || value.trim() === '';
}

/**
 * Per-tab attention dots on the story form. Each dot answers a single
 * question — "is there an action waiting on this tab?" — driven by the
 * server-rendered `data-tab-status` baseline plus live signals: field input,
 * the narrator poll (`alice:narration-status`), and the quiz count badge.
 *
 * Story   -> amber when any core field (title/author/license/content) is empty
 * Quiz    -> amber when the story is publishable-or-published and has 0 questions
 * Journal -> amber when the battle is on but the authored pool is a half set (1-2 of 3)
 * Publish -> amber  = checklist incomplete,
 *            sky    = narration action needed (published + requestable state,
 *                     or the draft text drifted from what the audio covers),
 *            emerald = complete draft not yet published ("ready to publish")
 *
 * Archived stories never show a dot, and create() sends an empty payload so a
 * brand-new blank form stays quiet.
 */
export function initStoryTabDots() {
    document.querySelectorAll('[data-story-tabs]').forEach((root) => {
        const dots = {
            story: root.querySelector('[data-tab-dot="story"]'),
            quiz: root.querySelector('[data-tab-dot="quiz"]'),
            journal: root.querySelector('[data-tab-dot="journal"]'),
            publish: root.querySelector('[data-tab-dot="publish"]'),
        };

        if (!dots.story || !dots.quiz || !dots.journal || !dots.publish) {
            return;
        }

        let config;
        try {
            config = JSON.parse(root.dataset.tabStatus || '{}');
        } catch (error) {
            return;
        }

        // create() mode ships an empty array: no dots until the story exists.
        if (!config || Array.isArray(config) || typeof config !== 'object') {
            return;
        }

        const coreField = {};
        for (const [key, selector] of Object.entries(CORE_SELECTORS)) {
            coreField[key] = root.querySelector(selector);
        }

        const content = coreField.content;
        let narrationState = config.narration?.state ?? null;
        const narratedText = typeof config.narration?.narrated_text === 'string'
            ? config.narration.narrated_text
            : null;
        const published = config.published === true;
        const archived = config.archived === true;
        const publishable = config.publishable === true;
        const missingCore = Array.isArray(config.missingCore) ? config.missingCore : [];
        const hasBaseline = narratedText !== null;

        const setDot = (dot, tone, reason) => {
            if (!dot) {
                return;
            }

            dot.removeAttribute('aria-hidden');

            if (tone) {
                dot.classList.remove('hidden', ...TONE_CLASSES);
                dot.classList.add(TONES[tone]);
                dot.setAttribute('role', 'img');
                dot.setAttribute('aria-label', reason);
                dot.title = reason;
            } else {
                dot.classList.add('hidden');
                dot.classList.remove(...TONE_CLASSES);
                dot.removeAttribute('role');
                dot.removeAttribute('aria-label');
                dot.title = '';
                dot.setAttribute('aria-hidden', 'true');
            }
        };

        const liveStoryMissing = () => Object.entries(coreField)
            .filter(([, field]) => field && empty(field.value))
            .map(([key]) => FIELD_LABELS[key]);

        const draftDriftedFromAudio = () => hasBaseline
            && content instanceof HTMLTextAreaElement
            && content.value.trim() !== narratedText.trim();

        const narrateNeeded = () => published
            && (REQUESTABLE.has(narrationState) || draftDriftedFromAudio());

        const refreshStory = () => {
            const missing = liveStoryMissing();

            setDot(
                dots.story,
                missing.length > 0 ? 'amber' : null,
                missing.length > 0 ? `Missing: ${missing.join(', ')}` : '',
            );
        };

        const refreshQuiz = () => {
            const count = Number(root.querySelector('[data-story-tab="quiz"] [data-quiz-count]')?.textContent
                ?? config.quiz?.count
                ?? 0);

            setDot(
                dots.quiz,
                count === 0 && (published || publishable) && !archived
                    ? 'amber'
                    : null,
                count === 0 ? 'No questions yet — add a quiz to go with the story' : '',
            );
        };

        // Journal pool: a half-authored pool (1-2 moves) still pads with
        // auto moves, but the tab deserves a nudge to finish the set of 3.
        // An empty pool is silent on purpose: auto moves cover it fine.
        const refreshJournal = () => {
            const count = Number(root.querySelector('[data-story-tab="journal"] [data-mission-count]')?.textContent
                ?? config.journal?.count
                ?? 0);
            const enabled = config.journal?.enabled === true;

            setDot(
                dots.journal,
                enabled && count > 0 && count < 3
                    ? 'amber'
                    : null,
                count > 0 && count < 3
                    ? `Only ${count} authored move${count === 1 ? '' : 's'} of the 3-move pool — add more so battles shuffle a fresh set`
                    : '',
            );
        };

        const refreshPublish = () => {
            const reasons = [];
            let tone = null;

            if (archived) {
                setDot(dots.publish, null, '');
                return;
            }

            if (!publishable) {
                reasons.push(`Publishing needs: ${missingCore.join(', ') || 'title, author, license, text'}`);
                tone = 'amber';
            }

            if (narrateNeeded()) {
                reasons.push('Narration audio is out of date — regenerate on Publish');
                tone = tone ?? 'sky';
            }

            if (!published && publishable && !archived) {
                reasons.push('Ready to publish');
                tone = tone ?? 'emerald';
            }

            setDot(dots.publish, tone, reasons.join(' · '));
        };

        for (const [key, field] of Object.entries(coreField)) {
            if (!field) {
                continue;
            }

            const eventName = key === 'license' ? 'change' : 'input';

            field.addEventListener(eventName, () => {
                refreshStory();
                refreshPublish();
            });
        }

        // Narration polling keeps the publish dot honest as a run progresses.
        window.document.addEventListener('alice:narration-status', (event) => {
            if (event instanceof CustomEvent && event.detail && typeof event.detail.state === 'string') {
                narrationState = event.detail.state;
                refreshPublish();
            }
        });

        // The quiz count badge changes only via server round-trips; watch it.
        const quizBadge = root.querySelector('[data-story-tab="quiz"] [data-quiz-count]');

        if (quizBadge && 'MutationObserver' in window) {
            new MutationObserver(() => refreshQuiz()).observe(quizBadge, {
                childList: true,
                characterData: true,
                subtree: true,
            });
        }

        refreshStory();
        refreshQuiz();
        refreshJournal();
        refreshPublish();
    });
}