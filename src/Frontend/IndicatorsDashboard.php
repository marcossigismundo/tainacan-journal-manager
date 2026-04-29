<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;

/**
 * Shortcode [tjm_indicators] — Public editorial indicators dashboard.
 * Submissions, acceptance rate, processing times, top reviewers, etc.
 *
 * Stub: full Chart.js implementation in Phase 2.
 */
final class IndicatorsDashboard
{
    public function register(): void
    {
        add_shortcode('tjm_indicators', [$this, 'render']);
    }

    public function render(): string
    {
        wp_enqueue_style('tjm-frontend');

        $stats = $this->compute_stats();

        ob_start();
        include TJM_PATH . 'templates/frontend/indicators.php';
        return ob_get_clean() ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    private function compute_stats(): array
    {
        $cached = get_transient('tjm_indicators_stats');
        if ($cached !== false) {
            return $cached;
        }

        $stats = [
            'total_submissions' => $this->count_submissions(),
            'total_published'   => $this->count_published(),
            'total_journals'    => $this->count_journals(),
        ];

        set_transient('tjm_indicators_stats', $stats, 15 * MINUTE_IN_SECONDS);
        return $stats;
    }

    private function count_submissions(): int
    {
        return (new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ]))->found_posts;
    }

    private function count_published(): int
    {
        return (new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
            'meta_query'     => [
                ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_PUBLISHED],
            ],
        ]))->found_posts;
    }

    private function count_journals(): int
    {
        return (new \WP_Query([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ]))->found_posts;
    }
}
