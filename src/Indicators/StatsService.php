<?php

declare(strict_types=1);

namespace TainacanJournalManager\Indicators;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Aggregates editorial metrics for the indicators dashboard.
 *
 * All queries are cached via transients (default 15 min) to avoid
 * heavy COUNTs on every page view. Cache invalidates when submissions
 * change status (`tjm_status_transition`) or reviews are submitted.
 */
final class StatsService
{
    private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    /**
     * @return array{
     *   total: array<string,int>,
     *   submissions_per_status: array<string,int>,
     *   submissions_per_journal: array<int, array{name:string, count:int}>,
     *   submissions_per_month: array<string,int>,
     *   top_reviewers: array<int, array{name:string, count:int}>,
     *   top_journals_published: array<int, array{name:string, count:int}>,
     *   acceptance_rate: array{accepted:int, rejected:int, rate:float}
     * }
     */
    public static function get_overview(?int $journal_id = null): array
    {
        $cache_key = 'tjm_stats_overview_' . ($journal_id ?: 'all');
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $data = [
            'total'                   => self::collect_totals($journal_id),
            'submissions_per_status'  => self::submissions_per_status($journal_id),
            'submissions_per_journal' => $journal_id ? [] : self::submissions_per_journal(),
            'submissions_per_month'   => self::submissions_per_month($journal_id),
            'top_reviewers'           => self::top_reviewers(10, $journal_id),
            'top_journals_published'  => $journal_id ? [] : self::top_journals_published(10),
            'acceptance_rate'         => self::acceptance_rate($journal_id),
        ];

        set_transient($cache_key, $data, self::CACHE_TTL);
        return $data;
    }

    public static function invalidate_cache(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tjm_stats_%' OR option_name LIKE '_transient_timeout_tjm_stats_%'");
    }

    /**
     * @return array{submissions:int, reviews:int, journals:int, issues:int, published:int}
     */
    private static function collect_totals(?int $journal_id): array
    {
        $sub_args = [
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ];
        if ($journal_id) {
            $sub_args['meta_query'] = [
                ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id],
            ];
        }
        $submissions = (new \WP_Query($sub_args))->found_posts;

        $pub_args = $sub_args;
        $pub_args['meta_query'] = array_merge($sub_args['meta_query'] ?? [], [
            ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_PUBLISHED],
        ]);
        $published = (new \WP_Query($pub_args))->found_posts;

        $reviews = (new \WP_Query([
            'post_type'      => Config::CPT_REVIEW,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ]))->found_posts;

        $journals = (new \WP_Query([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ]))->found_posts;

        $issues = (new \WP_Query([
            'post_type'      => Config::CPT_ISSUE,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ]))->found_posts;

        return [
            'submissions' => (int) $submissions,
            'reviews'     => (int) $reviews,
            'journals'    => (int) $journals,
            'issues'      => (int) $issues,
            'published'   => (int) $published,
        ];
    }

    /**
     * @return array<string,int>
     */
    private static function submissions_per_status(?int $journal_id): array
    {
        $out = [];
        foreach (array_keys(Config::SUBMISSION_STATUSES) as $status) {
            $args = [
                'post_type'      => Config::CPT_SUBMISSION,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_status'    => 'any',
                'meta_query'     => [
                    ['key' => Config::META_PREFIX . 'status', 'value' => $status],
                ],
            ];
            if ($journal_id) {
                $args['meta_query'][] = ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id];
            }
            $out[$status] = (int) (new \WP_Query($args))->found_posts;
        }
        return $out;
    }

    /**
     * @return array<int, array{name:string, count:int}>
     */
    private static function submissions_per_journal(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS journal_id, COUNT(*) AS cnt
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_type = %s
             GROUP BY pm.meta_value
             ORDER BY cnt DESC",
            Config::META_PREFIX . 'journal_id',
            Config::CPT_SUBMISSION
        ), ARRAY_A);

        $out = [];
        foreach ((array) $rows as $r) {
            $jid = (int) ($r['journal_id'] ?? 0);
            if ($jid <= 0) continue;
            $out[$jid] = [
                'name'  => (string) (get_the_title($jid) ?: '#' . $jid),
                'count' => (int) ($r['cnt'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array<string,int> 'YYYY-MM' => count, last 12 months.
     */
    private static function submissions_per_month(?int $journal_id): array
    {
        global $wpdb;

        $where_journal = '';
        $args = [Config::CPT_SUBMISSION];
        if ($journal_id) {
            $where_journal = "AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm
                 WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s)";
            $args[] = Config::META_PREFIX . 'journal_id';
            $args[] = (string) $journal_id;
        }

        $sql = "SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') AS ym, COUNT(*) AS cnt
                FROM {$wpdb->posts} p
                WHERE p.post_type = %s
                  AND p.post_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                  $where_journal
                GROUP BY ym
                ORDER BY ym ASC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

        $out = [];
        foreach ((array) $rows as $r) {
            $out[(string) $r['ym']] = (int) $r['cnt'];
        }
        // Fill missing months with zero
        $filled = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = gmdate('Y-m', strtotime("-{$i} months"));
            $filled[$key] = $out[$key] ?? 0;
        }
        return $filled;
    }

    /**
     * @return array<int, array{name:string, count:int}>
     */
    private static function top_reviewers(int $limit, ?int $journal_id): array
    {
        $reviewers = PluginRole::get_users_by_role(PluginRole::REVIEWER);
        $loads = [];
        foreach ($reviewers as $u) {
            $args = [
                'post_type'      => Config::CPT_REVIEW,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'author'         => $u->ID,
                'meta_query'     => [
                    ['key' => Config::META_PREFIX . 'review_status', 'value' => Config::REVIEW_SUBMITTED],
                ],
            ];
            if ($journal_id) {
                // Filter via submission's journal_id is more involved — best-effort: skip when scoping to one journal
            }
            $count = (int) (new \WP_Query($args))->found_posts;
            if ($count > 0) {
                $loads[(int) $u->ID] = [
                    'name'  => (string) ($u->display_name ?: $u->user_login),
                    'count' => $count,
                ];
            }
        }
        uasort($loads, fn($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($loads, 0, $limit, true);
    }

    /**
     * @return array<int, array{name:string, count:int}>
     */
    private static function top_journals_published(int $limit): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT j.meta_value AS journal_id, COUNT(*) AS cnt
             FROM {$wpdb->postmeta} j
             INNER JOIN {$wpdb->postmeta} s ON s.post_id = j.post_id AND s.meta_key = %s AND s.meta_value = %s
             INNER JOIN {$wpdb->posts} p   ON p.ID = j.post_id AND p.post_type = %s
             WHERE j.meta_key = %s
             GROUP BY j.meta_value
             ORDER BY cnt DESC
             LIMIT %d",
            Config::META_PREFIX . 'status',
            Config::STATUS_PUBLISHED,
            Config::CPT_SUBMISSION,
            Config::META_PREFIX . 'journal_id',
            $limit
        ), ARRAY_A);

        $out = [];
        foreach ((array) $rows as $r) {
            $jid = (int) ($r['journal_id'] ?? 0);
            if ($jid <= 0) continue;
            $out[$jid] = [
                'name'  => (string) (get_the_title($jid) ?: '#' . $jid),
                'count' => (int) ($r['cnt'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array{accepted:int, rejected:int, rate:float}
     */
    private static function acceptance_rate(?int $journal_id): array
    {
        $accepted_query = [
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_PUBLISHED],
                ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_COPYEDITING],
                ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_PRODUCTION],
            ],
        ];

        $rejected_query = [
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
            'meta_query'     => [
                ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_REJECTED],
            ],
        ];

        if ($journal_id) {
            $accepted_query['meta_query'][] = ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id];
            $rejected_query['meta_query'][] = ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id];
        }

        $accepted = (int) (new \WP_Query($accepted_query))->found_posts;
        $rejected = (int) (new \WP_Query($rejected_query))->found_posts;
        $total = $accepted + $rejected;
        $rate  = $total > 0 ? round(($accepted / $total) * 100, 1) : 0.0;

        return ['accepted' => $accepted, 'rejected' => $rejected, 'rate' => $rate];
    }

    /**
     * Convert overview to a flat CSV-friendly array (UTF-8 BOM applied by caller).
     *
     * @return string CSV body without headers row.
     */
    public static function overview_to_csv(array $overview): string
    {
        $rows = [];
        $rows[] = ['Section', 'Key', 'Value'];

        foreach ((array) ($overview['total'] ?? []) as $k => $v) {
            $rows[] = ['totals', (string) $k, (string) $v];
        }
        foreach ((array) ($overview['submissions_per_status'] ?? []) as $k => $v) {
            $rows[] = ['submissions_per_status', (string) $k, (string) $v];
        }
        foreach ((array) ($overview['submissions_per_journal'] ?? []) as $entry) {
            $rows[] = ['submissions_per_journal', (string) ($entry['name'] ?? ''), (string) ($entry['count'] ?? 0)];
        }
        foreach ((array) ($overview['submissions_per_month'] ?? []) as $k => $v) {
            $rows[] = ['submissions_per_month', (string) $k, (string) $v];
        }
        foreach ((array) ($overview['top_reviewers'] ?? []) as $entry) {
            $rows[] = ['top_reviewers', (string) ($entry['name'] ?? ''), (string) ($entry['count'] ?? 0)];
        }
        foreach ((array) ($overview['top_journals_published'] ?? []) as $entry) {
            $rows[] = ['top_journals_published', (string) ($entry['name'] ?? ''), (string) ($entry['count'] ?? 0)];
        }
        $ar = (array) ($overview['acceptance_rate'] ?? []);
        $rows[] = ['acceptance_rate', 'accepted', (string) ($ar['accepted'] ?? 0)];
        $rows[] = ['acceptance_rate', 'rejected', (string) ($ar['rejected'] ?? 0)];
        $rows[] = ['acceptance_rate', 'rate_percent', (string) ($ar['rate'] ?? 0)];

        $out = '';
        foreach ($rows as $r) {
            $out .= self::csv_line($r) . "\r\n";
        }
        return $out;
    }

    /**
     * @param string[] $cells
     */
    private static function csv_line(array $cells): string
    {
        $escaped = array_map(function ($c) {
            $c = (string) $c;
            if (str_contains($c, '"') || str_contains($c, ',') || str_contains($c, "\n")) {
                return '"' . str_replace('"', '""', $c) . '"';
            }
            return $c;
        }, $cells);
        return implode(',', $escaped);
    }
}
