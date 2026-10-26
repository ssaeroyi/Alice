<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\ClientError;
use App\Support\Audit;
use App\Support\AuditCatalog;
use App\Support\AuditRange;
use App\Support\Monitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public const TABS = [
        'audit' => 'Audit',
        'server' => 'Server errors',
        'browser' => 'Browser errors',
    ];

    public function index(AuditLogIndexRequest $request): View
    {
        Monitor::markErrorLogSeen();

        $tab = in_array($request->query('tab'), array_keys(self::TABS), true)
            ? $request->query('tab')
            : 'audit';

        $range = AuditRange::resolve(
            $request->validated('preset'),
            $request->validated('from'),
            $request->validated('to'),
        );

        $data = [
            'tab' => $tab,
            'tabs' => self::TABS,
            'range' => $range,
            'categories' => AuditCatalog::CATEGORIES,
            'activeCategory' => $request->validated('category'),
        ];

        if ($tab === 'server') {
            $status = in_array($request->query('status'), ['4xx', '5xx', 'all'], true)
                ? $request->query('status')
                : 'all';

            return view('admin.logs.index', $data + [
                'entries' => $this->serverErrors($range, $status)->paginate(50)->withQueryString(),
                'serverStatus' => $status,
            ]);
        }

        if ($tab === 'browser') {
            return view('admin.logs.index', $data + [
                'clientErrors' => $this->clientErrors(),
                'clientErrorCount' => ClientError::where('created_at', '>=', now()->subHours(24))->count(),
            ]);
        }

        $actions = DB::table('audit_logs')
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.logs.index', $data + [
            'entries' => $this->filtered($request)
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString(),
            'actions' => $actions,
        ]);
    }

    /**
     * CSV of the exact filtered audit view the admin currently sees. The
     * audit log is append-only by design (integrity), so export is the
     * meaningful action — the school can take a filtered snapshot offline
     * without any write.
     */
    public function export(AuditLogIndexRequest $request): StreamedResponse|Response
    {
        $entries = $this->filtered($request)->latest('created_at')->get();

        $filename = 'alice-audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($entries) {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['When', 'Action', 'Label', 'Actor', 'Details']);

                foreach ($entries as $entry) {
                    fputcsv($out, [
                        $entry->created_at->toDateTimeString(),
                        $entry->action,
                        AuditCatalog::entry($entry->action)['label'],
                        $entry->actor?->nickname ?? $entry->actor?->email ?? 'System',
                        (string) json_encode($entry->meta ?? []),
                    ]);
                }

                fclose($out);
            },
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * Failing requests (4xx/5xx) from the request log across the chosen
     * window — the destination for the dashboard's error tiles.
     */
    private function serverErrors(array $range, string $status): QueryBuilder
    {
        return DB::table('request_logs')
            ->when($status === '4xx', fn ($q) => $q->whereBetween('status', [400, 499]))
            ->when($status === '5xx', fn ($q) => $q->whereBetween('status', [500, 599]))
            ->when($status === 'all', fn ($q) => $q->whereBetween('status', [400, 599]))
            ->when($range['from'] !== null, fn ($q) => $q->where('created_at', '>=', $range['from']))
            ->when($range['to'] !== null, fn ($q) => $q->where('created_at', '<=', $range['to']))
            ->orderByDesc('created_at');
    }

    /**
     * Browser-reported errors for the Browser errors tab (same shape the
     * Status panel used, with the same inspect + clear affordances).
     *
     * @return array<int, array<string, mixed>>
     */
    private function clientErrors(): array
    {
        return ClientError::with('user')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (ClientError $error) {
                $message = strtolower($error->message);
                $hint = null;

                if (app()->isDownForMaintenance()) {
                    $hint = 'Maintenance mode is on — this may be why pages fail. Turn it off on the Control center.';
                } elseif (str_contains($message, 'fetch failed') || str_contains($message, 'load resource')) {
                    $hint = 'A network or resource problem on the device — check the Server errors tab for server-side issues first.';
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
     * Clear every browser-reported error (the Browser errors tab action).
     */
    public function clearClientErrors(): Response
    {
        $deleted = ClientError::query()->delete();

        Audit::log('system.client_errors_cleared', null, ['count' => $deleted]);

        $message = 'Removed '.$deleted.' reported '.($deleted === 1 ? 'error' : 'errors').' from the browser error log.';

        if (request()->expectsJson()) {
            return response()->json([
                'html' => view('admin.logs.partials.browser-errors', [
                    'clientErrors' => $this->clientErrors(),
                    'clientErrorCount' => 0,
                ])->render(),
                'toast' => ['type' => 'success', 'message' => $message],
            ]);
        }

        return redirect()
            ->to(route('admin.logs.index', ['tab' => 'browser']))
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    private function filtered(AuditLogIndexRequest $request): Builder
    {
        $range = AuditRange::resolve(
            $request->validated('preset'),
            $request->validated('from'),
            $request->validated('to'),
        );

        return AuditLog::query()
            ->where(function ($q) {
                $q->whereNull('actor_id')
                    ->orWhereHas('actor', fn ($a) => $a->realAccounts());
            })
            ->with('actor')
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.$request->action.'%'))
            ->when($request->filled('category'), function ($query) use ($request) {
                $actions = AuditCatalog::actionsFor((string) $request->validated('category'));

                $actions === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('action', $actions);
            })
            ->when($request->filled('actor'), function ($query) use ($request) {
                $query->whereHas('actor', function ($query) use ($request) {
                    $query->where('nickname', 'like', '%'.$request->actor.'%')
                        ->orWhere('email', 'like', '%'.$request->actor.'%');
                });
            })
            ->when($range['from'] !== null, fn ($query) => $query->where('created_at', '>=', $range['from']))
            ->when($range['to'] !== null, fn ($query) => $query->where('created_at', '<=', $range['to']));
    }
}
