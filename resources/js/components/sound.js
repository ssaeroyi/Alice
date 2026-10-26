/**
 * Global sound core (S3) — makes the synthesized sfx singleton available to
 * every student page and binds the header mute toggle. The sfx module itself
 * (resources/js/game/sfx.js) owns the Web Audio context + mute persistence;
 * this component only:
 *   1. exposes `window.SFX` so any page/module can play cues,
 *   2. wires every `[data-sound-toggle]` button to toggle mute and re-sync its
 *      sound-on / sound-off glyphs, and
 *   3. keeps those buttons in sync with mute changes from anywhere.
 */

import {
    play,
    prime,
    toggleMute,
    setMuted,
    getMuted,
    onMuteChange,
} from '../game/sfx.js';

export function initSound() {
    // Sfx are student-side only. Skip entirely on admin / teacher areas so
    // nothing (toast cues, click cues, audio priming) ever makes noise there.
    const area = document.body?.dataset?.area ?? '';
    if (area === 'admin' || area === 'teacher') {
        return;
    }

    window.SFX = { play, prime, toggleMute, setMuted, getMuted };

    // Eagerly create + resume the audio context on the very first user
    // gesture (capture phase, before page-level cue handlers run) so a
    // freshly loaded page never drops its first sound.
    const primeOnce = () => {
        try {
            prime();
        } catch {
            // Audio context unavailable — prime silently.
        }
        window.removeEventListener('pointerdown', primeOnce, { capture: true });
        window.removeEventListener('keydown', primeOnce, { capture: true });
    };
    window.addEventListener('pointerdown', primeOnce, { capture: true });
    window.addEventListener('keydown', primeOnce, { capture: true });

    // Blade-friendly hook: any element carrying `data-cue="<name>"` plays
    // that cue on click/tap (settings toggles, reader turn zones, etc.).
    document.addEventListener('click', (event) => {
        const target = event.target?.closest?.('[data-cue]');
        if (!target) return;
        play(target.getAttribute('data-cue'));
    });

    const buttons = Array.from(document.querySelectorAll('[data-sound-toggle]'));
    if (!buttons.length) return;

    const sync = () => {
        const muted = getMuted();
        buttons.forEach((btn) => {
            const on = btn.querySelector('[data-sound-on]');
            const off = btn.querySelector('[data-sound-off]');
            if (on) on.classList.toggle('hidden', muted);
            if (off) off.classList.toggle('hidden', !muted);
            btn.setAttribute('aria-pressed', muted ? 'false' : 'true');
            btn.setAttribute('title', muted ? 'Sound: off — tap to enable' : 'Sound: on — tap to mute');
        });
    };

    buttons.forEach((btn) => {
        btn.setAttribute('role', 'switch');
        btn.setAttribute('aria-pressed', getMuted() ? 'false' : 'true');
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMute();
            // Play a feedback blip only when enabling sound.
            if (!getMuted()) play('select');
        });
    });

    onMuteChange(sync);
    sync();
}
