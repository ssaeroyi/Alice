<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function log(string $action, ?Model $target = null, array $meta = []): AuditLog
    {
        // While an admin is "viewing as" another user, audit writes must stay
        // attributable to the real administrator, not the previewed user.
        $actor = ViewAs::actor();

        if ($actor) {
            $meta['view_as'] = true;
        }

        return AuditLog::create([
            'actor_id' => $actor->id ?? Auth::id(),
            'action' => $action,
            'target_type' => $target ? $target->getMorphClass() : null,
            'target_id' => $target?->getKey(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
