export function initConfirmForms(root = document) {
    let activeForm = null;

    root.querySelectorAll('[data-confirm-open]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();

            const form = trigger.closest('form');

            if (!form || !form.reportValidity()) {
                return;
            }

            const modal = root.getElementById(trigger.getAttribute('data-confirm-open'));

            if (!modal) {
                return;
            }

            modal.querySelectorAll('[data-confirm-field]').forEach((el) => {
                const name = el.getAttribute('data-confirm-field');
                const input = form.querySelector(`[name="${name}"]`);
                let value = '';

                if (input) {
                    if (input.tagName === 'SELECT') {
                        value = input.selectedIndex >= 0 ? input.options[input.selectedIndex].textContent.trim() : '';
                    } else if (input.type === 'radio') {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        value = checked ? checked.value : '';
                    } else {
                        value = input.value;
                    }
                }

                el.textContent = value + (el.getAttribute('data-confirm-suffix') ?? '');
            });

            activeForm = form;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.dispatchEvent(new CustomEvent('alice:modal-open'));
        });
    });

    root.querySelectorAll('[data-confirm-approve]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = button.closest('[data-modal]');

            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            if (activeForm) {
                const form = activeForm;

                activeForm = null;
                form.requestSubmit();
            }
        });
    });

    initDoubleConfirms(root);
}

function initDoubleConfirms(root) {
    root.querySelectorAll('[data-modal]').forEach((modal) => {
        const checkbox = modal.querySelector('[data-double-confirm]');
        const approve = modal.querySelector('[data-double-confirm-submit], [data-confirm-approve]');

        if (!checkbox || !approve) {
            return;
        }

        const sync = () => {
            approve.disabled = !checkbox.checked;
            approve.classList.toggle('opacity-40', !checkbox.checked);
            approve.classList.toggle('pointer-events-none', !checkbox.checked);
        };

        checkbox.addEventListener('change', sync);

        modal.addEventListener('alice:modal-open', () => {
            checkbox.checked = false;
            sync();
        });

        sync();
    });
}