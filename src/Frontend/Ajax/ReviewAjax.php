<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Config;
use TainacanJournalManager\Notifications\Mailer;
use TainacanJournalManager\Notifications\TokenManager;
use TainacanJournalManager\Review\ReviewService;

/**
 * AJAX + URL handlers for the reviewer dashboard.
 *
 *   - tjm_review_accept  (logged-in)
 *   - tjm_review_decline (logged-in)
 *   - tjm_review_submit  (logged-in, full parecer)
 *
 * Plus: a `template_redirect` listener to handle one-click accept/decline
 * via emailed magic links (?tjm_action=review_accept&token=...).
 */
final class ReviewAjax
{
    public function register(): void
    {
        add_action('wp_ajax_tjm_review_accept',  [$this, 'accept']);
        add_action('wp_ajax_tjm_review_decline', [$this, 'decline']);
        add_action('wp_ajax_tjm_review_submit',  [$this, 'submit_review']);

        // One-click email links
        add_action('template_redirect', [$this, 'handle_token_link']);
    }

    public function accept(): void
    {
        $review_id = $this->ensure_reviewer_action();
        $ok = ReviewService::accept_invitation($review_id, get_current_user_id());
        if (! $ok) {
            wp_send_json_error(__('Could not accept invitation.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function decline(): void
    {
        $review_id = $this->ensure_reviewer_action();
        $reason    = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash((string) $_POST['reason'])) : '';
        $ok = ReviewService::decline_invitation($review_id, get_current_user_id(), $reason);
        if (! $ok) {
            wp_send_json_error(__('Could not decline invitation.', 'tainacan-journal-manager'));
        }
        wp_send_json_success();
    }

    public function submit_review(): void
    {
        $review_id = $this->ensure_reviewer_action();

        $author_comments = isset($_POST['author_comments']) ? sanitize_textarea_field(wp_unslash((string) $_POST['author_comments'])) : '';
        $editor_comments = isset($_POST['editor_comments']) ? sanitize_textarea_field(wp_unslash((string) $_POST['editor_comments'])) : '';
        $recommendation  = isset($_POST['recommendation']) ? sanitize_text_field(wp_unslash((string) $_POST['recommendation'])) : '';

        $sections = [];
        if (isset($_POST['sections']) && is_array($_POST['sections'])) {
            foreach (wp_unslash($_POST['sections']) as $key => $val) {
                $sections[(string) $key] = (string) $val;
            }
        }

        $ok = ReviewService::submit_review($review_id, get_current_user_id(), [
            'author_comments' => $author_comments,
            'editor_comments' => $editor_comments,
            'recommendation'  => $recommendation,
            'sections'        => $sections,
        ]);
        if (! $ok) {
            wp_send_json_error(__('Could not submit review. Provide author comments and a recommendation.', 'tainacan-journal-manager'));
        }

        $this->notify_editor_review_received($review_id);
        $this->notify_reviewer_thanks($review_id);

        wp_send_json_success();
    }

    public function handle_token_link(): void
    {
        if (! isset($_GET['tjm_action'], $_GET['token'])) {
            return;
        }
        $action = sanitize_text_field((string) $_GET['tjm_action']);
        $token  = sanitize_text_field((string) $_GET['token']);

        if (! in_array($action, ['review_accept', 'review_decline'], true)) {
            return;
        }

        $data = TokenManager::validate($token);
        if (! $data) {
            wp_die(esc_html__('This link is invalid or has expired.', 'tainacan-journal-manager'), '', ['response' => 410]);
        }

        $review_id   = isset($data['payload']['review_id']) ? (int) $data['payload']['review_id'] : 0;
        $reviewer_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        if ($review_id <= 0 || $reviewer_id <= 0) {
            wp_die(esc_html__('Invalid token payload.', 'tainacan-journal-manager'), '', ['response' => 400]);
        }

        if ($action === 'review_accept' && $data['purpose'] === 'review_accept') {
            ReviewService::accept_invitation($review_id, $reviewer_id);
            TokenManager::consume($token);
            $url = Config::page_url(Config::PAGE_REVIEWER);
            wp_safe_redirect(add_query_arg('tjm_msg', 'review_accepted', $url));
            exit;
        }

        if ($action === 'review_decline' && $data['purpose'] === 'review_decline') {
            ReviewService::decline_invitation($review_id, $reviewer_id, __('Declined via email link.', 'tainacan-journal-manager'));
            TokenManager::consume($token);
            $url = Config::page_url(Config::PAGE_REVIEWER);
            wp_safe_redirect(add_query_arg('tjm_msg', 'review_declined', $url));
            exit;
        }
    }

    private function notify_editor_review_received(int $review_id): void
    {
        $submission_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'submission_id', true);
        if ($submission_id <= 0) {
            return;
        }
        $editor_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'invited_by', true);
        $editor    = $editor_id ? get_userdata($editor_id) : null;
        if (! $editor || ! is_email($editor->user_email)) {
            return;
        }

        (new Mailer())->send($editor->user_email, 'editor-review-received', [
            'editor_name' => $editor->display_name ?: $editor->user_login,
            'title'       => (string) get_the_title($submission_id),
            'review_id'   => $review_id,
        ]);
    }

    private function notify_reviewer_thanks(int $review_id): void
    {
        $reviewer_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', true);
        $reviewer    = $reviewer_id ? get_userdata($reviewer_id) : null;
        if (! $reviewer || ! is_email($reviewer->user_email)) {
            return;
        }
        $submission_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'submission_id', true);

        (new Mailer())->send($reviewer->user_email, 'review-thanks', [
            'reviewer_name' => $reviewer->display_name ?: $reviewer->user_login,
            'title'         => $submission_id ? (string) get_the_title($submission_id) : '',
        ]);
    }

    private function ensure_reviewer_action(): int
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }

        $review_id = isset($_POST['review_id']) ? (int) $_POST['review_id'] : 0;
        if ($review_id <= 0) {
            wp_send_json_error(__('Invalid review.', 'tainacan-journal-manager'));
        }

        $user_id = get_current_user_id();
        $reviewer_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', true);
        if ($reviewer_id !== $user_id) {
            wp_send_json_error(__('You are not the assigned reviewer.', 'tainacan-journal-manager'), 403);
        }
        return $review_id;
    }
}
