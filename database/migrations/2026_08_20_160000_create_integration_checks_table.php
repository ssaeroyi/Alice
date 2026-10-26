<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_checks', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 40);
            $table->string('provider', 40)->nullable();
            $table->boolean('ok');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('message', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_checks');
    }
};
