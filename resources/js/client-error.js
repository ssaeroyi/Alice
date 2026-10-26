const REPORTING_URL = '/client-error';

let queue = [];
let flushTimer = null;
let reporting = false;

const dedupe = new Map();

function token() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute('content') : '';
}

function push(entry) {
    const key = entry.message + '|' + entry.url;
    const now = Date.now();

    if (dedupe.has(key) && now - dedupe.get(key) < 60000) {
        return;
    }

    dedupe.set(key, now);
    queue.push(entry);

    if (queue.length >= 5) {
        flush();
    } else if (!flushTimer) {
        flushTimer = setTimeout(flush, 2000);
    }
}

function flush() {
    if (flushTimer) {
        clearTimeout(flushTimer);
        flushTimer = null;
    }

    if (queue.length === 0 || reporting) {
        return;
    }

    reporting = true;
    const batch = queue.splice(0, queue.length);

    fetch(REPORTING_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token(),
        },
        body: JSON.stringify({ errors: batch }),
        keepalive: true,
    })
        .catch(() => {})
        .finally(() => {
            reporting = false;
        });
}

function messageFrom(event) {
    if (event.message) {
        return typeof event.message === 'string' ? event.message.slice(0, 2000) : 'Unknown script error';
    }

    if (event.target && (event.target.src || event.target.href)) {
        return 'Failed to load resource: ' + (event.target.src || event.target.href).slice(0, 2000);
    }

    return 'Unknown client error';
}

window.addEventListener('error', (event) => {
    push({
        message: messageFrom(event),
        url: window.location.href.slice(0, 500),
        stack: event.error && event.error.stack ? event.error.stack.slice(0, 8000) : null,
    });
});

window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;

    push({
        message: reason instanceof Error
            ? (reason.message || 'Unhandled promise rejection').slice(0, 2000)
            : String(reason).slice(0, 2000),
        url: window.location.href.slice(0, 500),
        stack: reason instanceof Error && reason.stack ? reason.stack.slice(0, 8000) : null,
    });
});

window.addEventListener('pagehide', flush);