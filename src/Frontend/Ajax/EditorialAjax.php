<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Config;
use TainacanJournalManager\Editorial\DecisionManager;
use TainacanJournalManager\Editorial\WorkflowManager;
use TainacanJournalManager\Notifications\Mailer;
use TainacanJournalManager\Notifications\TokenManager;
use TainacanJournalManager\Review\ReviewService;
use TainacanJournalManager\Roles\PermissionChecker;
use TainacanJournalManager\Roles\PluginRole;

/**
 * AJAX handlers for the editorial dashboard.
 *
 *  - tjm_editorial_to_triage          (submitted → triage)
 *  - tjm_editorial_to_review          (triage → review)
 *  - tjm_editorial_invite_reviewer    (creates Review post + token + email)
 *  - tjm_editorial_record_decision    (accept/revisions/resubmit/reject)
 */
final class EditorialAjax
{
    public function register(): void
    {
        $actions = [
            'tjm_editorial_to_triage'       => 'to_triage',
            'tjm_editorial_to_review'       => 'to_review',
            'tjm_editorial_invite_reviewer' => 'invite_reviewer',
            'tjm_editorial_record_decision' => 'record_decision',
        ];
        foreach ($actions as $hook => $method) {
            add_action('wp_ajax_' . $hook, [$this, $method]);
        }
    }

    public function to_triage(): void
    {
        $submission_id = $this->ensure_editor_action();
        $ok = WorkflowManager::transition($submission_id, Config::STATUS_TRIAGE, get_current_user_id());
        if (! $ok) {
            wp_send_json_error(__('Cannot transition to triage from current status.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function to_review(): void
    {
        $submission_id = $this->ensure_editor_action();
        $ok = WorkflowManager::transition($submission_id, Config::STATUS_REVIEW, get_current_user_id());
        if (! $ok) {
            wp_send_json_error(__('Cannot transition to review from current status.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function invite_reviewer(): void
    {
        $submission_id = $this->ensure_editor_action();

        $reviewer_id = isset($_POST['reviewer_id']) ? (int) $_POST['reviewer_id'] : 0;
        $deadline    = isset($_POST['deadline']) ? sanitize_text_field(wp_unslash((string) $_POST['deadline'])) : '';

        if ($reviewer_id <= 0 || ! get_userdata($reviewer_id)) {
            wp_send_json_error(__('Invalid reviewer.', 'tainacan-journal-manager'));
        }
        if (! PluginRole::has_role($reviewer_id, PluginRole::REVIEWER)) {
            wp_send_json_error(__('User does not have the Reviewer role.', 'tainacan-journal-manager'));
        }

        // No double-invitation for the same reviewer on same submission
        $existing = get_post_meta($submission_id, Config::META_PREFIX . 'reviewers', true);
        if (is_array($existing) && in_array($reviewer_id, array_map('intval', $existing), true)) {
            wp_send_json_error(__('This reviewer has already been invited.', 'tainacan-journal-manager'));
        }

        $review_id = ReviewService::invite($submission_id, $reviewer_id, get_current_user_id(), $deadline ?: null);
        if ($review_id <= 0) {
            wp_send_json_error(__('Could not create invitation.', 'tainacan-journal-manager'));
        }

        $this->send_invitation_email($review_id, $submission_id, $reviewer_id);

        wp_send_json_success(['review_id' => $review_id]);
    }

    public function record_decision(): void
    {
        $submission_id = $this->ensure_editor_action();

        $decision = isset($_POST['decision']) ? sanitize_text_field(wp_unslash((string) $_POST['decision'])) : '';
        $note     = isset($_POST['justification']) ? sanitize_textarea_field(wp_unslash((string) $_POST['justification'])) : '';

        $allowed = [
            Config::DECISION_ACCEPT,
            Config::DECISION_REVISIONS,
            Config::DECISION_RESUBMIT,
            Config::DECISION_REJECT,
        ];
        if (! in_array($decision, $allowed, true)) {
            wp_send_json_error(__('Invalid decision.', 'tainacan-journal-manager'));
        }

        // Require the submission to be in review or decision before recording
        $current = (string) get_post_meta($submission_id, Config::META_PREFIX . 'status', true);
        if (! in_array($current, [Config::STATUS_REVIEW, Config::STATUS_DECISION, Config::STATUS_TRIAGE], true)) {
            wp_send_json_error(__('Decision can only be recorded after triage/review.', 'tainacan-journal-manager'));
        }

        $ok = DecisionManager::record($submission_id, $decision, get_current_user_id(), $note);
        if (! $ok) {
            wp_send_json_error(__('Could not record decision.', 'tainacan-journal-manager'));
        }

        $this->notify_author_of_decision($submission_id, $decision, $note);

        wp_send_json_success();
    }

    private function send_invitation_email(int $review_id, int $submission_id, int $reviewer_id): void
    {
        $reviewer = get_userdata($reviewer_id);
        if (! $reviewer || ! is_email($reviewer->user_email)) {
            return;
        }

        $accept_token  = TokenManager::generate($reviewer_id, 'review_accept',  ['review_id' => $review_id]);
        $decline_token = TokenManager::generate($reviewer_id, 'review_decline', ['review_id' => $review_id]);

        $base = home_url('/');
        $accept_url  = add_query_arg(['tjm_action' => 'review_accept',  'token' => $accept_token],  $base);
        $decline_url = add_query_arg(['tjm_action' => 'review_decline', 'token' => $decline_token], $base);

        $deadline = (string) get_post_meta($review_id, Config::META_PREFIX . 'deadline', true);

        (new Mailer())->send($reviewer->user_email, 'review-invitation', [
            'reviewer_name' => $reviewer->display_name ?: $reviewer->user_login,
            'title'         => (string) get_the_title($submission_id),
            'deadline'      => $deadline ? date_i18n('d/m/Y', strtotime($deadline)) : '',
            'accept_url'    => $accept_url,
            'decline_url'   => $decline_url,
        ]);
    }

    private function notify_author_of_decision(int $submission_id, string $decision, string $note): void
    {
        $post = get_post($submission_id);
        if (! $post) {
            return;
        }
        $author = get_userdata((int) $post->post_author);
        if (! $author || ! is_email($author->user_email)) {
            return;
        }

        $template_key = match ($decision) {
            Config::DECISION_ACCEPT     => 'decision-accept',
            Config::DECISION_REVISIONS  => 'decision-revisions',
            Config::DECISION_RESUBMIT   => 'decision-revisions',
            Config::DECISION_REJECT     => 'decision-reject',
            default                     => 'decision-reject',
        };

        (new Mailer())->send($author->user_email, $template_key, [
            'author_name' => $author->display_name ?: $author->user_login,
            'title'       => (string) $post->post_title,
            'note'        => $note,
        ]);
    }

    private function ensure_editor_action(): int
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }

        $submission_id = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
        if ($submission_id <= 0) {
            wp_send_json_error(__('Invalid submission.', 'tainacan-journal-manager'));
        }

        $user_id = get_current_user_id();
        if (! PermissionChecker::can_decide($user_id, $submission_id) && ! PluginRole::is_admin_institutional($user_id)) {
            wp_send_json_error(__('You do not have permission to manage this submission.', 'tainacan-journal-manager'), 403);
        }
        return $submission_id;
    }
}
