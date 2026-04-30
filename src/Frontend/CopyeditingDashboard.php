<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\CopyeditingService;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Production\ProofApprovalService;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Roles\PermissionChecker;
use TainacanJournalManager\Submission\AnonymizationService;
use TainacanJournalManager\Submission\FileUploadService;

/**
 * Shortcode [tjm_copyediting_dashboard] — view for copyeditor and
 * layout editor roles.
 *
 *  - default     → list of submissions in copyediting/production
 *  - ?submission=N → detail with version uploads + galleys + proof status
 */
final class CopyeditingDashboard
{
    public function register(): void
    {
        add_shortcode('tjm_copyediting_dashboard', [$this, 'render']);
    }

    public function render(): string
    {
        if (! is_user_logged_in()) {
            return '<div class="tjm-notice">' . esc_html__('Please log in.', 'tainacan-journal-manager') . '</div>';
        }
        $uid = get_current_user_id();

        $allowed = PluginRole::has_role($uid, PluginRole::COPYEDITOR)
                || PluginRole::has_role($uid, PluginRole::LAYOUT_EDITOR)
                || PluginRole::is_editor($uid)
                || PluginRole::is_admin_institutional($uid);

        if (! $allowed) {
            return '<div class="tjm-notice tjm-notice--error">' . esc_html__('You need a copyeditor, layout editor or editor role.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');

        if (isset($_GET['submission'])) {
            $sid = (int) $_GET['submission'];
            if (! PermissionChecker::can_view_submission($uid, $sid)) {
                return '<div class="tjm-notice tjm-notice--error">' . esc_html__('Submission not found.', 'tainacan-journal-manager') . '</div>';
            }
            $detail = $this->load_detail($sid);
            ob_start();
            include TJM_PATH . 'templates/frontend/copyediting-detail.php';
            return ob_get_clean() ?: '';
        }

        $submissions = $this->get_relevant_submissions();
        ob_start();
        include TJM_PATH . 'templates/frontend/copyediting-dashboard.php';
        return ob_get_clean() ?: '';
    }

    /**
     * @return \WP_Post[]
     */
    private function get_relevant_submissions(): array
    {
        $query = new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 50,
            'post_status'    => 'any',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => Config::META_PREFIX . 'status',
                    'value'   => [Config::STATUS_COPYEDITING, Config::STATUS_PRODUCTION],
                    'compare' => 'IN',
                ],
            ],
        ]);
        return $query->posts;
    }

    /**
     * @return array<string, mixed>
     */
    private function load_detail(int $submission_id): array
    {
        $post = get_post($submission_id);
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);

        return [
            'id'              => $submission_id,
            'title'           => $post ? (string) $post->post_title : '',
            'abstract'        => $post ? (string) $post->post_content : '',
            'status'          => (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true),
            'journal_id'      => $journal_id,
            'journal_name'    => $journal_id ? (string) get_the_title($journal_id) : '',
            'authors'         => AnonymizationService::collect_authors($submission_id),
            'manuscript'      => FileUploadService::get_manuscript_info($submission_id),
            'copyediting'     => [
                'versions' => CopyeditingService::get_versions($submission_id),
                'status'   => CopyeditingService::get_status($submission_id),
            ],
            'galleys'         => GalleyService::get_galleys_with_urls($submission_id),
            'proof'           => [
                'status'  => ProofApprovalService::get_status($submission_id),
                'history' => ProofApprovalService::get_history($submission_id),
            ],
        ];
    }
}
