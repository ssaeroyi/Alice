<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_cache', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
            $table->json('payload');
            $table->timestamp('fetched_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_cache');
    }
};
