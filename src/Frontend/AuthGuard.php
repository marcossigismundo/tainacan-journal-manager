<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * URL-level access control: redirects unauthenticated users to login.
 *
 * Maps frontend page slugs to required roles. First line of defense
 * (defense-in-depth: shortcodes and AJAX handlers also verify).
 */
final class AuthGuard
{
    private const PAGE_ROLE_MAP = [
        Config::PAGE_AUTHOR    => [PluginRole::AUTHOR],
        Config::PAGE_REVIEWER  => [PluginRole::REVIEWER],
        Config::PAGE_EDITORIAL => [
            PluginRole::JOURNAL_MANAGER,
            PluginRole::EDITOR_CHIEF,
            PluginRole::EDITOR_SECTION,
            PluginRole::ADMIN_INSTITUTIONAL,
        ],
    ];

    public function register(): void
    {
        add_action('template_redirect', [$this, 'guard']);
    }

    public function guard(): void
    {
        if (! is_page()) {
            return;
        }

        $post = get_post();
        if (! $post) {
            return;
        }

        $slug = $post->post_name;
        if (! isset(self::PAGE_ROLE_MAP[$slug])) {
            return;
        }

        if (! is_user_logged_in()) {
            wp_safe_redirect(Config::page_url(Config::PAGE_LOGIN) . '?redirect_to=' . urlencode((string) get_permalink($post)));
            exit;
        }

        $user_id = get_current_user_id();
        $allowed = self::PAGE_ROLE_MAP[$slug];

        // Administrators always allowed
        if (user_can($user_id, 'manage_options')) {
            return;
        }

        $user_roles = PluginRole::get_roles($user_id);
        foreach ($allowed as $role) {
            if (in_array($role, $user_roles, true)) {
                return; // Authorized
            }
        }

        // Unauthorized
        wp_die(
            esc_html__('You do not have permission to access this page.', 'tainacan-journal-manager'),
            esc_html__('Access denied', 'tainacan-journal-manager'),
            ['response' => 403]
        );
    }
}
