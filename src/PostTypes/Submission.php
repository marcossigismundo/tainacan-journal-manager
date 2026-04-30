<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;

/**
 * CPT: Submission (manuscript in editorial workflow).
 *
 * NOT exposed publicly — submissions are private until published.
 * After publication, an Article is created in a Tainacan collection (the public face).
 */
final class Submission
{
    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        register_post_type(Config::CPT_SUBMISSION, [
            'labels' => [
                'name'          => __('Submissions', 'tainacan-journal-manager'),
                'singular_name' => __('Submission', 'tainacan-journal-manager'),
                'add_new_item'  => __('New Submission', 'tainacan-journal-manager'),
                'edit_item'     => __('Edit Submission', 'tainacan-journal-manager'),
                'menu_name'     => __('Submissions', 'tainacan-journal-manager'),
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'author'],
            'taxonomies'          => [Config::TAX_SECTION, Config::TAX_KEYWORD, Config::TAX_LANGUAGE],
        ]);
    }
}
