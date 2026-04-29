<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

/**
 * OAI-PMH 2.0 endpoint provider — STUB.
 *
 * Will expose published articles for harvesting by indexing services
 * (Google Scholar, BASE, OpenAIRE, etc.).
 *
 * @link https://www.openarchives.org/OAI/openarchivesprotocol.html
 */
final class OaiPmhProvider
{
    /**
     * Verb implementations:
     * - Identify
     * - ListMetadataFormats
     * - ListSets
     * - ListRecords / ListIdentifiers / GetRecord
     */
    public static function handle_request(): void
    {
        // TODO: implement OAI-PMH 2.0 protocol
        status_header(501);
        echo 'OAI-PMH endpoint not yet implemented.';
        exit;
    }
}
