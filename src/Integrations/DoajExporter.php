<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * DOAJ (Directory of Open Access Journals) exporter — STUB.
 *
 * Will export articles in DOAJ XML/JSON format.
 *
 * @link https://doaj.org/api/v3/docs
 */
final class DoajExporter
{
    public static function export_article(int $submission_id): array
    {
        // TODO: implement DOAJ JSON export
        return [];
    }
}
