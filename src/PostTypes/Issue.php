<?php

declare(strict_types=1);

namespace TainacanJournalManager\PostTypes;

use TainacanJournalManager\Config;
use TainacanJournalManager\Issues\IssueManager;

/**
 * CPT: Issue (volume / number / dossier / continuous flow batch).
 *
 * Public when published. Linked to a Journal via meta `_tjm_journal_id`.
 * Editorial settings (volume / number / year / type) are exposed via
 * a metabox on the Issue edit screen.
 */
final class Issue
{
    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_' . Config::CPT_ISSUE, [$this, 'save_metaboxes'], 10, 2);
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
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rewrite'             => ['slug' => 'issue'],
            'capability_type'     => 'post',
            'has_archive'         => 'issues',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt'],
        ]);
    }

    public function register_metaboxes(): void
    {
        add_meta_box(
            'tjm_issue_settings',
            __('Issue settings', 'tainacan-journal-manager'),
            [$this, 'render_settings_metabox'],
            Config::CPT_ISSUE,
            'normal',
            'default'
        );
    }

    public function render_settings_metabox(\WP_Post $post): void
    {
        wp_nonce_field('tjm_issue_settings', 'tjm_issue_settings_nonce');

        $journal_id = (int) get_post_meta($post->ID, Config::META_PREFIX . 'journal_id', true);
        $volume     = (string) get_post_meta($post->ID, Config::META_PREFIX . 'volume', true);
        $number     = (string) get_post_meta($post->ID, Config::META_PREFIX . 'number', true);
        $year       = (int) get_post_meta($post->ID, Config::META_PREFIX . 'year', true) ?: (int) gmdate('Y');
        $type       = (string) get_post_meta($post->ID, Config::META_PREFIX . 'publication_type', true) ?: IssueManager::TYPE_REGULAR;
        $articles   = IssueManager::get_article_ids($post->ID);

        $journals = get_posts([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $type_labels = [
            IssueManager::TYPE_REGULAR    => __('Regular', 'tainacan-journal-manager'),
            IssueManager::TYPE_SPECIAL    => __('Special issue', 'tainacan-journal-manager'),
            IssueManager::TYPE_DOSSIER    => __('Dossier', 'tainacan-journal-manager'),
            IssueManager::TYPE_CONTINUOUS => __('Continuous flow', 'tainacan-journal-manager'),
        ];
        ?>
        <p>
            <label for="tjm_issue_journal"><strong><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></strong></label><br>
            <select name="tjm_issue_journal" id="tjm_issue_journal">
                <option value="0"><?php esc_html_e('— select —', 'tainacan-journal-manager'); ?></option>
                <?php foreach ($journals as $j) : ?>
                    <option value="<?php echo (int) $j->ID; ?>" <?php selected($journal_id, (int) $j->ID); ?>><?php echo esc_html((string) $j->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><?php esc_html_e('Volume', 'tainacan-journal-manager'); ?>
                <input type="text" name="tjm_issue_volume" value="<?php echo esc_attr($volume); ?>" class="regular-text">
            </label>
            <label><?php esc_html_e('Number', 'tainacan-journal-manager'); ?>
                <input type="text" name="tjm_issue_number" value="<?php echo esc_attr($number); ?>" class="regular-text">
            </label>
            <label><?php esc_html_e('Year', 'tainacan-journal-manager'); ?>
                <input type="number" name="tjm_issue_year" value="<?php echo (int) $year; ?>" min="1900" max="2100" class="small-text">
            </label>
        </p>
        <p>
            <label for="tjm_issue_type"><strong><?php esc_html_e('Publication type', 'tainacan-journal-manager'); ?></strong></label><br>
            <select name="tjm_issue_type" id="tjm_issue_type">
                <?php foreach ($type_labels as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <h4><?php esc_html_e('Assigned articles', 'tainacan-journal-manager'); ?></h4>
        <?php if (empty($articles)) : ?>
            <p class="description"><?php esc_html_e('No articles assigned yet. Use the editorial dashboard or AJAX endpoints to attach published articles.', 'tainacan-journal-manager'); ?></p>
        <?php else : ?>
            <ol>
                <?php foreach ($articles as $sid) : ?>
                    <li><a href="<?php echo esc_url(get_edit_post_link((int) $sid) ?: '#'); ?>"><?php echo esc_html((string) get_the_title((int) $sid)); ?></a> <code>#<?php echo (int) $sid; ?></code></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php
    }

    public function save_metaboxes(int $post_id, \WP_Post $post): void
    {
        if (! isset($_POST['tjm_issue_settings_nonce'])
            || ! wp_verify_nonce((string) $_POST['tjm_issue_settings_nonce'], 'tjm_issue_settings')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, Config::META_PREFIX . 'journal_id', (int) ($_POST['tjm_issue_journal'] ?? 0));
        update_post_meta($post_id, Config::META_PREFIX . 'volume', sanitize_text_field((string) ($_POST['tjm_issue_volume'] ?? '')));
        update_post_meta($post_id, Config::META_PREFIX . 'number', sanitize_text_field((string) ($_POST['tjm_issue_number'] ?? '')));
        update_post_meta($post_id, Config::META_PREFIX . 'year',   (int) ($_POST['tjm_issue_year'] ?? gmdate('Y')));

        $type = (string) ($_POST['tjm_issue_type'] ?? IssueManager::TYPE_REGULAR);
        if (in_array($type, IssueManager::ALL_TYPES, true)) {
            update_post_meta($post_id, Config::META_PREFIX . 'publication_type', $type);
        }
    }
}
