<?php
/**
 * Issue management UI (under [tjm_editorial_dashboard]?issues=1).
 *
 * @var \WP_Post[] $journals
 * @var int        $journal_id
 * @var \WP_Post[] $issues
 * @var \WP_Post[] $publishable
 */
if (! defined('ABSPATH')) exit;

use TainacanJournalManager\Config;
use TainacanJournalManager\Issues\IssueManager;
?>
<div class="tjm-portal tjm-issues-mgmt">
    <header class="tjm-portal-header">
        <h2><?php esc_html_e('Issues management', 'tainacan-journal-manager'); ?></h2>
        <a href="?" class="tjm-btn tjm-btn--secondary tjm-btn--sm">&larr; <?php esc_html_e('Back to dashboard', 'tainacan-journal-manager'); ?></a>
    </header>

    <div class="tjm-message" id="tjm-issues-message"></div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Pick a journal', 'tainacan-journal-manager'); ?></h3>
        <form method="get" class="tjm-invite-row">
            <input type="hidden" name="issues" value="1">
            <div class="tjm-field">
                <label for="tjm-iss-journal"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></label>
                <select id="tjm-iss-journal" name="journal">
                    <option value="0"><?php esc_html_e('— select —', 'tainacan-journal-manager'); ?></option>
                    <?php foreach ($journals as $j) : ?>
                        <option value="<?php echo (int) $j->ID; ?>" <?php selected((int) $journal_id, (int) $j->ID); ?>><?php echo esc_html((string) $j->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="tjm-btn tjm-btn--secondary"><?php esc_html_e('Load', 'tainacan-journal-manager'); ?></button>
        </form>
    </div>

    <?php if ($journal_id > 0) : ?>
    <div class="tjm-section tjm-issues-create" data-journal-id="<?php echo (int) $journal_id; ?>">
        <h3><?php esc_html_e('Create new issue', 'tainacan-journal-manager'); ?></h3>
        <div class="tjm-invite-row">
            <div class="tjm-field">
                <label><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></label>
                <input type="text" id="tjm-new-issue-title" placeholder="<?php echo esc_attr__('e.g. v.10 n.2 (2026)', 'tainacan-journal-manager'); ?>">
            </div>
            <div class="tjm-field">
                <label><?php esc_html_e('Volume', 'tainacan-journal-manager'); ?></label>
                <input type="text" id="tjm-new-issue-volume">
            </div>
            <div class="tjm-field">
                <label><?php esc_html_e('Number', 'tainacan-journal-manager'); ?></label>
                <input type="text" id="tjm-new-issue-number">
            </div>
            <div class="tjm-field">
                <label><?php esc_html_e('Year', 'tainacan-journal-manager'); ?></label>
                <input type="number" id="tjm-new-issue-year" value="<?php echo (int) gmdate('Y'); ?>">
            </div>
            <div class="tjm-field">
                <label><?php esc_html_e('Type', 'tainacan-journal-manager'); ?></label>
                <select id="tjm-new-issue-type">
                    <option value="<?php echo esc_attr(IssueManager::TYPE_REGULAR); ?>"><?php esc_html_e('Regular', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(IssueManager::TYPE_SPECIAL); ?>"><?php esc_html_e('Special', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(IssueManager::TYPE_DOSSIER); ?>"><?php esc_html_e('Dossier', 'tainacan-journal-manager'); ?></option>
                    <option value="<?php echo esc_attr(IssueManager::TYPE_CONTINUOUS); ?>"><?php esc_html_e('Continuous', 'tainacan-journal-manager'); ?></option>
                </select>
            </div>
            <button type="button" class="tjm-btn tjm-btn--primary" data-action="create-issue"><?php esc_html_e('Create', 'tainacan-journal-manager'); ?></button>
        </div>
    </div>

    <div class="tjm-section">
        <h3><?php esc_html_e('Existing issues', 'tainacan-journal-manager'); ?></h3>
        <?php if (empty($issues)) : ?>
            <p class="tjm-text-muted"><?php esc_html_e('No issues yet.', 'tainacan-journal-manager'); ?></p>
        <?php else : ?>
            <?php foreach ($issues as $iss) :
                $articles = IssueManager::get_article_ids((int) $iss->ID);
                $vol  = (string) get_post_meta($iss->ID, Config::META_PREFIX . 'volume', true);
                $num  = (string) get_post_meta($iss->ID, Config::META_PREFIX . 'number', true);
                $year = (int) get_post_meta($iss->ID, Config::META_PREFIX . 'year', true);
                $type = (string) get_post_meta($iss->ID, Config::META_PREFIX . 'publication_type', true);
                $published = (bool) get_post_meta($iss->ID, Config::META_PREFIX . 'issue_published', true);
            ?>
            <div class="tjm-issue-card" data-issue-id="<?php echo (int) $iss->ID; ?>">
                <div class="tjm-issue-card-header">
                    <h4><?php echo esc_html((string) $iss->post_title); ?></h4>
                    <p class="tjm-detail-meta">
                        <?php if ($vol)  : ?><span>v. <?php echo esc_html($vol); ?></span><?php endif; ?>
                        <?php if ($num)  : ?><span>n. <?php echo esc_html($num); ?></span><?php endif; ?>
                        <?php if ($year) : ?><span><?php echo (int) $year; ?></span><?php endif; ?>
                        <span><?php echo esc_html($type); ?></span>
                        <span class="tjm-status-badge tjm-status-<?php echo $published ? 'published' : 'draft'; ?>">
                            <?php echo $published ? esc_html__('Published', 'tainacan-journal-manager') : esc_html__('Draft', 'tainacan-journal-manager'); ?>
                        </span>
                    </p>
                </div>

                <details>
                    <summary><?php
                        printf(
                            /* translators: %d: count */
                            esc_html__('%d articles', 'tainacan-journal-manager'),
                            (int) count($articles)
                        );
                    ?></summary>
                    <ul class="tjm-history">
                        <?php foreach ($articles as $sid) : ?>
                            <li>
                                <strong><?php echo esc_html((string) get_the_title((int) $sid)); ?></strong>
                                <button type="button" class="tjm-btn tjm-btn--secondary tjm-btn--sm" data-action="unassign-article" data-submission-id="<?php echo (int) $sid; ?>"><?php esc_html_e('Unassign', 'tainacan-journal-manager'); ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>

                <div class="tjm-invite-row">
                    <div class="tjm-field">
                        <label><?php esc_html_e('Assign article', 'tainacan-journal-manager'); ?></label>
                        <select class="tjm-assign-select">
                            <option value=""><?php esc_html_e('— pick —', 'tainacan-journal-manager'); ?></option>
                            <?php foreach ($publishable as $p) :
                                if (in_array((int) $p->ID, $articles, true)) continue;
                            ?>
                                <option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html((string) $p->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="tjm-btn tjm-btn--primary" data-action="assign-article"><?php esc_html_e('Assign', 'tainacan-journal-manager'); ?></button>
                    <?php if (! $published) : ?>
                        <button type="button" class="tjm-btn tjm-btn--primary" data-action="publish-issue"><?php esc_html_e('Publish issue', 'tainacan-journal-manager'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
