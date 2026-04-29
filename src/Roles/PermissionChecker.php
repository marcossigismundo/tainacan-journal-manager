<?php

declare(strict_types=1);

namespace TainacanJournalManager\Roles;

use TainacanJournalManager\Config;

/**
 * Centralized permission checks for editorial actions.
 * Each method returns bool — never sends 403/exits.
 */
final class PermissionChecker
{
    public static function can_submit(int $user_id, int $journal_id): bool
    {
        return $user_id > 0
            && (PluginRole::has_role($user_id, PluginRole::AUTHOR)
                || PluginRole::has_journal_role($user_id, $journal_id, PluginRole::AUTHOR));
    }

    public static function can_view_submission(int $user_id, int $submission_id): bool
    {
        if (! $user_id || ! $submission_id) {
            return false;
        }

        $submission = get_post($submission_id);
        if (! $submission || $submission->post_type !== Config::CPT_SUBMISSION) {
            return false;
        }

        // Author of the submission
        if ((int) $submission->post_author === $user_id) {
            return true;
        }

        // Coauthors
        $coauthors = get_post_meta($submission_id, Config::META_PREFIX . 'coauthors', true);
        if (is_array($coauthors) && in_array($user_id, array_map('intval', $coauthors), true)) {
            return true;
        }

        // Editors of the journal
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if ($journal_id && PluginRole::is_editor($user_id, $journal_id)) {
            return true;
        }

        // Assigned reviewers
        $reviewers = get_post_meta($submission_id, Config::META_PREFIX . 'reviewers', true);
        if (is_array($reviewers) && in_array($user_id, array_map('intval', $reviewers), true)) {
            return true;
        }

        // Institutional admin
        if (PluginRole::is_admin_institutional($user_id)) {
            return true;
        }

        return false;
    }

    public static function can_review(int $user_id, int $submission_id): bool
    {
        if (! $user_id || ! $submission_id) {
            return false;
        }

        $reviewers = get_post_meta($submission_id, Config::META_PREFIX . 'reviewers', true);
        return is_array($reviewers) && in_array($user_id, array_map('intval', $reviewers), true);
    }

    public static function can_edit_journal(int $user_id, int $journal_id): bool
    {
        return PluginRole::is_editor($user_id, $journal_id)
            || PluginRole::is_admin_institutional($user_id);
    }

    public static function can_decide(int $user_id, int $submission_id): bool
    {
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        return $journal_id > 0 && PluginRole::is_editor($user_id, $journal_id);
    }

    public static function is_journal_manager(int $user_id, ?int $journal_id = null): bool
    {
        if ($journal_id) {
            return PluginRole::has_journal_role($user_id, $journal_id, PluginRole::JOURNAL_MANAGER);
        }
        return PluginRole::has_role($user_id, PluginRole::JOURNAL_MANAGER);
    }
}
