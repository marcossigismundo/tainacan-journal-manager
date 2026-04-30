<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Roles\PermissionChecker;
use TainacanJournalManager\Submission\SubmissionService;
use TainacanJournalManager\Submission\FileUploadService;

/**
 * AJAX handlers for the multi-step submission wizard.
 *
 * Action map (all use nonce 'tjm_frontend_nonce'):
 *   - tjm_submission_create_draft   (step 0 → creates draft, returns ID)
 *   - tjm_submission_save_metadata  (step 1)
 *   - tjm_submission_save_authors   (step 2)
 *   - tjm_submission_upload_file    (step 3, multipart)
 *   - tjm_submission_save_declarations (step 4)
 *   - tjm_submission_finalize       (step 5 → submit)
 *   - tjm_submission_withdraw       (any time, on draft / submitted)
 */
final class SubmissionAjax
{
    public function register(): void
    {
        $actions = [
            'tjm_submission_create_draft'      => 'create_draft',
            'tjm_submission_save_metadata'     => 'save_metadata',
            'tjm_submission_save_authors'      => 'save_authors',
            'tjm_submission_upload_file'       => 'upload_file',
            'tjm_submission_save_declarations' => 'save_declarations',
            'tjm_submission_finalize'          => 'finalize',
            'tjm_submission_withdraw'          => 'withdraw',
        ];
        foreach ($actions as $hook => $method) {
            add_action('wp_ajax_' . $hook, [$this, $method]);
        }
    }

    public function create_draft(): void
    {
        $this->check_nonce_and_login();

        $user_id    = get_current_user_id();
        $journal_id = isset($_POST['journal_id']) ? (int) $_POST['journal_id'] : 0;
        $title      = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';

        if (! $journal_id || $title === '') {
            wp_send_json_error(__('Title and journal are required.', 'tainacan-journal-manager'));
        }
        if (! PermissionChecker::can_submit($user_id, $journal_id)) {
            wp_send_json_error(__('You do not have permission to submit to this journal.', 'tainacan-journal-manager'), 403);
        }

        $submission_id = SubmissionService::create_draft($user_id, [
            'title'      => $title,
            'journal_id' => $journal_id,
        ]);

        if ($submission_id <= 0) {
            wp_send_json_error(__('Could not create draft.', 'tainacan-journal-manager'));
        }

        wp_send_json_success(['submission_id' => $submission_id]);
    }

    public function save_metadata(): void
    {
        $submission_id = $this->ensure_owned_submission();

        $data = [];
        if (isset($_POST['title'])) {
            $data['title'] = sanitize_text_field(wp_unslash((string) $_POST['title']));
        }
        if (isset($_POST['abstract'])) {
            $data['abstract'] = sanitize_textarea_field(wp_unslash((string) $_POST['abstract']));
        }
        if (isset($_POST['journal_id'])) {
            $data['journal_id'] = (int) $_POST['journal_id'];
        }
        if (isset($_POST['language'])) {
            $data['language'] = sanitize_text_field(wp_unslash((string) $_POST['language']));
        }
        if (isset($_POST['references'])) {
            $data['references'] = sanitize_textarea_field(wp_unslash((string) $_POST['references']));
        }
        if (isset($_POST['funding'])) {
            $data['funding'] = sanitize_text_field(wp_unslash((string) $_POST['funding']));
        }
        if (isset($_POST['section_term_id'])) {
            $data['section_term_id'] = (int) $_POST['section_term_id'];
        }
        if (isset($_POST['keywords'])) {
            $raw = wp_unslash((string) $_POST['keywords']);
            $data['keywords'] = array_filter(array_map('trim', explode(',', $raw)));
        }

        SubmissionService::update_metadata($submission_id, $data);
        wp_send_json_success();
    }

    public function save_authors(): void
    {
        $submission_id = $this->ensure_owned_submission();

        $coauthors = [];
        $raw = isset($_POST['coauthors']) && is_array($_POST['coauthors']) ? wp_unslash($_POST['coauthors']) : [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $coauthors[] = [
                'name'        => (string) ($entry['name'] ?? ''),
                'email'       => (string) ($entry['email'] ?? ''),
                'affiliation' => (string) ($entry['affiliation'] ?? ''),
                'orcid'       => (string) ($entry['orcid'] ?? ''),
            ];
        }

        SubmissionService::set_coauthors($submission_id, $coauthors);
        wp_send_json_success();
    }

    public function upload_file(): void
    {
        $submission_id = $this->ensure_owned_submission();

        if (empty($_FILES['manuscript'])) {
            wp_send_json_error(__('No file received.', 'tainacan-journal-manager'));
        }

        $kind = isset($_POST['kind']) && $_POST['kind'] === 'supplementary' ? 'supplementary' : 'manuscript';
        $file = $_FILES['manuscript'];

        $result = $kind === 'supplementary'
            ? FileUploadService::upload_supplementary($submission_id, get_current_user_id(), $file)
            : FileUploadService::upload_manuscript($submission_id, get_current_user_id(), $file);

        if (! empty($result['error']) || empty($result['attachment_id'])) {
            wp_send_json_error($result['error'] ?? __('Upload failed.', 'tainacan-journal-manager'));
        }

        $att_id = (int) $result['attachment_id'];
        wp_send_json_success([
            'attachment_id' => $att_id,
            'filename'      => get_the_title($att_id),
            'url'           => wp_get_attachment_url($att_id),
        ]);
    }

    public function save_declarations(): void
    {
        $submission_id = $this->ensure_owned_submission();

        SubmissionService::set_declarations($submission_id, [
            'original'  => ! empty($_POST['original']),
            'coi'       => ! empty($_POST['coi']),
            'copyright' => ! empty($_POST['copyright']),
            'ethics'    => ! empty($_POST['ethics']),
        ]);
        wp_send_json_success();
    }

    public function finalize(): void
    {
        $submission_id = $this->ensure_owned_submission();

        if (! SubmissionService::is_complete($submission_id)) {
            wp_send_json_error(__('Submission is incomplete. Check title, abstract, journal, manuscript file and declarations.', 'tainacan-journal-manager'));
        }

        $ok = SubmissionService::submit($submission_id, get_current_user_id());
        if (! $ok) {
            wp_send_json_error(__('Could not finalize the submission.', 'tainacan-journal-manager'));
        }
        wp_send_json_success(['submission_id' => $submission_id]);
    }

    public function withdraw(): void
    {
        $submission_id = $this->ensure_owned_submission();

        $current = (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true);
        if (! in_array($current, [Config::STATUS_DRAFT, Config::STATUS_SUBMITTED], true)) {
            wp_send_json_error(__('You can only withdraw drafts or recently submitted manuscripts.', 'tainacan-journal-manager'));
        }

        $ok = \TainacanJournalManager\Editorial\WorkflowManager::transition(
            $submission_id,
            Config::STATUS_WITHDRAWN,
            get_current_user_id(),
            __('Withdrawn by author.', 'tainacan-journal-manager')
        );
        if (! $ok) {
            wp_send_json_error(__('Could not withdraw.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    private function check_nonce_and_login(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
    }

    /**
     * Verify nonce, login, ownership/permission for an existing submission;
     * returns the submission ID or terminates with json_error.
     */
    private function ensure_owned_submission(): int
    {
        $this->check_nonce_and_login();
        $submission_id = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
        if ($submission_id <= 0) {
            wp_send_json_error(__('Invalid submission.', 'tainacan-journal-manager'));
        }

        $user_id = get_current_user_id();
        $post = get_post($submission_id);
        if (! $post || $post->post_type !== Config::CPT_SUBMISSION) {
            wp_send_json_error(__('Submission not found.', 'tainacan-journal-manager'), 404);
        }

        $is_author = (int) $post->post_author === $user_id;
        $is_admin  = user_can($user_id, 'manage_options');
        if (! $is_author && ! $is_admin) {
            wp_send_json_error(__('You do not own this submission.', 'tainacan-journal-manager'), 403);
        }

        $status = (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true);
        // Authors can only edit drafts (admins can edit anytime)
        if (! $is_admin && $status !== Config::STATUS_DRAFT) {
            wp_send_json_error(__('This submission is no longer editable.', 'tainacan-journal-manager'), 403);
        }

        return $submission_id;
    }
}
