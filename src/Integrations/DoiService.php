<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * DOI integration — STUB.
 *
 * Will provide:
 * - DOI minting via Crossref / DataCite
 * - DOI URL formatting
 *
 * @link https://www.crossref.org/services/content-registration/
 */
final class DoiService
{
    public static function format_url(string $doi): string
    {
        $doi = trim($doi);
        if (! $doi) {
            return '';
        }
        if (str_starts_with($doi, 'http')) {
            return $doi;
        }
        return 'https://doi.org/' . ltrim($doi, '/');
    }

    public static function is_valid(string $doi): bool
    {
        // Basic validation: must start with 10. and contain a slash
        return (bool) preg_match('#^10\.\d{4,9}/\S+$#', trim($doi));
    }
}
