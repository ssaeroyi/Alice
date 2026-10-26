<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->enum('status', ['draft', 'active', 'cancelled', 'archived'])->default('draft')->change();
            $table->timestamp('finished_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('finished_at');
            $table->enum('status', ['draft', 'active', 'cancelled'])->default('draft')->change();
        });
    }
};
