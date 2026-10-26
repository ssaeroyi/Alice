<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('status_snapshots');
    }

    public function down(): void
    {
        Schema::create('status_snapshots', function ($table) {
            $table->id();
            $table->json('data');
            $table->timestamp('created_at')->nullable()->index();
        });
    }
};
