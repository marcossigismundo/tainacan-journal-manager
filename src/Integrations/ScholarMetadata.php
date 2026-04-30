<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * Emits Google Scholar `citation_*` metadata tags in <head> on the public
 * article page (single tjm_submission, status=published, OR a page where
 * the [tjm_article id=N] shortcode appears).
 *
 * The tags follow Google Scholar's inclusion guidelines:
 * https://scholar.google.com/intl/en/scholar/inclusion.html#indexing
 */
final class ScholarMetadata
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'maybe_emit_tags'], 5);
    }

    public function maybe_emit_tags(): void
    {
        $sid = $this->detect_article_id();
        if ($sid <= 0) {
            return;
        }

        $post = get_post($sid);
        if (! $post || $post->post_type !== Config::CPT_SUBMISSION) {
            return;
        }
        if ((string) get_post_meta($sid, Config::META_PREFIX . 'status', true) !== Config::STATUS_PUBLISHED) {
            return;
        }

        $journal_id = (int) get_post_meta($sid, Config::META_PREFIX . 'journal_id', true);
        $issue_id   = (int) get_post_meta($sid, Config::META_PREFIX . 'issue_id', true);
        $doi        = DoiService::normalize((string) get_post_meta($sid, Config::META_PREFIX . 'doi', true));
        $language   = (string) (get_post_meta($sid, Config::META_PREFIX . Config::META_LANGUAGE, true) ?: 'en');
        $published  = (string) (get_post_meta($sid, Config::META_PREFIX . 'published_at', true) ?: $post->post_date);
        $journal_title = $journal_id ? (string) get_the_title($journal_id) : '';

        $vol = $issue_id ? (string) get_post_meta($issue_id, Config::META_PREFIX . 'volume', true) : '';
        $num = $issue_id ? (string) get_post_meta($issue_id, Config::META_PREFIX . 'number', true) : '';

        $tags = [];
        $tags[] = $this->tag('citation_title', (string) $post->post_title);
        $tags[] = $this->tag('citation_journal_title', $journal_title);
        $tags[] = $this->tag('citation_publication_date', date('Y/m/d', strtotime($published)));
        $tags[] = $this->tag('citation_language', $language);

        foreach (AnonymizationService::collect_authors($sid) as $a) {
            $name = trim((string) ($a['name'] ?? ''));
            if ($name !== '') {
                $tags[] = $this->tag('citation_author', $name);
            }
        }

        if ($vol !== '') $tags[] = $this->tag('citation_volume', $vol);
        if ($num !== '') $tags[] = $this->tag('citation_issue', $num);
        if ($doi !== '') $tags[] = $this->tag('citation_doi', $doi);

        // Galleys: emit citation_pdf_url for the first PDF
        foreach (GalleyService::get_galleys_with_urls($sid) as $g) {
            if (($g['format'] ?? '') === 'pdf' && ! empty($g['url'])) {
                $tags[] = $this->tag('citation_pdf_url', (string) $g['url']);
                break;
            }
        }

        $abstract = (string) $post->post_content;
        if ($abstract !== '') {
            $tags[] = $this->tag('citation_abstract', mb_substr($abstract, 0, 4000));
        }

        $keywords = (array) get_post_meta($sid, Config::META_PREFIX . Config::META_KEYWORDS, true);
        if (! empty($keywords)) {
            $tags[] = $this->tag('citation_keywords', implode('; ', array_map('strval', $keywords)));
        }

        echo "\n<!-- Tainacan Journal Manager: Google Scholar tags -->\n";
        echo implode("\n", $tags);
        echo "\n<!-- /TJM Scholar tags -->\n";
    }

    private function tag(string $name, string $content): string
    {
        return '<meta name="' . esc_attr($name) . '" content="' . esc_attr($content) . '">';
    }

    /**
     * Determine which article (if any) this page is about.
     */
    private function detect_article_id(): int
    {
        // Single tjm_submission view (rare — CPT is private — but plugins/themes may expose)
        if (is_singular(Config::CPT_SUBMISSION)) {
            return (int) get_queried_object_id();
        }

        // Detect [tjm_article id=N] shortcode in current post content
        $post = get_post();
        if ($post && has_shortcode((string) $post->post_content, 'tjm_article')) {
            if (preg_match('/\[tjm_article[^\]]*\bid=("?)(\d+)\1/', (string) $post->post_content, $m)) {
                return (int) $m[2];
            }
        }
        return 0;
    }
}
