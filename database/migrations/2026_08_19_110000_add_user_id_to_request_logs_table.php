<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('method');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
