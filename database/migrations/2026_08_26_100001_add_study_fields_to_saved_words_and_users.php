<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_words', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('last_drilled_at');
            $table->index(['user_id', 'due_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('daily_study_goal')->default(20)->after('daily_reading_goal');
        });
    }

    public function down(): void
    {
        Schema::table('saved_words', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'due_at']);
            $table->dropColumn('due_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('daily_study_goal');
        });
    }
};
