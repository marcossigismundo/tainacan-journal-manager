<?php
/**
 * Editorial indicators dashboard.
 *
 * @var int  $journal_id
 * @var bool $can_export
 */
if (! defined('ABSPATH')) exit;
?>
<div class="tjm-indicators" data-journal-id="<?php echo (int) $journal_id; ?>">
    <header class="tjm-indicators-header">
        <h2><?php esc_html_e('Editorial Indicators', 'tainacan-journal-manager'); ?></h2>
        <div class="tjm-indicators-actions">
            <button type="button" class="tjm-btn tjm-btn--secondary tjm-btn--sm" data-action="print"><?php esc_html_e('Print / PDF', 'tainacan-journal-manager'); ?></button>
            <?php if ($can_export) : ?>
                <button type="button" class="tjm-btn tjm-btn--secondary tjm-btn--sm" data-action="export-csv"><?php esc_html_e('Export CSV', 'tainacan-journal-manager'); ?></button>
            <?php endif; ?>
        </div>
    </header>

    <div class="tjm-message" id="tjm-ind-message"></div>

    <div class="tjm-cards-grid" id="tjm-ind-cards">
        <div class="tjm-card"><div class="tjm-card-number" data-stat="submissions">—</div><div class="tjm-card-label"><?php esc_html_e('Submissions', 'tainacan-journal-manager'); ?></div></div>
        <div class="tjm-card tjm-card--success"><div class="tjm-card-number" data-stat="published">—</div><div class="tjm-card-label"><?php esc_html_e('Published', 'tainacan-journal-manager'); ?></div></div>
        <div class="tjm-card"><div class="tjm-card-number" data-stat="reviews">—</div><div class="tjm-card-label"><?php esc_html_e('Reviews', 'tainacan-journal-manager'); ?></div></div>
        <div class="tjm-card tjm-card--primary"><div class="tjm-card-number" data-stat="journals">—</div><div class="tjm-card-label"><?php esc_html_e('Journals', 'tainacan-journal-manager'); ?></div></div>
        <div class="tjm-card"><div class="tjm-card-number" data-stat="issues">—</div><div class="tjm-card-label"><?php esc_html_e('Issues', 'tainacan-journal-manager'); ?></div></div>
        <div class="tjm-card"><div class="tjm-card-number" data-stat="acceptance_rate">—</div><div class="tjm-card-label"><?php esc_html_e('Acceptance rate', 'tainacan-journal-manager'); ?></div></div>
    </div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Submissions per status', 'tainacan-journal-manager'); ?></h3>
        <canvas id="tjm-chart-status" height="120"></canvas>
    </div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Submissions per month (last 12)', 'tainacan-journal-manager'); ?></h3>
        <canvas id="tjm-chart-monthly" height="120"></canvas>
    </div>

    <?php if (! $journal_id) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Submissions per journal', 'tainacan-journal-manager'); ?></h3>
        <canvas id="tjm-chart-journals" height="160"></canvas>
    </div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Top journals (published)', 'tainacan-journal-manager'); ?></h3>
        <canvas id="tjm-chart-top-journals" height="160"></canvas>
    </div>
    <?php endif; ?>

    <div class="tjm-section">
        <h3><?php esc_html_e('Top reviewers', 'tainacan-journal-manager'); ?></h3>
        <canvas id="tjm-chart-reviewers" height="160"></canvas>
    </div>
</div>
