<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;

/**
 * Taxonomies: editorial sections, keywords, languages.
 */
final class Taxonomies
{
    public function register(): void
    {
        add_action('init', [$this, 'register_taxonomies']);
    }

    public function register_taxonomies(): void
    {
        // Editorial sections (Article, Review, Dossier, Interview, etc.)
        register_taxonomy(Config::TAX_SECTION, [Config::CPT_SUBMISSION, Config::CPT_ISSUE], [
            'labels' => [
                'name'          => __('Sections', 'tainacan-journal-manager'),
                'singular_name' => __('Section', 'tainacan-journal-manager'),
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'rewrite'           => ['slug' => 'section'],
        ]);

        // Keywords (free, multiple per submission)
        register_taxonomy(Config::TAX_KEYWORD, [Config::CPT_SUBMISSION], [
            'labels' => [
                'name'          => __('Keywords', 'tainacan-journal-manager'),
                'singular_name' => __('Keyword', 'tainacan-journal-manager'),
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => ['slug' => 'keyword'],
        ]);

        // Languages
        register_taxonomy(Config::TAX_LANGUAGE, [Config::CPT_SUBMISSION], [
            'labels' => [
                'name'          => __('Languages', 'tainacan-journal-manager'),
                'singular_name' => __('Language', 'tainacan-journal-manager'),
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
        ]);
    }
}
