<?php

declare(strict_types=1);

namespace TainacanJournalManager\Tainacan;

use TainacanJournalManager\Config;

/**
 * Provisions Tainacan collections for published articles, one per journal.
 *
 * Idempotent: safe to call multiple times. Stores collection ID per journal
 * in `wp_options` (tjm_collection_for_journal_{journal_id}).
 *
 * Article metadata follows OJS / Dublin Core conventions for scientific
 * publication interoperability.
 */
final class CollectionProvisioner
{
    /**
     * Provision a Tainacan collection for a specific journal.
     * Returns the collection ID (existing or newly created).
     */
    public static function provision_for_journal(int $journal_id): int
    {
        if (! Integration::is_available()) {
            return 0;
        }

        $existing = Integration::get_collection_id_for_journal($journal_id);
        if ($existing > 0) {
            self::upgrade_metadata($existing);
            return $existing;
        }

        try {
            $journal = get_post($journal_id);
            if (! $journal) {
                return 0;
            }

            $col_repo = \Tainacan\Repositories\Collections::get_instance();
            $collection = new \Tainacan\Entities\Collection();
            $collection->set_name(sprintf(__('%s — Articles', 'tainacan-journal-manager'), $journal->post_title));
            $collection->set_description(sprintf(__('Published articles for the journal %s.', 'tainacan-journal-manager'), $journal->post_title));
            $collection->set_status('publish');

            if (! $collection->validate()) {
                error_log('[TJM] Collection validation failed: ' . wp_json_encode($collection->get_errors()));
                return 0;
            }

            $saved = $col_repo->insert($collection);
            $collection_id = (int) $saved->get_id();

            if ($collection_id > 0) {
                Integration::set_collection_id_for_journal($journal_id, $collection_id);
                self::create_all_metadata($collection_id);
            }

            return $collection_id;
        } catch (\Throwable $e) {
            error_log('[TJM] Error provisioning collection: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Article metadata definitions (Dublin Core / OJS-compatible).
     *
     * @return array<int, array{name: string, type: string, option_key: string, required: bool, description: string}>
     */
    private static function get_field_definitions(): array
    {
        return [
            ['name' => __('Title', 'tainacan-journal-manager'),       'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_title_id',       'required' => true,  'description' => 'Article title.'],
            ['name' => __('Title (other language)', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Text', 'option_key' => 'tjm_meta_title_alt_id', 'required' => false, 'description' => 'Title in alternate language.'],
            ['name' => __('Abstract', 'tainacan-journal-manager'),    'type' => 'Tainacan\Metadata_Types\Textarea', 'option_key' => 'tjm_meta_abstract_id',   'required' => true,  'description' => 'Article abstract.'],
            ['name' => __('Abstract (English)', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Textarea', 'option_key' => 'tjm_meta_abstract_en_id', 'required' => false, 'description' => 'English abstract.'],
            ['name' => __('Keywords', 'tainacan-journal-manager'),    'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_keywords_id',    'required' => true,  'description' => 'Comma-separated keywords.'],
            ['name' => __('Keywords (English)', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Text', 'option_key' => 'tjm_meta_keywords_en_id', 'required' => false, 'description' => 'English keywords.'],
            ['name' => __('Authors', 'tainacan-journal-manager'),     'type' => 'Tainacan\Metadata_Types\Textarea', 'option_key' => 'tjm_meta_authors_id',    'required' => true,  'description' => 'Authors with affiliations and ORCID.'],
            ['name' => __('Section', 'tainacan-journal-manager'),     'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_section_id',     'required' => true,  'description' => 'Editorial section.'],
            ['name' => __('Issue', 'tainacan-journal-manager'),       'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_issue_id',       'required' => false, 'description' => 'Volume / number / dossier.'],
            ['name' => __('Language', 'tainacan-journal-manager'),    'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_language_id',    'required' => true,  'description' => 'Article language code.'],
            ['name' => __('References', 'tainacan-journal-manager'),  'type' => 'Tainacan\Metadata_Types\Textarea', 'option_key' => 'tjm_meta_references_id', 'required' => false, 'description' => 'Bibliographic references.'],
            ['name' => __('License', 'tainacan-journal-manager'),     'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_license_id',     'required' => true,  'description' => 'Creative Commons license.'],
            ['name' => __('DOI', 'tainacan-journal-manager'),         'type' => 'Tainacan\Metadata_Types\Text',    'option_key' => 'tjm_meta_doi_id',         'required' => false, 'description' => 'Digital Object Identifier.'],
            ['name' => __('Submission date', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Date', 'option_key' => 'tjm_meta_submitted_at_id', 'required' => false, 'description' => 'Submission date.'],
            ['name' => __('Acceptance date', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Date', 'option_key' => 'tjm_meta_accepted_at_id', 'required' => false, 'description' => 'Acceptance date.'],
            ['name' => __('Publication date', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Date', 'option_key' => 'tjm_meta_published_at_id', 'required' => false, 'description' => 'Publication date.'],
            ['name' => __('Funding agency', 'tainacan-journal-manager'), 'type' => 'Tainacan\Metadata_Types\Text', 'option_key' => 'tjm_meta_funding_id',     'required' => false, 'description' => 'Funding agency.'],
        ];
    }

    private static function create_all_metadata(int $collection_id): void
    {
        if (! class_exists('\Tainacan\Repositories\Metadata')) {
            return;
        }

        $meta_repo = \Tainacan\Repositories\Metadata::get_instance();
        foreach (self::get_field_definitions() as $field) {
            self::create_single_metadatum($meta_repo, $collection_id, $field);
        }
    }

    /**
     * Idempotent: only creates missing metadata fields.
     */
    public static function upgrade_metadata(int $collection_id): void
    {
        if (! class_exists('\Tainacan\Repositories\Metadata')) {
            return;
        }

        $meta_repo = \Tainacan\Repositories\Metadata::get_instance();
        foreach (self::get_field_definitions() as $field) {
            $existing_id = (int) get_option($field['option_key'], 0);
            if ($existing_id > 0) {
                continue;
            }
            self::create_single_metadatum($meta_repo, $collection_id, $field);
        }
    }

    private static function create_single_metadatum(
        \Tainacan\Repositories\Metadata $meta_repo,
        int $collection_id,
        array $field
    ): void {
        try {
            $metadatum = new \Tainacan\Entities\Metadatum();
            $metadatum->set_name($field['name']);
            $metadatum->set_collection_id($collection_id);
            $metadatum->set_metadata_type($field['type']);
            $metadatum->set_status('publish');
            $metadatum->set_description($field['description']);

            if ($field['required']) {
                $metadatum->set_required('yes');
            }

            if ($metadatum->validate()) {
                $saved = $meta_repo->insert($metadatum);
                update_option($field['option_key'], (int) $saved->get_id(), false);
            }
        } catch (\Throwable $e) {
            error_log('[TJM] Metadatum creation failed (' . $field['name'] . '): ' . $e->getMessage());
        }
    }
}
