<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Shortcode [tjm_editorial_dashboard] — Editor's command center.
 * Cards with submission counts by status, lists, and management actions.
 */
final class EditorialDashboard
{
    public function register(): void
    {
        add_shortcode('tjm_editorial_dashboard', [$this, 'render']);
    }

    public function render(): string
    {
        if (! is_user_logged_in()) {
            return '<div class="tjm-notice">' . esc_html__('Please log in to access the editorial dashboard.', 'tainacan-journal-manager') . '</div>';
        }

        $user_id = get_current_user_id();
        $is_editor = PluginRole::is_editor($user_id) || PluginRole::is_admin_institutional($user_id);

        if (! $is_editor && ! user_can($user_id, 'manage_options')) {
            return '<div class="tjm-notice tjm-notice--error">' . esc_html__('You need an Editor role to access this dashboard.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');

        $stats = $this->get_stats();

        ob_start();
        include TJM_PATH . 'templates/frontend/editorial-dashboard.php';
        return ob_get_clean() ?: '';
    }

    /**
     * @return array<string, int>
     */
    private function get_stats(): array
    {
        $stats = [];
        foreach (Config::SUBMISSION_STATUSES as $status_key => $label) {
            $count = (new \WP_Query([
                'post_type'      => Config::CPT_SUBMISSION,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_status'    => 'any',
                'meta_query'     => [
                    ['key' => Config::META_PREFIX . 'status', 'value' => $status_key],
                ],
            ]))->found_posts;
            $stats[$status_key] = $count;
        }
        return $stats;
    }
}
