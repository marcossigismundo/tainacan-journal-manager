<?php
/** @var \WP_Post[] $submissions */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Production\ProofApprovalService;
?>
<div class="tjm-portal">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Copyediting &amp; Production', 'tainacan-journal-manager'); ?></h2>
    </header>

    <?php if (empty($submissions)) : ?>
        <div class="tjm-empty-state">
            <p><?php esc_html_e('No submissions in copyediting or production.', 'tainacan-journal-manager'); ?></p>
        </div>
    <?php else : ?>
        <table class="tjm-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Stage', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Galleys', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Proof', 'tainacan-journal-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub) :
                    $status     = (string) get_post_meta($sub->ID, Config::META_PREFIX . 'status', true);
                    $journal_id = (int) get_post_meta($sub->ID, Config::META_PREFIX . 'journal_id', true);
                    $journal    = $journal_id ? get_the_title($journal_id) : '—';
                    $galleys    = count(GalleyService::get_galleys($sub->ID));
                    $proof      = ProofApprovalService::get_status($sub->ID) ?: '—';
                ?>
                <tr>
                    <td><strong><?php echo esc_html($sub->post_title); ?></strong></td>
                    <td><?php echo esc_html((string) $journal); ?></td>
                    <td><span class="tjm-status-badge tjm-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(Config::get_status_label($status)); ?></span></td>
                    <td><?php echo (int) $galleys; ?></td>
                    <td><span class="tjm-status-badge"><?php echo esc_html((string) $proof); ?></span></td>
                    <td><a href="?submission=<?php echo (int) $sub->ID; ?>" class="tjm-btn tjm-btn--secondary tjm-btn--sm"><?php esc_html_e('Open', 'tainacan-journal-manager'); ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
