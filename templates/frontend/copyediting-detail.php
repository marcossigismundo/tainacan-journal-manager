<?php
/**
 * Copyediting + production detail.
 *
 * @var array<string, mixed> $detail
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
use TainacanJournalManager\Production\CopyeditingService;
use TainacanJournalManager\Production\GalleyService;
use TainacanJournalManager\Production\ProofApprovalService;

$id      = (int) $detail['id'];
$status  = (string) $detail['status'];
$ce      = $detail['copyediting'];
$galleys = is_array($detail['galleys']) ? $detail['galleys'] : [];
$proof   = $detail['proof'];
?>
<div class="tjm-portal tjm-copyediting-detail" data-submission-id="<?php echo (int) $id; ?>">
    <header class="tjm-portal-header">
        <h2><?php echo esc_html((string) $detail['title']); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back', 'tainacan-journal-manager'); ?></a>
    </header>

    <div class="tjm-detail-meta">
        <span class="tjm-status-badge tjm-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(Config::get_status_label($status)); ?></span>
        <span><?php esc_html_e('Journal:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html((string) $detail['journal_name']); ?></strong></span>
    </div>

    <div class="tjm-message" id="tjm-prod-message"></div>

    <?php if ($detail['manuscript']) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Original manuscript', 'tainacan-journal-manager'); ?></h3>
        <a href="<?php echo esc_url((string) $detail['manuscript']['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) $detail['manuscript']['filename']); ?></a>
    </div>
    <?php endif; ?>

    <!-- ── Copyediting versions ─────────────────────────────────── -->
    <?php if ($status === Config::STATUS_COPYEDITING || ! empty($ce['versions'])) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Copyediting versions', 'tainacan-journal-manager'); ?></h3>

        <?php if (empty($ce['versions'])) : ?>
            <p class="tjm-text-muted"><?php esc_html_e('No edited versions yet.', 'tainacan-journal-manager'); ?></p>
        <?php else : ?>
            <ul class="tjm-history">
                <?php foreach (array_reverse((array) $ce['versions']) as $v) :
                    $att_id   = (int) ($v['attachment_id'] ?? 0);
                    $url      = $att_id ? wp_get_attachment_url($att_id) : '';
                    $uploader = isset($v['uploaded_by']) ? get_userdata((int) $v['uploaded_by']) : null;
                ?>
                    <li>
                        <span class="tjm-history-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) ($v['uploaded_at'] ?? '')))); ?></span>
                        <strong><?php echo esc_html((string) ($v['filename'] ?? '')); ?></strong>
                        <?php if ($url) : ?> — <a href="<?php echo esc_url((string) $url); ?>" target="_blank" rel="noopener"><?php esc_html_e('download', 'tainacan-journal-manager'); ?></a><?php endif; ?>
                        <?php if (! empty($v['role'])) : ?> · <em><?php echo esc_html((string) $v['role']); ?></em><?php endif; ?>
                        <?php if ($uploader) : ?> · <?php echo esc_html((string) ($uploader->display_name ?: $uploader->user_login)); ?><?php endif; ?>
                        <?php if (! empty($v['note'])) : ?><div><?php echo esc_html((string) $v['note']); ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($status === Config::STATUS_COPYEDITING) : ?>
        <h4><?php esc_html_e('Upload a new version', 'tainacan-journal-manager'); ?></h4>
        <div class="tjm-field">
            <input type="file" id="tjm-ce-file" accept=".pdf,.doc,.docx,.odt,.rtf,.tex">
        </div>
        <div class="tjm-field">
            <label for="tjm-ce-note"><?php esc_html_e('Notes for this round', 'tainacan-journal-manager'); ?></label>
            <textarea id="tjm-ce-note" rows="3"></textarea>
        </div>
        <div class="tjm-wizard-actions">
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="upload-copyediting"><?php esc_html_e('Upload version', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--secondary" data-action="notify-author"><?php esc_html_e('Notify author', 'tainacan-journal-manager'); ?></button>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="copyediting-done"><?php esc_html_e('Mark ready for production', 'tainacan-journal-manager'); ?></button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Galleys ──────────────────────────────────────────────── -->
    <?php if ($status === Config::STATUS_PRODUCTION || ! empty($galleys)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Galleys', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('Final delivery files (PDF, HTML, XML, EPUB or JATS).', 'tainacan-journal-manager'); ?></p>

        <?php if (empty($galleys)) : ?>
            <p class="tjm-text-muted"><?php esc_html_e('No galleys yet.', 'tainacan-journal-manager'); ?></p>
        <?php else : ?>
            <table class="tjm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Format', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Label', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Language', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('File', 'tainacan-journal-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($galleys as $g) : ?>
                        <tr data-att="<?php echo (int) ($g['attachment_id'] ?? 0); ?>">
                            <td><strong><?php echo esc_html(strtoupper((string) ($g['format'] ?? ''))); ?></strong></td>
                            <td><?php echo esc_html((string) ($g['label'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($g['language'] ?? '')); ?></td>
                            <td><a href="<?php echo esc_url((string) ($g['url'] ?? '')); ?>" target="_blank" rel="noopener"><?php esc_html_e('download', 'tainacan-journal-manager'); ?></a></td>
                            <td><button type="button" class="tjm-btn tjm-btn--danger tjm-btn--sm" data-action="remove-galley"><?php esc_html_e('Remove', 'tainacan-journal-manager'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($status === Config::STATUS_PRODUCTION) : ?>
        <h4><?php esc_html_e('Add a galley', 'tainacan-journal-manager'); ?></h4>
        <div class="tjm-field">
            <label for="tjm-galley-format"><?php esc_html_e('Format', 'tainacan-journal-manager'); ?></label>
            <select id="tjm-galley-format">
                <?php foreach (GalleyService::ALLOWED_FORMATS as $fmt) : ?>
                    <option value="<?php echo esc_attr($fmt); ?>"><?php echo esc_html(strtoupper($fmt)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="tjm-field">
            <label for="tjm-galley-label"><?php esc_html_e('Label', 'tainacan-journal-manager'); ?></label>
            <input type="text" id="tjm-galley-label" placeholder="<?php echo esc_attr__('e.g. PDF (Portuguese)', 'tainacan-journal-manager'); ?>">
        </div>
        <div class="tjm-field">
            <label for="tjm-galley-language"><?php esc_html_e('Language', 'tainacan-journal-manager'); ?></label>
            <input type="text" id="tjm-galley-language" placeholder="pt-br">
        </div>
        <div class="tjm-field">
            <input type="file" id="tjm-galley-file">
        </div>
        <button type="button" class="tjm-btn tjm-btn--primary" data-action="add-galley"><?php esc_html_e('Add galley', 'tainacan-journal-manager'); ?></button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Proof approval ───────────────────────────────────────── -->
    <?php if ($status === Config::STATUS_PRODUCTION) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Proof approval', 'tainacan-journal-manager'); ?></h3>
        <p>
            <?php esc_html_e('Status:', 'tainacan-journal-manager'); ?>
            <strong><?php echo esc_html((string) ($proof['status'] ?: __('not requested', 'tainacan-journal-manager'))); ?></strong>
        </p>
        <button type="button" class="tjm-btn tjm-btn--primary" data-action="proof-request"><?php esc_html_e('Send proof to author', 'tainacan-journal-manager'); ?></button>

        <?php if (! empty($proof['history'])) : ?>
            <h4><?php esc_html_e('Proof history', 'tainacan-journal-manager'); ?></h4>
            <ul class="tjm-history">
                <?php foreach (array_reverse((array) $proof['history']) as $h) :
                    $u = isset($h['user_id']) ? get_userdata((int) $h['user_id']) : null;
                ?>
                    <li>
                        <span class="tjm-history-date"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) ($h['date'] ?? '')))); ?></span>
                        <strong><?php echo esc_html((string) ($h['action'] ?? '')); ?></strong>
                        <?php if ($u) : ?> · <?php echo esc_html((string) ($u->display_name ?: $u->user_login)); ?><?php endif; ?>
                        <?php if (! empty($h['note'])) : ?> — <em><?php echo esc_html((string) $h['note']); ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Publish ──────────────────────────────────────────────── -->
    <?php if ($status === Config::STATUS_PRODUCTION) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Publish', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('Publishing pushes the article to the journal\'s Tainacan collection and notifies the author.', 'tainacan-journal-manager'); ?></p>
        <p class="tjm-text-muted">
            <?php esc_html_e('Requirements:', 'tainacan-journal-manager'); ?>
            <?php esc_html_e('at least one galley + author proof approval.', 'tainacan-journal-manager'); ?>
        </p>
        <button type="button" class="tjm-btn tjm-btn--primary" data-action="publish-article"><?php esc_html_e('Publish article', 'tainacan-journal-manager'); ?></button>
    </div>
    <?php endif; ?>
</div>
