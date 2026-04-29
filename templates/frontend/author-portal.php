<?php
/** @var \WP_Post[] $submissions */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
?>
<div class="tjm-portal">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('My Submissions', 'tainacan-journal-manager'); ?></h2>
        <a href="#" class="tjm-btn tjm-btn--primary tjm-new-submission" data-action="new"><?php esc_html_e('+ New Submission', 'tainacan-journal-manager'); ?></a>
    </header>

    <?php if (empty($submissions)) : ?>
        <div class="tjm-empty-state">
            <p><?php esc_html_e('You have no submissions yet.', 'tainacan-journal-manager'); ?></p>
        </div>
    <?php else : ?>
        <table class="tjm-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Submitted', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Actions', 'tainacan-journal-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub) :
                    $status = get_post_meta($sub->ID, Config::META_PREFIX . 'status', true) ?: Config::STATUS_DRAFT;
                    $journal_id = (int) get_post_meta($sub->ID, Config::META_PREFIX . 'journal_id', true);
                    $journal_name = $journal_id ? get_the_title($journal_id) : '—';
                ?>
                <tr>
                    <td><strong><?php echo esc_html($sub->post_title); ?></strong></td>
                    <td><?php echo esc_html($journal_name); ?></td>
                    <td><span class="tjm-status-badge tjm-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(Config::get_status_label($status)); ?></span></td>
                    <td><?php echo esc_html(get_the_date('d/m/Y', $sub)); ?></td>
                    <td><a href="?submission=<?php echo (int) $sub->ID; ?>" class="tjm-btn tjm-btn--secondary tjm-btn--sm"><?php esc_html_e('View', 'tainacan-journal-manager'); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
