<?php

namespace App\Support;

/**
 * Deterministic pagination window shared by every pager partial
 * (library / teacher / alice). Always at most four numbered chips
 * around the current page, with page 1 and the last page pinned at the
 * ends and an ellipsis marking any gap - so a large library never
 * spills dozens of page numbers and the ellipsis never appears in a
 * surprising spot.
 *
 * @return array<int, int|string> ordered items; the string '…' marks a gap
 */
class PagerWindow
{
    /**
     * Shapes (last = 26, ellipsis marks a gap):
     *   - near start  ->  1 2 3 4 … 26
     *   - middle      ->  1 … 12 13 14 15 … 26
     *   - near end    ->  1 … 23 24 25 26
     *
     * @return array<int, int|string> ordered items; the string '…' marks a gap
     */
    public static function items(int $current, int $last): array
    {
        // Small pagers show every page; no ellipsis needed.
        if ($last <= 6) {
            return range(1, $last);
        }

        // Four-chip run [start, start + 3]; current stays inside it. Near the
        // start the run anchors on page 1 so the leading end looks crisp.
        $start = max(1, min($current <= 4 ? 1 : $current - 1, $last - 3));
        $end = min($last, $start + 3);

        $items = [];

        if ($start > 1) {
            $items[] = 1;

            if ($start > 2) {
                $items[] = '…';
            }
        }

        for ($page = $start; $page <= $end; $page++) {
            $items[] = $page;
        }

        if ($end < $last) {
            if ($last - $end > 1) {
                $items[] = '…';
            }

            $items[] = $last;
        }

        return $items;
    }
}
