<?php
/**
 * @var array<string,int>     $stats
 * @var \WP_Post[]            $recent
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
?>
<div class="tjm-portal">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Editorial Dashboard', 'tainacan-journal-manager'); ?></h2>
    </header>

    <div class="tjm-cards-grid">
        <?php foreach (Config::SUBMISSION_STATUSES as $status_key => $label) :
            $count = $stats[$status_key] ?? 0;
        ?>
        <div class="tjm-card tjm-card--<?php echo esc_attr($status_key); ?>">
            <div class="tjm-card-number"><?php echo (int) $count; ?></div>
            <div class="tjm-card-label"><?php echo esc_html(Config::get_status_label($status_key)); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Recent Submissions', 'tainacan-journal-manager'); ?></h3>

        <?php if (empty($recent)) : ?>
            <p class="tjm-text-muted"><?php esc_html_e('No submissions yet.', 'tainacan-journal-manager'); ?></p>
        <?php else : ?>
            <table class="tjm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Author', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Updated', 'tainacan-journal-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $sub) :
                        $status     = (string) get_post_meta($sub->ID, Config::META_PREFIX . 'status', true) ?: Config::STATUS_DRAFT;
                        $journal_id = (int) get_post_meta($sub->ID, Config::META_PREFIX . 'journal_id', true);
                        $journal    = $journal_id ? get_the_title($journal_id) : '—';
                        $author     = get_userdata((int) $sub->post_author);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($sub->post_title); ?></strong></td>
                        <td><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></td>
                        <td><?php echo esc_html((string) $journal); ?></td>
                        <td><span class="tjm-status-badge tjm-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(Config::get_status_label($status)); ?></span></td>
                        <td><?php echo esc_html(get_the_modified_date('d/m/Y', $sub)); ?></td>
                        <td><a href="?submission=<?php echo (int) $sub->ID; ?>" class="tjm-btn tjm-btn--secondary tjm-btn--sm"><?php esc_html_e('Manage', 'tainacan-journal-manager'); ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
