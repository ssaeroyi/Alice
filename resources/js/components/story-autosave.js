const DEBOUNCE_MS = 1500;
const MIN_INTERVAL_MS = 4000;
const EXCLUDED_FIELDS = ['cover_mode', 'cover_url', 'remove_cover', 'cover_image', '_token', '_method'];

export function initStoryAutosave() {
    const form = document.querySelector('form[data-story-autosave]');
    const badge = document.querySelector('[data-autosave-badge]');

    if (!form || !badge) {
        return;
    }

    let timer = null;
    let controller = null;
    let lastSaveAt = 0;
    let dirty = false;
    let savedAt = null;
    let agoTimer = null;

    const setBadge = (text, tone) => {
        badge.textContent = text;
        badge.classList.toggle('text-[#2D231E]', tone === 'quiet' || tone === 'busy');
        badge.classList.toggle('animate-pulse', tone === 'busy');
        badge.classList.toggle('text-emerald-700', tone === 'good');
        badge.classList.toggle('text-amber-700', tone === 'warn');
    };

    const renderSaved = () => {
        if (!savedAt) {
            return;
        }

        const mins = Math.floor((Date.now() - savedAt) / 60000);

        setBadge(mins < 1 ? 'All changes saved' : 'Saved ' + mins + 'm ago', 'good');
    };

    const markSaved = () => {
        savedAt = Date.now();
        renderSaved();
        clearInterval(agoTimer);
        agoTimer = setInterval(renderSaved, 30000);
    };

    const save = async () => {
        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        const data = new FormData(form);

        EXCLUDED_FIELDS.forEach((name) => data.delete(name));
        data.append('_method', 'PUT');

        setBadge('Saving…', 'busy');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: data,
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error('autosave rejected');
            }

            lastSaveAt = Date.now();

            if (dirty) {
                setBadge('Unsaved changes', 'quiet');
            } else {
                markSaved();
            }
        } catch (saveError) {
            if (saveError.name === 'AbortError') {
                return;
            }

            setBadge('Save failed — will retry when you type', 'warn');
        } finally {
            controller = null;
        }
    };

    const queue = (event) => {
        const name = event.target && event.target.name;

        if (name && EXCLUDED_FIELDS.includes(name)) {
            return;
        }

        dirty = true;
        clearTimeout(timer);
        setBadge('Unsaved changes', 'quiet');

        const wait = Math.max(DEBOUNCE_MS, MIN_INTERVAL_MS - (Date.now() - lastSaveAt));

        timer = setTimeout(save, wait);
    };

    form.addEventListener('input', queue);
    form.addEventListener('change', queue);

    form.addEventListener('submit', () => {
        clearTimeout(timer);

        if (controller) {
            controller.abort();
        }
    });
}
