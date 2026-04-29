<?php

declare(strict_types=1);

namespace TainacanJournalManager\Tainacan;

/**
 * Helpers for detecting and interacting with Tainacan.
 */
final class Integration
{
    public static function is_available(): bool
    {
        return class_exists('\Tainacan\Repositories\Items');
    }

    public static function get_collection_id_for_journal(int $journal_id): int
    {
        return (int) get_option('tjm_collection_for_journal_' . $journal_id, 0);
    }

    public static function set_collection_id_for_journal(int $journal_id, int $collection_id): void
    {
        update_option('tjm_collection_for_journal_' . $journal_id, $collection_id, false);
    }
}
