<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Entities;

use TainacanJournalManager\Config;
use TainacanJournalManager\Submission\AnonymizationService;
use TainacanJournalManager\Submission\FileUploadService;

/**
 * Tainacan-integrated read-only management for `tjm_submission` posts.
 *
 * Editors VIEW submissions here and use the deep-link to the editorial
 * portal (`[tjm_editorial_dashboard]?submission=N`) to actually take
 * editorial actions (assign reviewers, record decisions, request
 * revisions, etc.). Direct WordPress post editing is intentionally NOT
 * exposed — submissions are owned by their author and edited via the
 * structured wizard in the author portal.
 */
class SubmissionsPage extends AbstractEntityPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string         { return 'tjm_submissions_page'; }
    protected function get_icon(): string              { return 'processes'; }
    protected function get_label_plural(): string      { return __('Submissions', 'tainacan-journal-manager'); }
    protected function get_label_singular(): string    { return __('Submission', 'tainacan-journal-manager'); }
    protected function get_position(): int             { return 10; }
    protected function supports_editing(): bool        { return false; }

    protected function render_list(): void
    {
        $status_filter = isset($_GET['tjm_status']) ? sanitize_key((string) $_GET['tjm_status']) : '';

        $query_args = [
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 100,
            'post_status'    => 'any',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];
        if ($status_filter && array_key_exists($status_filter, Config::SUBMISSION_STATUSES)) {
            $query_args['meta_query'] = [
                ['key' => Config::META_PREFIX . 'status', 'value' => $status_filter],
            ];
        }

        $submissions = (new \WP_Query($query_args))->posts;

        // Status filter chips
        echo '<div class="tjm-tn-status-filters">';
        $base = $this->url_for('list');
        printf(
            '<a class="tjm-tn-status-chip%s" href="%s">%s</a>',
            $status_filter === '' ? ' is-active' : '',
            esc_url($base),
            esc_html__('All', 'tainacan-journal-manager')
        );
        foreach (Config::SUBMISSION_STATUSES as $key => $label) {
            $url = add_query_arg('tjm_status', $key, $base);
            printf(
                '<a class="tjm-tn-status-chip tjm-tn-status-chip--%s%s" href="%s">%s</a>',
                esc_attr($key),
                $status_filter === $key ? ' is-active' : '',
                esc_url($url),
                esc_html(Config::get_status_label($key))
            );
        }
        echo '</div>';
        ?>
        <table class="widefat striped tjm-tn-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Author', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Submitted', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Updated', 'tainacan-journal-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)) : ?>
                    <tr><td colspan="7"><em><?php esc_html_e('No submissions match the current filter.', 'tainacan-journal-manager'); ?></em></td></tr>
                <?php else : foreach ($submissions as $sub) :
                    $status     = (string) get_post_meta($sub->ID, Config::META_PREFIX . 'status', true) ?: Config::STATUS_DRAFT;
                    $journal_id = (int) get_post_meta($sub->ID, Config::META_PREFIX . 'journal_id', true);
                    $journal    = $journal_id ? get_the_title($journal_id) : '—';
                    $author     = get_userdata((int) $sub->post_author);
                    $submitted  = (string) get_post_meta($sub->ID, Config::META_PREFIX . 'submitted_at', true);
                ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url($this->url_for('view', (int) $sub->ID)); ?>"><?php echo esc_html($sub->post_title); ?></a></strong></td>
                        <td><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></td>
                        <td><?php echo esc_html((string) $journal); ?></td>
                        <td>
                            <span class="tjm-tn-pill tjm-tn-pill--<?php echo esc_attr($status); ?>">
                                <?php echo esc_html(Config::get_status_label($status)); ?>
                            </span>
                        </td>
                        <td><?php echo $submitted ? esc_html(date_i18n('d/m/Y', strtotime($submitted))) : '—'; ?></td>
                        <td><?php echo esc_html(get_the_modified_date('d/m/Y', $sub)); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($this->url_for('view', (int) $sub->ID)); ?>"><?php esc_html_e('View', 'tainacan-journal-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    protected function render_view(int $id): void
    {
        $sub = get_post($id);
        if (! $sub || $sub->post_type !== Config::CPT_SUBMISSION) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Submission not found.', 'tainacan-journal-manager') . '</p></div>';
            return;
        }

        $status     = (string) get_post_meta($id, Config::META_PREFIX . 'status', true) ?: Config::STATUS_DRAFT;
        $journal_id = (int) get_post_meta($id, Config::META_PREFIX . 'journal_id', true);
        $journal    = $journal_id ? get_the_title($journal_id) : '—';
        $author     = get_userdata((int) $sub->post_author);
        $submitted  = (string) get_post_meta($id, Config::META_PREFIX . 'submitted_at', true);
        $manuscript = FileUploadService::get_manuscript_info($id);
        $authors    = AnonymizationService::collect_authors($id);
        $keywords   = (array) get_post_meta($id, Config::META_PREFIX . Config::META_KEYWORDS, true);
        $history    = (array) get_post_meta($id, Config::META_PREFIX . 'status_history', true);
        $decisions  = (array) get_post_meta($id, Config::META_PREFIX . 'decisions', true);
        $reviewers  = (array) get_post_meta($id, Config::META_PREFIX . 'reviewers', true);

        // Deep-link to the editor's portal for taking action
        $editorial_dashboard_url = Config::page_url(Config::PAGE_EDITORIAL) . '?submission=' . $id;
        ?>
        <div class="tjm-tn-grid">
            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></h3>
                <p><strong><?php esc_html_e('Current:', 'tainacan-journal-manager'); ?></strong>
                    <span class="tjm-tn-pill tjm-tn-pill--<?php echo esc_attr($status); ?>">
                        <?php echo esc_html(Config::get_status_label($status)); ?>
                    </span>
                </p>
                <p><strong><?php esc_html_e('Submitted:', 'tainacan-journal-manager'); ?></strong> <?php echo $submitted ? esc_html(date_i18n('d/m/Y H:i', strtotime($submitted))) : '—'; ?></p>
                <p><strong><?php esc_html_e('Journal:', 'tainacan-journal-manager'); ?></strong>
                    <?php if ($journal_id > 0) : ?>
                        <a href="<?php echo esc_url($this->journal_view_url($journal_id)); ?>"><?php echo esc_html((string) $journal); ?></a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </p>
                <p><strong><?php esc_html_e('Submitting author:', 'tainacan-journal-manager'); ?></strong>
                    <?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?>
                </p>
                <p style="margin-top: 12px;">
                    <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url($editorial_dashboard_url); ?>">
                        <?php esc_html_e('Open in editorial portal', 'tainacan-journal-manager'); ?> &rarr;
                    </a>
                </p>
                <p class="description"><?php esc_html_e('All editorial actions (assign reviewers, record decisions, request revisions) live in the public editorial portal.', 'tainacan-journal-manager'); ?></p>
            </div>

            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Manuscript', 'tainacan-journal-manager'); ?></h3>
                <?php if ($manuscript) : ?>
                    <p><a href="<?php echo esc_url((string) $manuscript['url']); ?>" target="_blank" rel="noopener">&darr; <?php echo esc_html((string) $manuscript['filename']); ?></a></p>
                <?php else : ?>
                    <p class="description"><?php esc_html_e('No manuscript file uploaded yet.', 'tainacan-journal-manager'); ?></p>
                <?php endif; ?>

                <h4 style="margin-top: 14px;"><?php esc_html_e('Authors', 'tainacan-journal-manager'); ?></h4>
                <?php if (empty($authors)) : ?>
                    <p class="description">—</p>
                <?php else : ?>
                    <ul style="margin: 0 0 0 18px;">
                        <?php foreach ($authors as $a) : ?>
                            <li>
                                <strong><?php echo esc_html((string) ($a['name'] ?? '')); ?></strong>
                                <?php if (! empty($a['affiliation'])) : ?>— <?php echo esc_html((string) $a['affiliation']); ?><?php endif; ?>
                                <?php if (! empty($a['orcid'])) : ?>(<?php echo esc_html((string) $a['orcid']); ?>)<?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (! empty($keywords)) : ?>
                    <h4 style="margin-top: 14px;"><?php esc_html_e('Keywords', 'tainacan-journal-manager'); ?></h4>
                    <p><?php echo esc_html(implode(', ', array_map('strval', $keywords))); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tjm-tn-block" style="margin-top: 16px;">
            <h3><?php esc_html_e('Abstract', 'tainacan-journal-manager'); ?></h3>
            <p><?php echo nl2br(esc_html((string) $sub->post_content)); ?></p>
        </div>

        <?php if (! empty($reviewers)) : ?>
        <div class="tjm-tn-block" style="margin-top: 16px;">
            <h3><?php esc_html_e('Invited reviewers', 'tainacan-journal-manager'); ?> (<?php echo count($reviewers); ?>)</h3>
            <ul>
                <?php foreach ($reviewers as $rid) :
                    $r = get_userdata((int) $rid);
                    if (! $r) continue;
                ?>
                    <li><?php echo esc_html($r->display_name ?: $r->user_login); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (! empty($history)) : ?>
        <div class="tjm-tn-block" style="margin-top: 16px;">
            <h3><?php esc_html_e('Status history', 'tainacan-journal-manager'); ?></h3>
            <ul class="tjm-tn-history">
                <?php foreach (array_reverse($history) as $h) :
                    $u = isset($h['user_id']) ? get_userdata((int) $h['user_id']) : null;
                ?>
                    <li>
                        <code><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) ($h['date'] ?? '')))); ?></code>
                        <?php echo esc_html(Config::get_status_label((string) ($h['from'] ?? ''))); ?> &rarr;
                        <strong><?php echo esc_html(Config::get_status_label((string) ($h['to'] ?? ''))); ?></strong>
                        <?php if ($u) : ?> · <?php echo esc_html($u->display_name ?: $u->user_login); ?><?php endif; ?>
                        <?php if (! empty($h['note'])) : ?> · <em><?php echo esc_html((string) $h['note']); ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (! empty($decisions)) : ?>
        <div class="tjm-tn-block" style="margin-top: 16px;">
            <h3><?php esc_html_e('Editorial decisions', 'tainacan-journal-manager'); ?></h3>
            <ul class="tjm-tn-history">
                <?php foreach (array_reverse($decisions) as $d) :
                    $editor = isset($d['editor_id']) ? get_userdata((int) $d['editor_id']) : null;
                ?>
                    <li>
                        <code><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) ($d['date'] ?? '')))); ?></code>
                        <strong><?php echo esc_html((string) ($d['decision'] ?? '')); ?></strong>
                        <?php if ($editor) : ?> · <?php echo esc_html($editor->display_name ?: $editor->user_login); ?><?php endif; ?>
                        <?php if (! empty($d['justification'])) : ?> · <em><?php echo esc_html((string) $d['justification']); ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php
    }

    private function journal_view_url(int $journal_id): string
    {
        return admin_url('admin.php?page=tjm_journals_page&action=view&id=' . $journal_id);
    }
}
