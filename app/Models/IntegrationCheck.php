<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The outcome of one health probe for an external or bundled service (e.g. the Harper grammar engine).
 * Written by the integration probes so the admin health panel shows the
 * last manual test and the live behavior of the app.
 */
#[Fillable(['integration', 'ok', 'latency_ms', 'message', 'error_type', 'prompt_tokens', 'completion_tokens'])]
class IntegrationCheck extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'latency_ms' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public static function record(
        string $integration,
        bool $ok,
        ?int $latencyMs,
        ?string $message,
        ?string $errorType = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
    ): void {
        static::create([
            'integration' => $integration,
            'ok' => $ok,
            'latency_ms' => $latencyMs,
            'message' => $message,
            'error_type' => $errorType,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ]);

        static::where('created_at', '<', now()->subDays(30))->delete();
    }

    public static function latest(string $integration): ?self
    {
        return static::where('integration', $integration)->latest('id')->first();
    }
}
