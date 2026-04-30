<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * Builds the Crossref deposit XML for a journal article.
 *
 * Schema: Crossref schema 5.3.1 (https://data.crossref.org/schemas/).
 * The XML is intentionally compact — only fields supported by the editorial
 * workflow are emitted. Editors can edit the resulting XML before depositing
 * if they need to add elements not modelled here (funder, license, abstract
 * in second language, etc.).
 *
 * Deposits are sent via `CrossrefDeposit::submit()` separately so the XML
 * can be reviewed first.
 */
final class CrossrefExporter
{
    public const OPT_DEPOSITOR_NAME  = 'tjm_crossref_depositor_name';
    public const OPT_DEPOSITOR_EMAIL = 'tjm_crossref_depositor_email';
    public const OPT_REGISTRANT      = 'tjm_crossref_registrant';

    public static function export_article(int $submission_id): string
    {
        $post = get_post($submission_id);
        if (! $post) {
            return '';
        }

        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        $issue_id   = (int) get_post_meta($submission_id, Config::META_PREFIX . 'issue_id', true);
        $doi        = DoiService::normalize((string) get_post_meta($submission_id, Config::META_PREFIX . 'doi', true));
        $authors    = AnonymizationService::collect_authors($submission_id);
        $abstract   = (string) $post->post_content;
        $language   = (string) (get_post_meta($submission_id, Config::META_PREFIX . Config::META_LANGUAGE, true) ?: 'en');

        $journal_title = $journal_id ? (string) get_the_title($journal_id) : '';
        $issn  = (string) get_post_meta($journal_id, Config::META_PREFIX . 'issn', true);
        $eissn = (string) get_post_meta($journal_id, Config::META_PREFIX . 'eissn', true);

        $vol  = $issue_id ? (string) get_post_meta($issue_id, Config::META_PREFIX . 'volume', true) : '';
        $num  = $issue_id ? (string) get_post_meta($issue_id, Config::META_PREFIX . 'number', true) : '';
        $year = $issue_id ? (int) get_post_meta($issue_id, Config::META_PREFIX . 'year', true) : (int) gmdate('Y');

        $depositor_name  = (string) get_option(self::OPT_DEPOSITOR_NAME, get_bloginfo('name'));
        $depositor_email = (string) get_option(self::OPT_DEPOSITOR_EMAIL, get_option('admin_email', ''));
        $registrant      = (string) get_option(self::OPT_REGISTRANT, $journal_title);

        $batch_id = sprintf('tjm-%d-%d', $submission_id, time());
        $now      = gmdate('YmdHis');
        $resource = (string) get_permalink($submission_id) ?: home_url('/?p=' . $submission_id);

        $authors_xml = '';
        foreach ($authors as $i => $a) {
            $name = trim((string) ($a['name'] ?? ''));
            if ($name === '') continue;
            $parts = explode(' ', $name);
            $given = trim((string) array_shift($parts));
            $surname = trim(implode(' ', $parts)) ?: $given;
            $sequence = $i === 0 ? 'first' : 'additional';
            $orcid_url = ! empty($a['orcid']) ? OrcidService::url((string) $a['orcid']) : '';
            $authors_xml .= '<person_name sequence="' . $sequence . '" contributor_role="author">'
                . '<given_name>' . self::esc($given) . '</given_name>'
                . '<surname>' . self::esc($surname) . '</surname>'
                . (! empty($a['affiliation']) ? '<affiliation>' . self::esc((string) $a['affiliation']) . '</affiliation>' : '')
                . ($orcid_url ? '<ORCID>' . self::esc($orcid_url) . '</ORCID>' : '')
                . '</person_name>';
        }

        $issn_xml  = $issn  ? '<issn media_type="print">' . self::esc($issn) . '</issn>' : '';
        $eissn_xml = $eissn ? '<issn media_type="electronic">' . self::esc($eissn) . '</issn>' : '';

        $abstract_xml = '';
        if ($abstract !== '') {
            $abstract_xml = '<jats:abstract xmlns:jats="http://www.ncbi.nlm.nih.gov/JATS1">'
                . '<jats:p>' . self::esc($abstract) . '</jats:p></jats:abstract>';
        }

        $doi_xml = $doi ? sprintf(
            '<doi_data><doi>%s</doi><resource>%s</resource></doi_data>',
            self::esc($doi),
            self::esc($resource)
        ) : '';

        $title_safe   = self::esc((string) $post->post_title);
        $journal_safe = self::esc($journal_title);
        $depo_name_s  = self::esc($depositor_name);
        $depo_email_s = self::esc($depositor_email);
        $registrant_s = self::esc($registrant);
        $vol_s        = self::esc($vol);
        $num_s        = self::esc($num);
        $lang_s       = self::esc($language);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<doi_batch version="5.3.1"
           xmlns="http://www.crossref.org/schema/5.3.1"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://www.crossref.org/schema/5.3.1 https://data.crossref.org/schemas/crossref5.3.1.xsd">
  <head>
    <doi_batch_id>{$batch_id}</doi_batch_id>
    <timestamp>{$now}</timestamp>
    <depositor>
      <depositor_name>{$depo_name_s}</depositor_name>
      <email_address>{$depo_email_s}</email_address>
    </depositor>
    <registrant>{$registrant_s}</registrant>
  </head>
  <body>
    <journal>
      <journal_metadata language="{$lang_s}">
        <full_title>{$journal_safe}</full_title>
        {$issn_xml}
        {$eissn_xml}
      </journal_metadata>
      <journal_issue>
        <publication_date media_type="online"><year>{$year}</year></publication_date>
        <journal_volume><volume>{$vol_s}</volume></journal_volume>
        <issue>{$num_s}</issue>
      </journal_issue>
      <journal_article publication_type="full_text">
        <titles><title>{$title_safe}</title></titles>
        <contributors>{$authors_xml}</contributors>
        {$abstract_xml}
        <publication_date media_type="online"><year>{$year}</year></publication_date>
        {$doi_xml}
      </journal_article>
    </journal>
  </body>
</doi_batch>
XML;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
