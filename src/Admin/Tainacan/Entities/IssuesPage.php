<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Entities;

use TainacanJournalManager\Config;
use TainacanJournalManager\Issues\IssueManager;

/**
 * Tainacan-integrated management for `tjm_issue` posts.
 *
 * Issues group articles into volumes / numbers / dossiers / continuous
 * batches. Editors create them here and assign accepted articles. The
 * "publish issue" action is also exposed.
 */
class IssuesPage extends AbstractEntityPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string         { return 'tjm_issues_page'; }
    protected function get_icon(): string              { return 'collection'; }
    protected function get_label_plural(): string      { return __('Issues', 'tainacan-journal-manager'); }
    protected function get_label_singular(): string    { return __('Issue', 'tainacan-journal-manager'); }
    protected function get_position(): int             { return 12; }
    protected function supports_editing(): bool        { return true; }

    /* ── LIST ─────────────────────────────────────────────────── */

    protected function render_list(): void
    {
        $issues = get_posts([
            'post_type'      => Config::CPT_ISSUE,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'meta_value_num',
            'meta_key'       => Config::META_PREFIX . 'year',
            'order'          => 'DESC',
        ]);
        ?>
        <table class="widefat striped tjm-tn-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Volume', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Number', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Year', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Type', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Articles', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($issues)) : ?>
                    <tr><td colspan="9"><em><?php esc_html_e('No issues yet.', 'tainacan-journal-manager'); ?></em></td></tr>
                <?php else : foreach ($issues as $iss) :
                    $journal_id = (int) get_post_meta($iss->ID, Config::META_PREFIX . 'journal_id', true);
                    $journal    = $journal_id ? get_the_title($journal_id) : '—';
                    $vol  = (string) get_post_meta($iss->ID, Config::META_PREFIX . 'volume', true);
                    $num  = (string) get_post_meta($iss->ID, Config::META_PREFIX . 'number', true);
                    $year = (int) get_post_meta($iss->ID, Config::META_PREFIX . 'year', true);
                    $type = (string) get_post_meta($iss->ID, Config::META_PREFIX . 'publication_type', true) ?: '—';
                    $articles = count(IssueManager::get_article_ids((int) $iss->ID));
                    $published = (bool) get_post_meta($iss->ID, Config::META_PREFIX . 'issue_published', true);
                ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url($this->url_for('view', (int) $iss->ID)); ?>"><?php echo esc_html($iss->post_title); ?></a></strong></td>
                        <td><?php echo esc_html((string) $journal); ?></td>
                        <td><?php echo esc_html($vol ?: '—'); ?></td>
                        <td><?php echo esc_html($num ?: '—'); ?></td>
                        <td><?php echo $year ? (int) $year : '—'; ?></td>
                        <td><?php echo esc_html($type); ?></td>
                        <td><?php echo (int) $articles; ?></td>
                        <td>
                            <span class="tjm-tn-pill tjm-tn-pill--<?php echo $published ? 'publish' : 'draft'; ?>">
                                <?php echo $published ? esc_html__('Published', 'tainacan-journal-manager') : esc_html__('Draft', 'tainacan-journal-manager'); ?>
                            </span>
                        </td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($this->url_for('view', (int) $iss->ID)); ?>"><?php esc_html_e('View', 'tainacan-journal-manager'); ?></a>
                            <a class="button button-small button-primary" href="<?php echo esc_url($this->url_for('edit', (int) $iss->ID)); ?>"><?php esc_html_e('Edit', 'tainacan-journal-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    /* ── VIEW ─────────────────────────────────────────────────── */

    protected function render_view(int $id): void
    {
        $iss = get_post($id);
        if (! $iss || $iss->post_type !== Config::CPT_ISSUE) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Issue not found.', 'tainacan-journal-manager') . '</p></div>';
            return;
        }

        $journal_id = (int) get_post_meta($id, Config::META_PREFIX . 'journal_id', true);
        $journal    = $journal_id ? get_the_title($journal_id) : '—';
        $vol  = (string) get_post_meta($id, Config::META_PREFIX . 'volume', true);
        $num  = (string) get_post_meta($id, Config::META_PREFIX . 'number', true);
        $year = (int) get_post_meta($id, Config::META_PREFIX . 'year', true);
        $type = (string) get_post_meta($id, Config::META_PREFIX . 'publication_type', true) ?: '—';
        $published = (bool) get_post_meta($id, Config::META_PREFIX . 'issue_published', true);
        $article_ids = IssueManager::get_article_ids($id);
        ?>
        <div class="tjm-tn-grid">
            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Identification', 'tainacan-journal-manager'); ?></h3>
                <p><strong><?php esc_html_e('Title:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html($iss->post_title); ?></p>
                <p><strong><?php esc_html_e('Journal:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html((string) $journal); ?></p>
                <p>
                    <strong><?php esc_html_e('Volume / number / year:', 'tainacan-journal-manager'); ?></strong>
                    <?php echo esc_html(trim(($vol ? "v.$vol" : '') . ($num ? " n.$num" : '') . ($year ? " ($year)" : '')) ?: '—'); ?>
                </p>
                <p><strong><?php esc_html_e('Type:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html($type); ?></p>
                <p>
                    <strong><?php esc_html_e('Status:', 'tainacan-journal-manager'); ?></strong>
                    <span class="tjm-tn-pill tjm-tn-pill--<?php echo $published ? 'publish' : 'draft'; ?>">
                        <?php echo $published ? esc_html__('Published', 'tainacan-journal-manager') : esc_html__('Draft', 'tainacan-journal-manager'); ?>
                    </span>
                </p>
            </div>

            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Assigned articles', 'tainacan-journal-manager'); ?> (<?php echo count($article_ids); ?>)</h3>
                <?php if (empty($article_ids)) : ?>
                    <p class="description"><?php esc_html_e('No articles assigned yet.', 'tainacan-journal-manager'); ?></p>
                <?php else : ?>
                    <ol>
                        <?php foreach ($article_ids as $sid) :
                            $sub = get_post((int) $sid);
                            if (! $sub) continue;
                            $sub_status = (string) get_post_meta((int) $sid, Config::META_PREFIX . 'status', true);
                        ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tjm_submissions_page&action=view&id=' . (int) $sid)); ?>">
                                    <?php echo esc_html($sub->post_title); ?>
                                </a>
                                <span class="tjm-tn-pill"><?php echo esc_html($sub_status); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ── FORM ─────────────────────────────────────────────────── */

    protected function render_form(?int $id): void
    {
        $iss = $id ? get_post($id) : null;
        if ($id && (! $iss || $iss->post_type !== Config::CPT_ISSUE)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Issue not found.', 'tainacan-journal-manager') . '</p></div>';
            return;
        }

        $title      = $iss ? (string) $iss->post_title : '';
        $journal_id = $iss ? (int) get_post_meta($id, Config::META_PREFIX . 'journal_id', true) : 0;
        $vol        = $iss ? (string) get_post_meta($id, Config::META_PREFIX . 'volume', true) : '';
        $num        = $iss ? (string) get_post_meta($id, Config::META_PREFIX . 'number', true) : '';
        $year       = $iss ? (int) get_post_meta($id, Config::META_PREFIX . 'year', true) : (int) gmdate('Y');
        $type       = $iss ? (string) get_post_meta($id, Config::META_PREFIX . 'publication_type', true) : IssueManager::TYPE_REGULAR;
        $published  = $iss ? (bool) get_post_meta($id, Config::META_PREFIX . 'issue_published', true) : false;

        $journals = get_posts([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        // Article candidates: published or in production for this journal
        $candidates = [];
        $assigned   = $iss ? IssueManager::get_article_ids($id) : [];
        if ($iss) {
            $q = new \WP_Query([
                'post_type'      => Config::CPT_SUBMISSION,
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'meta_query'     => [
                    'relation' => 'AND',
                    ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $journal_id],
                    [
                        'key'     => Config::META_PREFIX . 'status',
                        'value'   => [Config::STATUS_PRODUCTION, Config::STATUS_PUBLISHED, Config::STATUS_COPYEDITING],
                        'compare' => 'IN',
                    ],
                ],
            ]);
            $candidates = $q->posts;
        }

        $type_labels = [
            IssueManager::TYPE_REGULAR    => __('Regular', 'tainacan-journal-manager'),
            IssueManager::TYPE_SPECIAL    => __('Special', 'tainacan-journal-manager'),
            IssueManager::TYPE_DOSSIER    => __('Dossier', 'tainacan-journal-manager'),
            IssueManager::TYPE_CONTINUOUS => __('Continuous flow', 'tainacan-journal-manager'),
        ];
        ?>
        <form method="post" action="<?php echo esc_url($this->save_action_url()); ?>" class="tjm-tn-form">
            <?php wp_nonce_field($this->nonce_action(), $this->nonce_name()); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($this->save_action_name()); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tjm-iss-title"><?php esc_html_e('Title', 'tainacan-journal-manager'); ?> *</label></th>
                    <td><input type="text" name="title" id="tjm-iss-title" value="<?php echo esc_attr($title); ?>" class="large-text" required placeholder="<?php echo esc_attr__('e.g. v.10 n.2 (2026)', 'tainacan-journal-manager'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tjm-iss-journal"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?> *</label></th>
                    <td>
                        <select name="journal_id" id="tjm-iss-journal" required>
                            <option value=""><?php esc_html_e('— select —', 'tainacan-journal-manager'); ?></option>
                            <?php foreach ($journals as $j) : ?>
                                <option value="<?php echo (int) $j->ID; ?>" <?php selected($journal_id, (int) $j->ID); ?>><?php echo esc_html((string) $j->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Volume / number / year', 'tainacan-journal-manager'); ?></th>
                    <td>
                        <input type="text"   name="volume" value="<?php echo esc_attr($vol);  ?>" placeholder="<?php esc_attr_e('Volume', 'tainacan-journal-manager'); ?>" style="width: 100px;">
                        <input type="text"   name="number" value="<?php echo esc_attr($num);  ?>" placeholder="<?php esc_attr_e('Number', 'tainacan-journal-manager'); ?>" style="width: 100px;">
                        <input type="number" name="year"   value="<?php echo (int) $year;     ?>" min="1900" max="2100" style="width: 100px;">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tjm-iss-type"><?php esc_html_e('Publication type', 'tainacan-journal-manager'); ?></label></th>
                    <td>
                        <select name="publication_type" id="tjm-iss-type">
                            <?php foreach ($type_labels as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="published" value="1" <?php checked($published); ?>>
                            <?php esc_html_e('Published (publicly visible)', 'tainacan-journal-manager'); ?>
                        </label>
                    </td>
                </tr>

                <?php if ($iss && ! empty($candidates)) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Assigned articles', 'tainacan-journal-manager'); ?></th>
                    <td>
                        <p class="description"><?php esc_html_e('Pick which articles in this journal belong to this issue.', 'tainacan-journal-manager'); ?></p>
                        <ul style="max-height: 220px; overflow-y: auto; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--tjm-tn-border); border-radius: 4px;">
                            <?php foreach ($candidates as $sub) :
                                $sub_status = (string) get_post_meta((int) $sub->ID, Config::META_PREFIX . 'status', true);
                            ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="article_ids[]" value="<?php echo (int) $sub->ID; ?>" <?php checked(in_array((int) $sub->ID, $assigned, true)); ?>>
                                        <strong><?php echo esc_html($sub->post_title); ?></strong>
                                        <span class="tjm-tn-pill"><?php echo esc_html($sub_status); ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php submit_button($id ? __('Save changes', 'tainacan-journal-manager') : __('Create issue', 'tainacan-journal-manager')); ?>
        </form>

        <?php if ($id) : ?>
        <form method="post" action="<?php echo esc_url($this->save_action_url()); ?>" style="margin-top: 24px;"
              onsubmit="return confirm('<?php echo esc_js(__('Delete this issue? Articles will be unassigned but kept.', 'tainacan-journal-manager')); ?>');">
            <?php wp_nonce_field($this->nonce_action(), $this->nonce_name()); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($this->delete_action_name()); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete issue', 'tainacan-journal-manager'); ?></button>
        </form>
        <?php endif; ?>
        <?php
    }

    /* ── SAVE / DELETE handlers ───────────────────────────────── */

    public function handle_save(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions.', 'tainacan-journal-manager'));
        }
        check_admin_referer($this->nonce_action(), $this->nonce_name());

        $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $title      = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $journal_id = isset($_POST['journal_id']) ? (int) $_POST['journal_id'] : 0;
        if ($title === '' || $journal_id <= 0) {
            $this->redirect_after_save('error', $id);
        }

        $vol  = isset($_POST['volume']) ? sanitize_text_field(wp_unslash((string) $_POST['volume'])) : '';
        $num  = isset($_POST['number']) ? sanitize_text_field(wp_unslash((string) $_POST['number'])) : '';
        $year = isset($_POST['year'])   ? (int) $_POST['year'] : (int) gmdate('Y');
        $type = isset($_POST['publication_type']) ? sanitize_key((string) $_POST['publication_type']) : IssueManager::TYPE_REGULAR;
        if (! in_array($type, IssueManager::ALL_TYPES, true)) {
            $type = IssueManager::TYPE_REGULAR;
        }
        $published = ! empty($_POST['published']);
        $articles  = isset($_POST['article_ids']) && is_array($_POST['article_ids'])
            ? array_map('intval', $_POST['article_ids'])
            : [];

        $post_data = [
            'post_type'   => Config::CPT_ISSUE,
            'post_title'  => $title,
            'post_status' => $published ? 'publish' : 'draft',
        ];
        if ($id > 0) {
            $post_data['ID'] = $id;
            wp_update_post($post_data, true);
            $msg = 'updated';
        } else {
            $id = (int) wp_insert_post($post_data, true);
            $msg = 'created';
            if ($id <= 0) {
                $this->redirect_after_save('error');
            }
        }

        update_post_meta($id, Config::META_PREFIX . 'journal_id',       $journal_id);
        update_post_meta($id, Config::META_PREFIX . 'volume',           $vol);
        update_post_meta($id, Config::META_PREFIX . 'number',           $num);
        update_post_meta($id, Config::META_PREFIX . 'year',             $year);
        update_post_meta($id, Config::META_PREFIX . 'publication_type', $type);
        update_post_meta($id, Config::META_PREFIX . 'issue_published',  $published);

        // Reconcile assigned articles: add new, remove dropped
        if ($msg === 'updated') {
            $current = IssueManager::get_article_ids($id);
            $to_add = array_diff($articles, $current);
            $to_remove = array_diff($current, $articles);
            foreach ($to_add as $sid) {
                IssueManager::assign_article($id, (int) $sid);
            }
            foreach ($to_remove as $sid) {
                IssueManager::unassign_article($id, (int) $sid);
            }
        } else {
            update_post_meta($id, Config::META_PREFIX . 'issue_articles', []);
            foreach ($articles as $sid) {
                IssueManager::assign_article($id, (int) $sid);
            }
        }

        if ($published) {
            do_action('tjm_issue_published', $id, get_current_user_id());
        }

        $this->redirect_after_save($msg, $id);
    }

    public function handle_delete(): void
    {
        if (! current_user_can('delete_posts')) {
            wp_die(esc_html__('Insufficient permissions.', 'tainacan-journal-manager'));
        }
        check_admin_referer($this->nonce_action(), $this->nonce_name());

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0 && get_post_type($id) === Config::CPT_ISSUE) {
            // Unassign articles first so each submission's _tjm_issue_id is cleared
            foreach (IssueManager::get_article_ids($id) as $sid) {
                IssueManager::unassign_article($id, (int) $sid);
            }
            wp_delete_post($id, true);
        }
        wp_safe_redirect($this->url_for('list', 0, ['msg' => 'deleted']));
        exit;
    }
}
