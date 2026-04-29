<?php

declare(strict_types=1);

namespace TainacanJournalManager\Tainacan;

use TainacanJournalManager\Config;

/**
 * Creates a Tainacan public item for an accepted/published article.
 * Stub: full implementation in Phase 2 (publishing flow).
 */
final class ArticleItemCreator
{
    /**
     * @return int New Tainacan item ID, or 0 on failure.
     */
    public function create_from_submission(int $submission_id): int
    {
        if (! Integration::is_available()) {
            return 0;
        }

        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if (! $journal_id) {
            return 0;
        }

        $collection_id = Integration::get_collection_id_for_journal($journal_id);
        if (! $collection_id) {
            $collection_id = CollectionProvisioner::provision_for_journal($journal_id);
        }

        if (! $collection_id) {
            return 0;
        }

        // TODO Phase 2: full population of metadata fields (title, abstract, authors,
        // keywords, DOI, dates, license) from the submission post meta.
        try {
            $items_repo = \Tainacan\Repositories\Items::get_instance();
            $item = new \Tainacan\Entities\Item();
            $item->set_collection_id($collection_id);
            $item->set_title(get_the_title($submission_id) ?: 'Untitled');
            $item->set_status('publish');

            if (! $item->validate()) {
                return 0;
            }

            $saved = $items_repo->insert($item);
            $item_id = (int) $saved->get_id();

            if ($item_id > 0) {
                update_post_meta($submission_id, Config::META_PREFIX . 'tainacan_item_id', $item_id);
                update_post_meta($item_id, Config::META_PREFIX . 'submission_id', $submission_id);
            }

            return $item_id;
        } catch (\Throwable $e) {
            error_log('[TJM] Article creation failed: ' . $e->getMessage());
            return 0;
        }
    }
}
