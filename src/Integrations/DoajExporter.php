<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * DOAJ article export.
 *
 * Builds the JSON payload accepted by the DOAJ Articles API
 * (https://doaj.org/api/docs#tag/Articles). Editors can either upload
 * the JSON manually at https://doaj.org/publisher/uploadFile or send
 * via the API by setting `tjm_doaj_api_key` and calling `submit()`.
 */
final class DoajExporter
{
    public const OPT_API_KEY = 'tjm_doaj_api_key';

    /**
     * @return array<string, mixed>
     */
    public static function build_article(int $submission_id): array
    {
        $post = get_post($submission_id);
        if (! $post) {
            return [];
        }

        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        $issue_id   = (int) get_post_meta($submission_id, Config::META_PREFIX . 'issue_id', true);
        $doi        = DoiService::normalize((string) get_post_meta($submission_id, Config::META_PREFIX . 'doi', true));
        $abstract   = (string) $post->post_content;
        $language   = (string) (get_post_meta($submission_id, Config::META_PREFIX . Config::META_LANGUAGE, true) ?: 'en');
        $keywords   = (array) get_post_meta($submission_id, Config::META_PREFIX . Config::META_KEYWORDS, true);
        $license    = (string) (get_post_meta($submission_id, Config::META_PREFIX . 'license', true) ?: 'CC BY 4.0');

        $authors = [];
        foreach (AnonymizationService::collect_authors($submission_id) as $a) {
            $authors[] = [
                'name'        => (string) ($a['name'] ?? ''),
                'affiliation' => (string) ($a['affiliation'] ?? ''),
                'orcid_id'    => ! empty($a['orcid']) ? OrcidService::format((string) $a['orcid']) : '',
            ];
        }

        $links = [];
        foreach (GalleyService::get_galleys_with_urls($submission_id) as $g) {
            $type = (string) ($g['format'] ?? 'fulltext');
            $links[] = [
                'type'         => $type === 'pdf' ? 'fulltext' : $type,
                'content_type' => (string) ($g['mime'] ?? 'application/octet-stream'),
                'url'          => (string) ($g['url'] ?? ''),
            ];
        }

        $journal = [
            'title'    => $journal_id ? (string) get_the_title($journal_id) : '',
            'language' => [$language],
            'license'  => [['type' => $license, 'open_access' => true]],
        ];
        $issn = (string) get_post_meta($journal_id, Config::META_PREFIX . 'issn', true);
        if ($issn !== '') $journal['issns'] = [$issn];

        if ($issue_id > 0) {
            $vol = (string) get_post_meta($issue_id, Config::META_PREFIX . 'volume', true);
            $num = (string) get_post_meta($issue_id, Config::META_PREFIX . 'number', true);
            if ($vol !== '') $journal['volume'] = $vol;
            if ($num !== '') $journal['number'] = $num;
        }

        $year = $issue_id ? (int) get_post_meta($issue_id, Config::META_PREFIX . 'year', true) : (int) gmdate('Y');

        $bibjson = [
            'title'      => (string) $post->post_title,
            'identifier' => $doi !== '' ? [['type' => 'doi', 'id' => $doi]] : [],
            'author'     => $authors,
            'abstract'   => $abstract,
            'keywords'   => array_values(array_filter(array_map('strval', $keywords))),
            'year'       => (string) $year,
            'journal'    => $journal,
            'link'       => $links,
        ];

        return ['bibjson' => $bibjson];
    }

    public static function export_article(int $submission_id): string
    {
        $payload = self::build_article($submission_id);
        return wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @return array{ok:bool, status:int, body:string, error?:string}
     */
    public static function submit(int $submission_id): array
    {
        $api_key = (string) get_option(self::OPT_API_KEY, '');
        if ($api_key === '') {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'DOAJ API key not configured.'];
        }
        $payload = self::build_article($submission_id);
        if (empty($payload)) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not build payload.'];
        }

        $resp = wp_remote_post('https://doaj.org/api/articles?api_key=' . rawurlencode($api_key), [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $resp->get_error_message()];
        }
        $status = (int) wp_remote_retrieve_response_code($resp);
        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => (string) wp_remote_retrieve_body($resp),
        ];
    }
}
