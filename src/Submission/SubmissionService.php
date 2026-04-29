<?php

declare(strict_types=1);

namespace TainacanJournalManager\Submission;

use TainacanJournalManager\Config;
use TainacanJournalManager\Editorial\WorkflowManager;

/**
 * High-level operations for submissions.
 *
 * Stub: full validation and file upload handling will be added in
 * Phase 2 (SubmissionForm + multi-step wizard).
 */
final class SubmissionService
{
    /**
     * Create a new draft submission.
     *
     * @param array{title: string, journal_id: int, abstract?: string} $data
     * @return int New submission ID, or 0 on failure.
     */
    public static function create_draft(int $author_id, array $data): int
    {
        if ($author_id <= 0 || empty($data['title']) || empty($data['journal_id'])) {
            return 0;
        }

        $post_id = wp_insert_post([
            'post_type'    => Config::CPT_SUBMISSION,
            'post_status'  => 'publish', // Visible only via permission checks
            'post_author'  => $author_id,
            'post_title'   => sanitize_text_field((string) $data['title']),
            'post_content' => isset($data['abstract']) ? sanitize_textarea_field((string) $data['abstract']) : '',
        ], true);

        if (is_wp_error($post_id)) {
            return 0;
        }

        update_post_meta($post_id, Config::META_PREFIX . 'journal_id', (int) $data['journal_id']);
        update_post_meta($post_id, Config::META_PREFIX . 'status', Config::STATUS_DRAFT);
        update_post_meta($post_id, Config::META_PREFIX . 'submitted_at', '');
        update_post_meta($post_id, Config::META_PREFIX . 'coauthors', []);

        return (int) $post_id;
    }

    /**
     * Submit (transition draft → submitted).
     */
    public static function submit(int $submission_id, int $user_id): bool
    {
        if (! WorkflowManager::transition($submission_id, Config::STATUS_SUBMITTED, $user_id)) {
            return false;
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'submitted_at', current_time('mysql'));

        do_action('tjm_submission_submitted', $submission_id);
        return true;
    }
}
