<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Function-word supplement (STUDENT_PAGES_PLAN §4.3a deviation): WordNet
 * has no articles/pronouns/prepositions by design, so the bundled
 * kid-friendly list seeds pos='function' rows - the enum grows to fit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE dictionary_entries MODIFY pos ENUM('noun', 'verb', 'adj', 'adv', 'function') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('dictionary_entries')->where('pos', 'function')->delete();
        DB::statement(
            "ALTER TABLE dictionary_entries MODIFY pos ENUM('noun', 'verb', 'adj', 'adv') NOT NULL"
        );
    }
};
