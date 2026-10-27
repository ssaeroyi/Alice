<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientError;
use App\Support\Audit;
use App\Support\Integrations;
use App\Support\LocalServices;
use App\Support\Maintenance;
use App\Support\Monitor;
use App\Support\Narration;
use App\Support\SystemStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemStatusController extends Controller
{
    public function index(Request $request): View
    {
        $errorLogCount = Monitor::errorLogErrorCount();
        Monitor::markErrorLogSeen();

        $range = in_array((int) $request->query('range'), [24, 168, 720, 2160], true)
            ? (int) $request->query('range')
            : 24;

        $traffic = Monitor::traffic($range);
        $loginThreat = Monitor::loginThreat($range);

        // The tapped tile whose series the chart below is drawing.
        $metrics = self::trafficMetrics($loginThreat);
        $selected = (string) $request->query('metric', 'traffic.requests');
        if (! isset($metrics[$selected])) {
            $selected = 'traffic.requests';
        }
        $series = $this->trafficSeriesFor($selected, $range);

        if ($request->query('partial') === '1') {
            return view('admin.partials.traffic', [
                'traffic' => $traffic,
                'range' => $range,
                'metrics' => $metrics,
                'selected' => $selected,
                'series' => $series,
                'loginThreat' => $loginThreat,
            ]);
        }

        return view('admin.status', [
            'status' => SystemStatus::snapshot(),
            'traffic' => $traffic,
            'range' => $range,
            'metrics' => $metrics,
            'selected' => $selected,
            'series' => $series,
            'mailHealth' => Monitor::mailHealth(),
            'loginThreat' => $loginThreat,
            'clientErrors' => $this->clientErrors(),
            'clientErrorCount' => ClientError::where('created_at', '>=', now()->subHours(24))->count(),
            'integrations' => Integrations::latestByIntegration(),
            'history' => collect(Integrations::definitions())
                ->keys()
                ->mapWithKeys(fn (string $name) => [$name => Integrations::history($name)])
                ->all(),
            'localTools' => LocalServices::check(),
            'narrationWanted' => Narration::wantedList(),
            'maintenance' => Maintenance::inventory(),
            'security' => [
                'topIps' => Monitor::topIps($range),
                'probeIps' => Monitor::probeIps($range),
                'loginAttempts' => Monitor::loginAttempts(50),
            ],
            'tab' => 'overview',
            'errorLogCount' => $errorLogCount,
        ]);
    }

    /**
     * Run the probe for one external service and flash the outcome. The
     * probe makes a tiny real call and records it, so the panel always
     * shows the last-known state even if nobody is looking.
     */
    public function testIntegration(Request $request, string $integration): RedirectResponse|JsonResponse
    {
        if (! array_key_exists($integration, Integrations::definitions())) {
            abort(404);
        }

        $result = Integrations::probe($integration);
        $label = Integrations::definitions()[$integration];

        Audit::log('system.integration_tested', null, ['integration' => $integration, 'ok' => $result['ok']]);

        $toast = [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['ok']
                ? $label.' is working ('.number_format($result['latency_ms']).' ms).'
                : $label.' check failed — '.$result['message'],
        ];

        if ($request->expectsJson()) {
            return response()->json(['html' => $this->integrationsFragment(), 'toast' => $toast]);
        }

        return $this->toTab('external')->with('toast', $toast);
    }

    public function testAllIntegrations(Request $request): RedirectResponse|JsonResponse
    {
        $results = [];

        foreach (array_keys(Integrations::definitions()) as $integration) {
            $results[$integration] = Integrations::probe($integration);
        }

        $working = count(array_filter($results, fn (array $result) => $result['ok']));
        $total = count($results);

        Audit::log('system.integrations_tested', null, ['ok' => $working, 'total' => $total]);

        $toast = [
            'type' => $working === $total ? 'success' : 'error',
            'message' => $working === $total
                ? 'All '.$total.' external services are working.'
                : ($total - $working).' of '.$total.' external services failed their check — see the panel for details.',
        ];

        if ($request->expectsJson()) {
            return response()->json(['html' => $this->integrationsFragment(), 'toast' => $toast]);
        }

        return $this->toTab('external')->with('toast', $toast);
    }

    /**
     * Toggle the session-scoped "Say-it debug" flag. When on, the student
     * say-it widget pages stamp data-stt-debug="1" so the STT debug pane
     * (state/stt-debug.js) mounts for device testing without a "View As" admin
     * session or a hand-typed ?stt-diag=1. Session-scoped: clears on logout.
     */
    public function toggleSttDebug(): RedirectResponse
    {
        $enabled = ! (bool) session('alice.stt_debug');
        session(['alice.stt_debug' => $enabled]);

        Audit::log('system.stt_debug_toggled', null, ['enabled' => $enabled]);

        return $this->toTab('external')->with('toast', [
            'type' => 'success',
            'message' => 'Say-it debug is now '.($enabled ? 'on' : 'off').'. '
                .($enabled
                    ? 'Open a student word/flashcard page to use the STT debug pane.'
                    : 'The pane will not appear until you turn it back on.'),
        ]);
    }

    /**
     * Redirect back to the status page on a specific tab. The browser's
     * Referer header never carries the URL fragment, so a plain `back()` after
     * a POST drops the admin onto the Overview tab; every status-page action
     * redirects to the tab that owns it instead.
     */
    private function toTab(string $tab): RedirectResponse
    {
        return redirect()->to(route('admin.status').'#'.$tab);
    }

    public function clearClientErrors(Request $request): RedirectResponse|JsonResponse
    {
        $deleted = ClientError::query()->delete();

        Audit::log('system.client_errors_cleared', null, ['count' => $deleted]);

        $toast = [
            'type' => 'success',
            'message' => 'Removed '.$deleted.' reported '.($deleted === 1 ? 'error' : 'errors').' from the client error log.',
        ];

        if ($request->expectsJson()) {
            return response()->json(['html' => $this->clientErrorsFragment(), 'toast' => $toast]);
        }

        return $this->toTab('traffic')->with('toast', $toast);
    }

    /**
     * Re-render the External services panel for an in-place AJAX swap.
     */
    private function integrationsFragment(): string
    {
        return view('admin.partials.status-integrations', [
            'integrations' => Integrations::latestByIntegration(),
            'history' => collect(Integrations::definitions())
                ->keys()
                ->mapWithKeys(fn (string $name) => [$name => Integrations::history($name)])
                ->all(),
        ])->render();
    }


    /**
     * Re-render the Client errors panel for an in-place AJAX swap.
     */
    private function clientErrorsFragment(): string
    {
        return view('admin.partials.status-client-errors', [
            'clientErrors' => $this->clientErrors(),
            'clientErrorCount' => ClientError::where('created_at', '>=', now()->subHours(24))->count(),
        ])->render();
    }

    private function clientErrors(): array
    {
        return ClientError::with('user')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (ClientError $error) {
                $message = strtolower($error->message);
                $hint = null;

                if (app()->isDownForMaintenance()) {
                    $hint = 'Maintenance mode is on — this may be why pages fail. Turn it off on the Control center.';
                } elseif (str_contains($message, 'fetch failed') || str_contains($message, 'load resource')) {
                    $hint = 'A network or resource problem on the device — check the Status probes for server-side issues first.';
                } elseif ($error->user_id !== null) {
                    $hint = 'Review this account — suspension, setup or a password reset may be needed.';
                }

                return [
                    'id' => $error->id,
                    'at' => $error->created_at,
                    'message' => $error->message,
                    'stack' => $error->stack,
                    'url' => $error->url,
                    'user_agent' => $error->user_agent,
                    'nickname' => $error->user?->nickname ?? strtok($error->user?->email ?? '', '@'),
                    'account_id' => $error->user_id,
                    'hint' => $hint,
                ];
            })
            ->all();
    }

    /**
     * One traffic metric's daily series as JSON, for the tile taps on the
     * Status traffic panel. Scoped to the panel's own hour chips rather than
     * the dashboard's day presets, so the chart a tile draws always matches
     * the window the chips selected.
     */
    public function trafficSeries(Request $request): JsonResponse
    {
        $metrics = self::trafficMetrics();
        $metric = (string) $request->query('metric', 'traffic.requests');

        if (! isset($metrics[$metric])) {
            return response()->json(['message' => 'Unknown metric.'], 422);
        }

        $range = in_array((int) $request->query('range'), [24, 168, 720, 2160], true)
            ? (int) $request->query('range')
            : 24;

        return response()->json([
            'metric' => $metric,
            'label' => $metrics[$metric]['label'],
            'unit' => $metrics[$metric]['unit'],
            'points' => $this->trafficSeriesFor($metric, $range),
        ]);
    }

    /**
     * The traffic-panel tiles that redraw the chart when tapped. Each one maps
     * to a daily series builder. When the login threat is passed in, the
     * distinct-emails hint also names the top offender IP.
     *
     * @return array<string, array{label: string, unit: string, hint: string}>
     */
    private static function trafficMetrics(?array $loginThreat = null): array
    {
        $distinctHint = 'How many different accounts the failed sign-ins in this window belong to. One or two is usually a mistyped password; a rising spread means someone is working through a list of accounts.';

        if (($loginThreat['top_ip'] ?? null) !== null) {
            $distinctHint .= ' Most of the recent attempts came from '.$loginThreat['top_ip'].' ('.$loginThreat['top_ip_count'].' tries).';
        }

        return [
            'traffic.requests' => ['label' => 'Page views', 'unit' => 'page views', 'hint' => 'Every page load in this window (students, admin, teachers) — measured live on each request, nothing runs in the background.'],
            'traffic.avg_ms' => ['label' => 'Avg load time', 'unit' => 'ms', 'hint' => 'The typical page-load speed. Healthy is usually under ~500 ms; the story-heavy pages are the slow ones.'],
            'traffic.p95_ms' => ['label' => 'Slowest loads', 'unit' => 'ms', 'hint' => '95% of requests were faster than this — the slowest outliers are ignored, so this is the real user experience. Values above ~1,500 ms mean pages feel sluggish.'],
            'traffic.four_xx' => ['label' => 'Visitor errors', 'unit' => 'errors', 'hint' => 'Failures caused by the visitor — mistyped URLs (404) or failed sign-ins (401/403). Usually harmless; not the app\'s fault.'],
            'traffic.five_xx' => ['label' => 'Server failures', 'unit' => 'errors', 'hint' => 'Requests the app itself failed. 0 is the healthy state; a handful now and then is normal — a rising trend needs the Logs page.'],
            'traffic.failed_logins' => ['label' => 'Failed logins', 'unit' => 'attempts', 'hint' => 'Wrong-password attempts in this window. Most are simply mistyped passwords; a spike means someone is hammering accounts.'],
            'traffic.distinct_emails' => ['label' => 'Distinct emails', 'unit' => 'accounts', 'hint' => $distinctHint],
        ];
    }

    /**
     * Points for one traffic metric across the panel's window, oldest first,
     * every slot filled so the line never gaps. The 24h window buckets by
     * hour (a day would be one pointless point); the wider windows bucket by
     * day. The requests metric reuses the nightly roll-up for daily windows
     * (it survives log pruning); everything else reads the live request log,
     * which only holds the newest rows.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function trafficSeriesFor(string $metric, int $range): array
    {
        try {
            if ($range === 24) {
                return $this->trafficHourlySeries($metric);
            }

            $days = match ($range) {
                168 => 7,
                720 => 30,
                default => 90,
            };

            $start = now()->subDays($days - 1)->startOfDay();

            if ($metric === 'traffic.requests') {
                $counts = collect(Monitor::dailySeries($days))->pluck('total', 'date');
            } elseif ($metric === 'traffic.failed_logins' || $metric === 'traffic.distinct_emails') {
                $expression = $metric === 'traffic.distinct_emails' ? 'COUNT(DISTINCT email)' : 'COUNT(*)';

                $counts = DB::table('login_attempts')
                    ->where('created_at', '>=', $start)
                    ->where('successful', false)
                    ->selectRaw("DATE(created_at) AS day, {$expression} AS total")
                    ->groupBy('day')
                    ->pluck('total', 'day');
            } elseif ($metric === 'traffic.p95_ms') {
                return $this->trafficP95Series($start);
            } else {
                $expression = match ($metric) {
                    'traffic.avg_ms' => 'AVG(duration_ms)',
                    'traffic.four_xx' => 'SUM(CASE WHEN status BETWEEN 400 AND 499 THEN 1 ELSE 0 END)',
                    'traffic.five_xx' => 'SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END)',
                    default => 'COUNT(*)',
                };

                $counts = DB::table('request_logs')
                    ->where('created_at', '>=', $start)
                    ->selectRaw("DATE(created_at) AS day, {$expression} AS total")
                    ->groupBy('day')
                    ->pluck('total', 'day');
            }

            return $this->fillDailySeries($start, $counts, $metric === 'traffic.avg_ms');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The last 24 hours bucketed by hour for one metric. Requests always come
     * from the live log here — the roll-up only stores whole days.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function trafficHourlySeries(string $metric): array
    {
        $start = now()->subHours(23)->startOfHour();

        if ($metric === 'traffic.p95_ms') {
            $rows = DB::table('request_logs')
                ->where('created_at', '>=', $start)
                ->whereNotNull('duration_ms')
                ->selectRaw('HOUR(created_at) AS hour, duration_ms')
                ->get()
                ->groupBy('hour');

            return $this->fillHourlySeries($start, $rows->map(function ($group) {
                $sorted = $group->pluck('duration_ms')->sort()->values();
                $index = (int) ceil(0.95 * $sorted->count()) - 1;

                return (int) round($sorted[$index] ?? 0);
            }), false);
        }

        if ($metric === 'traffic.failed_logins' || $metric === 'traffic.distinct_emails') {
            $expression = $metric === 'traffic.distinct_emails' ? 'COUNT(DISTINCT email)' : 'COUNT(*)';

            $counts = DB::table('login_attempts')
                ->where('created_at', '>=', $start)
                ->where('successful', false)
                ->selectRaw("HOUR(created_at) AS hour, {$expression} AS total")
                ->groupBy('hour')
                ->pluck('total', 'hour');
        } else {
            $expression = match ($metric) {
                'traffic.avg_ms' => 'AVG(duration_ms)',
                'traffic.four_xx' => 'SUM(CASE WHEN status BETWEEN 400 AND 499 THEN 1 ELSE 0 END)',
                'traffic.five_xx' => 'SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END)',
                default => 'COUNT(*)',
            };

            $counts = DB::table('request_logs')
                ->where('created_at', '>=', $start)
                ->selectRaw("HOUR(created_at) AS hour, {$expression} AS total")
                ->groupBy('hour')
                ->pluck('total', 'hour');
        }

        return $this->fillHourlySeries($start, $counts, $metric === 'traffic.avg_ms');
    }

    /**
     * Per-day p95 load time. duration_ms is not indexed for a grouped
     * percentile, so the window's rows are pulled once and bucketed in PHP —
     * the log only keeps the newest ~10,000 rows, so this stays cheap.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function trafficP95Series(Carbon $start): array
    {
        $rows = DB::table('request_logs')
            ->where('created_at', '>=', $start)
            ->whereNotNull('duration_ms')
            ->selectRaw('DATE(created_at) AS day, duration_ms')
            ->get()
            ->groupBy('day');

        return $this->fillDailySeries($start, $rows->map(function ($group) {
            $sorted = $group->pluck('duration_ms')->sort()->values();
            $index = (int) ceil(0.95 * $sorted->count()) - 1;

            return (int) round($sorted[$index] ?? 0);
        }), false);
    }

    /**
     * Walk every hour from $start to now, carrying the fetched totals across
     * so missing hours read as a real 0 rather than a hole in the line. Point
     * dates are "HH:00" labels, not calendar dates.
     *
     * @param  Collection<string, mixed>  $totals
     * @return array<int, array{date: string, count: int}>
     */
    private function fillHourlySeries(Carbon $start, $totals, bool $round): array
    {
        $series = [];
        $hour = $start->copy();
        $end = now()->startOfHour();

        while ($hour->lte($end)) {
            $key = (string) $hour->hour;
            $value = (float) ($totals[$key] ?? 0);
            $series[] = [
                'date' => $hour->format('G').':00',
                'count' => $round ? (int) round($value) : (int) $value,
            ];
            $hour->addHour();
        }

        return $series;
    }

    /**
     * Walk every day from $start to today, carrying the fetched totals across
     * so missing days read as a real 0 rather than a hole in the line.
     *
     * @param  Collection<string, mixed>  $totals
     * @return array<int, array{date: string, count: int}>
     */
    private function fillDailySeries(Carbon $start, $totals, bool $round): array
    {
        $series = [];
        $day = $start->copy();
        $end = now()->startOfDay();

        while ($day->lte($end)) {
            $key = $day->toDateString();
            $value = (float) ($totals[$key] ?? 0);
            $series[] = [
                'date' => $key,
                'count' => $round ? (int) round($value) : (int) $value,
            ];
            $day->addDay();
        }

        return $series;
    }
}
