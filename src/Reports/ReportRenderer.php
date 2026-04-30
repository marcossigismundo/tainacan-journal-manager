<?php

declare(strict_types=1);

namespace TainacanJournalManager\Reports;

use TainacanJournalManager\Config;
use TainacanJournalManager\Indicators\StatsService;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Manager-facing executive reports.
 *
 * URL: home_url('/?tjm_report=editorial[&journal=N]') — renders a
 * standalone HTML page (no theme chrome) styled for print, ready to
 * "Save as PDF" from the browser. Avoids shipping mPDF / DomPDF in
 * vendor: print-to-PDF is built into every modern browser, supports
 * embedded charts via <canvas>, and avoids cross-platform font hassles.
 *
 * Authorization: editor or institutional admin.
 */
final class ReportRenderer
{
    public function register(): void
    {
        add_action('init', [$this, 'maybe_handle']);
    }

    public function maybe_handle(): void
    {
        if (! isset($_GET['tjm_report'])) {
            return;
        }

        if (! is_user_logged_in()) {
            wp_safe_redirect(Config::page_url(Config::PAGE_LOGIN) . '?redirect_to=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/')));
            exit;
        }

        $uid = get_current_user_id();
        if (! PluginRole::is_editor($uid) && ! PluginRole::is_admin_institutional($uid)) {
            wp_die(esc_html__('Editor role required.', 'tainacan-journal-manager'), '', ['response' => 403]);
        }

        $report = (string) $_GET['tjm_report'];
        if ($report !== 'editorial') {
            wp_die(esc_html__('Unknown report.', 'tainacan-journal-manager'), '', ['response' => 404]);
        }

        $journal_id = isset($_GET['journal']) ? (int) $_GET['journal'] : 0;
        $this->render_editorial($journal_id);
        exit;
    }

    private function render_editorial(int $journal_id): void
    {
        $stats = StatsService::get_overview($journal_id ?: null);
        $journal_name = $journal_id ? (string) get_the_title($journal_id) : '';
        $generated_at = current_time('d/m/Y H:i');
        $author_user  = get_userdata(get_current_user_id());
        $author_name  = $author_user ? ($author_user->display_name ?: $author_user->user_login) : '';
        $site_name    = (string) get_bloginfo('name');

        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');

        include TJM_PATH . 'templates/reports/editorial-report.php';
    }
}
