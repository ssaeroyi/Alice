<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_checks', function (Blueprint $table) {
            $table->string('error_type', 40)->nullable()->after('message');
            $table->unsignedInteger('prompt_tokens')->nullable()->after('error_type');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');

        Schema::table('integration_checks', function (Blueprint $table) {
            $table->dropColumn(['error_type', 'prompt_tokens', 'completion_tokens']);
        });
    }
};
