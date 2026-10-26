export function initStoryQuiz() {
    const card = document.querySelector('[data-quiz-card]');

    if (!card) {
        return;
    }

    const form = card.querySelector('[data-quiz-form]');
    const typeSelect = card.querySelector('[data-quiz-type]');
    const sectionSelect = form.querySelector('[data-quiz-section]');
    const defaultSectionValue = () => (sectionSelect && sectionSelect.options[0] ? sectionSelect.options[0].value : '');
    const optionsBlock = card.querySelector('[data-quiz-options]');
    const answersBlock = card.querySelector('[data-quiz-answers]');
    const answersRows = card.querySelector('[data-quiz-answers-rows]');
    const answersAdd = card.querySelector('[data-quiz-answer-add]');
    const pairsBlock = card.querySelector('[data-quiz-pairs]');
    const pairsRows = card.querySelector('[data-quiz-pairs-rows]');
    const pairsAdd = card.querySelector('[data-quiz-pair-add]');
    const sequenceBlock = card.querySelector('[data-quiz-sequence]');
    const sequenceRows = card.querySelector('[data-quiz-sequence-rows]');
    const sequenceAdd = card.querySelector('[data-quiz-sequence-add]');
    const correctBlock = card.querySelector('[data-quiz-correct-block]');
    const MAX_ANSWERS = 10;
    const MAX_PAIRS = 4;
    const MIN_PAIRS = 2;
    const MAX_STEPS = 5;
    const MIN_STEPS = 3;
    const INPUT_CLASS = 'w-full px-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]';
    const REMOVE_CLASS = 'shrink-0 min-w-[44px] min-h-[44px] inline-flex items-center justify-center bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer';
    const BADGE_CLASS = 'shrink-0 w-7 h-7 flex items-center justify-center bg-[#F3ECE0] rounded-md text-[11px] font-extrabold text-[#2D231E] uppercase';

    const field = (name) => form.querySelector(`[data-quiz-${name}]`);

    const letterLabels = ['a', 'b', 'c', 'd'];
    const mcqOptionMarks = form.querySelectorAll('[data-quiz-mcq-option]');
    const tfGrid = form.querySelector('[data-quiz-tf-grid]');
    const allCorrectControls = form.querySelectorAll('[data-quiz-correct-radio], [data-quiz-correct-radio-tf]');

    const undoButton = card.querySelector('[data-quiz-undo]');
    const redoButton = card.querySelector('[data-quiz-redo]');

    const MAX_HISTORY = 50;
    const undoStack = [];
    const redoStack = [];
    let currentSnapshot = '';

    const snapshot = () => ({
        type: typeSelect.value,
        text: field('text').value,
        section: sectionSelect ? sectionSelect.value : '',
        options: letterLabels.map((letter) => {
            const input = form.querySelector(`[data-quiz-option="${letter}"]`);

            return input ? input.value : '';
        }),
        correct: form.querySelector('input[name="correct_option"]:checked')?.value || '',
        answers: Array.from(answersRows.querySelectorAll('input[data-quiz-answer]')).map((input) => input.value),
        pairs: Array.from(pairsRows.querySelectorAll('[data-quiz-pair-row]')).map((row) => ({
            left: row.querySelector('input[data-quiz-pair-left]').value,
            right: row.querySelector('input[data-quiz-pair-right]').value,
        })),
        sequence: Array.from(sequenceRows.querySelectorAll('input[data-quiz-sequence-item]')).map((input) => input.value),
    });

    const syncButtons = () => {
        [[undoButton, undoStack], [redoButton, redoStack]].forEach(([button, stack]) => {
            if (!button) {
                return;
            }

            const disabled = stack.length === 0;

            button.classList.toggle('opacity-40', disabled);
            button.classList.toggle('pointer-events-none', disabled);
        });
    };

    const resetHistory = () => {
        undoStack.length = 0;
        redoStack.length = 0;
        currentSnapshot = JSON.stringify(snapshot());
        syncButtons();
    };

    const pushSnapshot = () => {
        const now = JSON.stringify(snapshot());

        if (currentSnapshot === '') {
            currentSnapshot = now;

            return;
        }

        if (now === currentSnapshot) {
            return;
        }

        undoStack.push(currentSnapshot);

        if (undoStack.length > MAX_HISTORY) {
            undoStack.shift();
        }

        redoStack.length = 0;
        currentSnapshot = now;
        syncButtons();
    };

    const typeGrid = card.querySelector('[data-quiz-type-grid]');
    const sectionWrap = card.querySelector('[data-quiz-section-wrap]');

    const setMode = (edit, questionId) => {
        field('method').value = edit ? 'PUT' : 'POST';
        field('id').value = questionId || '';
        form.action = edit
            ? form.dataset.quizUrlUpdate.replace('__ID__', questionId)
            : form.dataset.quizUrlStore;

        if (typeGrid && sectionWrap) {
            const showSection = Boolean(edit);
            sectionWrap.classList.toggle('hidden', !showSection);
            typeGrid.classList.toggle('sm:grid-cols-2', showSection);
        }
    };

    const buildInput = ({ name, dataKey, placeholder, value = '' }) => {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = name;
        input.maxLength = 255;
        input.placeholder = placeholder;
        input.value = value;
        input.className = INPUT_CLASS;

        if (dataKey) {
            input.dataset[dataKey] = '';
        }

        return input;
    };

    const buildRemove = (dataKey, ariaLabel, onRemove) => {
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset[dataKey] = '';
        remove.setAttribute('aria-label', ariaLabel);
        remove.textContent = '×';
        remove.className = REMOVE_CLASS;
        remove.addEventListener('click', onRemove);

        return remove;
    };

    const numberAnswerRows = () => {
        answersRows.querySelectorAll('[data-quiz-answer-row]').forEach((row, index) => {
            const badge = row.querySelector('[data-quiz-answer-index]');

            if (badge) {
                badge.textContent = String(index + 1);
            }

            const remove = row.querySelector('[data-quiz-answer-remove]');
            remove.classList.toggle('opacity-40', answersRows.children.length === 1);
            remove.disabled = answersRows.children.length === 1;
        });

        answersAdd.classList.toggle('opacity-40', answersRows.children.length >= MAX_ANSWERS);
        answersAdd.disabled = answersRows.children.length >= MAX_ANSWERS;
    };

    const addAnswerRow = (value = '') => {
        if (answersRows.children.length >= MAX_ANSWERS) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'flex items-center gap-3';
        row.dataset.quizAnswerRow = '';

        const badge = document.createElement('span');
        badge.className = BADGE_CLASS;
        badge.dataset.quizAnswerIndex = '';

        const remove = buildRemove('quizAnswerRemove', 'Remove answer', () => {
            if (answersRows.children.length > 1) {
                row.remove();
                numberAnswerRows();
                pushSnapshot();
            }
        });

        row.append(badge, buildInput({ name: 'acceptable_answers[]', dataKey: 'quizAnswer', placeholder: 'e.g. sherlock', value }), remove);
        answersRows.append(row);
        numberAnswerRows();
    };

    const resetAnswers = () => {
        answersRows.querySelectorAll('[data-quiz-answer-row]').forEach((row) => row.remove());
    };

    const numberPairRows = () => {
        pairsRows.querySelectorAll('[data-quiz-pair-row]').forEach((row) => {
            const remove = row.querySelector('[data-quiz-pair-remove]');
            remove.classList.toggle('opacity-40', pairsRows.children.length <= MIN_PAIRS);
            remove.disabled = pairsRows.children.length <= MIN_PAIRS;
        });

        pairsAdd.classList.toggle('opacity-40', pairsRows.children.length >= MAX_PAIRS);
        pairsAdd.disabled = pairsRows.children.length >= MAX_PAIRS;
    };

    const addPairRow = (left = '', right = '') => {
        if (pairsRows.children.length >= MAX_PAIRS) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        row.dataset.quizPairRow = '';

        const arrow = document.createElement('span');
        arrow.className = 'shrink-0 text-xs font-extrabold text-[#2D231E]';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '→';

        const remove = buildRemove('quizPairRemove', 'Remove pair', () => {
            if (pairsRows.children.length > MIN_PAIRS) {
                row.remove();
                numberPairRows();
                pushSnapshot();
            }
        });

        row.append(
            buildInput({ name: 'match_pairs[left][]', dataKey: 'quizPairLeft', placeholder: 'Prompt e.g. London', value: left }),
            arrow,
            buildInput({ name: 'match_pairs[right][]', dataKey: 'quizPairRight', placeholder: 'Match e.g. Thames', value: right }),
            remove,
        );
        pairsRows.append(row);
        numberPairRows();
    };

    const resetPairs = () => {
        pairsRows.querySelectorAll('[data-quiz-pair-row]').forEach((row) => row.remove());
    };

    const numberSequenceRows = () => {
        sequenceRows.querySelectorAll('[data-quiz-sequence-row]').forEach((row, index) => {
            const badge = row.querySelector('[data-quiz-sequence-index]');

            if (badge) {
                badge.textContent = String(index + 1);
            }

            const remove = row.querySelector('[data-quiz-sequence-remove]');
            remove.classList.toggle('opacity-40', sequenceRows.children.length <= MIN_STEPS);
            remove.disabled = sequenceRows.children.length <= MIN_STEPS;
        });

        sequenceAdd.classList.toggle('opacity-40', sequenceRows.children.length >= MAX_STEPS);
        sequenceAdd.disabled = sequenceRows.children.length >= MAX_STEPS;
    };

    const addSequenceRow = (value = '') => {
        if (sequenceRows.children.length >= MAX_STEPS) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'flex items-center gap-3';
        row.dataset.quizSequenceRow = '';

        const badge = document.createElement('span');
        badge.className = BADGE_CLASS;
        badge.dataset.quizSequenceIndex = '';

        const remove = buildRemove('quizSequenceRemove', 'Remove step', () => {
            if (sequenceRows.children.length > MIN_STEPS) {
                row.remove();
                numberSequenceRows();
                pushSnapshot();
            }
        });

        row.append(badge, buildInput({ name: 'sequence_items[]', dataKey: 'quizSequenceItem', placeholder: 'e.g. She packs the basket', value }), remove);
        sequenceRows.append(row);
        numberSequenceRows();
    };

    const resetSequence = () => {
        sequenceRows.querySelectorAll('[data-quiz-sequence-row]').forEach((row) => row.remove());
    };

    const seedDefaults = () => {
        resetAnswers();
        addAnswerRow();
        resetPairs();
        addPairRow();
        addPairRow();
        resetSequence();
        addSequenceRow();
        addSequenceRow();
        addSequenceRow();
    };

    const restore = (snap) => {
        typeSelect.value = snap.type;
        field('text').value = snap.text;

        if (sectionSelect) {
            const target = snap.section || defaultSectionValue();

            if (sectionSelect.value !== target) {
                sectionSelect.value = target;
            }
        }

        letterLabels.forEach((letter, index) => {
            const input = form.querySelector(`[data-quiz-option="${letter}"]`);

            if (input) {
                input.value = snap.options[index] || '';
            }
        });

        allCorrectControls.forEach((control) => { control.checked = false; });

        if (snap.correct) {
            const radio = form.querySelector(
                snap.type === 'true_false'
                    ? `[data-quiz-correct-radio-tf="${snap.correct}"]`
                    : `[data-quiz-correct-radio="${snap.correct}"]`
            );

            if (radio) {
                radio.checked = true;
            }
        }

        resetAnswers();
        (snap.answers.length > 0 ? snap.answers : ['']).forEach((value) => addAnswerRow(value));

        resetPairs();
        (snap.pairs.length > 0 ? snap.pairs : [{ left: '', right: '' }, { left: '', right: '' }])
            .forEach((pair) => addPairRow(pair.left, pair.right));

        resetSequence();
        (snap.sequence.length > 0 ? snap.sequence : ['', '', '']).forEach((value) => addSequenceRow(value));

        syncType();
    };

    const stepBack = () => {
        if (undoStack.length === 0) {
            return;
        }

        redoStack.push(currentSnapshot);
        currentSnapshot = undoStack.pop();
        restore(JSON.parse(currentSnapshot));
        syncButtons();
    };

    const stepForward = () => {
        if (redoStack.length === 0) {
            return;
        }

        undoStack.push(currentSnapshot);
        currentSnapshot = redoStack.pop();
        restore(JSON.parse(currentSnapshot));
        syncButtons();
    };

    const syncType = () => {
        const type = typeSelect.value;

        const mcq = type === 'mcq';
        const tf = type === 'true_false';
        const ident = type === 'identification';
        const matching = type === 'matching';
        const sequence = type === 'sequence';

        optionsBlock.classList.toggle('hidden', !mcq);
        answersBlock.classList.toggle('hidden', !ident);
        pairsBlock.classList.toggle('hidden', !matching);
        sequenceBlock.classList.toggle('hidden', !sequence);

        if (correctBlock) {
            correctBlock.classList.toggle('hidden', ! (mcq || tf));
        }

        mcqOptionMarks.forEach((mark) => mark.classList.toggle('hidden', !mcq));

        if (tfGrid) {
            tfGrid.classList.toggle('hidden', !tf);
        }
    };

    const resetCard = () => {
        form.reset();
        seedDefaults();
        setMode(false, '');
        syncType();
        resetHistory();
    };

    const showCard = () => {
        card.classList.remove('hidden');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const hideCard = () => {
        card.classList.add('hidden');
    };

    typeSelect.addEventListener('change', () => {
        allCorrectControls.forEach((control) => { control.checked = false; });
        syncType();
        pushSnapshot();
    });

    answersAdd.addEventListener('click', () => {
        addAnswerRow();
        pushSnapshot();
    });

    pairsAdd.addEventListener('click', () => {
        addPairRow();
        pushSnapshot();
    });

    sequenceAdd.addEventListener('click', () => {
        addSequenceRow();
        pushSnapshot();
    });

    card.querySelector('[data-quiz-cancel]').addEventListener('click', hideCard);

    document.querySelectorAll('[data-quiz-add]').forEach((addButton) => {
        addButton.addEventListener('click', () => {
            resetCard();

            if (sectionSelect) {
                sectionSelect.value = addButton.dataset.quizAddSection || defaultSectionValue();
            }

            showCard();
        });
    });

    const parseJsonList = (raw) => {
        try {
            const parsed = JSON.parse(raw || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    };

    document.querySelectorAll('[data-quiz-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.dataset.questionType;

            typeSelect.value = type;

            if (sectionSelect) {
                sectionSelect.value = button.dataset.questionSection || defaultSectionValue();
            }

            field('text').value = button.dataset.questionText || '';
            letterLabels.forEach((letter) => {
                const optionInput = form.querySelector(`[data-quiz-option="${letter}"]`);
                if (optionInput) {
                    optionInput.value = button.dataset['question' + letter.toUpperCase()] || '';
                }
            });

            allCorrectControls.forEach((control) => { control.checked = false; });

            const correct = button.dataset.questionCorrect;

            if (correct) {
                const radio = form.querySelector(
                    type === 'true_false'
                        ? `[data-quiz-correct-radio-tf="${correct}"]`
                        : `[data-quiz-correct-radio="${correct}"]`
                );
                if (radio) {
                    radio.checked = true;
                }
            }

            const answers = parseJsonList(button.dataset.questionAnswers);

            resetAnswers();
            (answers.length > 0 ? answers : [null]).forEach((answer) => addAnswerRow(answer || ''));

            const pairs = parseJsonList(button.dataset.questionPairs);

            resetPairs();
            (pairs.length > 0 ? pairs : [null, null]).forEach((pair) => addPairRow(pair?.left || '', pair?.right || ''));

            const steps = parseJsonList(button.dataset.questionSequence);

            resetSequence();
            (steps.length > 0 ? steps : [null, null, null]).forEach((step) => addSequenceRow(step || ''));

            setMode(true, button.dataset.questionId);
            syncType();
            showCard();
            resetHistory();
        });
    });

    if (undoButton) {
        undoButton.addEventListener('click', stepBack);
    }

    if (redoButton) {
        redoButton.addEventListener('click', stepForward);
    }

    document.addEventListener('keydown', (event) => {
        if (!(event.ctrlKey || event.metaKey) || event.altKey || card.classList.contains('hidden')) {
            return;
        }

        const key = event.key.toLowerCase();
        const isUndo = key === 'z' && !event.shiftKey;
        const isRedo = key === 'y' || (key === 'z' && event.shiftKey);

        if (!isUndo && !isRedo) {
            return;
        }

        const target = event.target;

        if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA'
            || target.tagName === 'SELECT' || target.isContentEditable)) {
            return;
        }

        event.preventDefault();

        if (isUndo) {
            stepBack();
        } else {
            stepForward();
        }
    });

    if (card.classList.contains('hidden') === false) {
        setMode(Boolean(field('id').value), field('id').value);
        syncType();
        resetHistory();
    }
}
