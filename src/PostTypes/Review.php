<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;

/**
 * CPT: Review (peer review report).
 *
 * Confidential — never publicly accessible.
 * Linked to a Submission via meta `_tjm_submission_id`.
 */
final class Review
{
    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        register_post_type(Config::CPT_REVIEW, [
            'labels' => [
                'name'          => __('Reviews', 'tainacan-journal-manager'),
                'singular_name' => __('Review', 'tainacan-journal-manager'),
                'menu_name'     => __('Reviews', 'tainacan-journal-manager'),
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => 'tjm-main',
            'show_in_rest'        => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'author'],
        ]);
    }
}
