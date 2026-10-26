<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('story_id')->constrained('groups')->nullOnDelete();
            $table->enum('scope', ['all', 'grade', 'section', 'students', 'group'])->default('all')->change();
            $table->index(['scope', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['scope', 'group_id']);
            $table->dropConstrainedForeignId('group_id');
            $table->enum('scope', ['all', 'grade', 'section', 'students'])->default('all')->change();
        });
    }
};
