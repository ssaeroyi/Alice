<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('copied_from_id')->nullable()->constrained('stories')->nullOnDelete()->after('condensed_from_id');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropForeign(['copied_from_id']);
            $table->dropColumn('copied_from_id');
        });
    }
};
