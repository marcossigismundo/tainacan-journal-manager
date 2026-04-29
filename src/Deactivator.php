<?php

declare(strict_types=1);

namespace TainacanJournalManager;

/**
 * Plugin deactivation: clean up rewrite rules, scheduled tasks.
 * Roles and data are preserved (only removed on uninstall).
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        // Clear scheduled cron events
        $hooks = [
            'tjm_cleanup_tokens',
            'tjm_send_review_reminders',
        ];

        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        flush_rewrite_rules();
    }
}
