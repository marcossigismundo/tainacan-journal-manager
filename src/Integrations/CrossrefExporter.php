<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * Crossref XML deposit exporter — STUB.
 *
 * Will export article metadata in Crossref schema format for DOI registration.
 *
 * @link https://www.crossref.org/documentation/schema-library/
 */
final class CrossrefExporter
{
    public static function export_article(int $submission_id): string
    {
        // TODO: implement Crossref XML schema export
        return '';
    }
}
