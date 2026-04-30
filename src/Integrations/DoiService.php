<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * DOI helpers — format, validation, URL.
 *
 * Pattern: 10.{registrant}/{suffix}, where suffix accepts most printable
 * ASCII. Validation is intentionally permissive (matches Crossref's own
 * regex) — it does NOT verify the DOI resolves.
 */
final class DoiService
{
    private const DOI_REGEX = '#^10\.\d{4,9}/[\-._;()/:A-Z0-9]+$#i';

    public static function is_valid(string $doi): bool
    {
        return (bool) preg_match(self::DOI_REGEX, trim($doi));
    }

    public static function format_url(string $doi): string
    {
        $doi = self::normalize($doi);
        return self::is_valid($doi) ? 'https://doi.org/' . $doi : '';
    }

    /**
     * Strip the doi.org prefix (and any whitespace) leaving just the DOI.
     */
    public static function normalize(string $value): string
    {
        $v = trim($value);
        $v = (string) preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $v);
        return $v;
    }

    /**
     * Build a default DOI suffix from a journal prefix and submission ID.
     * Used when an editor wants a deterministic suffix (admin can override).
     */
    public static function suggest_suffix(string $journal_prefix, int $submission_id): string
    {
        $prefix = trim($journal_prefix, '/');
        return ($prefix !== '' ? $prefix . '.' : '') . 'tjm-' . $submission_id;
    }
}
