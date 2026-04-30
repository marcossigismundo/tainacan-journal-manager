<?php

declare(strict_types=1);

namespace TainacanJournalManager\Submission;

use TainacanJournalManager\Config;

/**
 * Provides anonymized views of submissions for blind/double-blind review.
 *
 * The journal's `_tjm_review_type` controls what reviewers see:
 *  - open         → full disclosure (authors visible, reviewers identified)
 *  - blind        → reviewers see authors hidden ("Author N")
 *  - double_blind → both sides anonymous; authors also do not see reviewer names
 *  - editorial    → no peer reviewers, only editor
 *
 * This service does NOT remove identifying information from the manuscript file
 * itself — that is the author's responsibility (anonymized PDF). It controls
 * which fields the reviewer UI exposes.
 */
final class AnonymizationService
{
    public const VIEW_AUTHOR  = 'author';
    public const VIEW_EDITOR  = 'editor';
    public const VIEW_REVIEWER = 'reviewer';

    public static function review_type_for_submission(int $submission_id): string
    {
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if ($journal_id <= 0) {
            return Config::REVIEW_TYPE_DOUBLE;
        }
        $type = (string) get_post_meta($journal_id, Config::META_PREFIX . 'review_type', true);
        return $type !== '' ? $type : Config::REVIEW_TYPE_DOUBLE;
    }

    /**
     * Should the given viewer see real author identities for this submission?
     */
    public static function show_authors(int $submission_id, string $viewer_role): bool
    {
        if ($viewer_role === self::VIEW_AUTHOR || $viewer_role === self::VIEW_EDITOR) {
            return true;
        }

        // viewer is reviewer
        $type = self::review_type_for_submission($submission_id);
        return $type === Config::REVIEW_TYPE_OPEN;
    }

    /**
     * Should the given viewer see reviewer identities?
     */
    public static function show_reviewers(int $submission_id, string $viewer_role): bool
    {
        if ($viewer_role === self::VIEW_EDITOR) {
            return true;
        }

        $type = self::review_type_for_submission($submission_id);

        if ($viewer_role === self::VIEW_AUTHOR) {
            // Author sees reviewers only in open review
            return $type === Config::REVIEW_TYPE_OPEN;
        }

        // Reviewer-to-reviewer: only in open
        return $type === Config::REVIEW_TYPE_OPEN;
    }

    /**
     * @return array<int, array<string, string>> List of authors as [{name, affiliation, orcid}],
     *   anonymized when applicable.
     */
    public static function authors_for_view(int $submission_id, string $viewer_role): array
    {
        $authors = self::collect_authors($submission_id);

        if (self::show_authors($submission_id, $viewer_role)) {
            return $authors;
        }

        $masked = [];
        foreach ($authors as $i => $author) {
            $masked[] = [
                'name'        => sprintf(__('Author %d', 'tainacan-journal-manager'), $i + 1),
                'affiliation' => '',
                'orcid'       => '',
            ];
        }
        return $masked;
    }

    /**
     * @return array<int, array{name: string, affiliation: string, orcid: string}>
     */
    public static function collect_authors(int $submission_id): array
    {
        $authors = [];

        $primary_id = (int) (get_post($submission_id)->post_author ?? 0);
        if ($primary_id > 0) {
            $u = get_userdata($primary_id);
            if ($u) {
                $authors[] = [
                    'name'        => trim($u->display_name ?: ($u->first_name . ' ' . $u->last_name)) ?: $u->user_login,
                    'affiliation' => (string) get_user_meta($primary_id, '_tjm_affiliation', true),
                    'orcid'       => (string) get_user_meta($primary_id, '_tjm_orcid', true),
                ];
            }
        }

        $coauthors = get_post_meta($submission_id, Config::META_PREFIX . 'coauthors', true);
        if (is_array($coauthors)) {
            foreach ($coauthors as $entry) {
                if (is_array($entry)) {
                    $authors[] = [
                        'name'        => (string) ($entry['name'] ?? ''),
                        'affiliation' => (string) ($entry['affiliation'] ?? ''),
                        'orcid'       => (string) ($entry['orcid'] ?? ''),
                    ];
                } elseif (is_numeric($entry)) {
                    $u = get_userdata((int) $entry);
                    if ($u) {
                        $authors[] = [
                            'name'        => trim($u->display_name) ?: $u->user_login,
                            'affiliation' => (string) get_user_meta((int) $entry, '_tjm_affiliation', true),
                            'orcid'       => (string) get_user_meta((int) $entry, '_tjm_orcid', true),
                        ];
                    }
                }
            }
        }

        return $authors;
    }

    /**
     * Build the masked title for a reviewer view (currently unchanged — manuscript
     * title is shown as-is; anonymization is on author identities only).
     */
    public static function title_for_view(int $submission_id, string $viewer_role): string
    {
        return get_the_title($submission_id);
    }
}
