<?php

namespace App\Http\Controllers;

use App\Models\ClientError;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'errors' => ['required', 'array', 'max:20'],
            'errors.*.message' => ['required', 'string', 'max:2000'],
            'errors.*.url' => ['nullable', 'string', 'max:500'],
            'errors.*.stack' => ['nullable', 'string', 'max:8000'],
        ]);

        $userId = $request->user()?->id;
        $userAgent = Str::limit($request->userAgent() ?? '', 255);

        foreach ($validated['errors'] as $error) {
            ClientError::create([
                'user_id' => $userId,
                'message' => $error['message'],
                'url' => Str::limit($error['url'] ?? $request->path(), 500),
                'stack' => $error['stack'] ?? null,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);
        }

        if (mt_rand(1, 50) === 1) {
            ClientError::query()
                ->whereNotIn('id', ClientError::query()->orderByDesc('id')->limit(2000)->pluck('id'))
                ->delete();
        }

        return response()->noContent();
    }
}
