/**
 * Lightweight entry for the marketing landing page only.
 * Provides theme toggle, server flash toasts, global SFX, scroll-reveal
 * (IntersectionObserver) and the ALICE sprite hover cue — nothing from the
 * 38-import student/teacher bundle ships here.
 */

import { initThemeToggles } from './components/theme-toggle.js';
import { initToasts } from './components/toast.js';
import { initSound } from './components/sound.js';

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
};

ready(() => {
    initThemeToggles();
    initToasts();
    initSound();

    // Scroll reveal — respected reduced-motion, skipped entirely when the
    // user prefers no animation.
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const reveals = document.querySelectorAll('[data-reveal]');
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.15 });
            reveals.forEach((el, i) => {
                el.style.transitionDelay = `${i * 60}ms`;
                observer.observe(el);
            });
        } else {
            reveals.forEach((el) => el.classList.add('revealed'));
        }

        // ALICE sprite hover SFX
        const alice = document.getElementById('landing-alice');
        if (alice && window.SFX) {
            alice.addEventListener('mouseenter', () => window.SFX.play('flip'));
        }
    }
});
