<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Entities;

use TainacanJournalManager\Config;
use TainacanJournalManager\Review\ReviewFormConfig;
use TainacanJournalManager\Tainacan\CollectionProvisioner;
use TainacanJournalManager\Tainacan\Integration;

/**
 * Tainacan-integrated management for `tjm_journal` posts.
 * Full CRUD: editors create / configure / publish journals here.
 */
class JournalsPage extends AbstractEntityPage
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string         { return 'tjm_journals_page'; }
    protected function get_icon(): string              { return 'repository'; }
    protected function get_label_plural(): string      { return __('Journals', 'tainacan-journal-manager'); }
    protected function get_label_singular(): string    { return __('Journal', 'tainacan-journal-manager'); }
    protected function get_position(): int             { return 9; }
    protected function supports_editing(): bool        { return true; }

    /* ── LIST ─────────────────────────────────────────────────── */

    protected function render_list(): void
    {
        $journals = get_posts([
            'post_type'      => Config::CPT_JOURNAL,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        ?>
        <table class="widefat striped tjm-tn-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Review type', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('ISSN', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></th>
                    <th><?php esc_html_e('Updated', 'tainacan-journal-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($journals)) : ?>
                    <tr><td colspan="6"><em><?php esc_html_e('No journals yet. Create your first one.', 'tainacan-journal-manager'); ?></em></td></tr>
                <?php else : foreach ($journals as $j) :
                    $review_type = (string) get_post_meta($j->ID, Config::META_PREFIX . 'review_type', true) ?: '—';
                    $issn        = (string) get_post_meta($j->ID, Config::META_PREFIX . 'issn', true) ?: '—';
                ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url($this->url_for('view', (int) $j->ID)); ?>"><?php echo esc_html($j->post_title); ?></a></strong></td>
                        <td><?php echo esc_html($review_type); ?></td>
                        <td><code><?php echo esc_html($issn); ?></code></td>
                        <td>
                            <span class="tjm-tn-pill tjm-tn-pill--<?php echo esc_attr($j->post_status); ?>">
                                <?php echo esc_html($j->post_status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(get_the_modified_date('d/m/Y', $j)); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($this->url_for('view', (int) $j->ID)); ?>"><?php esc_html_e('View', 'tainacan-journal-manager'); ?></a>
                            <a class="button button-small button-primary" href="<?php echo esc_url($this->url_for('edit', (int) $j->ID)); ?>"><?php esc_html_e('Edit', 'tainacan-journal-manager'); ?></a>
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
        $j = get_post($id);
        if (! $j || $j->post_type !== Config::CPT_JOURNAL) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Journal not found.', 'tainacan-journal-manager') . '</p></div>';
            return;
        }

        $review_type = (string) get_post_meta($id, Config::META_PREFIX . 'review_type', true) ?: '—';
        $sections    = ReviewFormConfig::get_for_journal($id);
        $issn        = (string) get_post_meta($id, Config::META_PREFIX . 'issn', true);
        $eissn       = (string) get_post_meta($id, Config::META_PREFIX . 'eissn', true);
        $license     = (string) get_post_meta($id, Config::META_PREFIX . 'license', true) ?: 'CC BY 4.0';
        $collection  = Integration::is_available() ? Integration::get_collection_id_for_journal($id) : 0;

        $submission_count = (new \WP_Query([
            'post_type'      => Config::CPT_SUBMISSION,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'any',
            'meta_query'     => [
                ['key' => Config::META_PREFIX . 'journal_id', 'value' => (string) $id],
            ],
        ]))->found_posts;
        ?>
        <div class="tjm-tn-grid">
            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Identification', 'tainacan-journal-manager'); ?></h3>
                <p><strong><?php esc_html_e('Title:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html($j->post_title); ?></p>
                <p><strong><?php esc_html_e('Status:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html($j->post_status); ?></p>
                <p><strong>ISSN:</strong> <code><?php echo esc_html($issn ?: '—'); ?></code></p>
                <p><strong>e-ISSN:</strong> <code><?php echo esc_html($eissn ?: '—'); ?></code></p>
                <p><strong><?php esc_html_e('License:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html($license); ?></p>
            </div>
            <div class="tjm-tn-block">
                <h3><?php esc_html_e('Editorial settings', 'tainacan-journal-manager'); ?></h3>
                <p><strong><?php esc_html_e('Review type:', 'tainacan-journal-manager'); ?></strong> <?php echo esc_html($review_type); ?></p>
                <p><strong><?php esc_html_e('Form sections:', 'tainacan-journal-manager'); ?></strong>
                    <?php echo $sections
                        ? esc_html(implode(', ', array_map([ReviewFormConfig::class, 'label'], $sections)))
                        : '<em>' . esc_html__('default only', 'tainacan-journal-manager') . '</em>';
                    ?>
                </p>
                <p><strong><?php esc_html_e('Submissions:', 'tainacan-journal-manager'); ?></strong> <?php echo (int) $submission_count; ?></p>
                <p><strong><?php esc_html_e('Tainacan collection:', 'tainacan-journal-manager'); ?></strong>
                    <?php if ($collection > 0) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tainacan_admin#/collections/' . (int) $collection)); ?>">#<?php echo (int) $collection; ?></a>
                    <?php else : ?>
                        <em><?php esc_html_e('not provisioned', 'tainacan-journal-manager'); ?></em>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (! empty($j->post_content)) : ?>
        <div class="tjm-tn-block" style="margin-top: 16px;">
            <h3><?php esc_html_e('About', 'tainacan-journal-manager'); ?></h3>
            <div><?php echo wp_kses_post($j->post_content); ?></div>
        </div>
        <?php endif; ?>
        <?php
    }

    /* ── FORM (create + edit) ─────────────────────────────────── */

    protected function render_form(?int $id): void
    {
        $j = $id ? get_post($id) : null;
        if ($id && (! $j || $j->post_type !== Config::CPT_JOURNAL)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Journal not found.', 'tainacan-journal-manager') . '</p></div>';
            return;
        }

        $title       = $j ? (string) $j->post_title : '';
        $about       = $j ? (string) $j->post_content : '';
        $status      = $j ? (string) $j->post_status : 'publish';
        $review_type = $j ? (string) get_post_meta($id, Config::META_PREFIX . 'review_type', true) : Config::REVIEW_TYPE_DOUBLE;
        $sections    = $j ? ReviewFormConfig::get_for_journal((int) $id) : [];
        $issn        = $j ? (string) get_post_meta($id, Config::META_PREFIX . 'issn', true) : '';
        $eissn       = $j ? (string) get_post_meta($id, Config::META_PREFIX . 'eissn', true) : '';
        $license     = $j ? (string) get_post_meta($id, Config::META_PREFIX . 'license', true) : 'CC BY 4.0';

        $review_types = [
            Config::REVIEW_TYPE_OPEN      => __('Open (identities visible)', 'tainacan-journal-manager'),
            Config::REVIEW_TYPE_BLIND     => __('Single-blind', 'tainacan-journal-manager'),
            Config::REVIEW_TYPE_DOUBLE    => __('Double-blind', 'tainacan-journal-manager'),
            Config::REVIEW_TYPE_EDITORIAL => __('Editorial only (no peer review)', 'tainacan-journal-manager'),
        ];
        ?>
        <form method="post" action="<?php echo esc_url($this->save_action_url()); ?>" class="tjm-tn-form">
            <?php wp_nonce_field($this->nonce_action(), $this->nonce_name()); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($this->save_action_name()); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tjm-jou-title"><?php esc_html_e('Title', 'tainacan-journal-manager'); ?> *</label></th>
                    <td><input type="text" name="title" id="tjm-jou-title" value="<?php echo esc_attr($title); ?>" class="large-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tjm-jou-status"><?php esc_html_e('Status', 'tainacan-journal-manager'); ?></label></th>
                    <td>
                        <select name="post_status" id="tjm-jou-status">
                            <option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Published', 'tainacan-journal-manager'); ?></option>
                            <option value="draft"   <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'tainacan-journal-manager'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tjm-jou-about"><?php esc_html_e('About / scope', 'tainacan-journal-manager'); ?></label></th>
                    <td>
                        <textarea name="about" id="tjm-jou-about" rows="6" class="large-text"><?php echo esc_textarea($about); ?></textarea>
                        <p class="description"><?php esc_html_e('Mission, scope, editorial policies. Plain text or basic HTML.', 'tainacan-journal-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Identifiers', 'tainacan-journal-manager'); ?></th>
                    <td>
                        <label>ISSN <input type="text" name="issn" value="<?php echo esc_attr($issn); ?>" class="regular-text" placeholder="0000-0000"></label><br><br>
                        <label>e-ISSN <input type="text" name="eissn" value="<?php echo esc_attr($eissn); ?>" class="regular-text" placeholder="0000-0000"></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tjm-jou-license"><?php esc_html_e('Default license', 'tainacan-journal-manager'); ?></label></th>
                    <td><input type="text" name="license" id="tjm-jou-license" value="<?php echo esc_attr($license); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tjm-jou-review-type"><?php esc_html_e('Review type', 'tainacan-journal-manager'); ?></label></th>
                    <td>
                        <select name="review_type" id="tjm-jou-review-type">
                            <?php foreach ($review_types as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($review_type, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Optional review form sections', 'tainacan-journal-manager'); ?></th>
                    <td>
                        <p class="description"><?php esc_html_e('Reviewers always have the overall comments + recommendation fields. These optional sections add free-text fields per topic.', 'tainacan-journal-manager'); ?></p>
                        <ul style="margin-top: 8px;">
                            <?php foreach (ReviewFormConfig::ALL_SECTIONS as $section) : ?>
                                <li>
                                    <label>
                                        <input type="checkbox" name="review_sections[]" value="<?php echo esc_attr($section); ?>" <?php checked(in_array($section, $sections, true)); ?>>
                                        <?php echo esc_html(ReviewFormConfig::label($section)); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            </table>

            <?php submit_button($id ? __('Save changes', 'tainacan-journal-manager') : __('Create journal', 'tainacan-journal-manager')); ?>
        </form>

        <?php if ($id) : ?>
        <form method="post" action="<?php echo esc_url($this->save_action_url()); ?>" style="margin-top: 24px;"
              onsubmit="return confirm('<?php echo esc_js(__('Delete this journal? Submissions will keep their reference but the journal record will be lost.', 'tainacan-journal-manager')); ?>');">
            <?php wp_nonce_field($this->nonce_action(), $this->nonce_name()); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($this->delete_action_name()); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete journal', 'tainacan-journal-manager'); ?></button>
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

        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $title  = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $about  = isset($_POST['about']) ? wp_kses_post(wp_unslash((string) $_POST['about'])) : '';
        $status = isset($_POST['post_status']) ? sanitize_key((string) $_POST['post_status']) : 'publish';
        if (! in_array($status, ['publish', 'draft'], true)) {
            $status = 'publish';
        }

        if ($title === '') {
            $this->redirect_after_save('error', $id);
        }

        $review_type = isset($_POST['review_type']) ? sanitize_key((string) $_POST['review_type']) : Config::REVIEW_TYPE_DOUBLE;
        $issn        = isset($_POST['issn'])   ? sanitize_text_field(wp_unslash((string) $_POST['issn']))   : '';
        $eissn       = isset($_POST['eissn'])  ? sanitize_text_field(wp_unslash((string) $_POST['eissn']))  : '';
        $license     = isset($_POST['license'])? sanitize_text_field(wp_unslash((string) $_POST['license'])): 'CC BY 4.0';
        $sections_raw = isset($_POST['review_sections']) && is_array($_POST['review_sections'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['review_sections']))
            : [];

        $allowed_types = [Config::REVIEW_TYPE_OPEN, Config::REVIEW_TYPE_BLIND, Config::REVIEW_TYPE_DOUBLE, Config::REVIEW_TYPE_EDITORIAL];
        if (! in_array($review_type, $allowed_types, true)) {
            $review_type = Config::REVIEW_TYPE_DOUBLE;
        }

        $post_data = [
            'post_type'    => Config::CPT_JOURNAL,
            'post_title'   => $title,
            'post_content' => $about,
            'post_status'  => $status,
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

        update_post_meta($id, Config::META_PREFIX . 'review_type', $review_type);
        update_post_meta($id, Config::META_PREFIX . 'issn',        $issn);
        update_post_meta($id, Config::META_PREFIX . 'eissn',       $eissn);
        update_post_meta($id, Config::META_PREFIX . 'license',     $license);
        ReviewFormConfig::set_for_journal($id, $sections_raw);

        // Provision a Tainacan collection for this journal (idempotent)
        if (Integration::is_available()) {
            CollectionProvisioner::provision_for_journal($id);
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
        if ($id > 0 && get_post_type($id) === Config::CPT_JOURNAL) {
            wp_delete_post($id, true);
        }
        wp_safe_redirect($this->url_for('list', 0, ['msg' => 'deleted']));
        exit;
    }
}
