<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;
use TainacanJournalManager\Review\ReviewFormConfig;

/**
 * CPT: Journal (the periodical itself).
 *
 * Each journal has its own configuration, sections, editorial team and policies.
 * Stored as posts because they have rich content (about, scope, policies).
 *
 * Editorial settings (review type + parecer form sections) are exposed via
 * a metabox on the Journal edit screen.
 */
final class Journal
{
    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_' . Config::CPT_JOURNAL, [$this, 'save_metaboxes'], 10, 2);
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
            'show_in_menu'        => true,
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

    public function register_metaboxes(): void
    {
        add_meta_box(
            'tjm_journal_editorial',
            __('Editorial settings', 'tainacan-journal-manager'),
            [$this, 'render_editorial_metabox'],
            Config::CPT_JOURNAL,
            'normal',
            'default'
        );
    }

    public function render_editorial_metabox(\WP_Post $post): void
    {
        wp_nonce_field('tjm_journal_editorial', 'tjm_journal_editorial_nonce');

        $type = (string) get_post_meta($post->ID, Config::META_PREFIX . 'review_type', true) ?: Config::REVIEW_TYPE_DOUBLE;
        $sections = ReviewFormConfig::get_for_journal($post->ID);

        $types = [
            Config::REVIEW_TYPE_OPEN      => __('Open (identities visible)', 'tainacan-journal-manager'),
            Config::REVIEW_TYPE_BLIND     => __('Single-blind (author hidden from reviewer)', 'tainacan-journal-manager'),
            Config::REVIEW_TYPE_DOUBLE    => __('Double-blind (both anonymous)', 'tainacan-journal-manager'),
            Config::REVIEW_TYPE_EDITORIAL => __('Editorial only (no peer review)', 'tainacan-journal-manager'),
        ];
        ?>
        <p>
            <label for="tjm_review_type"><strong><?php esc_html_e('Review type', 'tainacan-journal-manager'); ?></strong></label><br>
            <select name="tjm_review_type" id="tjm_review_type">
                <?php foreach ($types as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p><strong><?php esc_html_e('Optional review form sections', 'tainacan-journal-manager'); ?></strong></p>
        <p class="description"><?php esc_html_e('Reviewers will be asked to write a free-text comment for each enabled section, in addition to the required overall comments and recommendation. No numeric scoring.', 'tainacan-journal-manager'); ?></p>
        <ul>
            <?php foreach (ReviewFormConfig::ALL_SECTIONS as $section) : ?>
                <li>
                    <label>
                        <input type="checkbox" name="tjm_review_sections[]" value="<?php echo esc_attr($section); ?>" <?php checked(in_array($section, $sections, true)); ?>>
                        <?php echo esc_html(ReviewFormConfig::label($section)); ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    public function save_metaboxes(int $post_id, \WP_Post $post): void
    {
        if (! isset($_POST['tjm_journal_editorial_nonce'])
            || ! wp_verify_nonce((string) $_POST['tjm_journal_editorial_nonce'], 'tjm_journal_editorial')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $type = isset($_POST['tjm_review_type']) ? sanitize_text_field((string) $_POST['tjm_review_type']) : '';
        $allowed = [Config::REVIEW_TYPE_OPEN, Config::REVIEW_TYPE_BLIND, Config::REVIEW_TYPE_DOUBLE, Config::REVIEW_TYPE_EDITORIAL];
        if (in_array($type, $allowed, true)) {
            update_post_meta($post_id, Config::META_PREFIX . 'review_type', $type);
        }

        $sections_raw = isset($_POST['tjm_review_sections']) && is_array($_POST['tjm_review_sections'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['tjm_review_sections']))
            : [];
        ReviewFormConfig::set_for_journal($post_id, $sections_raw);
    }
}
