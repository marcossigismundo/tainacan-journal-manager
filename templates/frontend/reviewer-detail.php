<?php
/**
 * Reviewer's parecer view: accept/decline + configurable form.
 *
 * @var array<string, mixed> $detail
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
use TainacanJournalManager\Review\ReviewFormConfig;

$rid       = (int) $detail['review_id'];
$status    = (string) $detail['review_status'];
$type      = (string) $detail['review_type'];
$sections  = is_array($detail['sections']) ? $detail['sections'] : [];
$section_v = is_array($detail['section_comments']) ? $detail['section_comments'] : [];
$authors   = is_array($detail['authors']) ? $detail['authors'] : [];
$is_submitted = $status === Config::REVIEW_SUBMITTED;
$is_invited   = $status === Config::REVIEW_INVITED;
?>
<div class="tjm-portal tjm-reviewer-detail" data-review-id="<?php echo (int) $rid; ?>">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Peer review', 'tainacan-journal-manager'); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back to dashboard', 'tainacan-journal-manager'); ?></a>
    </header>

    <div class="tjm-detail-meta">
        <span class="tjm-status-badge"><?php echo esc_html($status); ?></span>
        <?php if (! empty($detail['deadline'])) : ?>
            <span><?php esc_html_e('Deadline:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime((string) $detail['deadline']))); ?></strong></span>
        <?php endif; ?>
        <span><?php esc_html_e('Review type:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html($type); ?></strong></span>
    </div>

    <div class="tjm-message" id="tjm-review-message"></div>

    <div class="tjm-section">
        <h3><?php echo esc_html((string) $detail['title']); ?></h3>
        <p><?php echo esc_html((string) $detail['abstract']); ?></p>

        <?php if (! empty($authors)) : ?>
            <h4><?php esc_html_e('Authors', 'tainacan-journal-manager'); ?></h4>
            <ul>
                <?php foreach ($authors as $a) : ?>
                    <li>
                        <strong><?php echo esc_html((string) ($a['name'] ?? '')); ?></strong>
                        <?php if (! empty($a['affiliation'])) : ?> — <?php echo esc_html((string) $a['affiliation']); ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (! empty($detail['manuscript'])) : ?>
            <p>
                <a href="<?php echo esc_url((string) $detail['manuscript']['url']); ?>" target="_blank" rel="noopener" class="tjm-btn tjm-btn--secondary tjm-btn--sm">
                    &darr; <?php echo esc_html((string) $detail['manuscript']['filename']); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($is_invited) : ?>
        <div class="tjm-section">
            <h3><?php esc_html_e('Accept or decline', 'tainacan-journal-manager'); ?></h3>
            <p><?php esc_html_e('Please respond to this invitation. If you accept, you will be able to fill in the parecer below.', 'tainacan-journal-manager'); ?></p>
            <div class="tjm-wizard-actions">
                <button type="button" class="tjm-btn tjm-btn--primary" data-action="accept-review"><?php esc_html_e('Accept invitation', 'tainacan-journal-manager'); ?></button>
                <button type="button" class="tjm-btn tjm-btn--danger" data-action="decline-review"><?php esc_html_e('Decline', 'tainacan-journal-manager'); ?></button>
            </div>
            <div class="tjm-decline-reason" hidden>
                <div class="tjm-field">
                    <label for="tjm-decline-reason"><?php esc_html_e('Reason for declining (optional)', 'tainacan-journal-manager'); ?></label>
                    <textarea id="tjm-decline-reason" rows="3"></textarea>
                </div>
                <button type="button" class="tjm-btn tjm-btn--danger" data-action="confirm-decline"><?php esc_html_e('Confirm decline', 'tainacan-journal-manager'); ?></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($status === Config::REVIEW_ACCEPTED || $is_submitted) : ?>
        <div class="tjm-section">
            <h3><?php esc_html_e('Review form', 'tainacan-journal-manager'); ?></h3>

            <?php if ($is_submitted) : ?>
                <p class="tjm-text-muted"><?php esc_html_e('You have submitted this review. The form is now read-only.', 'tainacan-journal-manager'); ?></p>
            <?php endif; ?>

            <?php foreach ($sections as $section) : ?>
                <div class="tjm-field">
                    <label><?php echo esc_html(ReviewFormConfig::label($section)); ?></label>
                    <textarea data-section="<?php echo esc_attr($section); ?>" rows="3" <?php disabled($is_submitted); ?>><?php echo esc_textarea((string) ($section_v[$section] ?? '')); ?></textarea>
                </div>
            <?php endforeach; ?>

            <div class="tjm-field">
                <label for="tjm-author-comments"><?php esc_html_e('Comments to the author', 'tainacan-journal-manager'); ?> *</label>
                <textarea id="tjm-author-comments" rows="6" <?php disabled($is_submitted); ?>><?php echo esc_textarea((string) $detail['author_comments']); ?></textarea>
            </div>

            <div class="tjm-field">
                <label for="tjm-editor-comments"><?php esc_html_e('Confidential comments to the editor', 'tainacan-journal-manager'); ?></label>
                <textarea id="tjm-editor-comments" rows="4" <?php disabled($is_submitted); ?>><?php echo esc_textarea((string) $detail['editor_comments']); ?></textarea>
            </div>

            <div class="tjm-field">
                <label for="tjm-recommendation"><?php esc_html_e('Recommendation', 'tainacan-journal-manager'); ?> *</label>
                <select id="tjm-recommendation" <?php disabled($is_submitted); ?>>
                    <option value=""><?php esc_html_e('— choose —', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(Config::RECOMMEND_ACCEPT); ?>" <?php selected((string) $detail['recommendation'], Config::RECOMMEND_ACCEPT); ?>><?php esc_html_e('Accept', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(Config::RECOMMEND_REVISIONS_MINOR); ?>" <?php selected((string) $detail['recommendation'], Config::RECOMMEND_REVISIONS_MINOR); ?>><?php esc_html_e('Minor revisions', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(Config::RECOMMEND_REVISIONS_MAJOR); ?>" <?php selected((string) $detail['recommendation'], Config::RECOMMEND_REVISIONS_MAJOR); ?>><?php esc_html_e('Major revisions', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(Config::RECOMMEND_RESUBMIT_REVIEW); ?>" <?php selected((string) $detail['recommendation'], Config::RECOMMEND_RESUBMIT_REVIEW); ?>><?php esc_html_e('Resubmit for review', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(Config::RECOMMEND_REJECT); ?>" <?php selected((string) $detail['recommendation'], Config::RECOMMEND_REJECT); ?>><?php esc_html_e('Reject', 'tainacan-journal-manager'); ?></option>
                </select>
            </div>

            <?php if (! $is_submitted) : ?>
                <button type="button" class="tjm-btn tjm-btn--primary" data-action="submit-review"><?php esc_html_e('Submit review', 'tainacan-journal-manager'); ?></button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
