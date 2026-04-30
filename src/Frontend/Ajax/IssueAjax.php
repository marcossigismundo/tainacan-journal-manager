<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Config;
use TainacanJournalManager\Issues\IssueManager;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Issue management:
 *   - tjm_issue_create
 *   - tjm_issue_assign_article
 *   - tjm_issue_unassign_article
 *   - tjm_issue_publish
 */
final class IssueAjax
{
    public function register(): void
    {
        $actions = [
            'tjm_issue_create'           => 'create',
            'tjm_issue_assign_article'   => 'assign_article',
            'tjm_issue_unassign_article' => 'unassign_article',
            'tjm_issue_publish'          => 'publish',
        ];
        foreach ($actions as $hook => $method) {
            add_action('wp_ajax_' . $hook, [$this, $method]);
        }
    }

    public function create(): void
    {
        $journal_id = $this->ensure_editor_for_journal();

        $data = [
            'journal_id' => $journal_id,
            'title'      => isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '',
            'volume'     => isset($_POST['volume']) ? sanitize_text_field(wp_unslash((string) $_POST['volume'])) : '',
            'number'     => isset($_POST['number']) ? sanitize_text_field(wp_unslash((string) $_POST['number'])) : '',
            'year'       => isset($_POST['year']) ? (int) $_POST['year'] : (int) gmdate('Y'),
            'type'       => isset($_POST['type']) ? sanitize_text_field(wp_unslash((string) $_POST['type'])) : IssueManager::TYPE_REGULAR,
        ];
        if ($data['title'] === '') {
            wp_send_json_error(__('Issue title is required.', 'tainacan-journal-manager'));
        }
        $issue_id = IssueManager::create($data, get_current_user_id());
        if ($issue_id <= 0) {
            wp_send_json_error(__('Could not create issue.', 'tainacan-journal-manager'));
        }
        wp_send_json_success(['issue_id' => $issue_id]);
    }

    public function assign_article(): void
    {
        [$issue_id, $submission_id] = $this->ensure_issue_action();
        if (! IssueManager::assign_article($issue_id, $submission_id)) {
            wp_send_json_error(__('Could not assign article.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function unassign_article(): void
    {
        [$issue_id, $submission_id] = $this->ensure_issue_action();
        IssueManager::unassign_article($issue_id, $submission_id);
        wp_send_json_success();
    }

    public function publish(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        $issue_id = isset($_POST['issue_id']) ? (int) $_POST['issue_id'] : 0;
        if ($issue_id <= 0 || get_post_type($issue_id) !== Config::CPT_ISSUE) {
            wp_send_json_error(__('Invalid issue.', 'tainacan-journal-manager'));
        }
        $journal_id = (int) get_post_meta($issue_id, Config::META_PREFIX . 'journal_id', true);
        $uid = get_current_user_id();
        if (! PluginRole::is_editor($uid, $journal_id) && ! PluginRole::is_admin_institutional($uid)) {
            wp_send_json_error(__('Editor role required.', 'tainacan-journal-manager'), 403);
        }
        if (! IssueManager::publish_issue($issue_id, $uid)) {
            wp_send_json_error(__('Could not publish issue.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    private function ensure_editor_for_journal(): int
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        $journal_id = isset($_POST['journal_id']) ? (int) $_POST['journal_id'] : 0;
        if ($journal_id <= 0 || get_post_type($journal_id) !== Config::CPT_JOURNAL) {
            wp_send_json_error(__('Invalid journal.', 'tainacan-journal-manager'));
        }
        $uid = get_current_user_id();
        if (! PluginRole::is_editor($uid, $journal_id) && ! PluginRole::is_admin_institutional($uid)) {
            wp_send_json_error(__('Editor role required.', 'tainacan-journal-manager'), 403);
        }
        return $journal_id;
    }

    /**
     * @return int[] [issue_id, submission_id]
     */
    private function ensure_issue_action(): array
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        $issue_id      = isset($_POST['issue_id']) ? (int) $_POST['issue_id'] : 0;
        $submission_id = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
        if ($issue_id <= 0 || $submission_id <= 0
            || get_post_type($issue_id) !== Config::CPT_ISSUE
            || get_post_type($submission_id) !== Config::CPT_SUBMISSION) {
            wp_send_json_error(__('Invalid issue or submission.', 'tainacan-journal-manager'));
        }
        $journal_id = (int) get_post_meta($issue_id, Config::META_PREFIX . 'journal_id', true);
        $uid = get_current_user_id();
        if (! PluginRole::is_editor($uid, $journal_id) && ! PluginRole::is_admin_institutional($uid)) {
            wp_send_json_error(__('Editor role required.', 'tainacan-journal-manager'), 403);
        }
        return [$issue_id, $submission_id];
    }
}
