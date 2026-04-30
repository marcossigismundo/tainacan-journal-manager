<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Shortcode [tjm_role_management] — manage roles for users.
 *
 * Visible only to journal_manager and admin_institucional. Allows
 * setting GLOBAL roles (admins only) and PER-JOURNAL roles
 * (journal managers can edit roles for their own journals).
 */
final class RoleManagement
{
    public function register(): void
    {
        add_shortcode('tjm_role_management', [$this, 'render']);
    }

    public function render(): string
    {
        if (! is_user_logged_in()) {
            return '<div class="tjm-notice">' . esc_html__('Please log in.', 'tainacan-journal-manager') . '</div>';
        }
        $actor = get_current_user_id();
        $is_admin       = PluginRole::is_admin_institutional($actor);
        $is_journal_mgr = PluginRole::has_role($actor, PluginRole::JOURNAL_MANAGER);

        if (! $is_admin && ! $is_journal_mgr) {
            return '<div class="tjm-notice tjm-notice--error">' . esc_html__('You do not have permission to manage roles.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');

        // Users with any TJM role (limit to 200 — enough for typical installs)
        $users = get_users([
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_tjm_roles',         'compare' => 'EXISTS'],
                ['key' => '_tjm_journal_roles', 'compare' => 'EXISTS'],
            ],
            'number'  => 200,
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]);

        $journals = get_posts([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $all_roles = PluginRole::ALL_ROLES;

        ob_start();
        include TJM_PATH . 'templates/frontend/role-management.php';
        return ob_get_clean() ?: '';
    }
}
