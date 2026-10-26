<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('status_snapshots', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
        Schema::dropIfExists('status_snapshots');
    }
};
