/**
 * Admin inbox page (per-page chunk, FRONTEND_GUIDELINES §10.6).
 * Tab/pager swaps are handled by components/inbox-panel.js; the top-bar
 * badge poll (admin-notifications.js) stays a global component.
 */
import { initInboxPanel } from '../components/inbox-panel.js';

export default function init() {
    initInboxPanel();
}
