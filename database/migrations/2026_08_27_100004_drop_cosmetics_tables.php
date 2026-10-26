<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_cosmetics');
        Schema::dropIfExists('cosmetics');
    }

    public function down(): void
    {
        Schema::create('cosmetics', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 20);
            $table->string('name');
            $table->string('rarity', 20)->default('common');
            $table->string('asset_path');
            $table->unsignedInteger('price_keys')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_cosmetics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('cosmetic_key');
            $table->timestamp('acquired_at')->useCurrent();
            $table->timestamp('equipped_at')->nullable();

            $table->unique(['user_id', 'cosmetic_key']);
            $table->foreign('cosmetic_key')->references('key')->on('cosmetics')->cascadeOnDelete();
        });
    }
};
