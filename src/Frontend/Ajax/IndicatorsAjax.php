<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Indicators\StatsService;
use TainacanJournalManager\Roles\PluginRole;

/**
 * AJAX endpoints for indicators dashboard.
 *
 *  - tjm_indicators_data    (logged-in or public, depends on dashboard policy)
 *  - tjm_indicators_export  (CSV download for editors / admins)
 *  - tjm_indicators_invalidate (admins only — refresh cache)
 *
 * The indicators dashboard is public per CLAUDE.md, so the data endpoint
 * does not require auth. Export and invalidate require an editor role.
 */
final class IndicatorsAjax
{
    public function register(): void
    {
        add_action('wp_ajax_tjm_indicators_data',       [$this, 'data']);
        add_action('wp_ajax_nopriv_tjm_indicators_data', [$this, 'data']);
        add_action('wp_ajax_tjm_indicators_export',     [$this, 'export_csv']);
        add_action('wp_ajax_tjm_indicators_invalidate', [$this, 'invalidate']);
    }

    public function data(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');

        $journal_id = isset($_POST['journal_id']) ? (int) $_POST['journal_id'] : 0;
        $overview = StatsService::get_overview($journal_id ?: null);
        wp_send_json_success($overview);
    }

    public function export_csv(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }

        $uid = get_current_user_id();
        if (! PluginRole::is_editor($uid) && ! PluginRole::is_admin_institutional($uid)) {
            wp_send_json_error(__('Editor role required to export.', 'tainacan-journal-manager'), 403);
        }

        $journal_id = isset($_POST['journal_id']) ? (int) $_POST['journal_id'] : 0;
        $overview = StatsService::get_overview($journal_id ?: null);
        $csv = StatsService::overview_to_csv($overview);

        // Stream as a CSV download.
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tjm-indicators-' . gmdate('Ymd-His') . '.csv"');
        // UTF-8 BOM so Excel reads accents correctly
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    public function invalidate(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        if (! PluginRole::is_admin_institutional(get_current_user_id())) {
            wp_send_json_error(__('Admin only.', 'tainacan-journal-manager'), 403);
        }
        StatsService::invalidate_cache();
        wp_send_json_success();
    }
}
