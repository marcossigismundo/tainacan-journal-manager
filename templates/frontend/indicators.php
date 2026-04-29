<?php
/** @var array $stats */
if (! defined('ABSPATH')) exit;
?>
<div class="tjm-indicators">
    <header class="tjm-indicators-header">
        <h2><?php esc_html_e('Editorial Indicators', 'tainacan-journal-manager'); ?></h2>
    </header>

    <div class="tjm-cards-grid">
        <div class="tjm-card">
            <div class="tjm-card-number"><?php echo (int) ($stats['total_submissions'] ?? 0); ?></div>
            <div class="tjm-card-label"><?php esc_html_e('Total Submissions', 'tainacan-journal-manager'); ?></div>
        </div>
        <div class="tjm-card tjm-card--success">
            <div class="tjm-card-number"><?php echo (int) ($stats['total_published'] ?? 0); ?></div>
            <div class="tjm-card-label"><?php esc_html_e('Published Articles', 'tainacan-journal-manager'); ?></div>
        </div>
        <div class="tjm-card tjm-card--primary">
            <div class="tjm-card-number"><?php echo (int) ($stats['total_journals'] ?? 0); ?></div>
            <div class="tjm-card-label"><?php esc_html_e('Active Journals', 'tainacan-journal-manager'); ?></div>
        </div>
    </div>

    <p class="tjm-text-muted" style="margin-top:24px;">
        <?php esc_html_e('Detailed charts (acceptance rate, processing times, top reviewers) will be added in the next development phase.', 'tainacan-journal-manager'); ?>
    </p>
</div>
