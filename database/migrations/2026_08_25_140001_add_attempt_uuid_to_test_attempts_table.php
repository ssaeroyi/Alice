<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz settlement idempotency key (STUDENT_PAGES_PLAN §6.5): the UUID is
 * minted when the player opens and resubmits of the same attempt settle
 * exactly once - the unique index is the gatekeeper inside the settle
 * transaction, not application-level checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->uuid('attempt_uuid')->nullable()->unique()->after('story_id');
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropUnique(['attempt_uuid']);
            $table->dropColumn('attempt_uuid');
        });
    }
};
