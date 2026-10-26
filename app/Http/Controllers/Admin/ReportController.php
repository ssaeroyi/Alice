<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoryReport;
use App\Support\Audit;
use App\Support\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student issue reports: raised from the reader's "Report an issue" action.
 * A simple two-state console - review pending reports, mark them resolved, or
 * remove them. Every resolution and deletion is written to the audit log.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'resolved'], true) ? $request->query('status') : null;
        $category = in_array($request->query('category'), ['typo', 'content', 'other'], true) ? $request->query('category') : null;
        $q = trim((string) $request->query('q'));

        $reports = StoryReport::query()
            ->whereHas('user', fn ($q) => $q->realAccounts())
            ->with(['user', 'story'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('description', 'like', $like)
                        ->orWhereHas('story', fn ($story) => $story->where('title', 'like', $like))
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('nickname', 'like', $like)
                            ->orWhere('email', 'like', $like));
                });
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reports.index', [
            'reports' => $reports,
            'pendingCount' => StoryReport::query()->where('status', 'pending')->count(),
        ]);
    }

    public function resolve(Request $request, StoryReport $report): RedirectResponse
    {
        if ($report->status !== 'resolved') {
            $report->update(['status' => 'resolved']);
        }

        NotificationService::reportResolved($report);

        Audit::log('report.resolved', $report);

        return redirect()->route('admin.reports.index', $request->query())
            ->with('toast', ['message' => 'Report marked as resolved.']);
    }

    public function destroy(Request $request, StoryReport $report): RedirectResponse
    {
        NotificationService::reportDeleted($report);

        Audit::log('report.deleted', $report);

        $report->delete();

        return redirect()->route('admin.reports.index', $request->query())
            ->with('toast', ['message' => 'Report removed.']);
    }

    /**
     * Bulk resolve the checked pending reports. Each one notifies its reporter
     * exactly as a single resolve would, so the inbox loop stays honest even
     * during a cleanup run.
     */
    public function bulkResolve(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $reports = StoryReport::query()->whereIn('id', $ids)->where('status', 'pending')->get();

        foreach ($reports as $report) {
            $report->update(['status' => 'resolved']);
            NotificationService::reportResolved($report);
            Audit::log('report.resolved', $report);
        }

        $query = $request->query();
        unset($query['ids']);

        return redirect()->route('admin.reports.index', $query)
            ->with('toast', ['message' => $reports->count().' report'.($reports->count() === 1 ? '' : 's').' resolved.']);
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $reports = StoryReport::query()->whereIn('id', $ids)->get();

        foreach ($reports as $report) {
            NotificationService::reportDeleted($report);
            Audit::log('report.deleted', $report);
            $report->delete();
        }

        $query = $request->query();
        unset($query['ids']);

        return redirect()->route('admin.reports.index', $query)
            ->with('toast', ['message' => $reports->count().' report'.($reports->count() === 1 ? '' : 's').' removed.']);
    }
}
