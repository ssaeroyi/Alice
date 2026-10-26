const CARD = 'w-full bg-white border border-[#E6DCCF] rounded-lg p-5 sm:p-6 space-y-4 shadow-[0_8px_24px_rgba(45,35,30,0.06)]';
const CHIP = 'inline-block text-[11px] font-semibold uppercase tracking-wide text-[#2D231E]';
const OPTION = 'w-full text-left min-h-[44px] px-4 py-2.5 rounded-md border border-[#E6DCCF] bg-white text-sm sm:text-base text-[#2D231E] cursor-pointer flex items-center gap-3 transition-colors hover:border-[#2D231E]';
const OPTION_KEY = 'shrink-0 inline-flex items-center justify-center w-6 h-6 rounded border border-[#E6DCCF] text-[11px] font-semibold text-[#2D231E]';
const PRIMARY_BTN = 'min-h-[44px] px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold cursor-pointer hover:bg-emerald-700 disabled:opacity-40 disabled:pointer-events-none';
const SECONDARY_BTN = 'min-h-[44px] px-5 py-2.5 rounded-lg border border-[#E6DCCF] bg-white text-sm font-semibold text-[#2D231E] cursor-pointer hover:border-[#2D231E] transition-colors';
const SELECTED = 'bg-[#F3ECE0]';

const TYPE_LABELS = {
    mcq: 'MCQ',
    true_false: 'TRUE-FALSE',
    identification: 'IDENTIFY',
    matching: 'MATCHING',
    sequence: 'SEQUENCE',
};

let root = null;
let stage = null;
let isOpen = false;
let questions = [];
let idx = 0;
let score = 0;
let state = null;
let checked = false;

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

const safeParse = (value) => {
    try {
        const parsed = JSON.parse(value);

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const shuffle = (list) => {
    const copy = list.slice();

    for (let i = copy.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
};

const normalize = (value) => value.trim().toLowerCase().replace(/\s+/g, ' ').replace(/[.!?,;:]+$/, '');

const collectQuestions = () => [].concat(...document.querySelectorAll('[data-quiz-edit][data-question-id]'))
    .map((button) => {
        let options = ['a', 'b', 'c', 'd']
            .map((key) => ({ key, text: (button.dataset['question' + key.toUpperCase()] || '').trim() }))
            .filter((option) => option.text !== '');

        if (button.dataset.questionType === 'true_false' && options.length === 0) {
            options = [{ key: 'a', text: 'TRUE' }, { key: 'b', text: 'FALSE' }];
        }

        return {
            type: button.dataset.questionType,
            text: button.dataset.questionText,
            options,
            correct: (button.dataset.questionCorrect || '').toLowerCase(),
            answers: safeParse(button.dataset.questionAnswers),
            pairs: safeParse(button.dataset.questionPairs),
            sequence: safeParse(button.dataset.questionSequence),
        };
    })
    .filter((question) => question.text && TYPE_LABELS[question.type]);

const setBodyLock = (locked) => {
    document.body.style.overflow = locked ? 'hidden' : '';
};

function openPreview() {
    root = document.querySelector('[data-quiz-preview]');
    stage = root ? root.querySelector('[data-preview-stage]') : null;

    if (!stage) {
        return;
    }

    questions = collectQuestions();

    if (questions.length === 0) {
        return;
    }

    idx = 0;
    score = 0;
    isOpen = true;

    root.classList.remove('hidden');
    setBodyLock(true);
    renderQuestion();
}

function closePreview() {
    if (!isOpen || !root) {
        return;
    }

    isOpen = false;
    root.classList.add('hidden');
    stage.innerHTML = '';
    setBodyLock(false);
}

const headerRow = () => h('div', 'flex flex-wrap items-center justify-between gap-2', [
    h('span', CHIP, ['Question ' + (idx + 1) + ' of ' + questions.length]),
    h('span', CHIP, ['Score ' + score]),
]);

const feedbackBox = () => {
    const box = h('div', 'hidden p-4 rounded-md border border-[#E6DCCF]');
    box.dataset.previewFeedback = '';

    return box;
};

const footerRow = (answerArea, feedback) => {
    const check = h('button', PRIMARY_BTN, ['Check answer']);
    check.type = 'button';
    check.disabled = true;

    const next = h('button', PRIMARY_BTN, [idx === questions.length - 1 ? 'See results' : 'Next question']);
    next.type = 'button';
    next.classList.add('hidden');

    const refreshCheck = () => {
        check.disabled = checked || !canCheck(state);
    };

    check.addEventListener('click', () => {
        if (checked || !canCheck(state)) {
            return;
        }

        checked = true;
        const correct = gradeCurrent();

        if (correct) {
            score += 1;
        }

        answerArea.classList.add('pointer-events-none', 'opacity-80');
        revealInto(answerArea);
        showFeedback(feedback, correct);

        check.classList.add('hidden');
        next.classList.remove('hidden');
        next.focus({ preventScroll: true });
    });

    next.addEventListener('click', () => {
        if (idx === questions.length - 1) {
            renderResults();

            return;
        }

        idx += 1;
        renderQuestion();
    });

    state.refreshCheck = refreshCheck;

    return h('div', 'flex justify-end gap-2', [check, next]);
};

const canCheck = (current) => {
    if (!current) {
        return false;
    }

    if (current.kind === 'choice') {
        return current.key !== null;
    }

    if (current.kind === 'id') {
        return current.value.trim() !== '';
    }

    if (current.kind === 'match') {
        return Object.keys(current.assign).length === current.pairs.length;
    }

    if (current.kind === 'seq') {
        return current.order.length === current.items.length;
    }

    return false;
};

const gradeCurrent = () => {
    if (state.kind === 'choice') {
        return state.key === questions[idx].correct;
    }

    if (state.kind === 'id') {
        return questions[idx].answers.some((answer) => normalize(String(answer)) === normalize(state.value));
    }

    if (state.kind === 'match') {
        return questions[idx].pairs.every((pair, leftIndex) => {
            const assigned = state.assign[leftIndex];

            return assigned !== undefined && state.rights[assigned].origin === leftIndex;
        });
    }

    if (state.kind === 'seq') {
        return state.order.every((itemIndex, position) => itemIndex === position);
    }

    return false;
};

const revealLines = (lines) => h('div', 'mt-2 space-y-1',
    lines.map((line) => h('p', 'text-sm text-[#2D231E]', [line])),
);

const showFeedback = (feedback, correct) => {
    feedback.classList.remove('hidden');
    feedback.classList.remove('border-emerald-200', 'bg-emerald-50', 'border-rose-200', 'bg-rose-50');
    feedback.classList.add(correct ? 'border-emerald-200' : 'border-rose-200', correct ? 'bg-emerald-50' : 'bg-rose-50');
    feedback.innerHTML = '';

    const title = h('p', 'text-sm font-semibold ' + (correct ? 'text-emerald-700' : 'text-rose-700'), [correct ? 'Correct' : 'Incorrect']);
    feedback.append(title);

    const q = questions[idx];

    if (!correct) {
        if (state.kind === 'choice') {
            const match = q.options.find((option) => option.key === q.correct);
            feedback.append(revealLines(['Correct answer: ' + (match ? match.text : q.correct.toUpperCase())]));
        } else if (state.kind === 'match') {
            feedback.append(revealLines(q.pairs.map((pair) => pair.left + ' \u2192 ' + pair.right)));
        } else if (state.kind === 'seq') {
            feedback.append(revealLines(
                q.sequence.map((item, position) => (position + 1) + '. ' + item),
            ));
        }
    }

    if (state.kind === 'id') {
        feedback.append(revealLines(['Accepted answers: ' + q.answers.map((answer) => String(answer)).join(', ')]));
    }
};

const revealInto = (answerArea) => {
    if (state.kind !== 'choice') {
        return;
    }

    state.buttons.forEach((button) => {
        if (button.dataset.optKey === questions[idx].correct) {
            button.classList.remove(SELECTED);
            button.classList.add('bg-emerald-50');
        } else if (button.dataset.optKey === state.key && state.key !== questions[idx].correct) {
            button.classList.remove(SELECTED);
            button.classList.add('bg-rose-50');
        }
    });
};

const renderChoice = (q) => {
    state = { kind: 'choice', key: null, buttons: [] };

    const wrap = h('div', 'space-y-3');

    q.options.forEach((option) => {
        const button = h('button', OPTION, [
            h('span', OPTION_KEY, [option.key.toUpperCase()]),
            h('span', 'min-w-0 flex-1', [option.text]),
        ]);
        button.type = 'button';
        button.dataset.optKey = option.key;

        button.addEventListener('click', () => {
            if (checked) {
                return;
            }

            state.key = option.key;
            state.buttons.forEach((sibling) => sibling.classList.remove(SELECTED));
            button.classList.add(SELECTED);
            state.refreshCheck();
        });

        state.buttons.push(button);
        wrap.append(button);
    });

    return wrap;
};

const renderIdentification = (q) => {
    state = { kind: 'id', value: '' };

    const input = h('input', 'w-full min-h-[44px] px-4 rounded-md border border-[#E6DCCF] bg-white text-base text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]');
    input.type = 'text';
    input.placeholder = 'Type your answer';
    input.autocomplete = 'off';

    input.addEventListener('input', () => {
        state.value = input.value;
        state.refreshCheck();
    });

    return h('div', 'space-y-2', [
        input,
        h('p', 'text-xs text-[#2D231E]', ['Spelling counts, capital letters and punctuation do not.']),
    ]);
};

const clearPair = (current, leftIndex, rightIndex) => {
    const leftButton = current.leftButtons[leftIndex];
    leftButton.classList.remove(SELECTED);
    leftButton.querySelector('[data-pair-badge]').textContent = '';

    if (rightIndex === undefined || rightIndex === null) {
        return;
    }

    const rightButton = current.rightButtons[rightIndex];
    rightButton.classList.remove(SELECTED);
    rightButton.querySelector('[data-pair-badge]').textContent = '';
};

const unassign = (current, leftIndex) => {
    const rightIndex = current.assign[leftIndex];

    if (rightIndex === undefined) {
        return;
    }

    delete current.assign[leftIndex];
    clearPair(current, leftIndex, rightIndex);
};

const paintPair = (current, leftIndex, rightIndex) => {
    const leftButton = current.leftButtons[leftIndex];
    const leftBadge = leftButton.querySelector('[data-pair-badge]');
    const rightButton = current.rightButtons[rightIndex];
    const rightBadge = rightButton.querySelector('[data-pair-badge]');
    const number = Object.keys(current.assign).length;

    leftButton.classList.add(SELECTED);
    rightButton.classList.add(SELECTED);
    leftBadge.textContent = String(number);
    rightBadge.textContent = String(number);
};

const renderMatching = (q) => {
    const pairs = q.pairs.filter((pair) => pair && pair.left && pair.right);
    state = {
        kind: 'match',
        pairs,
        rights: shuffle(pairs.map((pair, origin) => ({ right: pair.right, origin }))),
        selectedLeft: null,
        assign: {},
        leftButtons: [],
        rightButtons: [],
    };

    const makeItem = (label) => {
        const button = h('button', OPTION + ' min-h-[48px]', [
            h('span', OPTION_KEY, ['']),
            h('span', 'min-w-0 flex-1', [label]),
        ]);
        button.type = 'button';
        button.querySelector('span').dataset.pairBadge = '';

        return button;
    };

    const leftCol = h('div', 'space-y-2');
    const rightCol = h('div', 'space-y-2');

    pairs.forEach((pair, leftIndex) => {
        const button = makeItem(pair.left);

        button.addEventListener('click', () => {
            if (checked) {
                return;
            }

            if (state.assign[leftIndex] !== undefined) {
                unassign(state, leftIndex);
                state.selectedLeft = null;
                state.refreshCheck();

                return;
            }

            state.selectedLeft = state.selectedLeft === leftIndex ? null : leftIndex;
            state.leftButtons.forEach((sibling, i) => sibling.classList.toggle(SELECTED, i === state.selectedLeft));
        });

        state.leftButtons.push(button);
        leftCol.append(button);
    });

    state.rights.forEach((entry, rightIndex) => {
        const button = makeItem(entry.right);

        button.addEventListener('click', () => {
            if (checked) {
                return;
            }

            const takenBy = Object.keys(state.assign).find((key) => state.assign[key] === rightIndex);

            if (takenBy !== undefined) {
                unassign(state, Number(takenBy));
                state.refreshCheck();

                return;
            }

            if (state.selectedLeft === null) {
                return;
            }

            state.assign[state.selectedLeft] = rightIndex;
            paintPair(state, state.selectedLeft, rightIndex);
            state.selectedLeft = null;
            state.refreshCheck();
        });

        state.rightButtons.push(button);
        rightCol.append(button);
    });

    return h('div', 'space-y-2', [
        h('p', 'text-xs text-[#2D231E]', ['Tap an item on the left, then its match on the right.']),
        h('div', 'grid grid-cols-1 sm:grid-cols-2 gap-4', [leftCol, rightCol]),
    ]);
};

const renderSequence = (q) => {
    state = { kind: 'seq', items: q.sequence, bank: shuffle(q.sequence.map((_, i) => i)), order: [] };

    const strip = h('div', 'min-h-[48px] p-2 rounded-md border border-dashed border-[#E6DCCF] bg-[#FAF6EE] flex flex-wrap gap-2');
    const bank = h('div', 'flex flex-wrap gap-2');

    const chipClass = 'min-h-[44px] px-3 py-2 rounded-md border border-[#E6DCCF] bg-white text-sm text-[#2D231E] cursor-pointer';
    const selectedChip = chipClass + ' ' + SELECTED;

    const refreshNumbers = () => {
        [...strip.children].forEach((chip, position) => {
            const label = chip.querySelector('[data-seq-label]');
            label.textContent = (position + 1) + '. ' + state.items[Number(chip.dataset.seqItem)];
            chip.className = selectedChip;
        });

        [...bank.children].forEach((chip) => {
            const label = chip.querySelector('[data-seq-label]');
            label.textContent = state.items[Number(chip.dataset.seqItem)];
            chip.className = chipClass;
        });
    };

    const placeChip = (chip, itemIndex) => {
        chip.dataset.seqItem = String(itemIndex);

        chip.addEventListener('click', () => {
            if (checked) {
                return;
            }

            const isPlaced = chip.parentElement === strip;
            const from = isPlaced ? state.order : state.bank;
            const to = isPlaced ? state.bank : state.order;
            const at = from.indexOf(itemIndex);

            if (at !== -1) {
                from.splice(at, 1);
            }

            to.push(itemIndex);
            (isPlaced ? bank : strip).appendChild(chip);
            refreshNumbers();
            state.refreshCheck();
        });
    };

    state.bank.forEach((itemIndex) => {
        const chip = h('button', chipClass, [h('span', 'min-w-0', [state.items[itemIndex]])]);
        chip.type = 'button';
        chip.querySelector('span').dataset.seqLabel = '';
        placeChip(chip, itemIndex);
        bank.append(chip);
    });

    return h('div', 'space-y-2', [
        h('p', 'text-xs text-[#2D231E]', ['Tap the items below in the correct order.']),
        strip,
        bank,
    ]);
};

const renderQuestion = () => {
    checked = false;
    const q = questions[idx];

    const builders = {
        mcq: renderChoice,
        true_false: renderChoice,
        identification: renderIdentification,
        matching: renderMatching,
        sequence: renderSequence,
    };

    const answerArea = builders[q.type](q);
    const feedback = feedbackBox();
    const footer = footerRow(answerArea, feedback);

    const card = h('div', CARD, [
        headerRow(),
        h('span', CHIP, [TYPE_LABELS[q.type]]),
        h('p', 'font-medium text-base sm:text-lg text-[#2D231E] leading-snug', [q.text]),
        answerArea,
        feedback,
        footer,
    ]);

    stage.innerHTML = '';
    stage.append(card);
    state.refreshCheck();
};

const renderResults = () => {
    const percent = Math.round((score / questions.length) * 100);

    const retry = h('button', PRIMARY_BTN, ['Retry test run']);
    retry.type = 'button';
    retry.addEventListener('click', () => {
        idx = 0;
        score = 0;
        renderQuestion();
    });

    const back = h('button', SECONDARY_BTN, ['Back to editing']);
    back.type = 'button';
    back.addEventListener('click', closePreview);

    const card = h('div', CARD + ' text-center space-y-4', [
        h('p', CHIP, ['Test run complete']),
        h('p', 'text-3xl sm:text-4xl font-semibold text-[#2D231E]', ['Score ' + score + '/' + questions.length]),
        h('p', 'text-sm text-[#2D231E]', [percent + '% correct']),
        h('div', 'flex flex-col sm:flex-row items-center justify-center gap-3 pt-2', [retry, back]),
    ]);

    stage.innerHTML = '';
    stage.append(h('div', 'max-w-md mx-auto', [card]));
};

export function initStoryQuizPreview() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-preview-quiz]')) {
            event.preventDefault();
            openPreview();

            return;
        }

        if (isOpen && event.target.closest('[data-preview-close]')) {
            closePreview();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            closePreview();
        }
    });
}
