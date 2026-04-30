<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Config;
use TainacanJournalManager\Integrations\CrossrefDeposit;
use TainacanJournalManager\Integrations\CrossrefExporter;
use TainacanJournalManager\Integrations\DoajExporter;
use TainacanJournalManager\Integrations\DoiService;
use TainacanJournalManager\Integrations\JatsExporter;
use TainacanJournalManager\Roles\PluginRole;

/**
 * AJAX endpoints for Phase 5 integrations.
 *
 *   - tjm_export_crossref     (download Crossref XML for a submission)
 *   - tjm_export_doaj         (download DOAJ JSON)
 *   - tjm_export_jats         (download JATS XML)
 *   - tjm_doi_mint            (submit Crossref deposit for DOI minting)
 *   - tjm_doaj_submit         (submit to DOAJ API)
 *   - tjm_doi_set             (manually attach a DOI to a submission)
 *
 * All require an editor role on the journal of the submission.
 */
final class IntegrationsAjax
{
    public function register(): void
    {
        $actions = [
            'tjm_export_crossref' => 'export_crossref',
            'tjm_export_doaj'     => 'export_doaj',
            'tjm_export_jats'     => 'export_jats',
            'tjm_doi_mint'        => 'doi_mint',
            'tjm_doaj_submit'     => 'doaj_submit',
            'tjm_doi_set'         => 'doi_set',
        ];
        foreach ($actions as $hook => $method) {
            add_action('wp_ajax_' . $hook, [$this, $method]);
        }
    }

    public function export_crossref(): void
    {
        $sid = $this->ensure_editor();
        $xml = CrossrefExporter::export_article($sid);
        $this->stream_download($xml, "tjm-{$sid}-crossref.xml", 'application/xml');
    }

    public function export_doaj(): void
    {
        $sid = $this->ensure_editor();
        $json = DoajExporter::export_article($sid);
        $this->stream_download($json, "tjm-{$sid}-doaj.json", 'application/json');
    }

    public function export_jats(): void
    {
        $sid = $this->ensure_editor();
        $xml = JatsExporter::export_article($sid);
        $this->stream_download($xml, "tjm-{$sid}-jats.xml", 'application/xml');
    }

    public function doi_mint(): void
    {
        $sid = $this->ensure_editor();
        $res = CrossrefDeposit::submit($sid);
        if (! $res['ok']) {
            wp_send_json_error([
                'message' => $res['error'] ?? __('Crossref deposit failed.', 'tainacan-journal-manager'),
                'status'  => $res['status'] ?? 0,
                'body'    => $res['body'] ?? '',
            ]);
        }
        wp_send_json_success(['status' => $res['status'], 'body' => $res['body']]);
    }

    public function doaj_submit(): void
    {
        $sid = $this->ensure_editor();
        $res = DoajExporter::submit($sid);
        if (! $res['ok']) {
            wp_send_json_error([
                'message' => $res['error'] ?? __('DOAJ submission failed.', 'tainacan-journal-manager'),
                'status'  => $res['status'] ?? 0,
                'body'    => $res['body'] ?? '',
            ]);
        }
        wp_send_json_success(['status' => $res['status'], 'body' => $res['body']]);
    }

    public function doi_set(): void
    {
        $sid = $this->ensure_editor();
        $doi = isset($_POST['doi']) ? DoiService::normalize(wp_unslash((string) $_POST['doi'])) : '';
        if ($doi !== '' && ! DoiService::is_valid($doi)) {
            wp_send_json_error(__('Invalid DOI format.', 'tainacan-journal-manager'));
        }
        update_post_meta($sid, Config::META_PREFIX . 'doi', $doi);
        wp_send_json_success(['doi' => $doi, 'url' => DoiService::format_url($doi)]);
    }

    // ── helpers ──────────────────────────────────────────────────

    private function ensure_editor(): int
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        $sid = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : (isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0);
        if ($sid <= 0 || get_post_type($sid) !== Config::CPT_SUBMISSION) {
            wp_send_json_error(__('Invalid submission.', 'tainacan-journal-manager'), 404);
        }
        $journal_id = (int) get_post_meta($sid, Config::META_PREFIX . 'journal_id', true);
        $uid = get_current_user_id();
        if (! PluginRole::is_editor($uid, $journal_id) && ! PluginRole::is_admin_institutional($uid)) {
            wp_send_json_error(__('Editor role required.', 'tainacan-journal-manager'), 403);
        }
        return $sid;
    }

    private function stream_download(string $body, string $filename, string $mime): void
    {
        nocache_headers();
        header('Content-Type: ' . $mime . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $body;
        exit;
    }
}
