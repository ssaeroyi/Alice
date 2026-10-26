<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('metric', 40);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['day', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
