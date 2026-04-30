<?php
/**
 * Read-only submission detail view (after draft).
 *
 * @var array<string, mixed> $data
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;

$status     = (string) $data['status'];
$journal_id = (int) $data['journal_id'];
$journal    = $journal_id ? get_post($journal_id) : null;
$manuscript = $data['manuscript'] ?? null;
$history    = is_array($data['status_history']) ? $data['status_history'] : [];
$decisions  = is_array($data['decisions']) ? $data['decisions'] : [];
$keywords   = is_array($data['keywords']) ? $data['keywords'] : [];
$coauthors  = is_array($data['coauthors']) ? $data['coauthors'] : [];
$galleys    = isset($data['galleys']) && is_array($data['galleys']) ? $data['galleys'] : [];
$proof      = (string) ($data['proof_status'] ?? '');
?>
<div class="tjm-portal">
    <header class="tjm-portal-header">
        <h2><?php echo esc_html((string) $data['title']); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back to list', 'tainacan-journal-manager'); ?></a>
    </header>

    <div class="tjm-detail-meta">
        <span class="tjm-status-badge tjm-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(Config::get_status_label($status)); ?></span>
        <?php if ($journal) : ?>
            <span class="tjm-detail-journal"><?php esc_html_e('Journal:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html($journal->post_title); ?></strong></span>
        <?php endif; ?>
    </div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Abstract', 'tainacan-journal-manager'); ?></h3>
        <p><?php echo esc_html((string) $data['abstract']); ?></p>
    </div>

    <?php if (! empty($keywords)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Keywords', 'tainacan-journal-manager'); ?></h3>
        <p><?php echo esc_html(implode(', ', array_map('strval', $keywords))); ?></p>
    </div>
    <?php endif; ?>

    <?php if (! empty($coauthors)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Co-authors', 'tainacan-journal-manager'); ?></h3>
        <ul>
            <?php foreach ($coauthors as $a) : ?>
                <li>
                    <strong><?php echo esc_html((string) ($a['name'] ?? '')); ?></strong>
                    <?php if (! empty($a['affiliation'])) : ?>— <?php echo esc_html((string) $a['affiliation']); ?><?php endif; ?>
                    <?php if (! empty($a['orcid'])) : ?>(<?php echo esc_html((string) $a['orcid']); ?>)<?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($manuscript) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Manuscript file', 'tainacan-journal-manager'); ?></h3>
        <a href="<?php echo esc_url((string) $manuscript['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $manuscript['filename']); ?></a>
    </div>
    <?php endif; ?>

    <?php if (! empty($galleys)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Galleys', 'tainacan-journal-manager'); ?></h3>
        <ul>
            <?php foreach ($galleys as $g) : ?>
                <li>
                    <strong><?php echo esc_html(strtoupper((string) ($g['format'] ?? ''))); ?></strong>
                    — <a href="<?php echo esc_url((string) ($g['url'] ?? '')); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) ($g['label'] ?? __('Download', 'tainacan-journal-manager'))); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($status === \TainacanJournalManager\Config::STATUS_PRODUCTION && $proof !== '') : ?>
    <div class="tjm-section tjm-author-proof" data-submission-id="<?php echo (int) $data['id']; ?>">
        <h3><?php esc_html_e('Proof approval', 'tainacan-journal-manager'); ?></h3>
        <p>
            <?php esc_html_e('Status:', 'tainacan-journal-manager'); ?>
            <strong><?php echo esc_html($proof); ?></strong>
        </p>
        <?php if ($proof !== \TainacanJournalManager\Production\ProofApprovalService::STATUS_APPROVED) : ?>
            <p><?php esc_html_e('Review the galleys above. Approve them or describe the changes you need.', 'tainacan-journal-manager'); ?></p>
            <div class="tjm-message" id="tjm-proof-message"></div>
            <div class="tjm-field">
                <label for="tjm-proof-note"><?php esc_html_e('Notes (required when requesting changes)', 'tainacan-journal-manager'); ?></label>
                <textarea id="tjm-proof-note" rows="3"></textarea>
            </div>
            <div class="tjm-wizard-actions">
                <button type="button" class="tjm-btn tjm-btn--primary" data-action="proof-approve"><?php esc_html_e('Approve proof', 'tainacan-journal-manager'); ?></button>
                <button type="button" class="tjm-btn tjm-btn--danger"  data-action="proof-changes"><?php esc_html_e('Request changes', 'tainacan-journal-manager'); ?></button>
            </div>
        <?php else : ?>
            <p class="tjm-text-muted"><?php esc_html_e('You have approved the proof. The article will be published soon.', 'tainacan-journal-manager'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (! empty($history)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Status history', 'tainacan-journal-manager'); ?></h3>
        <ul class="tjm-history">
            <?php foreach (array_reverse($history) as $h) : ?>
                <li>
                    <span class="tjm-history-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) ($h['date'] ?? '')))); ?></span>
                    <?php echo esc_html(Config::get_status_label((string) ($h['from'] ?? ''))); ?> &rarr;
                    <strong><?php echo esc_html(Config::get_status_label((string) ($h['to'] ?? ''))); ?></strong>
                    <?php if (! empty($h['note'])) : ?> — <em><?php echo esc_html((string) $h['note']); ?></em><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (! empty($decisions)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Editorial decisions', 'tainacan-journal-manager'); ?></h3>
        <ul class="tjm-history">
            <?php foreach (array_reverse($decisions) as $d) : ?>
                <li>
                    <span class="tjm-history-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) ($d['date'] ?? '')))); ?></span>
                    <strong><?php echo esc_html((string) ($d['decision'] ?? '')); ?></strong>
                    <?php if (! empty($d['justification'])) : ?> — <?php echo esc_html((string) $d['justification']); ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
