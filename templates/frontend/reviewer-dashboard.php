<?php
/** @var \WP_Post[] $reviews */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
?>
<div class="tjm-portal">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Reviewer Dashboard', 'tainacan-journal-manager'); ?></h2>
    </header>

    <?php if (empty($reviews)) : ?>
        <div class="tjm-empty-state">
            <p><?php esc_html_e('You have no review assignments at the moment.', 'tainacan-journal-manager'); ?></p>
        </div>
    <?php else : ?>
        <table class="tjm-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Submission', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Deadline', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Actions', 'tainacan-journal-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $review) :
                    $status        = (string) get_post_meta($review->ID, Config::META_PREFIX . 'review_status', true) ?: Config::REVIEW_INVITED;
                    $deadline      = (string) get_post_meta($review->ID, Config::META_PREFIX . 'deadline', true);
                    $submission_id = (int) get_post_meta($review->ID, Config::META_PREFIX . 'submission_id', true);
                    $title         = $submission_id ? (string) get_the_title($submission_id) : (string) $review->post_title;
                ?>
                <tr>
                    <td><?php echo esc_html($title); ?></td>
                    <td><span class="tjm-status-badge"><?php echo esc_html($status); ?></span></td>
                    <td><?php echo $deadline ? esc_html(date_i18n('d/m/Y', strtotime($deadline))) : '—'; ?></td>
                    <td><a href="?review=<?php echo (int) $review->ID; ?>" class="tjm-btn tjm-btn--secondary tjm-btn--sm"><?php esc_html_e('Open', 'tainacan-journal-manager'); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
