<?php

declare(strict_types=1);

namespace TainacanJournalManager\Roles;

/**
 * Role lifecycle: install/upgrade capabilities for the WP administrator.
 *
 * Plugin users (authors, reviewers, editors) keep their WP role as `subscriber`
 * — actual editorial role lives in user meta (`_tjm_roles`). This class only
 * grants capabilities to the WP admin so they can manage CPTs in wp-admin.
 */
final class RoleManager
{
    private const ADMIN_CAPS = [
        'edit_tjm_journals',
        'edit_tjm_submissions',
        'edit_tjm_reviews',
        'edit_tjm_issues',
        'manage_tjm_settings',
    ];

    public static function install(): void
    {
        $admin = get_role('administrator');
        if (! $admin) {
            return;
        }

        foreach (self::ADMIN_CAPS as $cap) {
            $admin->add_cap($cap);
        }
    }

    public static function uninstall(): void
    {
        $admin = get_role('administrator');
        if (! $admin) {
            return;
        }

        foreach (self::ADMIN_CAPS as $cap) {
            $admin->remove_cap($cap);
        }
    }
}
