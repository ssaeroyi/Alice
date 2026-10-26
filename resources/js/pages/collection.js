/**
 * Collection page enhancements (STUDENT_PAGES_PLAN §4.5). The three
 * shelves are plain server-rendered GET tabs; filtering lives in the same
 * shared filter drawer used by the library, so this page wires it in along
 * with auto-search. Back-nav scroll restore is handled globally by
 * components/scroll-restore.js.
 */
import { initFilterDrawer, initAutoSearch } from '../components/filter-drawer.js';

export default function initCollectionPage() {
    initFilterDrawer();
    initAutoSearch();
}
