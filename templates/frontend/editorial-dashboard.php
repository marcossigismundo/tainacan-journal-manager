<?php
/** @var array<string,int> $stats */
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
        <p class="tjm-text-muted"><?php esc_html_e('Submission management features will appear here in the next development phase.', 'tainacan-journal-manager'); ?></p>
    </div>
</div>
