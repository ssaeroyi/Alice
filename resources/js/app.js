import { initPasswordToggle } from './password-toggle.js';
import { initNoSpaceInputs } from './no-space-input.js';
import { initLiveValidation } from './live-validation.js';
import { initToasts, showToast } from './components/toast.js';
import { initSubmitGuards } from './components/submit-guard.js';
import { initBusyOverlay } from './components/busy-overlay.js';
import { initAjaxForms } from './components/ajax-form.js';
import { initModals } from './components/modal.js';
import { initDropdowns } from './components/dropdown.js';
import { initBellPanel } from './components/bell-panel.js';
import { initAutoFilters } from './components/auto-filters.js';
import { initTabHistory } from './components/tab-history.js';
import { initConfirmForms } from './components/confirm-form.js';
import { initSidebars } from './components/sidebar.js';
import { initInfoPopovers } from './components/info-popover.js';
import { initTooltips } from './components/tooltips.js';
import { initTrafficTabs } from './components/traffic-tabs.js';
import { initDatePicker } from './components/date-picker.js';
import { initWordCount } from './components/word-count.js';
import { initStoryForm } from './components/story-form.js';
import { initStoryAutosave } from './components/story-autosave.js';
import { initStoryQuiz } from './components/story-quiz.js';
import { initStoryJournal } from './components/story-journal.js';
import { initStoryQuizPreview } from './components/story-quiz-preview.js';
import { initStoryReaderPreview } from './components/story-reader-preview.js';
import { initLibraryImport } from './components/alice-library.js';
import { initStoryReorder } from './components/story-reorder.js';
import { initStoryTabs } from './components/story-tabs.js';
import { initStoryChecklist } from './components/story-checklist.js';
import { initNarrationStatus } from './components/narration-status.js';
import { initHearNarration } from './components/hear-narration.js';
import { initStoryTabDots } from './components/story-tab-dots.js';
import { initStoryInsights } from './components/story-insights.js';
import { initScrollRestore } from './components/scroll-restore.js';
import { initStoryBatch } from './components/story-batch.js';
import { initStoryDrawer } from './components/story-drawer.js';
import { initStickyBars } from './components/sticky-bar.js';
import { initDevTools } from './components/dev-tools.js';
import { initSound } from './components/sound.js';
import { initPronounceChecks } from './components/speech.js';
import initSttDebug from './state/stt-debug.js';
import { initRankRoadmap } from './components/rank-roadmap.js';
import { initHelpPager } from './components/help-pager.js';
import { initJournalHelp } from './components/journal-help.js';
import { initCelebrate } from './components/celebrate.js';
import { initBadgeDetail } from './components/badge-detail.js';
import './client-error.js';
import { initPageLoader } from './pages/loader.js';
import { initTourDriver } from './tour/tour.js';
import { initThemeToggles } from './components/theme-toggle.js';
import { initFilterDrawer, initAutoSearch } from './components/filter-drawer.js';
import { initAdminNotifications } from './components/admin-notifications.js';
import { initReadingGoalSlider } from './pages/placement-step1.js';
import { initVocabPager } from './pages/placement-step2.js';
import { initComprehensionPager } from './pages/placement-step4.js';

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
};

// One broken component must never silently skip the inits after it (that is
// how the dashboard explorer lost its AJAX). Each init gets its own guard so
// the failure is logged, reported, and the rest of the page still works.
const safe = (label, fn) => {
    try {
        fn();
    } catch (error) {
        console.error(`[alice] ${label} failed`, error);
    }
};

ready(() => {
    // Every init is safe()-guarded: one broken component must never skip the
    // inits after it (that is how the dashboard explorer lost its AJAX, and
    // how the preview overlay can silently die).
    safe('passwordToggle', initPasswordToggle);
    safe('noSpaceInputs', initNoSpaceInputs);
    safe('liveValidation', initLiveValidation);
    safe('toasts', initToasts);
    window.showToast = showToast;
    safe('submitGuards', initSubmitGuards);
    safe('busyOverlay', initBusyOverlay);
    safe('ajaxForms', initAjaxForms);
    safe('modals', initModals);
    safe('dropdowns', initDropdowns);
    safe('bellPanel', initBellPanel);
    safe('autoFilters', initAutoFilters);
    safe('tabHistory', initTabHistory);
    safe('confirmForms', initConfirmForms);
    safe('sidebars', initSidebars);
    safe('infoPopovers', initInfoPopovers);
    safe('tooltips', initTooltips);
    safe('scrollRestore', initScrollRestore);
    safe('stickyBars', initStickyBars);
    safe('sound', initSound);
    safe('pronounceChecks', initPronounceChecks);
    safe('readingGoalSlider', initReadingGoalSlider);
    safe('vocabPager', initVocabPager);
    safe('comprehensionPager', initComprehensionPager);
    safe('sttDebug', initSttDebug);
    safe('rankRoadmap', initRankRoadmap);
    safe('helpPager', initHelpPager);
    safe('journalHelp', initJournalHelp);
    safe('celebrate', initCelebrate);
    safe('badgeDetail', initBadgeDetail);
    safe('pageLoader', initPageLoader);
    safe('tourDriver', initTourDriver);
    if (document.querySelector('[data-theme-toggle]')) {
        safe('themeToggles', initThemeToggles);
    }

    // Dev-tools overlay must bind on every area: it renders in the student and
    // teacher preview banners (data-dev-tools), so it cannot live behind the
    // teacher/admin-only branch below. The selector guard keeps it inert when
    // no overlay is present.
    safe('devTools', initDevTools);

    // Teacher/admin-only interactivity (story authoring, reporting, control
    // center). These never bound to student/guest DOM, so run them
    // only where the layout declares data-area="teacher" or "admin". Keeps
    // their parse+run cost off student pages (Level 1 split; the code stays in
    // this bundle but does not execute elsewhere).
    const area = document.body?.dataset?.area ?? '';
    if (area === 'teacher' || area === 'admin') {
        safe('trafficTabs', initTrafficTabs);
        safe('datePicker', initDatePicker);
        safe('wordCount', initWordCount);
        safe('storyForm', initStoryForm);
        safe('storyAutosave', initStoryAutosave);
        safe('storyQuiz', initStoryQuiz);
        safe('storyJournal', initStoryJournal);
        safe('storyQuizPreview', initStoryQuizPreview);
        safe('storyReaderPreview', initStoryReaderPreview);
        safe('libraryImport', initLibraryImport);
        safe('storyReorder', initStoryReorder);
        safe('storyTabs', initStoryTabs);
        safe('storyChecklist', initStoryChecklist);
        safe('storyInsights', initStoryInsights);
        safe('narrationStatus', initNarrationStatus);
        safe('hearNarration', initHearNarration);
        safe('storyTabDots', initStoryTabDots);
        safe('storyBatch', initStoryBatch);
        safe('storyDrawer', initStoryDrawer);
        safe('filterDrawer', initFilterDrawer);
        safe('autoSearch', initAutoSearch);
    }

    // Admin-only page modules (lazy-loaded to keep the global bundle lean).
    if (area === 'admin') {
        safe('adminNotifications', initAdminNotifications);
        if (
            document.querySelector('[data-growth-canvas]') ||
            document.querySelector('[data-metric-canvas]') ||
            document.querySelector('[data-onboarding-canvas]')
        ) {
            import('./pages/admin-dashboard.js')
                .then((m) => m.default?.())
                .catch((error) => console.error('[alice] admin-dashboard chunk failed', error));
        }
        if (document.querySelector('[data-traffic-chart-canvas]')) {
            import('./pages/admin-traffic-chart.js')
                .then((m) => m.default?.())
                .catch((error) => console.error('[alice] admin-traffic-chart chunk failed', error));
        }
        if (document.querySelector('[data-status-tabs]')) {
            import('./pages/admin-status.js')
                .then((m) => m.default?.())
                .catch((error) => console.error('[alice] admin-status chunk failed', error));
        }
    }
});