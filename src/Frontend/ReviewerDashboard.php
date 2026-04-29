<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Shortcode [tjm_reviewer_dashboard] — Peer reviewer's dashboard.
 * Lists invitations, in-progress and completed reviews.
 */
final class ReviewerDashboard
{
    public function register(): void
    {
        add_shortcode('tjm_reviewer_dashboard', [$this, 'render']);
    }

    public function render(): string
    {
        if (! is_user_logged_in()) {
            return '<div class="tjm-notice">' . esc_html__('Please log in to access the reviewer dashboard.', 'tainacan-journal-manager') . '</div>';
        }

        $user_id = get_current_user_id();
        if (! PluginRole::has_role($user_id, PluginRole::REVIEWER) && ! user_can($user_id, 'manage_options')) {
            return '<div class="tjm-notice tjm-notice--error">' . esc_html__('You need the Reviewer role to access this dashboard.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');

        $reviews = $this->get_user_reviews($user_id);

        ob_start();
        include TJM_PATH . 'templates/frontend/reviewer-dashboard.php';
        return ob_get_clean() ?: '';
    }

    /**
     * @return \WP_Post[]
     */
    private function get_user_reviews(int $user_id): array
    {
        $query = new \WP_Query([
            'post_type'      => Config::CPT_REVIEW,
            'author'         => $user_id,
            'posts_per_page' => 50,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        return $query->posts;
    }
}
