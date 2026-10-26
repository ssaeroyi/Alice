import Flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';

let inited = false;

/**
 * Flatpickr-powered date picking, shared across the app:
 *
 * 1. Single-date picker for the assignment due date. The month and year in the
 *    calendar header are clickable so a teacher can jump straight to it.
 *
 *    Markup contract (teacher/assignments/form.blade.php):
 *      <div data-date-picker>
 *        <input data-date-display readonly>          <- friendly "5 Sep 2026" field
 *        <input hidden name="due_at" data-date-value> <- YYYY-MM-DD submitted
 *      </div>
 *
 * 2. Range picker (mode:'range') for the admin logs date filter. Attaches to a
 *    read-only display field inside the drawer and writes `from`/`to` ISO
 *    values into the matching inputs the logs filter form submits. Choosing
 *    both ends auto-submits the form.
 *
 *    Markup contract (components/admin/date-range.blade.php):
 *      <div data-range-picker>
 *        <input data-date-display readonly value="Last 7 days ..."> <-- read-only label
 *        <input hidden name="from" data-range-from>
 *        <input hidden name="to"   data-range-to>
 *      </div>
 */
export function initDatePicker() {
    if (inited) {
        return;
    }

    inited = true;

    document.querySelectorAll('[data-date-picker]').forEach((wrapper) => {
        const display = wrapper.querySelector('[data-date-display]');
        const hidden = wrapper.querySelector('[data-date-value]');

        if (!display || !hidden) {
            return;
        }

        const iso = hidden.value && /^\d{4}-\d{2}-\d{2}$/.test(hidden.value) ? hidden.value : null;
        const serverLabel = display.value;

        Flatpickr(display, {
            dateFormat: 'j M Y',
            defaultDate: iso ? toLocalDate(iso) : undefined,
            minDate: 'today',
            disableMobile: true,
            onChange: (selectedDates) => {
                const picked = selectedDates[0];
                if (picked) {
                    hidden.value = toIso(picked);
                }
            },
        });

        display.value = serverLabel;
    });

    document.querySelectorAll('[data-range-picker]').forEach((wrapper) => {
        const display = wrapper.querySelector('[data-date-display]');
        const fromInput = wrapper.querySelector('[data-range-from]');
        const toInput = wrapper.querySelector('[data-range-to]');

        if (!display || !fromInput || !toInput) {
            return;
        }

        const fromVal = /^\d{4}-\d{2}-\d{2}$/.test(fromInput.value || '') ? fromInput.value : null;
        const toVal = /^\d{4}-\d{2}-\d{2}$/.test(toInput.value || '') ? toInput.value : null;
        const serverLabel = display.value;

        const fp = Flatpickr(display, {
            mode: 'range',
            dateFormat: 'j M Y',
            defaultDate: [fromVal, toVal].filter(Boolean).map(toLocalDate),
            disableMobile: true,
            onChange: (selectedDates) => {
                fromInput.value = selectedDates[0] ? toIso(selectedDates[0]) : '';
                toInput.value = selectedDates[1] ? toIso(selectedDates[1]) : '';
            },
            onOpen: () => {
                display.setAttribute('aria-expanded', 'true');
            },
            onClose: () => {
                display.setAttribute('aria-expanded', 'false');
                if (fromInput.value && toInput.value) {
                    submitForm(display);
                }
            },
            position: (instance) => positionCalendar(instance),
        });

        display.value = serverLabel;

        display.addEventListener('click', (event) => {
            event.stopPropagation();
            fp.open();
        });

        const clearBtn = wrapper.querySelector('[data-range-clear]');

        if (clearBtn) {
            clearBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                fp.clear();
                fromInput.value = '';
                toInput.value = '';
                submitForm(display);
            });
        }
    });
}

function toIso(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function toLocalDate(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function positionCalendar(fp) {
    const calendar = fp.calendarContainer;
    const toggle = fp.input;

    if (!calendar || !toggle) {
        return;
    }

    const rect = toggle.getBoundingClientRect();
    const width = calendar.offsetWidth;
    const height = calendar.offsetHeight;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    calendar.style.position = 'fixed';
    calendar.style.left = Math.max(8, Math.min(rect.left + toggle.offsetWidth / 2 - width / 2, window.innerWidth - width - 8)) + 'px';
    calendar.style.top = (spaceBelow < 320 && spaceAbove > spaceBelow ? Math.max(8, rect.top - height - 8) : rect.bottom + 8) + 'px';
}

function submitForm(toggle) {
    const form = toggle.closest('form') || document.getElementById('logs-filter-form');
    if (form) {
        form.submit();
    }
}