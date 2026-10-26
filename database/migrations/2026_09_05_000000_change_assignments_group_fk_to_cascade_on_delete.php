<?php

use App\Support\AssignmentCleanup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a class must remove its assignments too ("anything associated
 * with that class should be gone"). The group FK was nullOnDelete, which
 * orphaned the assignments (they stayed on the teacher's board). Mirror the
 * group_members cascade: group delete now hard-cascades its assignments.
 * Quiz attempts are keyed to user+story, not the assignment, so student
 * reading/quiz history is untouched.
 *
 * Upgrading from the old behavior means surviving orphans must be swept
 * first: rows that lost their group (scope 'group', group_id null) can never
 * resolve an audience again, so delete them for good.
 *
 * The (scope, group_id) index comes from add_group_to_assignments and would
 * collide on re-add (MySQL rejects duplicate index names), so it is dropped
 * and re-created around the FK swap.
 */
return new class extends Migration
{
    public function up(): void
    {
        AssignmentCleanup::sweepGroupOrphans();

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasIndex('assignments', ['scope', 'group_id'])) {
                $table->dropIndex(['scope', 'group_id']);
            }

            $table->dropConstrainedForeignId('group_id');

            $table->foreignId('group_id')->nullable()->after('story_id')->constrained('groups')->cascadeOnDelete();
            $table->index(['scope', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasIndex('assignments', ['scope', 'group_id'])) {
                $table->dropIndex(['scope', 'group_id']);
            }

            $table->dropConstrainedForeignId('group_id');

            $table->foreignId('group_id')->nullable()->after('story_id')->constrained('groups')->nullOnDelete();
            $table->index(['scope', 'group_id']);
        });
    }
};
