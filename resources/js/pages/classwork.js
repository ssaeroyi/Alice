/**
 * Classwork page enhancement (FRONTEND_GUIDELINES § pages/*.js loader).
 *
 * The dedicated /classwork page renders the same data-cw-root widget the hub
 * tab used to. Only hub-classwork.js is needed here: it wires the classes
 * landing <-> per-class drill-down toggles, the Join class modal submit, and
 * the modal Leave flow. No tabs, no stats panel on this page.
 */
import { initHubClasswork } from '../components/hub-classwork.js';

export default function () {
    initHubClasswork();
}
