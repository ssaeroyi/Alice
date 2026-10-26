const DATA_ROOT = '[data-hear-narration]';

function formatSeconds(seconds) {
    const total = Math.round(Number(seconds) || 0);
    const minutes = Math.floor(total / 60);
    const rest = total % 60;
    return `${minutes}:${String(rest).padStart(2, '0')}`;
}

/**
 * Teacher audition player: one native <audio> control that plays every fresh
 * narration section in sequence (page 1 -> 2 -> ... -> last), like one
 * continuous narration. Tracks are server-rendered on the root element
 * (data-hear-tracks), so without JS the control still plays the first page.
 */
export function initHearNarration() {
    document.querySelectorAll(DATA_ROOT).forEach((root) => {
        const audio = root.querySelector('[data-hear-audio]');
        const meta = root.querySelector('[data-hear-meta]');

        if (!audio || !meta) {
            return;
        }

        let tracks;
        try {
            tracks = JSON.parse(root.dataset.hearTracks || '[]');
        } catch (error) {
            return;
        }

        if (!Array.isArray(tracks) || tracks.length === 0) {
            return;
        }

        let index = 0;
        const total = tracks.reduce((sum, track) => sum + (Number(track.seconds) || 0), 0);

        // Native <audio> doesn't paint a buffering state on its own — surface it
        // so a slow narration track reads as loading, never as broken.
        audio.addEventListener('waiting', () => root.setAttribute('aria-busy', 'true'));
        audio.addEventListener('playing', () => root.removeAttribute('aria-busy'));
        audio.addEventListener('error', () => {
            root.removeAttribute('aria-busy');
            try { audio.load(); } catch { /* noop */ }
        });

        const render = () => {
            const track = tracks[index];
            const page = track && track.page ? track.page : index + 1;
            meta.textContent = `Page ${page} of ${tracks.length}, ${formatSeconds(total)} total`;
        };

        audio.addEventListener('ended', () => {
            if (index >= tracks.length - 1) {
                return;
            }

            index += 1;
            audio.src = tracks[index].url;
            audio.play();
            render();
        });

        render();
    });
}