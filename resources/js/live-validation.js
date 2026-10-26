import { emailRules, registerPasswordRules, loginPasswordRules, confirmRules } from './auth-rules.js';

const RULE_SETS = {
    email: emailRules,
    'register-password': registerPasswordRules,
    'login-password': loginPasswordRules,
    confirm: confirmRules,
};

const CHECK_ICON = '<svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

const CROSS_ICON = '<svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

export function initLiveValidation(root = document) {
    root.querySelectorAll('[data-live-validate]').forEach((input) => {
        const ruleSet = RULE_SETS[input.getAttribute('data-live-validate')];

        if (!ruleSet) {
            return;
        }

        const status = root.querySelector(`[data-live-status="${input.id}"]`);

        if (!status) {
            return;
        }

        const isList = status.tagName === 'DIV';
        const isConfirm = input.getAttribute('data-live-validate') === 'confirm';
        let touched = false;

        const passwordInput = () => {
            const form = input.closest('form');

            return form ? form.querySelector('[name="password"]') : null;
        };

        const evaluate = () => {
            if (!touched) {
                status.classList.add('hidden');
                status.textContent = '';
                input.setAttribute('aria-invalid', 'false');

                return;
            }

            const rules = isConfirm
                ? ruleSet.evaluate(passwordInput()?.value ?? '', input.value)
                : ruleSet.evaluate(input.value);
            const active = rules.filter((rule) => rule.required || input.value.length > 0);
            const failures = active.filter((rule) => !rule.info && !rule.ok);

            input.setAttribute('aria-invalid', failures.length > 0 ? 'true' : 'false');

            if (isList) {
                const lines = active
                    .filter((rule) => rule.info || !rule.required || !rule.ok)
                    .map((rule) => {
                        const infoCls = ['admin', 'teacher'].includes(document.body?.dataset?.area)
                            ? 'text-[#2D231E]'
                            : 'text-[#8C7B70]';
                        const cls = rule.info ? infoCls : rule.ok ? 'text-emerald-700' : 'text-rose-700';
                        const icon = rule.info || rule.ok ? CHECK_ICON : CROSS_ICON;

                        return `<p class="flex items-center gap-1.5 text-xs font-bold ${cls}">${icon}${rule.message}</p>`;
                    });

                status.innerHTML = lines.join('');

                if (lines.length > 0) {
                    status.classList.remove('hidden');
                } else {
                    status.classList.add('hidden');
                }

                return;
            }

            const firstFailure = failures[0];

            if (firstFailure) {
                status.textContent = firstFailure.message;
                status.classList.remove('hidden', 'text-emerald-700');
                status.classList.add('text-rose-700');
            } else if (input.value.length > 0) {
                status.textContent = ruleSet.okMessage || 'Looks good.';
                status.classList.remove('hidden', 'text-rose-700');
                status.classList.add('text-emerald-700');
            } else {
                status.classList.add('hidden');
                status.textContent = '';
            }
        };

        input.addEventListener('focus', () => {
            if (!touched) {
                touched = true;
                evaluate();
            }
        });

        input.addEventListener('blur', () => {
            touched = true;
            evaluate();
        });

        input.addEventListener('input', () => {
            if (touched) {
                evaluate();
            }
        });

        if (isConfirm) {
            passwordInput()?.addEventListener('input', () => {
                if (touched) {
                    evaluate();
                }
            });
        }
    });
}