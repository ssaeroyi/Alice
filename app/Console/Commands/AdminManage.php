<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminManage extends Command
{
    protected $signature = 'admin:manage
        {action : The action to perform (create|reset-password|set-role|list)}
        {email? : Email of the admin (required for reset-password and create)}
        {role? : Target role (required for set-role: student|teacher|admin)}
        {--nickname= : Nickname for the new admin (create only)}';

    protected $description = 'Manage admin accounts (create, reset-password, set-role, list)';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createAdmin(),
            'reset-password' => $this->resetPassword(),
            'set-role' => $this->setRole(),
            'list' => $this->listAdmins(),
            default => $this->invalidAction($action),
        };
    }

    private function createAdmin(): int
    {
        $email = $this->askRequired('Email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return self::FAILURE;
        }

        $nickname = $this->option('nickname') ?: $this->askRequired('Nickname');

        if (User::where('nickname', $nickname)->exists()) {
            $this->error("Nickname {$nickname} is already taken.");

            return self::FAILURE;
        }
        $password = $this->secret('Password (min 8 chars, no spaces)');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        if (strlen($password) < 8 || preg_match('/\s/', $password)) {
            $this->error('Password must be at least 8 characters with no spaces.');

            return self::FAILURE;
        }

        $user = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'nickname' => $nickname,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
            'email_verified_at' => now(),
            'is_setup_complete' => true,
        ]);

        Audit::log('user.created', $user, ['role' => Role::Admin->value, 'via' => 'cli']);

        $this->info("Admin created: {$user->email} ({$user->nickname})");

        return self::SUCCESS;
    }

    private function resetPassword(): int
    {
        $email = $this->argument('email') ?? $this->askRequired('Email');
        $user = User::where('email', $email)->where('role', Role::Admin)->first();

        if (! $user) {
            $this->error("No admin found with email {$email}.");

            return self::FAILURE;
        }

        $password = $this->secret('New password (min 8 chars, no spaces)');
        $confirm = $this->secret('Confirm new password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        if (strlen($password) < 8 || preg_match('/\s/', $password)) {
            $this->error('Password must be at least 8 characters with no spaces.');

            return self::FAILURE;
        }

        $user->update(['password' => Hash::make($password)]);

        Audit::log('user.password_reset', $user, ['via' => 'cli']);

        $this->info("Password reset for {$user->email}.");

        return self::SUCCESS;
    }

    private function setRole(): int
    {
        $email = $this->argument('email') ?? $this->askRequired('Email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $roleName = strtolower($this->argument('role') ?? $this->askRequired('Role (student|teacher|admin)'));
        $role = Role::tryFrom($roleName);

        if ($role === null) {
            $this->error("Invalid role: {$roleName}. Use student, teacher, or admin.");

            return self::FAILURE;
        }

        if ($role === $user->role) {
            $this->info("{$user->email} already has role {$role->value}.");

            return self::SUCCESS;
        }

        if ($user->role === Role::Admin && User::where('role', Role::Admin)->count() <= 1) {
            $this->error('The last administrator cannot be demoted.');

            return self::FAILURE;
        }

        $previous = $user->role;
        $user->forceFill(['role' => $role])->save();

        Audit::log('user.role_changed', $user, ['from' => $previous->value, 'to' => $role->value, 'via' => 'cli']);

        $this->info("Role updated for {$user->email}: {$previous->value} -> {$role->value}.");

        return self::SUCCESS;
    }

    private function listAdmins(): int
    {
        $admins = User::where('role', Role::Admin)
            ->orderBy('created_at')
            ->get(['email', 'nickname', 'created_at', 'last_login_at']);

        if ($admins->isEmpty()) {
            $this->info('No admin accounts found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Nickname', 'Created At', 'Last Login'],
            $admins->map(fn ($a) => [
                $a->email,
                $a->nickname,
                $a->created_at?->format('Y-m-d H:i'),
                $a->last_login_at?->format('Y-m-d H:i') ?? 'Never',
            ])->toArray()
        );

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}. Use create, reset-password, set-role, or list.");

        return self::FAILURE;
    }

    private function askRequired(string $field): string
    {
        $value = $this->ask($field);
        while (empty(trim($value ?? ''))) {
            $this->error("{$field} is required.");
            $value = $this->ask($field);
        }

        return trim($value);
    }
}
