<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * ORCID integration — STUB.
 *
 * Will provide:
 * - OAuth2 sign-in with ORCID
 * - Validate ORCID iD format (16 digits with checksum)
 * - Fetch author profile data
 *
 * @link https://info.orcid.org/documentation/api-tutorials/
 */
final class OrcidService
{
    /**
     * Validate ORCID iD format and Mod-11-2 checksum.
     */
    public static function is_valid(string $orcid): bool
    {
        $orcid = preg_replace('/[^0-9X]/', '', strtoupper($orcid));
        if (strlen($orcid) !== 16) {
            return false;
        }
        // TODO: implement Mod-11-2 checksum validation
        return true;
    }

    public static function format(string $orcid): string
    {
        $orcid = preg_replace('/[^0-9X]/', '', strtoupper($orcid));
        if (strlen($orcid) !== 16) {
            return '';
        }
        return substr($orcid, 0, 4) . '-' . substr($orcid, 4, 4) . '-' . substr($orcid, 8, 4) . '-' . substr($orcid, 12, 4);
    }
}
