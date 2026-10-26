<?php

namespace App\Support;

/**
 * Plain-language labels, categories and severities for every audit action.
 *
 * The audit log stores raw keys (`auth.failed_login`) for integrity and CSV
 * export; this catalog is display-only. Unknown keys fall back to the raw key
 * with a neutral pill, so a new action can never break the page.
 */
class AuditCatalog
{
    public const CATEGORIES = [
        'security' => 'Security',
        'accounts' => 'Accounts',
        'content' => 'Content',
        'classwork' => 'Classwork',
        'system' => 'System',
    ];

    /**
     * @var array<string, array{label: string, category: string, severity: string}>
     *                                                                              severity: neutral | warn | crit
     */
    private const ACTIONS = [
        // Security
        'auth.failed_login' => ['Failed sign-in', 'security', 'warn'],
        'auth.deletion_restore_offered' => ['Deletion restore offered', 'security', 'neutral'],
        'preview.started' => ['View-as session started', 'security', 'warn'],
        'preview.stopped' => ['View-as session ended', 'security', 'neutral'],
        'preview.switch_failed' => ['View-as switch failed', 'security', 'warn'],

        // Accounts
        'user.created' => ['Account created', 'accounts', 'neutral'],
        'user.suspended' => ['Account suspended', 'accounts', 'warn'],
        'user.reactivated' => ['Account reactivated', 'accounts', 'neutral'],
        'user.restored' => ['Account restored', 'accounts', 'neutral'],
        'user.deleted' => ['Account deleted', 'accounts', 'crit'],
        'user.deletion_requested' => ['Account deletion requested', 'accounts', 'crit'],
        'user.deletion_cancelled' => ['Account deletion cancelled', 'accounts', 'neutral'],
        'user.password_reset' => ['Password reset link issued', 'accounts', 'neutral'],
        'user.reset_link' => ['Password reset link issued', 'accounts', 'neutral'],
        'user.role_changed' => ['Role changed', 'accounts', 'warn'],
        'user.signed_out' => ['Sessions signed out', 'accounts', 'warn'],
        'user.signed_out_all' => ['All sessions signed out', 'accounts', 'warn'],
        'user.exported_csv' => ['Accounts exported', 'accounts', 'warn'],
        'group.created' => ['Class created', 'accounts', 'neutral'],
        'group.deleted' => ['Class deleted', 'accounts', 'warn'],

        // Content (stories, questions, narration)
        'story.created' => ['Story created', 'content', 'neutral'],
        'story.updated' => ['Story updated', 'content', 'neutral'],
        'story.published' => ['Story published', 'content', 'neutral'],
        'story.archived' => ['Story archived', 'content', 'neutral'],
        'story.duplicated' => ['Story duplicated', 'content', 'neutral'],
        'story.deleted' => ['Story deleted', 'content', 'warn'],
        'story.library_import' => ['Story imported from library', 'content', 'neutral'],
        'question.created' => ['Question added', 'content', 'neutral'],
        'question.updated' => ['Question updated', 'content', 'neutral'],
        'question.deleted' => ['Question deleted', 'content', 'neutral'],
        'question.duplicated' => ['Question duplicated', 'content', 'neutral'],
        'question_section.created' => ['Question section added', 'content', 'neutral'],
        'question_section.updated' => ['Question section updated', 'content', 'neutral'],
        'question_section.deleted' => ['Question section deleted', 'content', 'neutral'],
        'mission_action.created' => ['Journal move added', 'content', 'neutral'],
        'mission_action.updated' => ['Journal move updated', 'content', 'neutral'],
        'mission_action.deleted' => ['Journal move deleted', 'content', 'neutral'],
        'mission_action.moved' => ['Journal move reordered', 'content', 'neutral'],
        'mission.enabled' => ['Journal battles enabled', 'content', 'neutral'],
        'mission.disabled' => ['Journal battles disabled', 'content', 'neutral'],
        'narration.requested' => ['Narration requested', 'content', 'neutral'],

        // Classwork (assignments)
        'assignment.created' => ['Assignment created', 'classwork', 'neutral'],
        'assignment.updated' => ['Assignment updated', 'classwork', 'neutral'],
        'assignment.published' => ['Assignment published', 'classwork', 'neutral'],
        'assignment.archived' => ['Assignment archived', 'classwork', 'neutral'],
        'assignment.cancelled' => ['Assignment cancelled', 'classwork', 'warn'],
        'assignment.deleted' => ['Assignment deleted', 'classwork', 'warn'],
        'assignment.exported' => ['Assignment exported', 'classwork', 'neutral'],
        'assignment.marked' => ['Student marked on assignment', 'classwork', 'neutral'],
        'assignment.mark_removed' => ['Mark removed on assignment', 'classwork', 'neutral'],
        'assignment.share_click' => ['Assignment share used', 'classwork', 'neutral'],
        'report.resolved' => ['Student report resolved', 'classwork', 'neutral'],
        'report.deleted' => ['Student report deleted', 'classwork', 'neutral'],

        // System
        'system.cache_cleared' => ['Application cache cleared', 'system', 'neutral'],
        'system.maintenance_on' => ['Maintenance mode turned on', 'system', 'warn'],
        'system.maintenance_off' => ['Maintenance mode turned off', 'system', 'neutral'],
        'system.db_dump_downloaded' => ['Database backup downloaded', 'system', 'warn'],
        'system.db_dump_failed' => ['Database backup failed', 'system', 'crit'],
        'system.jobs_retried' => ['Failed tasks retried', 'system', 'neutral'],
        'system.jobs_cleared' => ['Failed tasks cleared', 'system', 'neutral'],
        'system.mail_test_sent' => ['Mail test delivered', 'system', 'neutral'],
        'system.mail_test_failed' => ['Mail test failed', 'system', 'warn'],
        'system.integration_tested' => ['External service tested', 'system', 'neutral'],
        'system.integrations_tested' => ['All external services tested', 'system', 'neutral'],
        'system.integration_toggled' => ['External service toggled', 'system', 'warn'],
        'system.stt_debug_toggled' => ['Say-it debug toggled', 'system', 'neutral'],
        'system.client_errors_cleared' => ['Browser errors cleared', 'system', 'neutral'],
        'system.dependencies.scan' => ['Dependency scan run', 'system', 'neutral'],
        'system.packages.composer-updated' => ['Composer package updated', 'system', 'warn'],
        'system.packages.npm-updated' => ['npm package updated', 'system', 'warn'],
        'system.maintenance.dry-run' => ['Maintenance dry run', 'system', 'neutral'],
        'system.maintenance.lock' => ['Maintenance lock action', 'system', 'neutral'],
        'system.maintenance.self-test' => ['Maintenance self-test', 'system', 'neutral'],
        'system.maintenance.update' => ['Maintenance self-update', 'system', 'warn'],
        'dev_tools.stamp_today' => ['Dev: today stamped', 'system', 'neutral'],
    ];

    /**
     * @return array{label: string, category: string, severity: string}
     */
    public static function entry(string $action): array
    {
        $meta = self::ACTIONS[$action] ?? null;

        if ($meta === null) {
            return [
                'label' => $action,
                'category' => 'system',
                'severity' => 'neutral',
            ];
        }

        // Support both keyed and positional [label, category, severity] rows.
        return [
            'label' => $meta['label'] ?? $meta[0] ?? $action,
            'category' => $meta['category'] ?? $meta[1] ?? 'system',
            'severity' => $meta['severity'] ?? $meta[2] ?? 'neutral',
        ];
    }

    /**
     * Map a category slug to its action keys (for the category filter).
     *
     * @return array<int, string>
     */
    public static function actionsFor(string $category): array
    {
        $actions = [];

        foreach (self::ACTIONS as $key => $meta) {
            $entry = self::entry($key);

            if ($entry['category'] === $category) {
                $actions[] = $key;
            }
        }

        return $actions;
    }

    /**
     * Label + pill tone for a severity, for the CSV export header notes.
     */
    public static function severityTone(string $severity): string
    {
        return match ($severity) {
            'crit' => 'crit',
            'warn' => 'warn',
            default => 'neutral',
        };
    }
}
