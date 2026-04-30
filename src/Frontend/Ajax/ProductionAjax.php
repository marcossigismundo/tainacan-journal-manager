<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\CopyeditingService;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Production\ProofApprovalService;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Tainacan\ArticlePublisher;

/**
 * AJAX handlers for production stage:
 *   - tjm_copyediting_upload         (copyeditor or author uploads version)
 *   - tjm_copyediting_notify_author  (copyeditor signals author to review)
 *   - tjm_copyediting_to_production  (copyeditor finishes round)
 *   - tjm_galley_add                 (layout editor uploads PDF/HTML/XML)
 *   - tjm_galley_remove              (remove a galley)
 *   - tjm_proof_request              (production sends proof to author)
 *   - tjm_proof_approve              (author approves)
 *   - tjm_proof_request_changes      (author requests changes)
 *   - tjm_article_publish            (push to Tainacan + transition to published)
 */
final class ProductionAjax
{
    public function register(): void
    {
        $actions = [
            'tjm_copyediting_upload'         => 'copyediting_upload',
            'tjm_copyediting_notify_author'  => 'copyediting_notify',
            'tjm_copyediting_to_production'  => 'copyediting_done',
            'tjm_galley_add'                 => 'galley_add',
            'tjm_galley_remove'              => 'galley_remove',
            'tjm_proof_request'              => 'proof_request',
            'tjm_proof_approve'              => 'proof_approve',
            'tjm_proof_request_changes'      => 'proof_changes',
            'tjm_article_publish'            => 'article_publish',
        ];
        foreach ($actions as $hook => $method) {
            add_action('wp_ajax_' . $hook, [$this, $method]);
        }
    }

    public function copyediting_upload(): void
    {
        $submission_id = $this->ensure_login_and_submission();

        $user_id = get_current_user_id();
        $role    = $this->resolve_uploader_role($submission_id, $user_id);
        if ($role === '') {
            wp_send_json_error(__('You cannot upload to this submission.', 'tainacan-journal-manager'), 403);
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file received.', 'tainacan-journal-manager'));
        }
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['note'])) : '';

        $res = CopyeditingService::upload_version($submission_id, $user_id, $role, $_FILES['file'], $note);
        if (! empty($res['error']) || empty($res['attachment_id'])) {
            wp_send_json_error($res['error'] ?? __('Upload failed.', 'tainacan-journal-manager'));
        }

        wp_send_json_success([
            'attachment_id' => (int) $res['attachment_id'],
            'filename'      => get_the_title((int) $res['attachment_id']),
            'url'           => wp_get_attachment_url((int) $res['attachment_id']),
        ]);
    }

    public function copyediting_notify(): void
    {
        $submission_id = $this->ensure_copyeditor($this->ensure_login_and_submission());
        CopyeditingService::notify_author_of_version($submission_id);
        wp_send_json_success();
    }

    public function copyediting_done(): void
    {
        $submission_id = $this->ensure_copyeditor($this->ensure_login_and_submission());
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['note'])) : '';
        $ok = CopyeditingService::mark_ready_for_production($submission_id, get_current_user_id(), $note);
        if (! $ok) {
            wp_send_json_error(__('Could not move to production. Upload at least one copyedited version first.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function galley_add(): void
    {
        $submission_id = $this->ensure_layout($this->ensure_login_and_submission());
        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file received.', 'tainacan-journal-manager'));
        }
        $format   = isset($_POST['format']) ? sanitize_text_field(wp_unslash((string) $_POST['format'])) : '';
        $label    = isset($_POST['label']) ? sanitize_text_field(wp_unslash((string) $_POST['label'])) : '';
        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash((string) $_POST['language'])) : '';

        $res = GalleyService::add_galley($submission_id, get_current_user_id(), $format, $_FILES['file'], $label, $language);
        if (! empty($res['error']) || empty($res['attachment_id'])) {
            wp_send_json_error($res['error'] ?? __('Upload failed.', 'tainacan-journal-manager'));
        }
        wp_send_json_success(['attachment_id' => (int) $res['attachment_id']]);
    }

    public function galley_remove(): void
    {
        $submission_id = $this->ensure_layout($this->ensure_login_and_submission());
        $att_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        if ($att_id <= 0) {
            wp_send_json_error(__('Invalid attachment.', 'tainacan-journal-manager'));
        }
        $ok = GalleyService::remove_galley($submission_id, $att_id);
        if (! $ok) {
            wp_send_json_error(__('Galley not found.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function proof_request(): void
    {
        $submission_id = $this->ensure_layout($this->ensure_login_and_submission());
        $ok = ProofApprovalService::request_proof($submission_id, get_current_user_id());
        if (! $ok) {
            wp_send_json_error(__('Add at least one galley before sending the proof.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function proof_approve(): void
    {
        $submission_id = $this->ensure_login_and_submission();
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['note'])) : '';
        $ok = ProofApprovalService::approve($submission_id, get_current_user_id(), $note);
        if (! $ok) {
            wp_send_json_error(__('You cannot approve this proof.', 'tainacan-journal-manager'), 403);
        }
        wp_send_json_success();
    }

    public function proof_changes(): void
    {
        $submission_id = $this->ensure_login_and_submission();
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['note'])) : '';
        if ($note === '') {
            wp_send_json_error(__('Describe the changes you need.', 'tainacan-journal-manager'));
        }
        $ok = ProofApprovalService::request_changes($submission_id, get_current_user_id(), $note);
        if (! $ok) {
            wp_send_json_error(__('Could not request changes.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function article_publish(): void
    {
        $submission_id = $this->ensure_editor($this->ensure_login_and_submission());
        $res = ArticlePublisher::publish($submission_id, get_current_user_id());
        if (empty($res['item_id'])) {
            wp_send_json_error($res['error'] ?? __('Publication failed.', 'tainacan-journal-manager'));
        }
        wp_send_json_success(['item_id' => (int) $res['item_id']]);
    }

    // ── permission helpers ────────────────────────────────────────

    private function ensure_login_and_submission(): int
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        $sid = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
        if ($sid <= 0 || get_post_type($sid) !== Config::CPT_SUBMISSION) {
            wp_send_json_error(__('Invalid submission.', 'tainacan-journal-manager'), 404);
        }
        return $sid;
    }

    private function ensure_copyeditor(int $submission_id): int
    {
        $uid = get_current_user_id();
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        $allowed = PluginRole::has_role($uid, PluginRole::COPYEDITOR)
            || PluginRole::has_journal_role($uid, $journal_id, PluginRole::COPYEDITOR)
            || PluginRole::is_editor($uid, $journal_id)
            || PluginRole::is_admin_institutional($uid);
        if (! $allowed) {
            wp_send_json_error(__('Copyeditor or editor role required.', 'tainacan-journal-manager'), 403);
        }
        return $submission_id;
    }

    private function ensure_layout(int $submission_id): int
    {
        $uid = get_current_user_id();
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        $allowed = PluginRole::has_role($uid, PluginRole::LAYOUT_EDITOR)
            || PluginRole::has_journal_role($uid, $journal_id, PluginRole::LAYOUT_EDITOR)
            || PluginRole::is_editor($uid, $journal_id)
            || PluginRole::is_admin_institutional($uid);
        if (! $allowed) {
            wp_send_json_error(__('Layout editor or editor role required.', 'tainacan-journal-manager'), 403);
        }
        return $submission_id;
    }

    private function ensure_editor(int $submission_id): int
    {
        $uid = get_current_user_id();
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if (! PluginRole::is_editor($uid, $journal_id) && ! PluginRole::is_admin_institutional($uid)) {
            wp_send_json_error(__('Editor role required.', 'tainacan-journal-manager'), 403);
        }
        return $submission_id;
    }

    /**
     * Decide whether the current user is acting as copyeditor/author/editor.
     * Returns '' if the user has no role on this submission.
     */
    private function resolve_uploader_role(int $submission_id, int $user_id): string
    {
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if (PluginRole::has_role($user_id, PluginRole::COPYEDITOR)
            || PluginRole::has_journal_role($user_id, $journal_id, PluginRole::COPYEDITOR)) {
            return 'copyeditor';
        }
        if (PluginRole::is_editor($user_id, $journal_id) || PluginRole::is_admin_institutional($user_id)) {
            return 'editor';
        }
        $post = get_post($submission_id);
        if ($post && (int) $post->post_author === $user_id) {
            return 'author';
        }
        return '';
    }
}
