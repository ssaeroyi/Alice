<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy free-text "section" scoping was replaced by the Class
        // (group) system. The table is never created; drop it in case an older
        // environment still has it.
        Schema::dropIfExists('section_teacher');
    }

    public function down(): void
    {
        Schema::dropIfExists('section_teacher');
    }
};
