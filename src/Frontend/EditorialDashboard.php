<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Issues\IssueManager;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Roles\PermissionChecker;
use TainacanJournalManager\Submission\AnonymizationService;
use TainacanJournalManager\Submission\FileUploadService;

/**
 * Shortcode [tjm_editorial_dashboard] — Editor's command center.
 *
 *   - default          → cards + list of recent submissions
 *   - ?submission=N    → detail with reviewer assignment + decision
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

        if (isset($_GET['submission'])) {
            $submission_id = (int) $_GET['submission'];
            if (! PermissionChecker::can_view_submission($user_id, $submission_id)) {
                return '<div class="tjm-notice tjm-notice--error">' . esc_html__('Submission not found or you do not have access.', 'tainacan-journal-manager') . '</div>';
            }
            $detail = $this->load_submission_detail($submission_id);
            $reviewers_list = PluginRole::get_users_by_role(PluginRole::REVIEWER);
            ob_start();
            include TJM_PATH . 'templates/frontend/editorial-detail.php';
            return ob_get_clean() ?: '';
        }

        if (isset($_GET['issues'])) {
            $journal_id = isset($_GET['journal']) ? (int) $_GET['journal'] : 0;
            $journals = get_posts([
                'post_type'      => Config::CPT_JOURNAL,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            $issues  = $journal_id ? IssueManager::get_issues_for_journal($journal_id) : [];
            $publishable = $journal_id ? $this->get_publishable_articles($journal_id) : [];
            ob_start();
            include TJM_PATH . 'templates/frontend/issues-management.php';
            return ob_get_clean() ?: '';
        }

        $stats = $this->get_stats();
        $recent = $this->get_recent_submissions();

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

    /**
     * @return \WP_Post[]
     */
    private function get_recent_submissions(int $limit = 30): array
    {
        $query = new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => $limit,
            'post_status'    => 'any',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);
        return $query->posts;
    }

    /**
     * @return array<string, mixed>
     */
    private function load_submission_detail(int $submission_id): array
    {
        $post = get_post($submission_id);
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);

        // Reviewers + their review records
        $reviewer_ids = (array) get_post_meta($submission_id, Config::META_PREFIX . 'reviewers', true);
        $reviewers = [];
        foreach ($reviewer_ids as $rid) {
            $rid = (int) $rid;
            $u = get_userdata($rid);
            if (! $u) continue;
            $review = $this->find_review_post($submission_id, $rid);
            $reviewers[] = [
                'user'              => $u,
                'review_id'         => $review ? (int) $review->ID : 0,
                'review_status'     => $review ? (string) get_post_meta($review->ID, Config::META_PREFIX . 'review_status', true) : '',
                'recommendation'    => $review ? (string) get_post_meta($review->ID, Config::META_PREFIX . 'recommendation', true) : '',
                'author_comments'   => $review ? (string) get_post_meta($review->ID, Config::META_PREFIX . 'author_comments', true) : '',
                'editor_comments'   => $review ? (string) get_post_meta($review->ID, Config::META_PREFIX . 'editor_comments', true) : '',
                'submitted_at'      => $review ? (string) get_post_meta($review->ID, Config::META_PREFIX . 'submitted_at', true) : '',
                'deadline'          => $review ? (string) get_post_meta($review->ID, Config::META_PREFIX . 'deadline', true) : '',
            ];
        }

        return [
            'id'             => $submission_id,
            'title'          => $post ? (string) $post->post_title : '',
            'abstract'       => $post ? (string) $post->post_content : '',
            'status'         => (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true),
            'journal_id'     => $journal_id,
            'journal_name'   => $journal_id ? (string) get_the_title($journal_id) : '',
            'review_type'    => AnonymizationService::review_type_for_submission($submission_id),
            'authors'        => AnonymizationService::collect_authors($submission_id),
            'manuscript'     => FileUploadService::get_manuscript_info($submission_id),
            'reviewers'      => $reviewers,
            'status_history' => (array) get_post_meta($submission_id, Config::META_PREFIX . 'status_history', true),
            'decisions'      => (array) get_post_meta($submission_id, Config::META_PREFIX . 'decisions', true),
        ];
    }

    private function find_review_post(int $submission_id, int $reviewer_id): ?\WP_Post
    {
        $q = new \WP_Query([
            'post_type'      => Config::CPT_REVIEW,
            'posts_per_page' => 1,
            'author'         => $reviewer_id,
            'meta_query'     => [
                ['key' => Config::META_PREFIX . 'submission_id', 'value' => (string) $submission_id],
            ],
        ]);
        return $q->posts[0] ?? null;
    }

    /**
     * Articles in production / published that can be assigned to an issue.
     *
     * @return \WP_Post[]
     */
    private function get_publishable_articles(int $journal_id): array
    {
        $q = new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id],
                [
                    'key'     => Config::META_PREFIX . 'status',
                    'value'   => [Config::STATUS_PRODUCTION, Config::STATUS_PUBLISHED, Config::STATUS_COPYEDITING],
                    'compare' => 'IN',
                ],
            ],
            'orderby' => 'modified',
            'order'   => 'DESC',
        ]);
        return $q->posts;
    }
}
