<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('condensed_from_id')
                ->nullable()
                ->after('gutenberg_id')
                ->unique()
                ->constrained('stories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condensed_from_id');
        });
    }
};
