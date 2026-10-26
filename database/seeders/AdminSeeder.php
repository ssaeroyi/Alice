<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@alice.local'],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'nickname' => 'Admin',
                'password' => 'admin12345',
                'role' => Role::Admin,
                'email_verified_at' => now(),
                'is_setup_complete' => true,
            ],
        );
    }
}
