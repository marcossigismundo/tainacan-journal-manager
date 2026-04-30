<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Entities;

use TainacanJournalManager\Config;
use TainacanJournalManager\Review\ReviewFormConfig;

/**
 * Tainacan-integrated read-only management for `tjm_review` posts.
 *
 * Reviews are filled by reviewers via the public reviewer dashboard
 * shortcode and are confidential — editors VIEW them here. Direct
 * editing isn't exposed because it would invalidate the audit trail
 * (the parecer is the reviewer's signed input).
 */
class ReviewsPage extends AbstractEntityPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string         { return 'tjm_reviews_page'; }
    protected function get_icon(): string              { return 'approved'; }
    protected function get_label_plural(): string      { return __('Reviews', 'tainacan-journal-manager'); }
    protected function get_label_singular(): string    { return __('Review', 'tainacan-journal-manager'); }
    protected function get_position(): int             { return 11; }
    protected function supports_editing(): bool        { return false; }

    protected function render_list(): void
    {
        $status_filter = isset($_GET['tjm_review_status']) ? sanitize_key((string) $_GET['tjm_review_status']) : '';

        $query_args = [
            'post_type'      => Config::CPT_REVIEW,
            'posts_per_page' => 100,
            'post_status'    => 'any',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];
        if ($status_filter) {
            $query_args['meta_query'] = [
                ['key' => Config::META_PREFIX . 'review_status', 'value' => $status_filter],
            ];
        }

        $reviews = (new \WP_Query($query_args))->posts;

        // Status chips
        $statuses = [
            ''                          => __('All', 'tainacan-journal-manager'),
            Config::REVIEW_INVITED      => __('Invited', 'tainacan-journal-manager'),
            Config::REVIEW_ACCEPTED     => __('Accepted', 'tainacan-journal-manager'),
            Config::REVIEW_DECLINED     => __('Declined', 'tainacan-journal-manager'),
            Config::REVIEW_SUBMITTED    => __('Submitted', 'tainacan-journal-manager'),
            Config::REVIEW_OVERDUE      => __('Overdue', 'tainacan-journal-manager'),
        ];
        $base = $this->url_for('list');
        echo '<div class="tjm-tn-status-filters">';
        foreach ($statuses as $key => $label) {
            $url = $key === ''
                ? $base
                : add_query_arg('tjm_review_status', $key, $base);
            printf(
                '<a class="tjm-tn-status-chip%s" href="%s">%s</a>',
                $status_filter === $key ? ' is-active' : '',
                esc_url($url),
                esc_html($label)
            );
        }
        echo '</div>';
        ?>
        <table class="widefat striped tjm-tn-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Submission', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Reviewer', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Recommendation', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Deadline', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Submitted at', 'tainacan-journal-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)) : ?>
                    <tr><td colspan="7"><em><?php esc_html_e('No reviews match the current filter.', 'tainacan-journal-manager'); ?></em></td></tr>
                <?php else : foreach ($reviews as $r) :
                    $sid       = (int) get_post_meta($r->ID, Config::META_PREFIX . 'submission_id', true);
                    $sub_title = $sid ? (string) get_the_title($sid) : (string) $r->post_title;
                    $reviewer  = get_userdata((int) $r->post_author);
                    $status    = (string) get_post_meta($r->ID, Config::META_PREFIX . 'review_status', true);
                    $rec       = (string) get_post_meta($r->ID, Config::META_PREFIX . 'recommendation', true) ?: '—';
                    $deadline  = (string) get_post_meta($r->ID, Config::META_PREFIX . 'deadline', true);
                    $submitted = (string) get_post_meta($r->ID, Config::META_PREFIX . 'submitted_at', true);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($sub_title); ?></strong></td>
                        <td><?php echo esc_html($reviewer ? ($reviewer->display_name ?: $reviewer->user_login) : '—'); ?></td>
                        <td><span class="tjm-tn-pill"><?php echo esc_html($status); ?></span></td>
                        <td><?php echo esc_html($rec); ?></td>
                        <td><?php echo $deadline ? esc_html(date_i18n('d/m/Y', strtotime($deadline))) : '—'; ?></td>
                        <td><?php echo $submitted ? esc_html(date_i18n('d/m/Y', strtotime($submitted))) : '—'; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($this->url_for('view', (int) $r->ID)); ?>"><?php esc_html_e('View', 'tainacan-journal-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    protected function render_view(int $id): void
    {
        $r = get_post($id);
        if (! $r || $r->post_type !== Config::CPT_REVIEW) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Review not found.', 'tainacan-journal-manager') . '</p></div>';
            return;
        }

        $sid       = (int) get_post_meta($id, Config::META_PREFIX . 'submission_id', true);
        $sub_title = $sid ? (string) get_the_title($sid) : '';
        $reviewer  = get_userdata((int) $r->post_author);
        $invited_by = get_userdata((int) get_post_meta($id, Config::META_PREFIX . 'invited_by', true));
        $status    = (string) get_post_meta($id, Config::META_PREFIX . 'review_status', true);
        $rec       = (string) get_post_meta($id, Config::META_PREFIX . 'recommendation', true);
        $deadline  = (string) get_post_meta($id, Config::META_PREFIX . 'deadline', true);
        $invited   = (string) get_post_meta($id, Config::META_PREFIX . 'invited_at', true);
        $accepted  = (string) get_post_meta($id, Config::META_PREFIX . 'accepted_at', true);
        $submitted = (string) get_post_meta($id, Config::META_PREFIX . 'submitted_at', true);
        $author_comments = (string) get_post_meta($id, Config::META_PREFIX . 'author_comments', true);
        $editor_comments = (string) get_post_meta($id, Config::META_PREFIX . 'editor_comments', true);
        $section_data    = (array) get_post_meta($id, Config::META_PREFIX . 'section_comments', true);
        $decline_reason  = (string) get_post_meta($id, Config::META_PREFIX . 'decline_reason', true);
        ?>
        <div class="tjm-tn-grid">
            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Identification', 'tainacan-journal-manager'); ?></h3>
                <p><strong><?php esc_html_e('Submission:', 'tainacan-journal-manager'); ?></strong>
                    <?php if ($sid > 0) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tjm_submissions_page&action=view&id=' . $sid)); ?>"><?php echo esc_html($sub_title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html((string) $r->post_title); ?>
                    <?php endif; ?>
                </p>
                <p><strong><?php esc_html_e('Reviewer:', 'tainacan-journal-manager'); ?></strong>
                    <?php echo esc_html($reviewer ? ($reviewer->display_name ?: $reviewer->user_login) : '—'); ?>
                </p>
                <p><strong><?php esc_html_e('Invited by:', 'tainacan-journal-manager'); ?></strong>
                    <?php echo esc_html($invited_by ? ($invited_by->display_name ?: $invited_by->user_login) : '—'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Status:', 'tainacan-journal-manager'); ?></strong>
                    <span class="tjm-tn-pill"><?php echo esc_html($status); ?></span>
                </p>
                <p><strong><?php esc_html_e('Recommendation:', 'tainacan-journal-manager'); ?></strong>
                    <?php echo $rec ? '<code>' . esc_html($rec) . '</code>' : '—'; ?>
                </p>
            </div>

            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Timeline', 'tainacan-journal-manager'); ?></h3>
                <p><strong><?php esc_html_e('Invited:', 'tainacan-journal-manager'); ?></strong> <?php echo $invited  ? esc_html(date_i18n('d/m/Y H:i', strtotime($invited)))  : '—'; ?></p>
                <p><strong><?php esc_html_e('Accepted:', 'tainacan-journal-manager'); ?></strong> <?php echo $accepted ? esc_html(date_i18n('d/m/Y H:i', strtotime($accepted))) : '—'; ?></p>
                <p><strong><?php esc_html_e('Deadline:', 'tainacan-journal-manager'); ?></strong> <?php echo $deadline ? esc_html(date_i18n('d/m/Y', strtotime($deadline))) : '—'; ?></p>
                <p><strong><?php esc_html_e('Submitted:', 'tainacan-journal-manager'); ?></strong> <?php echo $submitted ? esc_html(date_i18n('d/m/Y H:i', strtotime($submitted))) : '—'; ?></p>
            </div>
        </div>

        <?php if ($status === Config::REVIEW_DECLINED && $decline_reason !== '') : ?>
        <div class="tjm-tn-block" style="margin-top: 16px;">
            <h3><?php esc_html_e('Decline reason', 'tainacan-journal-manager'); ?></h3>
            <p><?php echo nl2br(esc_html($decline_reason)); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($status === Config::REVIEW_SUBMITTED) : ?>
            <?php if ($author_comments !== '') : ?>
            <div class="tjm-tn-block" style="margin-top: 16px;">
                <h3><?php esc_html_e('Comments to the author', 'tainacan-journal-manager'); ?></h3>
                <p><?php echo nl2br(esc_html($author_comments)); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($editor_comments !== '') : ?>
            <div class="tjm-tn-block" style="margin-top: 16px;">
                <h3><?php esc_html_e('Confidential comments to the editor', 'tainacan-journal-manager'); ?></h3>
                <p><?php echo nl2br(esc_html($editor_comments)); ?></p>
            </div>
            <?php endif; ?>

            <?php if (! empty($section_data)) :
                $journal_sections = $sid ? ReviewFormConfig::sections_for_submission($sid) : [];
            ?>
            <div class="tjm-tn-block" style="margin-top: 16px;">
                <h3><?php esc_html_e('Form sections', 'tainacan-journal-manager'); ?></h3>
                <?php foreach ($section_data as $section_key => $value) :
                    $value = (string) $value;
                    if ($value === '') continue;
                ?>
                    <h4 style="margin-bottom: 4px;"><?php echo esc_html(ReviewFormConfig::label((string) $section_key)); ?></h4>
                    <p><?php echo nl2br(esc_html($value)); ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }
}
