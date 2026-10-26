import { WorkerLinter, createBinaryModuleFromUrl } from 'harper.js';

const ENGINE_TIMEOUT_MS = 30000;

let linterPromise = null;

function withTimeout(promise, message) {
    let timer;

    const timeout = new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error(message)), ENGINE_TIMEOUT_MS);
    });

    return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

function getLinter(binaryUrl) {
    if (!linterPromise) {
        // The engine fetches this inside a blob worker, where relative URLs
        // cannot be parsed — always hand it an absolute URL.
        const absoluteUrl = new URL(binaryUrl, window.location.origin).href;

        linterPromise = (async () => {
            const linter = new WorkerLinter({
                binary: createBinaryModuleFromUrl(absoluteUrl, 'full'),
            });
            await linter.setup();

            return linter;
        })();

        // A failed first load (offline, missing binary) must not wedge the
        // button forever — allow the next click to try again.
        linterPromise.catch(() => {
            linterPromise = null;
        });
    }

    return linterPromise;
}

export function warmUp(binaryUrl) {
    return getLinter(binaryUrl);
}

const BUCKET_KINDS = {
    spelling: ['Spelling', 'Typo', 'Eggcorn', 'Malapropism'],
    punctuation: ['Punctuation', 'Capitalization', 'BoundaryError', 'Formatting'],
    style: ['Style', 'Repetition', 'Redundancy', 'Enhancement', 'Readability', 'WordChoice', 'WordOrder'],
};

const SUGGESTION_INSERT_AFTER = 2;

function bucketFor(lintKind) {
    const match = Object.entries(BUCKET_KINDS).find(([, kinds]) => kinds.includes(lintKind));

    return match ? match[0] : 'grammar';
}

export async function checkGrammar(text, binaryUrl) {
    const linter = await withTimeout(
        getLinter(binaryUrl),
        'The grammar engine is taking too long to start. Please try again.'
    );
    const lints = await withTimeout(
        linter.lint(text, { language: 'plaintext' }),
        'The grammar check is taking too long. Please try again.'
    );

    const issues = lints.map((lint) => {
        const span = lint.span();
        const suggestions = lint
            .suggestions()
            .map((suggestion) => suggestion.get_replacement_text())
            .filter((replacement) => typeof replacement === 'string' && replacement !== '');
        const insertAfter = suggestions.length > 0 && lint.suggestions()[0].kind() === SUGGESTION_INSERT_AFTER;
        const lintKind = lint.lint_kind();

        return {
            message: lint.message().replace(/`/g, ''),
            category: lint.lint_kind_pretty(),
            bucket: bucketFor(lintKind),
            offset: insertAfter ? span.end : span.start,
            length: insertAfter ? 0 : span.end - span.start,
            context: text.slice(Math.max(0, span.start - 30), Math.min(text.length, span.end + 30)),
            suggestions,
        };
    });

    return { count: issues.length, issues };
}
