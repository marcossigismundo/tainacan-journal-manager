<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * Public-facing single article page.
 *
 * Two integration modes:
 *  - Shortcode `[tjm_article id=N]` for inserting in any page.
 *  - On `single-{tjm_submission}` we filter `the_content` to render the
 *    public article view automatically when the submission is published.
 *    (Submissions are not publicly_queryable, so the filter is a no-op
 *    unless someone exposes them via a child theme.)
 */
final class PublicArticle
{
    public function register(): void
    {
        add_shortcode('tjm_article', [$this, 'render_shortcode']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render_shortcode($atts = []): string
    {
        $atts = is_array($atts) ? $atts : [];
        $id = isset($atts['id']) ? (int) $atts['id'] : (int) get_the_ID();
        if ($id <= 0) {
            return '';
        }

        $post = get_post($id);
        if (! $post || $post->post_type !== Config::CPT_SUBMISSION) {
            return '';
        }
        $status = (string) get_post_meta($id, Config::META_PREFIX . 'status', true);
        if ($status !== Config::STATUS_PUBLISHED) {
            return '<div class="tjm-notice">' . esc_html__('This article is not published yet.', 'tainacan-journal-manager') . '</div>';
        }

        wp_enqueue_style('tjm-frontend');

        $data = [
            'id'           => $id,
            'title'        => (string) $post->post_title,
            'abstract'     => (string) $post->post_content,
            'authors'      => AnonymizationService::collect_authors($id),
            'keywords'     => (array) get_post_meta($id, Config::META_PREFIX . Config::META_KEYWORDS, true),
            'language'     => (string) get_post_meta($id, Config::META_PREFIX . Config::META_LANGUAGE, true),
            'doi'          => (string) get_post_meta($id, Config::META_PREFIX . 'doi', true),
            'journal_id'   => (int) get_post_meta($id, Config::META_PREFIX . 'journal_id', true),
            'issue_id'     => (int) get_post_meta($id, Config::META_PREFIX . 'issue_id', true),
            'galleys'      => GalleyService::get_galleys_with_urls($id),
            'published_at' => (string) get_post_meta($id, Config::META_PREFIX . 'published_at', true),
            'license'      => (string) (get_post_meta($id, Config::META_PREFIX . 'license', true) ?: 'CC BY 4.0'),
            'tainacan_id'  => (int) get_post_meta($id, Config::META_PREFIX . 'tainacan_item_id', true),
        ];

        ob_start();
        include TJM_PATH . 'templates/frontend/article-public.php';
        return ob_get_clean() ?: '';
    }
}
