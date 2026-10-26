<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class AuditRange
{
    /**
     * @var array<string, string>
     */
    public const PRESETS = [
        'last_hour' => 'Last 1 hour',
        'last_24h' => 'Last 24 hours',
        'today' => 'Today',
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'last_90_days' => 'Last 90 days',
        'all' => 'All time',
    ];

    /**
     * Resolve the active audit-log range from the request.
     * A valid preset always wins over custom from/to dates.
     *
     * @return array{from: ?Carbon, to: ?Carbon, preset: ?string, label: string}
     */
    public static function resolve(?string $preset, ?string $from, ?string $to): array
    {
        if ($preset !== null && array_key_exists($preset, self::PRESETS)) {
            $now = now();

            [$from, $to] = match ($preset) {
                'last_hour' => [$now->copy()->subHour(), $now->copy()],
                'last_24h' => [$now->copy()->subDay(), $now->copy()],
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
                'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
                'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
                default => [null, null],
            };

            return [
                'from' => $from,
                'to' => $to,
                'preset' => $preset,
                'label' => static::label($preset, $from, $to),
            ];
        }

        $customFrom = $from !== null && $from !== '' ? Carbon::parse($from)->startOfDay() : null;
        $customTo = $to !== null && $to !== '' ? Carbon::parse($to)->endOfDay() : null;

        if ($customFrom !== null || $customTo !== null) {
            return [
                'from' => $customFrom,
                'to' => $customTo,
                'preset' => null,
                'label' => 'Custom: '.($customFrom?->format('M j, Y') ?? '…').' – '.($customTo?->format('M j, Y') ?? '…'),
            ];
        }

        return ['from' => null, 'to' => null, 'preset' => 'all', 'label' => 'All time'];
    }

    private static function label(string $preset, ?Carbon $from, ?Carbon $to): string
    {
        if (in_array($preset, ['last_hour', 'last_24h', 'all'], true) || $from === null || $to === null) {
            return self::PRESETS[$preset];
        }

        return self::PRESETS[$preset].' ('.$from->format('M j').' – '.$to->format('M j, Y').')';
    }
}
