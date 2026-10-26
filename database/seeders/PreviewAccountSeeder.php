<?php

namespace Database\Seeders;

use App\Support\ViewAs;
use Illuminate\Database\Seeder;

/**
 * Creates the two disposable "scratch" accounts used for admin "view as"
 * previews (a Student and a Teacher). They exist so the one-click admin
 * preview switch is always ready without account picking. Preview activity is
 * written only to these throwaway accounts and reset on return-to-admin.
 *
 * Resolving through ViewAs also gives the mismatch guard: if the fixed email
 * ever maps to a real/non-scratch account, the seeder leaves it untouched
 * rather than "fixing" it.
 */
class PreviewAccountSeeder extends Seeder
{
    public function run(): void
    {
        ViewAs::scratchStudent();
        ViewAs::scratchTeacher();
    }
}
