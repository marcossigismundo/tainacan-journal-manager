<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;

/**
 * CPT: Journal (the periodical itself).
 *
 * Each journal has its own configuration, sections, editorial team and policies.
 * Stored as posts because they have rich content (about, scope, policies).
 */
final class Journal
{
    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        register_post_type(Config::CPT_JOURNAL, [
            'labels' => [
                'name'          => __('Journals', 'tainacan-journal-manager'),
                'singular_name' => __('Journal', 'tainacan-journal-manager'),
                'add_new_item'  => __('Add New Journal', 'tainacan-journal-manager'),
                'edit_item'     => __('Edit Journal', 'tainacan-journal-manager'),
                'menu_name'     => __('Journals', 'tainacan-journal-manager'),
            ],
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'tjm-main',
            'show_in_rest'        => true,
            'rewrite'             => ['slug' => 'journal'],
            'capability_type'     => 'post',
            'has_archive'         => 'journals',
            'hierarchical'        => false,
            'menu_position'       => 5,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt'],
            'taxonomies'          => [],
        ]);
    }
}
