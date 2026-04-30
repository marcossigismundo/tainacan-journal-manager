<?php
/**
 * Editorial detail view: reviewer assignment + decision recording.
 *
 * @var array<string, mixed> $detail
 * @var \WP_User[]           $reviewers_list
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
use TainacanJournalManager\Editorial\WorkflowManager;

$id          = (int) $detail['id'];
$status      = (string) $detail['status'];
$next_states = WorkflowManager::get_allowed_next_statuses($id);
$manuscript  = $detail['manuscript'] ?? null;
$reviewers   = is_array($detail['reviewers']) ? $detail['reviewers'] : [];
$authors     = is_array($detail['authors']) ? $detail['authors'] : [];
$history     = is_array($detail['status_history']) ? $detail['status_history'] : [];
$decisions   = is_array($detail['decisions']) ? $detail['decisions'] : [];
?>
<div class="tjm-portal tjm-editorial-detail" data-submission-id="<?php echo (int) $id; ?>">
    <header class="tjm-portal-header">
        <h2><?php echo esc_html((string) $detail['title']); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back to dashboard', 'tainacan-journal-manager'); ?></a>
    </header>

    <div class="tjm-detail-meta">
        <span class="tjm-status-badge tjm-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(Config::get_status_label($status)); ?></span>
        <span class="tjm-detail-journal"><?php esc_html_e('Journal:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html((string) $detail['journal_name']); ?></strong></span>
        <span class="tjm-detail-journal"><?php esc_html_e('Review type:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html((string) $detail['review_type']); ?></strong></span>
    </div>

    <div class="tjm-message" id="tjm-editorial-message"></div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Abstract', 'tainacan-journal-manager'); ?></h3>
        <p><?php echo esc_html((string) $detail['abstract']); ?></p>
    </div>

    <?php if (! empty($authors)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Authors', 'tainacan-journal-manager'); ?></h3>
        <ul>
            <?php foreach ($authors as $a) : ?>
                <li>
                    <strong><?php echo esc_html((string) ($a['name'] ?? '')); ?></strong>
                    <?php if (! empty($a['affiliation'])) : ?> — <?php echo esc_html((string) $a['affiliation']); ?><?php endif; ?>
                    <?php if (! empty($a['orcid'])) : ?> (<?php echo esc_html((string) $a['orcid']); ?>)<?php endif; ?>
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

    <!-- ── Workflow controls ─────────────────────────────────────── -->
    <div class="tjm-section">
        <h3><?php esc_html_e('Workflow', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted">
            <?php esc_html_e('Allowed next states from current status:', 'tainacan-journal-manager'); ?>
            <?php echo esc_html(empty($next_states) ? __('— none —', 'tainacan-journal-manager') : implode(', ', $next_states)); ?>
        </p>

        <div class="tjm-wizard-actions">
            <?php if (in_array(Config::STATUS_TRIAGE, $next_states, true)) : ?>
                <button type="button" class="tjm-btn tjm-btn--secondary" data-action="to-triage"><?php esc_html_e('Move to triage', 'tainacan-journal-manager'); ?></button>
            <?php endif; ?>
            <?php if (in_array(Config::STATUS_REVIEW, $next_states, true)) : ?>
                <button type="button" class="tjm-btn tjm-btn--secondary" data-action="to-review"><?php esc_html_e('Move to peer review', 'tainacan-journal-manager'); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Reviewer assignment ───────────────────────────────────── -->
    <div class="tjm-section">
        <h3><?php esc_html_e('Reviewers', 'tainacan-journal-manager'); ?></h3>

        <?php if (empty($reviewers)) : ?>
            <p class="tjm-text-muted"><?php esc_html_e('No reviewers invited yet.', 'tainacan-journal-manager'); ?></p>
        <?php else : ?>
            <table class="tjm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Reviewer', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Recommendation', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Deadline', 'tainacan-journal-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviewers as $r) : ?>
                        <tr>
                            <td><?php echo esc_html($r['user']->display_name ?: $r['user']->user_login); ?></td>
                            <td><span class="tjm-status-badge"><?php echo esc_html((string) $r['review_status']); ?></span></td>
                            <td><?php echo esc_html((string) $r['recommendation']); ?></td>
                            <td><?php echo $r['deadline'] ? esc_html(date_i18n('d/m/Y', strtotime((string) $r['deadline']))) : '—'; ?></td>
                        </tr>
                        <?php if (! empty($r['author_comments'])) : ?>
                        <tr><td colspan="4">
                            <details>
                                <summary><?php esc_html_e('Comments to author', 'tainacan-journal-manager'); ?></summary>
                                <p><?php echo nl2br(esc_html((string) $r['author_comments'])); ?></p>
                            </details>
                            <?php if (! empty($r['editor_comments'])) : ?>
                            <details>
                                <summary><?php esc_html_e('Confidential comments to editor', 'tainacan-journal-manager'); ?></summary>
                                <p><?php echo nl2br(esc_html((string) $r['editor_comments'])); ?></p>
                            </details>
                            <?php endif; ?>
                        </td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h4><?php esc_html_e('Invite a reviewer', 'tainacan-journal-manager'); ?></h4>
        <div class="tjm-invite-row">
            <div class="tjm-field">
                <label for="tjm-invite-reviewer"><?php esc_html_e('Reviewer', 'tainacan-journal-manager'); ?></label>
                <select id="tjm-invite-reviewer">
                    <option value=""><?php esc_html_e('— choose a reviewer —', 'tainacan-journal-manager'); ?></option>
                    <?php foreach ($reviewers_list as $u) :
                        $already = false;
                        foreach ($reviewers as $r) {
                            if ((int) $r['user']->ID === (int) $u->ID) { $already = true; break; }
                        }
                    ?>
                        <option value="<?php echo (int) $u->ID; ?>" <?php disabled($already); ?>><?php echo esc_html($u->display_name ?: $u->user_login); ?><?php echo $already ? ' — ' . esc_html__('invited', 'tainacan-journal-manager') : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tjm-field">
                <label for="tjm-invite-deadline"><?php esc_html_e('Deadline', 'tainacan-journal-manager'); ?></label>
                <input type="date" id="tjm-invite-deadline" value="<?php echo esc_attr(date('Y-m-d', strtotime('+' . (int) get_option(Config::OPTION_REVIEW_DEADLINE_DAYS, Config::DEFAULT_REVIEW_DEADLINE) . ' days'))); ?>">
            </div>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="invite-reviewer"><?php esc_html_e('Send invitation', 'tainacan-journal-manager'); ?></button>
        </div>
    </div>

    <!-- ── Decision ──────────────────────────────────────────────── -->
    <div class="tjm-section">
        <h3><?php esc_html_e('Editorial decision', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('The editor always makes the final call. Reviewer recommendations are a guide, never a vote.', 'tainacan-journal-manager'); ?></p>

        <div class="tjm-field">
            <label for="tjm-decision"><?php esc_html_e('Decision', 'tainacan-journal-manager'); ?></label>
            <select id="tjm-decision">
                <option value=""><?php esc_html_e('— choose a decision —', 'tainacan-journal-manager'); ?></option>
                <option value="<?php echo esc_attr(Config::DECISION_ACCEPT); ?>"><?php esc_html_e('Accept', 'tainacan-journal-manager'); ?></option>
                <option value="<?php echo esc_attr(Config::DECISION_REVISIONS); ?>"><?php esc_html_e('Request revisions', 'tainacan-journal-manager'); ?></option>
                <option value="<?php echo esc_attr(Config::DECISION_RESUBMIT); ?>"><?php esc_html_e('Resubmit for review', 'tainacan-journal-manager'); ?></option>
                <option value="<?php echo esc_attr(Config::DECISION_REJECT); ?>"><?php esc_html_e('Reject', 'tainacan-journal-manager'); ?></option>
            </select>
        </div>
        <div class="tjm-field">
            <label for="tjm-decision-note"><?php esc_html_e('Justification (sent to author)', 'tainacan-journal-manager'); ?></label>
            <textarea id="tjm-decision-note" rows="4"></textarea>
        </div>
        <button type="button" class="tjm-btn tjm-btn--primary" data-action="record-decision"><?php esc_html_e('Record decision', 'tainacan-journal-manager'); ?></button>
    </div>

    <?php if (in_array($status, [\TainacanJournalManager\Config::STATUS_COPYEDITING, \TainacanJournalManager\Config::STATUS_PRODUCTION, \TainacanJournalManager\Config::STATUS_PUBLISHED], true)) : ?>
    <div class="tjm-section">
        <h3><?php esc_html_e('Production', 'tainacan-journal-manager'); ?></h3>
        <p class="tjm-text-muted"><?php esc_html_e('Use the dedicated copyediting dashboard to manage versions, galleys and proof. Once requirements are met, an editor can publish the article from there.', 'tainacan-journal-manager'); ?></p>
        <p>
            <a class="tjm-btn tjm-btn--secondary" href="<?php echo esc_url(\TainacanJournalManager\Config::page_url(\TainacanJournalManager\Config::PAGE_COPYEDITING) . '?submission=' . (int) $id); ?>">
                <?php esc_html_e('Open in production dashboard', 'tainacan-journal-manager'); ?>
            </a>
            <a class="tjm-btn tjm-btn--secondary" href="?issues=1&journal=<?php echo (int) $detail['journal_id']; ?>">
                <?php esc_html_e('Manage issues', 'tainacan-journal-manager'); ?>
            </a>
        </p>
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
</div>
