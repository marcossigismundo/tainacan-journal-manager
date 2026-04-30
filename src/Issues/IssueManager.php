<?php

declare(strict_types=1);

namespace TainacanJournalManager\Issues;

use TainacanJournalManager\Config;

/**
 * Issue (volume / number / dossier / continuous) management.
 *
 * Each Issue post stores:
 *   _tjm_journal_id       (int)
 *   _tjm_volume           (string)
 *   _tjm_number           (string)
 *   _tjm_year             (int)
 *   _tjm_publication_type (regular|special|dossier|continuous)
 *   _tjm_issue_published  (bool) — true once made public
 *   _tjm_issue_articles   (array<int>) — submission IDs assigned to this issue
 *
 * Submissions track their issue via meta `_tjm_issue_id`.
 */
final class IssueManager
{
    public const TYPE_REGULAR    = 'regular';
    public const TYPE_SPECIAL    = 'special';
    public const TYPE_DOSSIER    = 'dossier';
    public const TYPE_CONTINUOUS = 'continuous';

    public const ALL_TYPES = [
        self::TYPE_REGULAR,
        self::TYPE_SPECIAL,
        self::TYPE_DOSSIER,
        self::TYPE_CONTINUOUS,
    ];

    /**
     * @param array{journal_id:int, title:string, volume?:string, number?:string, year?:int, type?:string} $data
     */
    public static function create(array $data, int $author_id): int
    {
        if (empty($data['journal_id']) || empty($data['title'])) {
            return 0;
        }

        $post_id = wp_insert_post([
            'post_type'   => Config::CPT_ISSUE,
            'post_status' => 'draft',
            'post_author' => $author_id,
            'post_title'  => sanitize_text_field((string) $data['title']),
        ], true);

        if (is_wp_error($post_id) || (int) $post_id <= 0) {
            return 0;
        }

        update_post_meta($post_id, Config::META_PREFIX . 'journal_id', (int) $data['journal_id']);
        update_post_meta($post_id, Config::META_PREFIX . 'volume', sanitize_text_field((string) ($data['volume'] ?? '')));
        update_post_meta($post_id, Config::META_PREFIX . 'number', sanitize_text_field((string) ($data['number'] ?? '')));
        update_post_meta($post_id, Config::META_PREFIX . 'year',   (int) ($data['year'] ?? (int) gmdate('Y')));
        $type = (string) ($data['type'] ?? self::TYPE_REGULAR);
        update_post_meta($post_id, Config::META_PREFIX . 'publication_type', in_array($type, self::ALL_TYPES, true) ? $type : self::TYPE_REGULAR);
        update_post_meta($post_id, Config::META_PREFIX . 'issue_published', false);
        update_post_meta($post_id, Config::META_PREFIX . 'issue_articles', []);

        return (int) $post_id;
    }

    /**
     * Assign a submission to an issue. Reciprocal meta on both sides.
     */
    public static function assign_article(int $issue_id, int $submission_id): bool
    {
        if (get_post_type($issue_id) !== Config::CPT_ISSUE
            || get_post_type($submission_id) !== Config::CPT_SUBMISSION) {
            return false;
        }

        $articles = self::get_article_ids($issue_id);
        if (! in_array($submission_id, $articles, true)) {
            $articles[] = $submission_id;
            update_post_meta($issue_id, Config::META_PREFIX . 'issue_articles', $articles);
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'issue_id', $issue_id);
        return true;
    }

    public static function unassign_article(int $issue_id, int $submission_id): bool
    {
        $articles = self::get_article_ids($issue_id);
        $next = array_values(array_filter($articles, fn($a) => (int) $a !== $submission_id));
        update_post_meta($issue_id, Config::META_PREFIX . 'issue_articles', $next);

        if ((int) get_post_meta($submission_id, Config::META_PREFIX . 'issue_id', true) === $issue_id) {
            delete_post_meta($submission_id, Config::META_PREFIX . 'issue_id');
        }
        return true;
    }

    /**
     * @return int[]
     */
    public static function get_article_ids(int $issue_id): array
    {
        $a = get_post_meta($issue_id, Config::META_PREFIX . 'issue_articles', true);
        return is_array($a) ? array_values(array_map('intval', $a)) : [];
    }

    public static function publish_issue(int $issue_id, int $user_id): bool
    {
        $post = get_post($issue_id);
        if (! $post || $post->post_type !== Config::CPT_ISSUE) {
            return false;
        }

        wp_update_post(['ID' => $issue_id, 'post_status' => 'publish']);
        update_post_meta($issue_id, Config::META_PREFIX . 'issue_published', true);
        update_post_meta($issue_id, Config::META_PREFIX . 'published_at', current_time('mysql'));
        update_post_meta($issue_id, Config::META_PREFIX . 'published_by', $user_id);

        do_action('tjm_issue_published', $issue_id, $user_id);
        return true;
    }

    /**
     * @return \WP_Post[]
     */
    public static function get_issues_for_journal(int $journal_id): array
    {
        if ($journal_id <= 0) {
            return [];
        }
        $q = new \WP_Query([
            'post_type'      => Config::CPT_ISSUE,
            'posts_per_page' => -1,
            'post_status'    => ['draft', 'publish'],
            'orderby'        => 'meta_value_num',
            'meta_key'       => Config::META_PREFIX . 'year',
            'order'          => 'DESC',
            'meta_query'     => [
                ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id],
            ],
        ]);
        return $q->posts;
    }
}
