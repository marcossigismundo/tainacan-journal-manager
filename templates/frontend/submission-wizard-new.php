<?php
/**
 * Wizard entry point — create new submission. Once a draft exists it
 * redirects (client-side) to ?submission=N where the full wizard runs.
 *
 * @var \WP_Post[] $journals
 */
if (! defined('ABSPATH')) exit;
?>
<div class="tjm-portal tjm-wizard-new">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('New submission', 'tainacan-journal-manager'); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back to list', 'tainacan-journal-manager'); ?></a>
    </header>

    <div class="tjm-message" id="tjm-new-message"></div>

    <div class="tjm-section">
        <p><?php esc_html_e('Start by giving your manuscript a working title and choosing a journal. You can edit everything in the next steps.', 'tainacan-journal-manager'); ?></p>

        <div class="tjm-field">
            <label for="tjm-new-title"><?php esc_html_e('Working title', 'tainacan-journal-manager'); ?> *</label>
            <input type="text" id="tjm-new-title" required>
        </div>

        <div class="tjm-field">
            <label for="tjm-new-journal"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?> *</label>
            <select id="tjm-new-journal" required>
                <option value=""><?php esc_html_e('— choose a journal —', 'tainacan-journal-manager'); ?></option>
                <?php foreach ($journals as $j) : ?>
                    <option value="<?php echo (int) $j->ID; ?>"><?php echo esc_html($j->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="button" class="tjm-btn tjm-btn--primary" id="tjm-new-create"><?php esc_html_e('Create draft', 'tainacan-journal-manager'); ?></button>
    </div>
</div>
