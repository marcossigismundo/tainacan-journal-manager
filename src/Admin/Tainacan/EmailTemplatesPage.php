<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Notifications\TemplateOverrides;

/**
 * Tainacan-integrated email template editor.
 *
 * Save / reset are handled via `admin-post.php` actions registered in
 * the constructor (Tainacan's render layer doesn't process forms — we
 * keep them as standard admin-post handlers for safety).
 */
class EmailTemplatesPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    public function init(): void
    {
        parent::init();
        add_action('admin_post_tjm_save_email_template',  [$this, 'handle_save']);
        add_action('admin_post_tjm_reset_email_template', [$this, 'handle_reset']);
    }

    protected function get_page_slug(): string
    {
        return 'tjm_email_templates';
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_other_links_slug,
            __('Journal Manager — Email templates', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('notifications') . '</span>'
                . '<span class="menu-text">' . __('Journal Manager — Email templates', 'tainacan-journal-manager') . '</span>',
            'manage_options',
            $this->get_page_slug(),
            [&$this, 'render_page']
        );
        add_action('load-' . $page_suffix, [&$this, 'load_page']);
    }

    public function admin_enqueue_css(): void
    {
        wp_enqueue_style('tjm-tainacan-admin', TJM_URL . 'assets/css/admin-tainacan.css', [], TJM_VERSION);
    }

    public function render_page_content(): void
    {
        $current = isset($_GET['tpl']) ? sanitize_text_field((string) $_GET['tpl']) : TemplateOverrides::KNOWN_KEYS[0];
        if (! in_array($current, TemplateOverrides::KNOWN_KEYS, true)) {
            $current = TemplateOverrides::KNOWN_KEYS[0];
        }

        $override = TemplateOverrides::get($current);
        $subject  = $override['subject'] ?? '';
        $body     = $override['body'] ?? '';
        $tokens   = self::tokens_for($current);
        $base_url = admin_url('admin.php?page=' . $this->get_page_slug());
        $saved    = isset($_GET['saved']);
        $reset    = isset($_GET['reset']);
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager — Email templates', 'tainacan-journal-manager'); ?></h1>
                <p class="tjm-page-subtitle">
                    <?php esc_html_e('Override subject and body of any email template. Use {{token}} placeholders — they are interpolated from the data passed by the mailer.', 'tainacan-journal-manager'); ?>
                </p>
            </div>

            <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Template saved.', 'tainacan-journal-manager'); ?></p></div><?php endif; ?>
            <?php if ($reset) : ?><div class="notice notice-info is-dismissible"><p><?php esc_html_e('Reverted to default.', 'tainacan-journal-manager'); ?></p></div><?php endif; ?>

            <h2 class="nav-tab-wrapper tjm-tn-tabs">
                <?php foreach (TemplateOverrides::KNOWN_KEYS as $key) :
                    $is_active = $key === $current;
                    $is_custom = (bool) TemplateOverrides::get($key);
                ?>
                    <a class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>"
                       href="<?php echo esc_url(add_query_arg('tpl', $key, $base_url)); ?>">
                        <?php echo esc_html($key); ?>
                        <?php if ($is_custom) : ?> <span class="dashicons dashicons-edit" title="<?php echo esc_attr__('Customized', 'tainacan-journal-manager'); ?>"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tjm-tn-form" style="margin-top: 16px;">
                <?php wp_nonce_field('tjm_save_email_template', 'tjm_email_nonce'); ?>
                <input type="hidden" name="action" value="tjm_save_email_template">
                <input type="hidden" name="tpl" value="<?php echo esc_attr($current); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tjm-email-subject"><?php esc_html_e('Subject', 'tainacan-journal-manager'); ?></label></th>
                        <td>
                            <input type="text" id="tjm-email-subject" name="subject" value="<?php echo esc_attr($subject); ?>" class="large-text">
                            <p class="description"><?php esc_html_e('Leave empty to use the default subject. The site name prefix is added automatically.', 'tainacan-journal-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tjm-email-body"><?php esc_html_e('Body (HTML)', 'tainacan-journal-manager'); ?></label></th>
                        <td>
                            <textarea id="tjm-email-body" name="body" rows="14" class="large-text code"><?php echo esc_textarea($body); ?></textarea>
                            <p class="description"><?php esc_html_e('Leave empty to use the bundled default. The base layout (header / footer) wraps your content automatically.', 'tainacan-journal-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Available tokens', 'tainacan-journal-manager'); ?></th>
                        <td>
                            <?php foreach ($tokens as $t) : ?>
                                <code style="margin-right: 8px;">{{<?php echo esc_html($t); ?>}}</code>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Tokens not in this list will be left as-is, useful for spotting missing data.', 'tainacan-journal-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save template', 'tainacan-journal-manager')); ?>
            </form>

            <?php if ($override) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 8px;">
                <?php wp_nonce_field('tjm_reset_email_template', 'tjm_email_nonce'); ?>
                <input type="hidden" name="action" value="tjm_reset_email_template">
                <input type="hidden" name="tpl" value="<?php echo esc_attr($current); ?>">
                <button type="submit" class="button button-secondary"
                        onclick="return confirm('<?php echo esc_js(__('Revert to bundled default for this template?', 'tainacan-journal-manager')); ?>');">
                    <?php esc_html_e('Reset to default', 'tainacan-journal-manager'); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'tainacan-journal-manager'));
        }
        check_admin_referer('tjm_save_email_template', 'tjm_email_nonce');

        $tpl = isset($_POST['tpl']) ? sanitize_text_field((string) $_POST['tpl']) : '';
        if (! in_array($tpl, TemplateOverrides::KNOWN_KEYS, true)) {
            wp_die(esc_html__('Unknown template.', 'tainacan-journal-manager'));
        }

        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash((string) $_POST['subject'])) : '';
        $body    = isset($_POST['body']) ? wp_kses_post(wp_unslash((string) $_POST['body'])) : '';

        TemplateOverrides::save($tpl, $subject, $body);

        wp_safe_redirect(add_query_arg([
            'page'  => $this->get_page_slug(),
            'tpl'   => $tpl,
            'saved' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_reset(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'tainacan-journal-manager'));
        }
        check_admin_referer('tjm_reset_email_template', 'tjm_email_nonce');

        $tpl = isset($_POST['tpl']) ? sanitize_text_field((string) $_POST['tpl']) : '';
        if (! in_array($tpl, TemplateOverrides::KNOWN_KEYS, true)) {
            wp_die(esc_html__('Unknown template.', 'tainacan-journal-manager'));
        }
        TemplateOverrides::delete($tpl);

        wp_safe_redirect(add_query_arg([
            'page'  => $this->get_page_slug(),
            'tpl'   => $tpl,
            'reset' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * @return string[]
     */
    private static function tokens_for(string $key): array
    {
        return match ($key) {
            'submission-received',
            'submission-in-triage',
            'submission-in-review' => ['author_name', 'title', 'journal_name', 'submission_id'],
            'review-invitation'    => ['reviewer_name', 'title', 'deadline', 'accept_url', 'decline_url'],
            'review-reminder',
            'review-overdue'       => ['reviewer_name', 'title', 'deadline'],
            'review-thanks'        => ['reviewer_name', 'title'],
            'decision-accept',
            'decision-reject',
            'decision-revisions'   => ['author_name', 'title', 'note'],
            'submission-published' => ['author_name', 'title', 'submission_id'],
            'editor-new-submission' => ['editor_name', 'title', 'author_name', 'journal_name', 'submission_id'],
            'editor-review-received' => ['editor_name', 'title', 'review_id'],
            'copyediting-version'  => ['author_name', 'title', 'note', 'submission_id'],
            'proof-request'        => ['author_name', 'title', 'submission_id'],
            default                => ['author_name', 'title'],
        };
    }
}
