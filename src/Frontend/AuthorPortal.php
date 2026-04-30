<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Production\ProofApprovalService;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Roles\PermissionChecker;
use TainacanJournalManager\Submission\FileUploadService;

/**
 * Shortcode [tjm_author_portal] — Author's portal.
 *
 * Three views, controlled by query args:
 *   - default        → list of the author's submissions
 *   - ?new=1         → multi-step wizard for a new submission (no draft yet)
 *   - ?submission=N  → wizard editing an existing draft, OR read-only detail
 *                       (depending on submission status)
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

        // Wizard for new submission
        if (isset($_GET['new'])) {
            $journals = $this->get_open_journals($user_id);
            ob_start();
            include TJM_PATH . 'templates/frontend/submission-wizard-new.php';
            return ob_get_clean() ?: '';
        }

        // Detail / continued wizard for existing submission
        if (isset($_GET['submission'])) {
            $submission_id = (int) $_GET['submission'];
            if (! PermissionChecker::can_view_submission($user_id, $submission_id)) {
                return '<div class="tjm-notice tjm-notice--error">' . esc_html__('Submission not found.', 'tainacan-journal-manager') . '</div>';
            }
            $submission = get_post($submission_id);
            $status     = (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true);
            $is_owner   = (int) ($submission->post_author ?? 0) === $user_id;

            if ($is_owner && $status === Config::STATUS_DRAFT) {
                $journals = $this->get_open_journals($user_id);
                $data = $this->load_submission_data($submission_id);
                ob_start();
                include TJM_PATH . 'templates/frontend/submission-wizard.php';
                return ob_get_clean() ?: '';
            }

            $data = $this->load_submission_data($submission_id);
            ob_start();
            include TJM_PATH . 'templates/frontend/submission-detail.php';
            return ob_get_clean() ?: '';
        }

        // List view
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

    /**
     * Journals the user can submit to.
     *
     * @return \WP_Post[]
     */
    private function get_open_journals(int $user_id): array
    {
        $query = new \WP_Query([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        return $query->posts;
    }

    /**
     * Collect everything needed by the wizard / detail templates.
     *
     * @return array<string, mixed>
     */
    private function load_submission_data(int $submission_id): array
    {
        $post = get_post($submission_id);

        return [
            'id'              => $submission_id,
            'title'           => $post ? (string) $post->post_title : '',
            'abstract'        => $post ? (string) $post->post_content : '',
            'journal_id'      => (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true),
            'status'          => (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true),
            'language'        => (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_LANGUAGE, true),
            'references'      => (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_REFERENCES, true),
            'funding'         => (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_FUNDING, true),
            'keywords'        => (array) get_post_meta($submission_id, Config::META_PREFIX . Config::META_KEYWORDS, true),
            'coauthors'       => (array) get_post_meta($submission_id, Config::META_PREFIX . 'coauthors', true),
            'declarations'    => [
                'original'  => (bool) get_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_ORIGINAL, true),
                'coi'       => (bool) get_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_COI, true),
                'copyright' => (bool) get_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_COPYRIGHT, true),
                'ethics'    => (bool) get_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_ETHICS, true),
            ],
            'manuscript'      => FileUploadService::get_manuscript_info($submission_id),
            'galleys'         => GalleyService::get_galleys_with_urls($submission_id),
            'proof_status'    => ProofApprovalService::get_status($submission_id),
            'status_history'  => (array) get_post_meta($submission_id, Config::META_PREFIX . 'status_history', true),
            'decisions'       => (array) get_post_meta($submission_id, Config::META_PREFIX . 'decisions', true),
        ];
    }
}
