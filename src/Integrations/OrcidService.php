<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * ORCID iD validation and formatting.
 *
 * ORCID iDs use ISO/IEC 7064 MOD 11-2 checksum, where the last digit
 * (which can be the literal 'X' for value 10) validates the previous 15.
 *
 * @link https://support.orcid.org/hc/en-us/articles/360006897674-Structure-of-the-ORCID-Identifier
 */
final class OrcidService
{
    /**
     * Validate ORCID iD format AND Mod-11-2 checksum.
     */
    public static function is_valid(string $orcid): bool
    {
        $orcid = (string) preg_replace('/[^0-9X]/', '', strtoupper($orcid));
        if (strlen($orcid) !== 16) {
            return false;
        }
        return self::compute_checksum(substr($orcid, 0, 15)) === substr($orcid, 15, 1);
    }

    /**
     * Format ORCID with hyphens (0000-0000-0000-0000). Returns empty string
     * when input is not 16 chars after normalization.
     */
    public static function format(string $orcid): string
    {
        $orcid = (string) preg_replace('/[^0-9X]/', '', strtoupper($orcid));
        if (strlen($orcid) !== 16) {
            return '';
        }
        return substr($orcid, 0, 4) . '-' . substr($orcid, 4, 4) . '-' . substr($orcid, 8, 4) . '-' . substr($orcid, 12, 4);
    }

    /**
     * URL form (https://orcid.org/0000-0000-0000-0000).
     */
    public static function url(string $orcid): string
    {
        $f = self::format($orcid);
        return $f !== '' ? 'https://orcid.org/' . $f : '';
    }

    /**
     * Compute ISO 7064 MOD 11-2 check digit for the given 15-digit base.
     *
     * Algorithm:
     *   total = 0
     *   for each digit d (left to right): total = (total + d) * 2
     *   remainder = total % 11
     *   result = (12 - remainder) % 11
     *   check = '0'..'9' for 0..9, 'X' for 10
     */
    private static function compute_checksum(string $base15): string
    {
        if (! preg_match('/^[0-9]{15}$/', $base15)) {
            return '?';
        }
        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $base15[$i]) * 2;
        }
        $remainder = $total % 11;
        $result    = (12 - $remainder) % 11;
        return $result === 10 ? 'X' : (string) $result;
    }
}
