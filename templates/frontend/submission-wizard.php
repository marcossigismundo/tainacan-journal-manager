<?php
/**
 * Multi-step submission wizard for an existing draft.
 *
 * @var array<string, mixed> $data       Submission data (title, abstract, etc.)
 * @var \WP_Post[]            $journals  Available journals
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;

$id          = (int) $data['id'];
$keywords    = is_array($data['keywords']) ? $data['keywords'] : [];
$coauthors   = is_array($data['coauthors']) ? $data['coauthors'] : [];
$decl        = $data['declarations'];
$manuscript  = $data['manuscript'] ?? null;
?>
<div class="tjm-portal tjm-wizard" data-submission-id="<?php echo (int) $id; ?>">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Edit submission', 'tainacan-journal-manager'); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back to list', 'tainacan-journal-manager'); ?></a>
    </header>

    <ol class="tjm-wizard-steps">
        <li data-step="1" class="tjm-wizard-step is-active"><?php esc_html_e('Manuscript', 'tainacan-journal-manager'); ?></li>
        <li data-step="2" class="tjm-wizard-step"><?php esc_html_e('Authors', 'tainacan-journal-manager'); ?></li>
        <li data-step="3" class="tjm-wizard-step"><?php esc_html_e('File', 'tainacan-journal-manager'); ?></li>
        <li data-step="4" class="tjm-wizard-step"><?php esc_html_e('Declarations', 'tainacan-journal-manager'); ?></li>
        <li data-step="5" class="tjm-wizard-step"><?php esc_html_e('Review &amp; submit', 'tainacan-journal-manager'); ?></li>
    </ol>

    <div class="tjm-wizard-message tjm-message"></div>

    <!-- ── STEP 1: Metadata ──────────────────────────────────────────── -->
    <section class="tjm-wizard-pane is-active" data-pane="1">
        <h3><?php esc_html_e('Manuscript metadata', 'tainacan-journal-manager'); ?></h3>

        <div class="tjm-field">
            <label for="tjm-w-title"><?php esc_html_e('Title', 'tainacan-journal-manager'); ?> *</label>
            <input type="text" id="tjm-w-title" name="title" value="<?php echo esc_attr((string) $data['title']); ?>" required>
        </div>

        <div class="tjm-field">
            <label for="tjm-w-journal"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?> *</label>
            <select id="tjm-w-journal" name="journal_id" required>
                <option value=""><?php esc_html_e('— choose a journal —', 'tainacan-journal-manager'); ?></option>
                <?php foreach ($journals as $j) : ?>
                    <option value="<?php echo (int) $j->ID; ?>" <?php selected((int) $data['journal_id'], (int) $j->ID); ?>><?php echo esc_html($j->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="tjm-field">
            <label for="tjm-w-abstract"><?php esc_html_e('Abstract', 'tainacan-journal-manager'); ?> *</label>
            <textarea id="tjm-w-abstract" name="abstract" rows="6" required><?php echo esc_textarea((string) $data['abstract']); ?></textarea>
        </div>

        <div class="tjm-field">
            <label for="tjm-w-keywords"><?php esc_html_e('Keywords (comma-separated)', 'tainacan-journal-manager'); ?></label>
            <input type="text" id="tjm-w-keywords" name="keywords" value="<?php echo esc_attr(implode(', ', array_map('strval', $keywords))); ?>">
        </div>

        <div class="tjm-field">
            <label for="tjm-w-language"><?php esc_html_e('Language', 'tainacan-journal-manager'); ?></label>
            <input type="text" id="tjm-w-language" name="language" value="<?php echo esc_attr((string) $data['language']); ?>" placeholder="<?php echo esc_attr__('e.g. en, pt-br, es', 'tainacan-journal-manager'); ?>">
        </div>

        <div class="tjm-field">
            <label for="tjm-w-references"><?php esc_html_e('References', 'tainacan-journal-manager'); ?></label>
            <textarea id="tjm-w-references" name="references" rows="4"><?php echo esc_textarea((string) $data['references']); ?></textarea>
        </div>

        <div class="tjm-field">
            <label for="tjm-w-funding"><?php esc_html_e('Funding agency', 'tainacan-journal-manager'); ?></label>
            <input type="text" id="tjm-w-funding" name="funding" value="<?php echo esc_attr((string) $data['funding']); ?>">
        </div>

        <div class="tjm-wizard-actions">
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="save-metadata"><?php esc_html_e('Save and continue', 'tainacan-journal-manager'); ?></button>
        </div>
    </section>

    <!-- ── STEP 2: Authors ──────────────────────────────────────────── -->
    <section class="tjm-wizard-pane" data-pane="2">
        <h3><?php esc_html_e('Co-authors', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('Add co-authors with affiliation and ORCID iD. The submitting user is automatically the corresponding author.', 'tainacan-journal-manager'); ?></p>

        <div id="tjm-coauthors-list">
            <?php if (empty($coauthors)) : ?>
                <p class="tjm-empty-inline" data-empty><?php esc_html_e('No co-authors yet.', 'tainacan-journal-manager'); ?></p>
            <?php else : foreach ($coauthors as $i => $a) : ?>
                <div class="tjm-coauthor-row" data-row>
                    <div class="tjm-field">
                        <label><?php esc_html_e('Name', 'tainacan-journal-manager'); ?></label>
                        <input type="text" data-field="name" value="<?php echo esc_attr((string) ($a['name'] ?? '')); ?>">
                    </div>
                    <div class="tjm-field">
                        <label><?php esc_html_e('Email', 'tainacan-journal-manager'); ?></label>
                        <input type="email" data-field="email" value="<?php echo esc_attr((string) ($a['email'] ?? '')); ?>">
                    </div>
                    <div class="tjm-field">
                        <label><?php esc_html_e('Affiliation', 'tainacan-journal-manager'); ?></label>
                        <input type="text" data-field="affiliation" value="<?php echo esc_attr((string) ($a['affiliation'] ?? '')); ?>">
                    </div>
                    <div class="tjm-field">
                        <label><?php esc_html_e('ORCID iD', 'tainacan-journal-manager'); ?></label>
                        <input type="text" data-field="orcid" value="<?php echo esc_attr((string) ($a['orcid'] ?? '')); ?>" placeholder="0000-0000-0000-0000">
                    </div>
                    <button type="button" class="tjm-btn tjm-btn--secondary tjm-btn--sm" data-action="remove-author"><?php esc_html_e('Remove', 'tainacan-journal-manager'); ?></button>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="tjm-wizard-actions">
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="add-author">+ <?php esc_html_e('Add co-author', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="prev"><?php esc_html_e('Back', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="save-authors"><?php esc_html_e('Save and continue', 'tainacan-journal-manager'); ?></button>
        </div>
    </section>

    <!-- ── STEP 3: Manuscript file ──────────────────────────────────── -->
    <section class="tjm-wizard-pane" data-pane="3">
        <h3><?php esc_html_e('Manuscript file', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('Upload the manuscript (PDF, DOC, DOCX, ODT, RTF or TEX). Maximum 20 MB.', 'tainacan-journal-manager'); ?></p>

        <div id="tjm-manuscript-current">
            <?php if ($manuscript) : ?>
                <div class="tjm-notice">
                    <?php esc_html_e('Current file:', 'tainacan-journal-manager'); ?>
                    <a href="<?php echo esc_url((string) $manuscript['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $manuscript['filename']); ?></a>
                </div>
            <?php else : ?>
                <p class="tjm-text-muted"><?php esc_html_e('No manuscript file uploaded yet.', 'tainacan-journal-manager'); ?></p>
            <?php endif; ?>
        </div>

        <div class="tjm-field">
            <label for="tjm-w-file"><?php esc_html_e('Choose file', 'tainacan-journal-manager'); ?></label>
            <input type="file" id="tjm-w-file" name="manuscript" accept=".pdf,.doc,.docx,.odt,.rtf,.tex">
        </div>

        <div class="tjm-wizard-actions">
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="prev"><?php esc_html_e('Back', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="upload-file"><?php esc_html_e('Upload', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="next"><?php esc_html_e('Skip / continue', 'tainacan-journal-manager'); ?></button>
        </div>
    </section>

    <!-- ── STEP 4: Declarations ─────────────────────────────────────── -->
    <section class="tjm-wizard-pane" data-pane="4">
        <h3><?php esc_html_e('Declarations', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('Please confirm the following statements before submitting.', 'tainacan-journal-manager'); ?></p>

        <label class="tjm-checkbox">
            <input type="checkbox" name="original" <?php checked((bool) $decl['original']); ?>>
            <span><?php esc_html_e('I confirm this manuscript is original and has not been published or simultaneously submitted elsewhere.', 'tainacan-journal-manager'); ?></span>
        </label>

        <label class="tjm-checkbox">
            <input type="checkbox" name="coi" <?php checked((bool) $decl['coi']); ?>>
            <span><?php esc_html_e('All authors disclose any conflicts of interest related to this work.', 'tainacan-journal-manager'); ?></span>
        </label>

        <label class="tjm-checkbox">
            <input type="checkbox" name="copyright" <?php checked((bool) $decl['copyright']); ?>>
            <span><?php esc_html_e('I agree with the licensing and copyright terms of the journal.', 'tainacan-journal-manager'); ?></span>
        </label>

        <label class="tjm-checkbox">
            <input type="checkbox" name="ethics" <?php checked((bool) $decl['ethics']); ?>>
            <span><?php esc_html_e('Where applicable, ethical approvals (human/animal subjects) are documented in the manuscript.', 'tainacan-journal-manager'); ?></span>
        </label>

        <div class="tjm-wizard-actions">
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="prev"><?php esc_html_e('Back', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="save-declarations"><?php esc_html_e('Save and continue', 'tainacan-journal-manager'); ?></button>
        </div>
    </section>

    <!-- ── STEP 5: Review & submit ──────────────────────────────────── -->
    <section class="tjm-wizard-pane" data-pane="5">
        <h3><?php esc_html_e('Review and submit', 'tainacan-journal-manager'); ?></h3>
        <p><?php esc_html_e('Once submitted, your draft will move to triage and you will not be able to edit it. The editor will be notified.', 'tainacan-journal-manager'); ?></p>

        <div class="tjm-wizard-actions">
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="prev"><?php esc_html_e('Back', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="finalize"><?php esc_html_e('Submit manuscript', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--danger" data-action="withdraw"><?php esc_html_e('Withdraw draft', 'tainacan-journal-manager'); ?></button>
        </div>
    </section>
</div>
