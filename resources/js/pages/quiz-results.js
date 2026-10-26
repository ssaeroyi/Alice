/**
 * Quiz results page (FRONTEND_GUIDELINES § pages/*.js loader).
 *
 * This page is intentionally a no-op: it is fully server-rendered and uses only
 * global interactivity (toasts, submit-guards, sound). It previously had NO
 * module here while declaring data-page="quiz-results", which made the page
 * loader's chunk-error contract fire a spurious full page reload on first visit.
 *
 * Declaring a module that exists (even empty) keeps the loader happy and the
 * "every declared page must have a module" rule satisfied.
 */
export default function () {
    /* no-op by design */
}
