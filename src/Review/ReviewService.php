<?php

declare(strict_types=1);

namespace TainacanJournalManager\Review;

use TainacanJournalManager\Config;

/**
 * Peer review operations: invite reviewers, submit reviews, track status.
 */
final class ReviewService
{
    /**
     * Create a review invitation for a reviewer.
     */
    public static function invite(int $submission_id, int $reviewer_id, int $editor_id, ?string $deadline = null): int
    {
        $review_id = wp_insert_post([
            'post_type'    => Config::CPT_REVIEW,
            'post_status'  => 'publish',
            'post_author'  => $reviewer_id,
            'post_title'   => sprintf('Review #%d for submission #%d', $reviewer_id, $submission_id),
        ], true);

        if (is_wp_error($review_id)) {
            return 0;
        }

        update_post_meta($review_id, Config::META_PREFIX . 'submission_id', $submission_id);
        update_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', $reviewer_id);
        update_post_meta($review_id, Config::META_PREFIX . 'invited_by', $editor_id);
        update_post_meta($review_id, Config::META_PREFIX . 'review_status', Config::REVIEW_INVITED);
        update_post_meta($review_id, Config::META_PREFIX . 'invited_at', current_time('mysql'));

        $deadline = $deadline ?: gmdate('Y-m-d', strtotime('+' . Config::DEFAULT_REVIEW_DEADLINE . ' days'));
        update_post_meta($review_id, Config::META_PREFIX . 'deadline', $deadline);

        // Track on submission too
        $reviewers = get_post_meta($submission_id, Config::META_PREFIX . 'reviewers', true);
        if (! is_array($reviewers)) {
            $reviewers = [];
        }
        if (! in_array($reviewer_id, $reviewers, true)) {
            $reviewers[] = $reviewer_id;
            update_post_meta($submission_id, Config::META_PREFIX . 'reviewers', $reviewers);
        }

        do_action('tjm_review_invited', $review_id, $submission_id, $reviewer_id);

        return (int) $review_id;
    }

    public static function accept_invitation(int $review_id, int $user_id): bool
    {
        $reviewer_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', true);
        if ($reviewer_id !== $user_id) {
            return false;
        }
        update_post_meta($review_id, Config::META_PREFIX . 'review_status', Config::REVIEW_ACCEPTED);
        update_post_meta($review_id, Config::META_PREFIX . 'accepted_at', current_time('mysql'));
        do_action('tjm_review_accepted', $review_id);
        return true;
    }

    public static function decline_invitation(int $review_id, int $user_id, string $reason = ''): bool
    {
        $reviewer_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', true);
        if ($reviewer_id !== $user_id) {
            return false;
        }
        update_post_meta($review_id, Config::META_PREFIX . 'review_status', Config::REVIEW_DECLINED);
        update_post_meta($review_id, Config::META_PREFIX . 'decline_reason', sanitize_textarea_field($reason));
        do_action('tjm_review_declined', $review_id);
        return true;
    }

    /**
     * Submit a completed review.
     *
     * @param array{author_comments: string, editor_comments?: string, recommendation: string} $data
     */
    public static function submit_review(int $review_id, int $user_id, array $data): bool
    {
        $reviewer_id = (int) get_post_meta($review_id, Config::META_PREFIX . 'reviewer_id', true);
        if ($reviewer_id !== $user_id) {
            return false;
        }

        update_post_meta($review_id, Config::META_PREFIX . 'author_comments', sanitize_textarea_field((string) ($data['author_comments'] ?? '')));
        update_post_meta($review_id, Config::META_PREFIX . 'editor_comments', sanitize_textarea_field((string) ($data['editor_comments'] ?? '')));
        update_post_meta($review_id, Config::META_PREFIX . 'recommendation', sanitize_text_field((string) ($data['recommendation'] ?? '')));
        update_post_meta($review_id, Config::META_PREFIX . 'review_status', Config::REVIEW_SUBMITTED);
        update_post_meta($review_id, Config::META_PREFIX . 'submitted_at', current_time('mysql'));

        do_action('tjm_review_submitted', $review_id);
        return true;
    }
}
