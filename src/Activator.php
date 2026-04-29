<?php

declare(strict_types=1);

namespace TainacanJournalManager;

use TainacanJournalManager\Roles\RoleManager;
use TainacanJournalManager\PostTypes\Journal;
use TainacanJournalManager\PostTypes\Submission;
use TainacanJournalManager\PostTypes\Review;
use TainacanJournalManager\PostTypes\Issue;

/**
 * Plugin activation: register CPTs, set up roles, provision Tainacan collections.
 */
final class Activator
{
    public static function activate(): void
    {
        // Register CPTs so flush_rewrite_rules picks them up
        (new Journal())->register();
        (new Submission())->register();
        (new Review())->register();
        (new Issue())->register();

        // Set up application roles (capabilities for admin)
        RoleManager::install();

        // Mark version
        update_option('tjm_version', TJM_VERSION);
        update_option('tjm_activated_at', current_time('mysql'));

        flush_rewrite_rules();
    }
}
