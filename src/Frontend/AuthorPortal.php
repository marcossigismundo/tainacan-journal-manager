<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Shortcode [tjm_author_portal] — Author's portal.
 * Lists submissions, allows new submission, tracks status.
 */
final class AuthorPortal
{
    public function register(): void
    {
        add_shortcode('tjm_author_portal', [$this, 'render']);
    }

    public function render(): string
    {
        if (! is_user_logged_in()) {
            return '<div class="tjm-notice">' . esc_html__('Please log in to access the author portal.', 'tainacan-journal-manager') . '</div>';
        }

        $user_id = get_current_user_id();
        if (! PluginRole::has_role($user_id, PluginRole::AUTHOR) && ! user_can($user_id, 'manage_options')) {
            return '<div class="tjm-notice tjm-notice--error">' . esc_html__('You need the Author role to access this portal.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');

        $submissions = $this->get_user_submissions($user_id);

        ob_start();
        include TJM_PATH . 'templates/frontend/author-portal.php';
        return ob_get_clean() ?: '';
    }

    /**
     * @return \WP_Post[]
     */
    private function get_user_submissions(int $user_id): array
    {
        $query = new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'author'         => $user_id,
            'posts_per_page' => 50,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        return $query->posts;
    }
}
