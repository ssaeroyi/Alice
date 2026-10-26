<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestEmail;
use App\Support\Audit;
use App\Support\DatabaseDumper;
use App\Support\Monitor;
use App\Support\NotificationService;
use App\Support\SystemStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ControlCenterController extends Controller
{
    public function index()
    {
        return view('admin.control', [
            'snapshot' => SystemStatus::snapshot(),
            'maintenance' => app()->isDownForMaintenance(),
        ]);
    }

    public function clearCache()
    {
        $before = SystemStatus::cacheEntries();

        Cache::flush();
        Audit::log('system.cache_cleared', null, $before !== null ? ['entries' => $before] : []);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $before !== null
                ? 'Cache cleared. '.$before.' file '.Str::plural('entry', $before).' flushed.'
                : 'Cache cleared.',
        ]);
    }

    public function clearErrorLog()
    {
        Monitor::clearErrorLog();
        Audit::log('system.error_log_cleared');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Error log cleared. The Status dot goes away on the next page load.',
        ]);
    }

    public function toggleMaintenance()
    {
        if (app()->isDownForMaintenance()) {
            app()->maintenanceMode()->deactivate();
            Audit::log('system.maintenance_off');
            NotificationService::maintenanceToggled(false);

            return back()->with('toast', [
                'type' => 'success',
                'message' => 'Maintenance mode is off. The site is live again.',
            ]);
        }

        app()->maintenanceMode()->activate([]);
        Audit::log('system.maintenance_on');
        NotificationService::maintenanceToggled(true);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Maintenance mode is on. Visitors get the maintenance page; the admin panel stays reachable.',
        ]);
    }

    public function downloadDump()
    {
        $connection = config('database.default');

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Database dumps are only available when the app runs on MySQL or MariaDB.',
            ]);
        }

        $db = config('database.connections.'.$connection);

        try {
            $dumper = DatabaseDumper::create($db);
            $tables = $dumper->tableCount();
        } catch (\Throwable $e) {
            Audit::log('system.db_dump_failed', null, [
                'error' => class_basename($e),
                'message' => Str::limit($e->getMessage(), 200),
            ]);

            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Could not create a database dump. The database connection reported: "'.Str::limit($e->getMessage(), 160).'". Check the database credentials on the status page.',
            ]);
        }

        Audit::log('system.db_dump_downloaded', null, ['tables' => $tables]);

        $filename = 'alice-backup-'.now()->format('Y-m-d-His').'.sql';

        return response()->streamDownload(
            function () use ($dumper) {
                foreach ($dumper->dump() as $chunk) {
                    echo $chunk;
                }
            },
            $filename,
            ['Content-Type' => 'application/sql']
        );
    }

    public function signOutAll()
    {
        $count = DB::table('sessions')->where('user_id', '!=', auth()->id())->delete();

        Audit::log('user.signed_out_all', null, ['sessions' => $count]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $count > 0
                ? 'Signed out '.$count.' other active '.Str::plural('session', $count).'. Your session is untouched.'
                : 'No other active sessions were found.',
        ]);
    }

    public function testMail(Request $request)
    {
        $admin = $request->user();

        $started = microtime(true);

        try {
            Mail::to($admin->email)->send(new TestEmail);
        } catch (\Throwable $e) {
            Audit::log('system.mail_test_failed', null, [
                'error' => class_basename($e),
                'message' => Str::limit($e->getMessage(), 200),
            ]);

            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Test email failed to send ('.class_basename($e).': '.Str::limit($e->getMessage(), 80).'). Check the mail settings on the status page.',
            ]);
        }

        $elapsed = round(microtime(true) - $started, 1);

        Audit::log('system.mail_test_sent', null, [
            'to' => $admin->email,
            'duration_ms' => (int) round($elapsed * 1000),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Test email delivered to '.$admin->email.' in '.$elapsed.'s.',
        ]);
    }
}
