const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export const emailRules = {
    okMessage: 'Looks good.',

    evaluate(value) {
        return [
            {
                key: 'required',
                required: true,
                ok: value.trim().length > 0,
                message: 'Please enter your email address.',
            },
            {
                key: 'format',
                ok: value.trim().length === 0 || EMAIL_PATTERN.test(value.trim()),
                message: "That email doesn't look right.",
            },
        ];
    },
};

export const registerPasswordRules = {
    evaluate(value) {
        return [
            {
                key: 'required',
                required: true,
                ok: value.length > 0,
                message: 'Please enter a password.',
            },
            {
                key: 'min',
                ok: value.length === 0 || value.length >= 8,
                message: 'Minimum of 8 characters.',
            },
            {
                key: 'lowercase',
                ok: value.length === 0 || /[a-z]/.test(value),
                message: 'At least 1 lowercase letter (a-z).',
            },
            {
                key: 'uppercase',
                ok: value.length === 0 || /[A-Z]/.test(value),
                message: 'At least 1 uppercase letter (A-Z).',
            },
            {
                key: 'number',
                ok: value.length === 0 || /[0-9]/.test(value),
                message: 'At least 1 number.',
            },
            {
                key: 'special',
                ok: value.length === 0 || /[^a-zA-Z0-9]/.test(value),
                message: 'At least 1 special character (e.g., ! @ # $ %).',
            },
        ];
    },
};

export const loginPasswordRules = {
    okMessage: 'Looks good.',

    evaluate(value) {
        return [
            {
                key: 'required',
                required: true,
                ok: value.length > 0,
                message: 'Please enter your password.',
            },
        ];
    },
};

export const confirmRules = {
    okMessage: 'Passwords match',

    evaluate(password, confirm) {
        return [
            {
                key: 'required',
                required: true,
                ok: confirm.length > 0,
                message: 'Please confirm your password.',
            },
            {
                key: 'match',
                ok: confirm.length === 0 || password === confirm,
                message: "Passwords don't match",
            },
        ];
    },
};