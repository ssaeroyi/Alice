/**
 * Library search combobox, visual edition: typing drops an instant card box
 * under the bar — cover, title, author, minutes and the difficulty band —
 * and every card is a direct tap-to-read link. Typing never navigates: the
 * one-page library filters its own grid in place (see library-browse.js).
 * Esc closes; arrows walk the cards; Enter opens the focused card.
 */

const DEBOUNCE_MS = 250;

const BOOK_PATH = 'M101.667 400H464V16H100.667A60.863 60.863 0 0 0 40 76.667V430.25h.011c0 .151-.011.3-.011.453 0 35.4 27.782 65.3 60.667 65.3H464V464H100.667C85.664 464 72 448.129 72 430.7c0-16.64 13.585-30.7 29.667-30.7M360 48.333v172.816l-48.4-42.49L264 220.9V48.333ZM232 48v216h31.641l48.075-42.659L360.305 264H392V48h40v320H136.08L136 48Zm-131.333 0H104l.076 320h-2.413A59.8 59.8 0 0 0 72 375.883V76.917A28.825 28.825 0 0 1 100.667 48';

const CARD_ROW = 'flex items-center gap-3 px-3 py-2.5 bg-white hover:bg-[#FAF6EE] transition-colors cursor-pointer';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
})[ch]);

const thumb = (item) => (item.cover
    ? `<img src="${escapeHtml(item.cover)}" alt="" loading="lazy" decoding="async" class="w-10 h-14 rounded-md border-2 border-[#2D231E] object-cover bg-white shrink-0">`
    : `<span class="w-10 h-14 rounded-md border-2 border-[#2D231E] bg-[#F3ECE0] flex items-center justify-center shrink-0 text-[#2D231E]">` +
      `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 512 512" aria-hidden="true"><path d="${BOOK_PATH}"/></svg></span>`);

const card = (item) => `
    <span class="min-w-0 flex-1">
        <span class="block text-xs font-bold text-[#2D231E] truncate">${escapeHtml(item.title)}</span>
        <span class="block text-[10px] font-semibold text-[#8C7B70] truncate">${escapeHtml(item.author)}${item.genre ? ` · ${escapeHtml(item.genre)}` : ''}</span>
    </span>
    <span class="shrink-0 inline-flex flex-col items-end gap-0.5">
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase">${item.minutes} MIN</span>
        ${item.band
            ? `<span class="font-pixel text-[8px] font-bold uppercase text-[#2D231E] bg-[#F3ECE0] border border-[#2D231E]/20 rounded-md px-1.5 py-0.5">${escapeHtml(item.band)}</span>`
            : ''}
    </span>`;

export function initSearchCombobox(root = document) {
    root.querySelectorAll('input[data-combobox]').forEach((input) => {
        if (input.dataset.comboboxBound) {
            return;
        }
        input.dataset.comboboxBound = 'true';

        const anchor = input.parentElement; // the relative search wrapper
        let box = null;
        let timer = null;
        let controller = null;
        let options = [];
        let active = -1;

        const close = () => {
            box?.remove();
            box = null;
            options = [];
            active = -1;
        };

        const setActive = (index) => {
            active = index;
            if (!box) {
                return;
            }
            box.querySelectorAll('[role="option"]').forEach((row, i) => {
                row.classList.toggle('bg-[#FAF6EE]', i === active);
            });
        };

        const goTo = (step) => {
            if (!box || options.length === 0) {
                return;
            }
            const next = active < 0
                ? (step > 0 ? 0 : options.length - 1)
                : Math.min(options.length - 1, Math.max(0, active + step));
            setActive(next);
        };

        const open = (results) => {
            close();
            if (!results.length) {
                // One non-tappable row so silence never reads as "broken".
                box = document.createElement('div');
                box.className = 'absolute left-0 right-0 top-full mt-1 z-40 bg-white border-2 border-[#2D231E] rounded-2xl shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] overflow-hidden';
                const empty = document.createElement('p');
                empty.className = 'px-4 py-2.5 text-xs font-semibold text-[#8C7B70]';
                empty.textContent = 'No stories found';
                box.appendChild(empty);
                anchor.appendChild(box);
                return;
            }

            box = document.createElement('div');
            box.setAttribute('role', 'listbox');
            box.className = 'absolute left-0 right-0 top-full mt-1 z-40 bg-white border-2 border-[#2D231E] rounded-2xl shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] overflow-hidden divide-y divide-[#E6DCCF]';

            results.forEach((item) => {
                const row = document.createElement('a');
                row.href = item.url;
                row.setAttribute('role', 'option');
                row.className = CARD_ROW;
                row.innerHTML = thumb(item) + card(item);

                // A deliberate click opens the story to read. mousedown is
                // only prevented (so the field keeps focus and the dropdown
                // stays put); navigation happens on click, never on touch.
                row.addEventListener('mousedown', (event) => event.preventDefault());
                row.addEventListener('click', () => close());

                box.appendChild(row);
            });

            options = results;
            anchor.appendChild(box);
        };

        const lookup = async () => {
            const q = input.value.trim();
            if (q.length < 2) {
                close();
                return;
            }

            controller?.abort();
            controller = new AbortController();

            try {
                const res = await fetch(`${input.dataset.combobox}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                if (input.value.trim() === q) {
                    open(data.results ?? []);
                }
            } catch {
                // Aborted or offline: dropdown just stays closed.
            }
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(lookup, DEBOUNCE_MS);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                goTo(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                goTo(-1);
            } else if (event.key === 'Enter') {
                // Typing never navigates and never submits a half-formed
                // query: the in-place grid (library-browse.js) already
                // updates as the student types. Enter only opens a card
                // the arrows put focus on.
                event.preventDefault();
                const row = box?.querySelectorAll('[role="option"]')[active];
                if (row) {
                    window.location.assign(row.href);
                }
                close();
            }
        });
        input.addEventListener('blur', () => window.setTimeout(close, 100));
    });
}

export default initSearchCombobox;
