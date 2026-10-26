<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The one source of truth for dashboard date windows.
 *
 * Preset chips pass ?days=7|30|90, the custom picker passes ?from=&to=.
 * Everything clamps to MAX_DAYS and defaults to DEFAULT_DAYS so no
 * controller or view ever hardcodes a day count again.
 */
class DashboardRange
{
    public const DEFAULT_DAYS = 30;

    public const PRESETS = [7, 30, 90];

    public const MAX_DAYS = 366;

    private function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly int $days,
    ) {}

    public static function fromRequest(?Request $request = null): self
    {
        $request ??= request();

        $custom = self::custom($request);

        if ($custom !== null) {
            return $custom;
        }

        $days = (int) $request->query('days', self::DEFAULT_DAYS);

        if (! in_array($days, self::PRESETS, true)) {
            $days = self::DEFAULT_DAYS;
        }

        return new self(now()->subDays($days - 1)->toDateString(), now()->toDateString(), $days);
    }

    private static function custom(Request $request): ?self
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (! is_string($from) || ! is_string($to)) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return null;
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($start->greaterThan($end)) {
            return null;
        }

        $days = (int) $start->diffInDays($end) + 1;

        if ($days > self::MAX_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DAYS - 1);
            $days = self::MAX_DAYS;
        }

        return new self($start->toDateString(), $end->toDateString(), $days);
    }

    /** First day of the window (start of day). */
    public function start(): Carbon
    {
        return Carbon::parse($this->from)->startOfDay();
    }

    /** Last day of the window (end of day, inclusive). */
    public function end(): Carbon
    {
        return Carbon::parse($this->to)->endOfDay();
    }

    /** Every calendar day in the window, oldest first. */
    public function dates(): array
    {
        $dates = [];
        $cursor = $this->start();
        $last = Carbon::parse($this->to);

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /** day | week | month — how the charts bucket the window. */
    public function granularity(): string
    {
        return match (true) {
            $this->days <= 31 => 'day',
            $this->days <= 92 => 'week',
            default => 'month',
        };
    }

    /** Copy-friendly bucket word: "per day" / "per week" / "per month". */
    public function bucketNoun(): string
    {
        return $this->granularity();
    }

    /**
     * Roll a daily {date, count} series up to the window's granularity.
     *
     * @param  array<int, array{date: string, count?: int, total?: int}>  $daily
     * @return array<int, array{date: string, count: int}>
     */
    public function bucket(array $daily): array
    {
        $points = array_map(fn (array $point) => [
            'date' => $point['date'],
            'count' => (int) ($point['count'] ?? $point['total'] ?? 0),
        ], array_values($daily));

        $granularity = $this->granularity();

        if ($granularity === 'day') {
            return $points;
        }

        $buckets = [];

        foreach ($points as $point) {
            $key = $granularity === 'week'
                ? Carbon::parse($point['date'])->startOfWeek()->toDateString()
                : substr($point['date'], 0, 7).'-01';

            $buckets[$key] ??= ['date' => $key, 'count' => 0];
            $buckets[$key]['count'] += $point['count'];
        }

        return array_values($buckets);
    }

    /** Human window for chart subtitles, e.g. "Aug 8 to Sep 6". */
    public function humanWindow(): string
    {
        return Carbon::parse($this->from)->format('M j').' to '.Carbon::parse($this->to)->format('M j');
    }
}
