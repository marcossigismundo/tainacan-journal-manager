<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;

/**
 * CPT: Issue (volume / number / dossier / continuous flow batch).
 *
 * Public when published. Linked to a Journal via meta `_tjm_journal_id`.
 */
final class Issue
{
    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        register_post_type(Config::CPT_ISSUE, [
            'labels' => [
                'name'          => __('Issues', 'tainacan-journal-manager'),
                'singular_name' => __('Issue', 'tainacan-journal-manager'),
                'add_new_item'  => __('Add New Issue', 'tainacan-journal-manager'),
                'edit_item'     => __('Edit Issue', 'tainacan-journal-manager'),
                'menu_name'     => __('Issues', 'tainacan-journal-manager'),
            ],
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'tjm-main',
            'show_in_rest'        => true,
            'rewrite'             => ['slug' => 'issue'],
            'capability_type'     => 'post',
            'has_archive'         => 'issues',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt'],
        ]);
    }
}
