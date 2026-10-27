<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlacementAttemptStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\DashboardRange;
use App\Support\OpsMetrics;
use App\Support\SystemStatus;
use App\Support\ViewAs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $range = DashboardRange::fromRequest($request);
        $status = SystemStatus::snapshot();
        $counts = $status['counts'];

        $students = $counts['students'];
        $setupRate = $students > 0 ? min(100, (int) round($counts['setup_done_students'] / $students * 100)) : 0;

        $requestedDays = (int) $request->query('days', DashboardRange::DEFAULT_DAYS);
        $activePreset = $request->filled('from')
            ? null
            : (in_array($requestedDays, DashboardRange::PRESETS, true) ? $requestedDays : DashboardRange::DEFAULT_DAYS);

        $activeMetric = (string) $request->query('metric', 'traffic.requests');

        if (! OpsMetrics::has($activeMetric)) {
            $activeMetric = 'traffic.requests';
        }

        $metricSeries = OpsMetrics::series($activeMetric, $range);

        $uptime = SystemStatus::uptimeSeconds();

        return view('admin.dashboard', [
            'counts' => $counts,
            'vitals' => [
                'uptime_seconds' => $uptime,
                'uptime_booted_at' => $uptime !== null ? time() - $uptime : null,
                'storage_bytes' => $status['storage']['bytes'],
                'memory_mb' => SystemStatus::memoryMb(),
            ],
            'newThisWeek' => User::realAccounts()->where('created_at', '>=', now()->startOfWeek())->count(),
            'activeSessions' => DB::table('sessions')->count(),
            'setupRate' => $setupRate,
            'setupPending' => $students - $counts['setup_done_students'],
            'range' => $range,
            'activePreset' => $activePreset,
            'metrics' => collect(OpsMetrics::definitions())
                ->map(fn (array $meta, string $key) => $meta + [
                    'key' => $key,
                    'headline' => OpsMetrics::headline($key, OpsMetrics::series($key, $range)),
                ])
                ->all(),
            'activeMetric' => $activeMetric,
            'metricMeta' => OpsMetrics::meta($activeMetric),
            'metricSeries' => $metricSeries,
            'metricSubtitle' => OpsMetrics::subtitle($activeMetric, $range),
            'tileBase' => $request->query(),
            'recentAudit' => AuditLog::query()
                ->where(function ($q) {
                    $q->whereNull('actor_id')
                        ->orWhereHas('actor', fn ($a) => $a->realAccounts());
                })
                ->with('actor')
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'recentJoins' => User::realAccounts()->latest('created_at')->limit(5)->get(),
            // Freshest activity first, same GREATEST rule as the presence
            // filter, so the dashboard list and roster agree.
            'activeNow' => User::realAccounts()
                ->whereRaw("GREATEST(COALESCE(last_seen_at, '1970-01-01'), COALESCE(last_login_at, '1970-01-01')) >= ?", [now()->subDay()])
                ->orderByRaw("GREATEST(COALESCE(last_seen_at, '1970-01-01'), COALESCE(last_login_at, '1970-01-01')) DESC")
                ->limit(6)
                ->get(),
            'growthSeries' => $range->bucket(self::growthSeries($range)),
            'onboardingSeries' => $range->bucket(self::onboardingSeries($range)),
        ]);
    }

    /**
     * One metric of the explorer as JSON, for the tile click. Reads the same
     * shared date range as the page, so the chart a tile draws always matches
     * the window the admin picked.
     */
    public function series(Request $request): JsonResponse
    {
        $metric = (string) $request->query('metric', '');

        if (! OpsMetrics::has($metric)) {
            return response()->json(['message' => 'Unknown metric.'], 422);
        }

        $range = DashboardRange::fromRequest($request);
        $meta = OpsMetrics::meta($metric);
        $series = OpsMetrics::series($metric, $range);

        return response()->json([
            'metric' => $metric,
            'label' => $meta['label'],
            'unit' => $meta['unit'],
            'hint' => $meta['hint'],
            'precision' => $meta['precision'],
            'zero' => $meta['kind'] !== 'latest',
            'headline' => OpsMetrics::headline($metric, $series),
            'subtitle' => OpsMetrics::subtitle($metric, $range),
            'detail_url' => OpsMetrics::detailUrl($metric),
            'detail_label' => $meta['detail_label'] ?? null,
            'points' => $series,
        ]);
    }

    /**
     * Students registered per day inside the window, oldest first.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private static function growthSeries(DashboardRange $range): array
    {
        try {
            $rows = User::query()
                ->where('role', Role::Student)
                ->whereNot('email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
                ->whereBetween('created_at', [$range->start(), $range->end()])
                ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('total', 'day');

            return self::fill($range, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Students who finished the placement quick check per day inside the
     * window, oldest first. One attempt per user, so rows equal students.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private static function onboardingSeries(DashboardRange $range): array
    {
        try {
            $rows = DB::table('placement_attempts')
                ->join('users', 'users.id', '=', 'placement_attempts.user_id')
                ->where('placement_attempts.status', PlacementAttemptStatus::Completed->value)
                ->whereNotNull('placement_attempts.completed_at')
                ->where('users.role', Role::Student->value)
                ->whereNot('users.email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
                ->whereBetween('placement_attempts.completed_at', [$range->start(), $range->end()])
                ->selectRaw('DATE(placement_attempts.completed_at) AS day, COUNT(*) AS total')
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('total', 'day');

            return self::fill($range, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Zero-fill a per-day count map across every day of the window.
     *
     * @param  array<string, int>  $rows
     * @return array<int, array{date: string, count: int}>
     */
    private static function fill(DashboardRange $range, $rows): array
    {
        $series = [];

        foreach ($range->dates() as $day) {
            $series[] = ['date' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }

        return $series;
    }
}
