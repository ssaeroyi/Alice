<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);

        $response = $next($request);

        try {
            DB::table('request_logs')->insert([
                'method' => Str::upper($request->method()),
                'path' => Str::limit($request->path(), 190),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit($request->userAgent(), 510),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'created_at' => now(),
            ]);

            if (mt_rand(1, 50) === 1) {
                DB::table('request_logs')
                    ->whereNotIn('id', function ($query) {
                        $query->select('id')->from('request_logs')->orderByDesc('id')->limit(10000);
                    })
                    ->delete();
            }
        } catch (\Throwable) {
            // Telemetry must never break a request.
        }

        return $response;
    }
}
