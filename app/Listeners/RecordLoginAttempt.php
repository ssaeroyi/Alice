<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordLoginAttempt
{
    public function login(Login $event): void
    {
        $this->record($event->user->email, true);
    }

    public function failed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? '';
        $this->record($email, false);
    }

    private function record(string $email, bool $successful): void
    {
        $request = request();

        DB::table('login_attempts')->insert([
            'email' => Str::limit($email, 255),
            'ip_address' => $request->ip() ?? '',
            'user_agent' => Str::limit($request->userAgent(), 510),
            'successful' => $successful,
            'created_at' => now(),
        ]);
    }
}
