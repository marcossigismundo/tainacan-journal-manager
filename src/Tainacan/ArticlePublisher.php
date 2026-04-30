<?php

declare(strict_types=1);

namespace TainacanJournalManager\Tainacan;

use TainacanJournalManager\Config;
use TainacanJournalManager\Editorial\WorkflowManager;
use TainacanJournalManager\Notifications\Mailer;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Production\ProofApprovalService;
use TainacanJournalManager\Submission\AnonymizationService;

/**
 * Publishes an accepted submission to the Tainacan public collection.
 *
 * Steps:
 *   1. Verify Tainacan is available, gallleys exist, proof approved.
 *   2. Reuse existing item if `_tjm_tainacan_item_id` is set; otherwise create.
 *   3. Populate the 17 Dublin-Core / OJS-compatible metadata fields.
 *   4. Transition the submission to `published` and notify author.
 *
 * Idempotent: re-running on a submission that already has an item updates
 * the metadata in place (useful after corrections post-publication).
 */
final class ArticlePublisher
{
    /**
     * @return array{item_id: int, error?: string}
     */
    public static function publish(int $submission_id, int $user_id): array
    {
        if (! Integration::is_available()) {
            return ['item_id' => 0, 'error' => __('Tainacan is not active.', 'tainacan-journal-manager')];
        }

        $current = WorkflowManager::get_status($submission_id);
        if (! in_array($current, [Config::STATUS_PRODUCTION, Config::STATUS_PUBLISHED], true)) {
            return ['item_id' => 0, 'error' => __('Submission is not in production.', 'tainacan-journal-manager')];
        }

        if (empty(GalleyService::get_galleys($submission_id))) {
            return ['item_id' => 0, 'error' => __('Add at least one galley before publishing.', 'tainacan-journal-manager')];
        }

        if (ProofApprovalService::get_status($submission_id) !== ProofApprovalService::STATUS_APPROVED) {
            return ['item_id' => 0, 'error' => __('The author has not approved the proof yet.', 'tainacan-journal-manager')];
        }

        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if ($journal_id <= 0) {
            return ['item_id' => 0, 'error' => __('Submission has no journal.', 'tainacan-journal-manager')];
        }

        $collection_id = Integration::get_collection_id_for_journal($journal_id);
        if ($collection_id <= 0) {
            $collection_id = CollectionProvisioner::provision_for_journal($journal_id);
        }
        if ($collection_id <= 0) {
            return ['item_id' => 0, 'error' => __('Could not provision Tainacan collection.', 'tainacan-journal-manager')];
        }

        try {
            $item_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'tainacan_item_id', true);
            $items_repo = \Tainacan\Repositories\Items::get_instance();

            if ($item_id > 0 && get_post($item_id)) {
                $item = $items_repo->fetch($item_id);
                if (! $item) {
                    $item_id = 0;
                }
            }

            if ($item_id <= 0) {
                $item = new \Tainacan\Entities\Item();
                $item->set_collection_id($collection_id);
                $item->set_title(get_the_title($submission_id) ?: 'Untitled');
                $item->set_status('publish');
                if (! $item->validate()) {
                    return ['item_id' => 0, 'error' => __('Item validation failed.', 'tainacan-journal-manager')];
                }
                $saved = $items_repo->insert($item);
                $item_id = (int) $saved->get_id();
                if ($item_id <= 0) {
                    return ['item_id' => 0, 'error' => __('Could not create Tainacan item.', 'tainacan-journal-manager')];
                }
                update_post_meta($submission_id, Config::META_PREFIX . 'tainacan_item_id', $item_id);
                update_post_meta($item_id, Config::META_PREFIX . 'submission_id', $submission_id);
            } else {
                // Update title in case it changed during copyediting
                wp_update_post(['ID' => $item_id, 'post_title' => (string) get_the_title($submission_id)]);
            }

            self::populate_metadata($submission_id, $item_id);

            // Transition workflow
            if ($current === Config::STATUS_PRODUCTION) {
                WorkflowManager::transition($submission_id, Config::STATUS_PUBLISHED, $user_id, __('Published to Tainacan.', 'tainacan-journal-manager'));
            }
            update_post_meta($submission_id, Config::META_PREFIX . 'published_at', current_time('mysql'));

            self::notify_author_published($submission_id);
            do_action('tjm_article_published', $submission_id, $item_id);

            return ['item_id' => $item_id];
        } catch (\Throwable $e) {
            error_log('[TJM] Article publish failed: ' . $e->getMessage());
            return ['item_id' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Populate all 17 metadata fields on the Tainacan item.
     */
    private static function populate_metadata(int $submission_id, int $item_id): void
    {
        if (! class_exists('\Tainacan\Repositories\Item_Metadata')) {
            return;
        }

        $im_repo  = \Tainacan\Repositories\Item_Metadata::get_instance();
        $meta_repo = \Tainacan\Repositories\Metadata::get_instance();
        $items_repo = \Tainacan\Repositories\Items::get_instance();
        $item = $items_repo->fetch($item_id);
        if (! $item) {
            return;
        }

        $values = self::collect_values($submission_id);

        foreach (self::field_map() as $option_key => $value_key) {
            $metadatum_id = (int) get_option($option_key, 0);
            if ($metadatum_id <= 0) {
                continue;
            }
            $value = $values[$value_key] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            self::set_metadatum_value($im_repo, $meta_repo, $item, $metadatum_id, (string) $value);
        }
    }

    /**
     * @param array<string, mixed>|null $args
     */
    private static function set_metadatum_value(
        \Tainacan\Repositories\Item_Metadata $im_repo,
        \Tainacan\Repositories\Metadata $meta_repo,
        \Tainacan\Entities\Item $item,
        int $metadatum_id,
        string $value
    ): void {
        try {
            $metadatum = $meta_repo->fetch($metadatum_id);
            if (! $metadatum) {
                return;
            }
            $entity = new \Tainacan\Entities\Item_Metadata_Entity($item, $metadatum);
            $entity->set_value($value);
            if ($entity->validate()) {
                $im_repo->insert($entity);
            }
        } catch (\Throwable $e) {
            error_log('[TJM] Set metadatum failed (' . $metadatum_id . '): ' . $e->getMessage());
        }
    }

    /**
     * Map: option key (Tainacan metadatum id storage) → key in collected values.
     *
     * @return array<string, string>
     */
    private static function field_map(): array
    {
        return [
            'tjm_meta_title_id'        => 'title',
            'tjm_meta_title_alt_id'    => 'title_alt',
            'tjm_meta_abstract_id'     => 'abstract',
            'tjm_meta_abstract_en_id'  => 'abstract_en',
            'tjm_meta_keywords_id'     => 'keywords',
            'tjm_meta_keywords_en_id'  => 'keywords_en',
            'tjm_meta_authors_id'      => 'authors',
            'tjm_meta_section_id'      => 'section',
            'tjm_meta_issue_id'        => 'issue',
            'tjm_meta_language_id'     => 'language',
            'tjm_meta_references_id'   => 'references',
            'tjm_meta_license_id'      => 'license',
            'tjm_meta_doi_id'          => 'doi',
            'tjm_meta_submitted_at_id' => 'submitted_at',
            'tjm_meta_accepted_at_id'  => 'accepted_at',
            'tjm_meta_published_at_id' => 'published_at',
            'tjm_meta_funding_id'      => 'funding',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function collect_values(int $submission_id): array
    {
        $post = get_post($submission_id);
        if (! $post) {
            return [];
        }

        $authors = AnonymizationService::collect_authors($submission_id);
        $authors_str = '';
        foreach ($authors as $a) {
            $line = (string) ($a['name'] ?? '');
            if (! empty($a['affiliation'])) {
                $line .= ' (' . (string) $a['affiliation'] . ')';
            }
            if (! empty($a['orcid'])) {
                $line .= ' ORCID: ' . (string) $a['orcid'];
            }
            $authors_str .= $line . "\n";
        }

        $keywords = (array) get_post_meta($submission_id, Config::META_PREFIX . Config::META_KEYWORDS, true);
        $keywords_str = implode(', ', array_map('strval', $keywords));

        // Section: from term assignment
        $section_terms = wp_get_object_terms($submission_id, Config::TAX_SECTION, ['fields' => 'names']);
        $section_str = is_array($section_terms) && ! empty($section_terms) ? (string) $section_terms[0] : '';

        // Issue label: "{volume}({number}) — {year}"
        $issue_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'issue_id', true);
        $issue_str = '';
        if ($issue_id > 0) {
            $vol  = (string) get_post_meta($issue_id, Config::META_PREFIX . 'volume', true);
            $num  = (string) get_post_meta($issue_id, Config::META_PREFIX . 'number', true);
            $year = (int) get_post_meta($issue_id, Config::META_PREFIX . 'year', true);
            $parts = [];
            if ($vol !== '')  $parts[] = 'v.' . $vol;
            if ($num !== '')  $parts[] = 'n.' . $num;
            if ($year > 0)    $parts[] = (string) $year;
            $issue_str = implode(' ', $parts) ?: (string) get_the_title($issue_id);
        }

        $submitted_at = (string) get_post_meta($submission_id, Config::META_PREFIX . 'submitted_at', true);
        // accepted_at = date of decision-accept entry
        $accepted_at = '';
        $decisions = (array) get_post_meta($submission_id, Config::META_PREFIX . 'decisions', true);
        foreach ($decisions as $d) {
            if (! is_array($d)) continue;
            if ((string) ($d['decision'] ?? '') === Config::DECISION_ACCEPT) {
                $accepted_at = (string) ($d['date'] ?? '');
                break;
            }
        }

        return [
            'title'        => (string) $post->post_title,
            'title_alt'    => (string) get_post_meta($submission_id, Config::META_PREFIX . 'title_alt', true),
            'abstract'     => (string) $post->post_content,
            'abstract_en'  => (string) get_post_meta($submission_id, Config::META_PREFIX . 'abstract_en', true),
            'keywords'     => $keywords_str,
            'keywords_en'  => (string) get_post_meta($submission_id, Config::META_PREFIX . 'keywords_en', true),
            'authors'      => trim($authors_str),
            'section'      => $section_str,
            'issue'        => $issue_str,
            'language'     => (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_LANGUAGE, true),
            'references'   => (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_REFERENCES, true),
            'license'      => (string) (get_post_meta($submission_id, Config::META_PREFIX . 'license', true) ?: 'CC BY 4.0'),
            'doi'          => (string) get_post_meta($submission_id, Config::META_PREFIX . 'doi', true),
            'submitted_at' => $submitted_at ? gmdate('Y-m-d', strtotime($submitted_at)) : '',
            'accepted_at'  => $accepted_at ? gmdate('Y-m-d', strtotime($accepted_at)) : '',
            'published_at' => gmdate('Y-m-d'),
            'funding'      => (string) get_post_meta($submission_id, Config::META_PREFIX . Config::META_FUNDING, true),
        ];
    }

    private static function notify_author_published(int $submission_id): void
    {
        $post = get_post($submission_id);
        if (! $post) return;
        $author = get_userdata((int) $post->post_author);
        if (! $author || ! is_email($author->user_email)) return;

        (new Mailer())->send($author->user_email, 'submission-published', [
            'author_name'   => $author->display_name ?: $author->user_login,
            'title'         => (string) $post->post_title,
            'submission_id' => $submission_id,
        ]);
    }
}
