import { openStoryDrawer } from './story-drawer.js';
import { closeAllDropdowns } from './dropdown.js';

export function initStoryBatch() {
    const root = document.querySelector('[data-story-batch]');

    if (!root) {
        return;
    }

    const selectAll = root.querySelector('[data-batch-select-all]');
    const checkboxes = Array.from(root.querySelectorAll('[data-batch-select]'));
    const bar = root.querySelector('[data-batch-bar]');
    const count = root.querySelector('[data-batch-count]');
    const edit = root.querySelector('[data-batch-edit]');
    const clear = root.querySelector('[data-batch-clear]');
    const deleteCount = document.querySelector('[data-batch-delete-count]');
    const form = root.querySelector('[data-batch-form]');
    const deleteForm = document.querySelector('[data-batch-delete-form]');

    if (!bar || !form || !deleteForm) {
        return;
    }

    const editUrlTemplate = bar.dataset.batchEditUrl || '';

    const refresh = () => {
        const selected = checkboxes.filter((box) => box.checked);
        const n = selected.length;

        if (count) {
            count.textContent = n;
        }

        if (deleteCount) {
            deleteCount.textContent = n + (n === 1 ? ' story' : ' stories');
        }

        bar.classList.toggle('hidden', n === 0);
        document.body.style.paddingBottom = n === 0 ? '' : '96px';

        if (edit) {
            const single = n === 1 ? selected[0].value : '';

            edit.classList.toggle('hidden', n !== 1);
            edit.href = single ? editUrlTemplate.replace('__ID__', single) : '#';
        }

        if (selectAll) {
            selectAll.checked = n > 0 && n === checkboxes.length;
            selectAll.indeterminate = n > 0 && n < checkboxes.length;
        }
    };

    const syncIds = (target) => {
        target.querySelectorAll('[data-batch-id-input]').forEach((input) => input.remove());

        checkboxes.filter((box) => box.checked).forEach((box) => {
            const input = document.createElement('input');

            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = box.value;
            input.dataset.batchIdInput = '';
            target.appendChild(input);
        });
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((box) => {
                box.checked = selectAll.checked;
            });
            refresh();
        });
    }

    if (clear) {
        clear.addEventListener('click', () => {
            checkboxes.forEach((box) => {
                box.checked = false;
            });
            refresh();
        });
    }

    checkboxes.forEach((box) => box.addEventListener('change', refresh));

    root.querySelectorAll('[data-drawer-row-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openStoryDrawer(trigger.dataset.drawerRowTrigger);
        });
    });

    root.querySelectorAll('[data-needs-more]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-story-needs]');

            row?.querySelectorAll('[data-need-chip-extra]').forEach((chip) => {
                chip.classList.remove('hidden');
            });

            button.remove();
        });
    });

    const storyDeleteForm = document.querySelector('[data-story-delete-form]');
    const storyDeleteTitle = document.querySelector('[data-story-delete-title]');

    root.querySelectorAll('[data-story-delete]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!storyDeleteForm) {
                return;
            }

            storyDeleteForm.action = button.dataset.storyDelete || '#';

            if (storyDeleteTitle) {
                storyDeleteTitle.textContent = button.dataset.storyTitle || '';
            }

            closeAllDropdowns();
        });
    });

    form.addEventListener('submit', (event) => {
        if (checkboxes.filter((box) => box.checked).length === 0) {
            event.preventDefault();

            return;
        }

        syncIds(form);
    });

    deleteForm.addEventListener('submit', (event) => {
        if (checkboxes.filter((box) => box.checked).length === 0) {
            event.preventDefault();

            return;
        }

        syncIds(deleteForm);
    });

    refresh();
}