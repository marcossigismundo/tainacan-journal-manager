<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Review\ReviewFormConfig;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Submission\AnonymizationService;
use TainacanJournalManager\Submission\FileUploadService;

/**
 * Shortcode [tjm_reviewer_dashboard] — Peer reviewer's dashboard.
 *
 *   - default     → list of invitations / in-progress / completed reviews
 *   - ?review=N   → review detail with accept/decline + parecer form
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

        $message = $this->one_click_feedback();

        if (isset($_GET['review'])) {
            $review_id = (int) $_GET['review'];
            $review    = get_post($review_id);
            if (! $review || $review->post_type !== Config::CPT_REVIEW
                || (int) get_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', true) !== $user_id) {
                return '<div class="tjm-notice tjm-notice--error">' . esc_html__('Review not found or access denied.', 'tainacan-journal-manager') . '</div>';
            }
            $detail = $this->load_review_detail($review_id);
            ob_start();
            include TJM_PATH . 'templates/frontend/reviewer-detail.php';
            return $message . (ob_get_clean() ?: '');
        }

        $reviews = $this->get_user_reviews($user_id);

        ob_start();
        include TJM_PATH . 'templates/frontend/reviewer-dashboard.php';
        return $message . (ob_get_clean() ?: '');
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

    /**
     * @return array<string, mixed>
     */
    private function load_review_detail(int $review_id): array
    {
        $submission_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'submission_id', true);
        $review_status = (string) get_post_meta($review_id, Config::META_PREFIX . 'review_status', true);

        return [
            'review_id'      => $review_id,
            'review_status'  => $review_status,
            'deadline'       => (string) get_post_meta($review_id, Config::META_PREFIX . 'deadline', true),
            'submission_id'  => $submission_id,
            'title'          => $submission_id ? AnonymizationService::title_for_view($submission_id, AnonymizationService::VIEW_REVIEWER) : '',
            'abstract'       => $submission_id ? (string) (get_post($submission_id)->post_content ?? '') : '',
            'authors'        => $submission_id ? AnonymizationService::authors_for_view($submission_id, AnonymizationService::VIEW_REVIEWER) : [],
            'manuscript'     => $submission_id ? FileUploadService::get_manuscript_info($submission_id) : null,
            'sections'       => $submission_id ? ReviewFormConfig::sections_for_submission($submission_id) : [],
            'review_type'    => $submission_id ? AnonymizationService::review_type_for_submission($submission_id) : '',
            'author_comments' => (string) get_post_meta($review_id, Config::META_PREFIX . 'author_comments', true),
            'editor_comments' => (string) get_post_meta($review_id, Config::META_PREFIX . 'editor_comments', true),
            'recommendation'  => (string) get_post_meta($review_id, Config::META_PREFIX . 'recommendation', true),
            'section_comments' => (array) get_post_meta($review_id, Config::META_PREFIX . 'section_comments', true),
        ];
    }

    private function one_click_feedback(): string
    {
        if (! isset($_GET['tjm_msg'])) {
            return '';
        }
        $key = sanitize_text_field((string) $_GET['tjm_msg']);
        $msg = match ($key) {
            'review_accepted' => __('Invitation accepted. Thank you!', 'tainacan-journal-manager'),
            'review_declined' => __('Invitation declined.', 'tainacan-journal-manager'),
            default           => '',
        };
        return $msg !== '' ? '<div class="tjm-notice">' . esc_html($msg) . '</div>' : '';
    }
}
