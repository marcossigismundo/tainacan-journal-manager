<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Indicators\StatsService;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Shortcode [tjm_indicators] — Public editorial indicators dashboard.
 *
 * Renders Chart.js charts populated via AJAX. The shortcode itself only
 * outputs the page skeleton (cards + canvas placeholders); JS fetches data
 * from `tjm_indicators_data`.
 *
 * Optional shortcode attribute:
 *   [tjm_indicators journal=N]   → scope to a single journal
 */
final class IndicatorsDashboard
{
    public function register(): void
    {
        add_shortcode('tjm_indicators', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_script(
            'tjm-chartjs',
            TJM_URL . 'assets/js/vendor/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
        wp_register_script(
            'tjm-indicators',
            TJM_URL . 'assets/js/indicators.js',
            ['jquery', 'tjm-frontend', 'tjm-chartjs'],
            TJM_VERSION,
            true
        );
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function render($atts = []): string
    {
        $atts = is_array($atts) ? $atts : [];
        $journal_id = isset($atts['journal']) ? (int) $atts['journal'] : 0;

        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');
        wp_enqueue_script('tjm-chartjs');
        wp_enqueue_script('tjm-indicators');

        $uid = is_user_logged_in() ? get_current_user_id() : 0;
        $can_export = $uid && (PluginRole::is_editor($uid) || PluginRole::is_admin_institutional($uid));

        ob_start();
        include TJM_PATH . 'templates/frontend/indicators.php';
        return ob_get_clean() ?: '';
    }
}
