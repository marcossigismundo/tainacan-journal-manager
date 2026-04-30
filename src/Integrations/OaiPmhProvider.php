<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * OAI-PMH 2.0 endpoint.
 *
 * URL: home_url('/?tjm_oai=1') — the `init` listener catches it before
 * WordPress 404-handles. Supports the six verbs of OAI-PMH 2.0.
 *
 * Metadata formats:
 *   - oai_dc (Dublin Core, mandatory)
 *
 * Sets are mapped to journals (`journal:{post_id}`).
 *
 * @link https://www.openarchives.org/OAI/openarchivesprotocol.html
 */
final class OaiPmhProvider
{
    public function register(): void
    {
        add_action('init', [$this, 'maybe_handle']);
    }

    public function maybe_handle(): void
    {
        if (! isset($_GET['tjm_oai'])) {
            return;
        }
        self::handle_request();
    }

    public static function handle_request(): void
    {
        $verb = isset($_GET['verb']) ? (string) $_GET['verb'] : '';
        $valid = ['Identify', 'ListMetadataFormats', 'ListSets', 'ListIdentifiers', 'ListRecords', 'GetRecord'];

        nocache_headers();
        header('Content-Type: text/xml; charset=UTF-8');

        if (! in_array($verb, $valid, true)) {
            self::respond_error('badVerb', 'Illegal OAI verb');
            exit;
        }

        switch ($verb) {
            case 'Identify':
                self::respond_identify();
                break;
            case 'ListMetadataFormats':
                self::respond_list_metadata_formats();
                break;
            case 'ListSets':
                self::respond_list_sets();
                break;
            case 'ListIdentifiers':
                self::respond_list(true);
                break;
            case 'ListRecords':
                self::respond_list(false);
                break;
            case 'GetRecord':
                self::respond_get_record();
                break;
        }
        exit;
    }

    private static function endpoint_url(): string
    {
        return home_url('/?tjm_oai=1');
    }

    private static function envelope_open(string $request_attrs): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">'
            . '<responseDate>' . $now . '</responseDate>'
            . '<request ' . $request_attrs . '>' . self::esc(self::endpoint_url()) . '</request>';
    }

    private static function envelope_close(): string
    {
        return '</OAI-PMH>';
    }

    private static function respond_error(string $code, string $message): void
    {
        echo self::envelope_open('verb="' . self::esc((string) ($_GET['verb'] ?? '')) . '"');
        echo '<error code="' . self::esc($code) . '">' . self::esc($message) . '</error>';
        echo self::envelope_close();
    }

    private static function respond_identify(): void
    {
        $earliest = self::earliest_datestamp();
        echo self::envelope_open('verb="Identify"');
        echo '<Identify>'
            . '<repositoryName>' . self::esc((string) get_bloginfo('name')) . '</repositoryName>'
            . '<baseURL>' . self::esc(self::endpoint_url()) . '</baseURL>'
            . '<protocolVersion>2.0</protocolVersion>'
            . '<adminEmail>' . self::esc((string) get_option('admin_email', '')) . '</adminEmail>'
            . '<earliestDatestamp>' . self::esc($earliest) . '</earliestDatestamp>'
            . '<deletedRecord>no</deletedRecord>'
            . '<granularity>YYYY-MM-DDThh:mm:ssZ</granularity>'
            . '</Identify>';
        echo self::envelope_close();
    }

    private static function respond_list_metadata_formats(): void
    {
        echo self::envelope_open('verb="ListMetadataFormats"');
        echo '<ListMetadataFormats>'
            . '<metadataFormat>'
            . '<metadataPrefix>oai_dc</metadataPrefix>'
            . '<schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>'
            . '<metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>'
            . '</metadataFormat>'
            . '</ListMetadataFormats>';
        echo self::envelope_close();
    }

    private static function respond_list_sets(): void
    {
        echo self::envelope_open('verb="ListSets"');
        echo '<ListSets>';
        $journals = get_posts([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        foreach ($journals as $j) {
            echo '<set>'
                . '<setSpec>journal:' . (int) $j->ID . '</setSpec>'
                . '<setName>' . self::esc((string) $j->post_title) . '</setName>'
                . '</set>';
        }
        echo '</ListSets>';
        echo self::envelope_close();
    }

    private static function respond_list(bool $identifiers_only): void
    {
        $prefix = isset($_GET['metadataPrefix']) ? (string) $_GET['metadataPrefix'] : '';
        if ($prefix !== 'oai_dc') {
            self::respond_error('cannotDisseminateFormat', 'Only oai_dc is supported.');
            return;
        }

        $set  = isset($_GET['set']) ? (string) $_GET['set'] : '';
        $from = isset($_GET['from']) ? (string) $_GET['from'] : '';
        $until = isset($_GET['until']) ? (string) $_GET['until'] : '';

        $args = [
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 100,
            'post_status'    => 'any',
            'meta_query'     => [
                ['key' => Config::META_PREFIX . 'status', 'value' => Config::STATUS_PUBLISHED],
            ],
            'orderby'        => 'modified',
            'order'          => 'ASC',
        ];

        if (preg_match('/^journal:(\d+)$/', $set, $m)) {
            $args['meta_query'][] = ['key' => Config::META_PREFIX . 'journal_id', 'value' => $m[1]];
        }

        if ($from !== '' || $until !== '') {
            $date_q = [];
            if ($from !== '')  $date_q['after']  = $from;
            if ($until !== '') $date_q['before'] = $until;
            $date_q['inclusive'] = true;
            $args['date_query'] = [$date_q];
        }

        $q = new \WP_Query($args);

        echo self::envelope_open('verb="' . ($identifiers_only ? 'ListIdentifiers' : 'ListRecords') . '" metadataPrefix="oai_dc"');
        echo $identifiers_only ? '<ListIdentifiers>' : '<ListRecords>';

        if (empty($q->posts)) {
            echo $identifiers_only ? '</ListIdentifiers>' : '</ListRecords>';
            echo self::envelope_close();
            return;
        }

        foreach ($q->posts as $p) {
            if ($identifiers_only) {
                echo self::header_xml($p);
            } else {
                echo '<record>' . self::header_xml($p) . self::metadata_xml($p) . '</record>';
            }
        }

        echo $identifiers_only ? '</ListIdentifiers>' : '</ListRecords>';
        echo self::envelope_close();
    }

    private static function respond_get_record(): void
    {
        $prefix = isset($_GET['metadataPrefix']) ? (string) $_GET['metadataPrefix'] : '';
        if ($prefix !== 'oai_dc') {
            self::respond_error('cannotDisseminateFormat', 'Only oai_dc is supported.');
            return;
        }
        $id = isset($_GET['identifier']) ? (string) $_GET['identifier'] : '';
        if (! preg_match('/^oai:.+:(\d+)$/', $id, $m)) {
            self::respond_error('idDoesNotExist', 'Invalid identifier.');
            return;
        }
        $post = get_post((int) $m[1]);
        if (! $post || $post->post_type !== Config::CPT_SUBMISSION
            || (string) get_post_meta($post->ID, Config::META_PREFIX . 'status', true) !== Config::STATUS_PUBLISHED) {
            self::respond_error('idDoesNotExist', 'Record not found or not published.');
            return;
        }

        echo self::envelope_open('verb="GetRecord" identifier="' . self::esc($id) . '" metadataPrefix="oai_dc"');
        echo '<GetRecord><record>' . self::header_xml($post) . self::metadata_xml($post) . '</record></GetRecord>';
        echo self::envelope_close();
    }

    private static function header_xml(\WP_Post $p): string
    {
        $journal_id = (int) get_post_meta($p->ID, Config::META_PREFIX . 'journal_id', true);
        $set = $journal_id ? '<setSpec>journal:' . $journal_id . '</setSpec>' : '';
        return '<header>'
            . '<identifier>' . self::esc(self::oai_identifier($p->ID)) . '</identifier>'
            . '<datestamp>' . self::esc(gmdate('Y-m-d\TH:i:s\Z', strtotime($p->post_modified_gmt))) . '</datestamp>'
            . $set
            . '</header>';
    }

    private static function metadata_xml(\WP_Post $p): string
    {
        $authors = AnonymizationService::collect_authors($p->ID);
        $author_xml = '';
        foreach ($authors as $a) {
            if (empty($a['name'])) continue;
            $author_xml .= '<dc:creator>' . self::esc((string) $a['name']) . '</dc:creator>';
        }

        $keywords = (array) get_post_meta($p->ID, Config::META_PREFIX . Config::META_KEYWORDS, true);
        $kw_xml = '';
        foreach ($keywords as $k) {
            $kw_xml .= '<dc:subject>' . self::esc((string) $k) . '</dc:subject>';
        }

        $doi = DoiService::format_url((string) get_post_meta($p->ID, Config::META_PREFIX . 'doi', true));
        $journal_id = (int) get_post_meta($p->ID, Config::META_PREFIX . 'journal_id', true);
        $journal_title = $journal_id ? (string) get_the_title($journal_id) : '';
        $language = (string) (get_post_meta($p->ID, Config::META_PREFIX . Config::META_LANGUAGE, true) ?: 'en');
        $published = (string) (get_post_meta($p->ID, Config::META_PREFIX . 'published_at', true) ?: $p->post_date_gmt);
        $resource = (string) get_permalink($p->ID) ?: home_url('/?p=' . $p->ID);

        return '<metadata>'
            . '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">'
            . '<dc:title>' . self::esc((string) $p->post_title) . '</dc:title>'
            . $author_xml
            . '<dc:description>' . self::esc((string) $p->post_content) . '</dc:description>'
            . $kw_xml
            . '<dc:publisher>' . self::esc($journal_title) . '</dc:publisher>'
            . '<dc:date>' . self::esc(gmdate('Y-m-d', strtotime($published))) . '</dc:date>'
            . '<dc:type>article</dc:type>'
            . '<dc:format>text/html</dc:format>'
            . '<dc:identifier>' . self::esc($resource) . '</dc:identifier>'
            . ($doi !== '' ? '<dc:identifier>' . self::esc($doi) . '</dc:identifier>' : '')
            . '<dc:language>' . self::esc($language) . '</dc:language>'
            . '</oai_dc:dc>'
            . '</metadata>';
    }

    private static function oai_identifier(int $post_id): string
    {
        $host = (string) (parse_url(home_url(), PHP_URL_HOST) ?: 'localhost');
        return 'oai:' . $host . ':' . $post_id;
    }

    private static function earliest_datestamp(): string
    {
        global $wpdb;
        $earliest = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(post_date_gmt) FROM {$wpdb->posts} WHERE post_type = %s",
            Config::CPT_SUBMISSION
        ));
        return $earliest ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $earliest)) : gmdate('Y-m-d\TH:i:s\Z');
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
