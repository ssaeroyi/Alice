const INDICATOR_CLASS = 'absolute left-0 right-0 h-1 bg-[#2D231E]/40 rounded-full pointer-events-none z-10';
const ERROR_MS = 4000;

export function initStoryReorder() {
    document.querySelectorAll('[data-question-list]').forEach((list) => {
        if (!list.dataset.reorderUrl) {
            return;
        }

        let drag = null;

        const cards = () => Array.from(list.querySelectorAll('[data-question-card]'));

        const showError = () => {
            const existing = list.parentElement.querySelector('[data-reorder-error]');

            if (existing) {
                return;
            }

            const note = document.createElement('p');

            note.dataset.reorderError = '';
            note.className = 'mt-2 p-3 bg-rose-50 border border-rose-300 rounded-lg text-xs font-bold text-rose-800';
            note.textContent = "Couldn't save the new order — check your connection and try again.";

            list.parentElement.insertBefore(note, list);
            setTimeout(() => note.remove(), ERROR_MS);
        };

        const renumberAll = () => {
            document.querySelectorAll('[data-question-list]').forEach((otherList) => {
                otherList.querySelectorAll('[data-question-card]').forEach((card, index) => {
                    const badge = card.querySelector('[data-question-number]');

                    if (badge) {
                        badge.textContent = String(index + 1).padStart(2, '0');
                    }
                });
            });
        };

        const persistOrder = async (orderedIds) => {
            try {
                const response = await fetch(list.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: orderedIds }),
                });

                const result = await response.json().catch(() => ({}));

                return Boolean(response.ok && result.success);
            } catch {
                return false;
            }
        };

        const slotIndexAt = (clientY, exclude) => {
            let index = 0;

            for (const card of cards()) {
                if (card === exclude) {
                    continue;
                }

                const rect = card.getBoundingClientRect();

                if (clientY > rect.top + rect.height / 2) {
                    index += 1;
                }
            }

            return index;
        };

        const commitDom = (orderedIds, movedCard) => {
            const others = cards().filter((item) => item !== movedCard);

            if (orderedIds[orderedIds.length - 1] === movedCard.dataset.questionCard) {
                others[others.length - 1].after(movedCard);

                return;
            }

            const nextId = orderedIds[orderedIds.indexOf(movedCard.dataset.questionCard) + 1];
            const anchor = others.find((item) => item.dataset.questionCard === String(nextId));

            anchor.before(movedCard);
        };

        list.addEventListener('keydown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');

            if (!handle || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
                return;
            }

            event.preventDefault();

            const card = handle.closest('[data-question-card]');
            const sibling = event.key === 'ArrowUp' ? card.previousElementSibling : card.nextElementSibling;

            if (!sibling || !sibling.matches('[data-question-card]')) {
                return;
            }

            const ids = cards().map((item) => item.dataset.questionCard);
            const from = ids.indexOf(card.dataset.questionCard);

            [ids[from], ids[from + (event.key === 'ArrowUp' ? -1 : 1)]] =
                [ids[from + (event.key === 'ArrowUp' ? -1 : 1)], ids[from]];

            persistOrder(ids.map(Number)).then((saved) => {
                if (!saved) {
                    showError();

                    return;
                }

                list.insertBefore(card, event.key === 'ArrowUp' ? sibling : sibling.nextElementSibling);
                renumberAll();
            });
        });

        list.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');

            if (!handle || drag || (event.pointerType === 'mouse' && event.button !== 0)) {
                return;
            }

            const card = handle.closest('[data-question-card]');

            if (!card || cards().length < 2) {
                return;
            }

            event.preventDefault();

            const indicator = document.createElement('div');
            let lastY = event.clientY;
            let scrollTimer = null;

            indicator.className = INDICATOR_CLASS;
            list.appendChild(indicator);
            card.classList.add('opacity-50');
            document.body.classList.add('select-none');

            const others = () => cards().filter((item) => item !== card);

            const placeIndicator = () => {
                const target = slotIndexAt(lastY, card);
                const rest = others();

                if (target >= rest.length) {
                    rest[rest.length - 1].after(indicator);
                } else {
                    rest[target].before(indicator);
                }
            };

            const finish = () => {
                clearInterval(scrollTimer);
                indicator.remove();
                card.classList.remove('opacity-50');
                document.body.classList.remove('select-none');
                window.removeEventListener('scroll', onScroll);
                drag = null;
            };

            const onScroll = () => placeIndicator();

            scrollTimer = setInterval(() => {
                const margin = 72;

                if (lastY < margin) {
                    window.scrollBy(0, -12);
                } else if (lastY > window.innerHeight - margin) {
                    window.scrollBy(0, 12);
                }
            }, 16);

            window.addEventListener('scroll', onScroll);
            handle.setPointerCapture(event.pointerId);

            const onMove = (moveEvent) => {
                if (moveEvent.pointerId !== event.pointerId) {
                    return;
                }

                moveEvent.preventDefault();
                lastY = moveEvent.clientY;
                placeIndicator();
            };

            const onCancel = () => finish();

            const onUp = async (upEvent) => {
                if (upEvent.pointerId !== event.pointerId) {
                    return;
                }

                handle.removeEventListener('pointermove', onMove);
                handle.removeEventListener('pointerup', onUp);
                handle.removeEventListener('pointercancel', onCancel);

                const rest = others();
                const target = slotIndexAt(lastY, card);
                const orderedIds = rest.map((item) => item.dataset.questionCard);

                orderedIds.splice(target, 0, card.dataset.questionCard);

                if (JSON.stringify(orderedIds) === JSON.stringify(cards().map((item) => item.dataset.questionCard))) {
                    finish();

                    return;
                }

                const saved = await persistOrder(orderedIds.map(Number));

                if (saved) {
                    commitDom(orderedIds, card);
                    renumberAll();
                } else {
                    showError();
                }

                finish();
            };

            handle.addEventListener('pointermove', onMove);
            handle.addEventListener('pointerup', onUp);
            handle.addEventListener('pointercancel', onCancel);
        });
    });
}
