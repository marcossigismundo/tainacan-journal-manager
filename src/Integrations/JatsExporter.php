<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * JATS XML exporter (NISO JATS 1.2 — Journal Publishing Tag Set).
 *
 * Produces a minimal but valid <article> element suitable for archives that
 * accept JATS (PMC, SciELO, OpenAIRE, etc.). Editors can take the output
 * and post-process it (add MathML, tables, references markup) before
 * submitting to a specific archive.
 *
 * @link https://jats.nlm.nih.gov/publishing/tag-library/1.2/
 */
final class JatsExporter
{
    public static function export_article(int $submission_id): string
    {
        $post = get_post($submission_id);
        if (! $post) {
            return '';
        }

        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        $issue_id   = (int) get_post_meta($submission_id, Config::META_PREFIX . 'issue_id', true);
        $doi        = DoiService::normalize((string) get_post_meta($submission_id, Config::META_PREFIX . 'doi', true));
        $abstract   = (string) $post->post_content;
        $language   = (string) (get_post_meta($submission_id, Config::META_PREFIX . Config::META_LANGUAGE, true) ?: 'en');
        $keywords   = (array) get_post_meta($submission_id, Config::META_PREFIX . Config::META_KEYWORDS, true);
        $references = (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_REFERENCES, true);

        $journal_title = $journal_id ? (string) get_the_title($journal_id) : '';
        $issn  = (string) get_post_meta($journal_id, Config::META_PREFIX . 'issn', true);
        $eissn = (string) get_post_meta($journal_id, Config::META_PREFIX . 'eissn', true);

        $vol  = $issue_id ? (string) get_post_meta($issue_id, Config::META_PREFIX . 'volume', true) : '';
        $num  = $issue_id ? (string) get_post_meta($issue_id, Config::META_PREFIX . 'number', true) : '';
        $year = $issue_id ? (int) get_post_meta($issue_id, Config::META_PREFIX . 'year', true) : (int) gmdate('Y');

        $authors = AnonymizationService::collect_authors($submission_id);
        $contrib_xml = '<contrib-group>';
        $aff_xml = '';
        foreach ($authors as $i => $a) {
            $name = trim((string) ($a['name'] ?? ''));
            if ($name === '') continue;
            $parts = explode(' ', $name);
            $given = trim((string) array_shift($parts));
            $surname = trim(implode(' ', $parts)) ?: $given;
            $aff_id = 'aff' . ($i + 1);
            $orcid_url = ! empty($a['orcid']) ? OrcidService::url((string) $a['orcid']) : '';
            $contrib_xml .= '<contrib contrib-type="author">'
                . ($orcid_url ? '<contrib-id contrib-id-type="orcid">' . self::esc($orcid_url) . '</contrib-id>' : '')
                . '<name><surname>' . self::esc($surname) . '</surname><given-names>' . self::esc($given) . '</given-names></name>'
                . (! empty($a['affiliation']) ? '<xref ref-type="aff" rid="' . $aff_id . '"/>' : '')
                . '</contrib>';
            if (! empty($a['affiliation'])) {
                $aff_xml .= '<aff id="' . $aff_id . '">' . self::esc((string) $a['affiliation']) . '</aff>';
            }
        }
        $contrib_xml .= '</contrib-group>';

        $kw_xml = '';
        if (! empty($keywords)) {
            $kw_xml = '<kwd-group xml:lang="' . self::esc($language) . '">';
            foreach ($keywords as $k) {
                $kw_xml .= '<kwd>' . self::esc((string) $k) . '</kwd>';
            }
            $kw_xml .= '</kwd-group>';
        }

        $issn_xml  = $issn  ? '<issn pub-type="ppub">' . self::esc($issn)  . '</issn>' : '';
        $eissn_xml = $eissn ? '<issn pub-type="epub">' . self::esc($eissn) . '</issn>' : '';

        $doi_xml = $doi !== '' ? '<article-id pub-id-type="doi">' . self::esc($doi) . '</article-id>' : '';
        $vol_xml = $vol !== '' ? '<volume>' . self::esc($vol) . '</volume>' : '';
        $num_xml = $num !== '' ? '<issue>' . self::esc($num) . '</issue>' : '';
        $title_safe = self::esc((string) $post->post_title);
        $journal_safe = self::esc($journal_title);
        $abs_safe = self::esc($abstract);
        $lang = self::esc($language);

        $refs_xml = '';
        if ($references !== '') {
            $refs_xml = '<back><ref-list>';
            $i = 0;
            foreach (preg_split('/\r\n|\r|\n/', $references) as $line) {
                $line = trim((string) $line);
                if ($line === '') continue;
                $i++;
                $refs_xml .= '<ref id="ref' . $i . '"><mixed-citation>' . self::esc($line) . '</mixed-citation></ref>';
            }
            $refs_xml .= '</ref-list></back>';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.2 20190208//EN" "JATS-journalpublishing1.dtd">
<article xmlns:xlink="http://www.w3.org/1999/xlink" article-type="research-article" xml:lang="{$lang}">
  <front>
    <journal-meta>
      <journal-title-group><journal-title>{$journal_safe}</journal-title></journal-title-group>
      {$issn_xml}
      {$eissn_xml}
    </journal-meta>
    <article-meta>
      {$doi_xml}
      <title-group><article-title>{$title_safe}</article-title></title-group>
      {$contrib_xml}
      {$aff_xml}
      <pub-date pub-type="epub"><year>{$year}</year></pub-date>
      {$vol_xml}
      {$num_xml}
      <abstract><p>{$abs_safe}</p></abstract>
      {$kw_xml}
    </article-meta>
  </front>
  {$refs_xml}
</article>
XML;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
