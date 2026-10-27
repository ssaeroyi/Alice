/**
 * Library search combobox: typing shows a small dropdown of title/author
 * matches instead of navigating anywhere. Tap a row to open that story;
 * Enter still submits the form to full results; Esc closes the dropdown.
 * Pairs with auto-filters.js, which no longer auto-submits search fields.
 */

const DEBOUNCE_MS = 250;

export function initSearchCombobox(root = document) {
    root.querySelectorAll('input[data-combobox]').forEach((input) => {
        if (input.dataset.comboboxBound) {
            return;
        }
        input.dataset.comboboxBound = 'true';

        const form = input.closest('form');
        let box = null;
        let timer = null;
        let controller = null;

        const close = () => {
            box?.remove();
            box = null;
        };

        const open = (results) => {
            close();
            if (!results.length) {
                return;
            }

            box = document.createElement('div');
            box.setAttribute('role', 'listbox');
            box.className = 'absolute left-0 right-0 top-full mt-1 z-40 bg-white border-2 border-[#2D231E] rounded-2xl shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] overflow-hidden';
            results.forEach((item) => {
                const row = document.createElement('a');
                row.href = item.url;
                row.className = 'flex items-baseline justify-between gap-3 px-4 py-2.5 bg-white text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE]';
                const title = document.createElement('span');
                title.className = 'truncate';
                title.textContent = item.title;
                const author = document.createElement('span');
                author.className = 'shrink-0 text-[10px] font-semibold text-[#8C7B70]';
                author.textContent = item.author;
                row.append(title, author);
                // Fetching the row must beat the blur that closes the dropdown.
                row.addEventListener('pointerdown', (event) => {
                    event.preventDefault();
                    window.location.assign(row.href);
                });
                box.appendChild(row);
            });
            form.appendChild(box);
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
        input.addEventListener('focus', lookup);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }
        });
        input.addEventListener('blur', () => window.setTimeout(close, 100));
    });
}

export default initSearchCombobox;
