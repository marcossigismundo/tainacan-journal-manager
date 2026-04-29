<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;

/**
 * Shortcode [tjm_journal id=N] — Public journal homepage.
 * About, scope, editorial team, latest issues, articles.
 */
final class PublicJournal
{
    public function register(): void
    {
        add_shortcode('tjm_journal', [$this, 'render']);
    }

    public function render(array $atts = []): string
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'tjm_journal');
        $journal_id = (int) $atts['id'];

        if (! $journal_id) {
            return '<div class="tjm-notice">' . esc_html__('Journal ID is required.', 'tainacan-journal-manager') . '</div>';
        }

        $journal = get_post($journal_id);
        if (! $journal || $journal->post_type !== Config::CPT_JOURNAL) {
            return '<div class="tjm-notice tjm-notice--error">' . esc_html__('Journal not found.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');

        ob_start();
        include TJM_PATH . 'templates/frontend/public-journal.php';
        return ob_get_clean() ?: '';
    }
}
