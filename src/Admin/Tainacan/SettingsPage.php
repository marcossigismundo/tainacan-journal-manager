<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Config;

/**
 * Tainacan-integrated "Settings" page.
 *
 * Wrapped in `<div class="wrap tainacan-page-container-content">` so it
 * inherits Tainacan's sidebar / header chrome. Fields are POSTed to
 * options.php using the Settings API group `tjm_settings` registered
 * by `Admin\SettingsRegistry`.
 */
class SettingsPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string
    {
        return 'tjm_settings';
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_other_links_slug,
            __('Journal Manager — Settings', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('settings') . '</span>'
                . '<span class="menu-text">' . __('TJM Settings', 'tainacan-journal-manager') . '</span>',
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
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager — Settings', 'tainacan-journal-manager'); ?></h1>
            </div>

            <form method="post" action="options.php" class="tjm-tn-form">
                <?php settings_fields('tjm_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tjm-emails-enabled"><?php esc_html_e('Enable emails', 'tainacan-journal-manager'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="tjm-emails-enabled" name="<?php echo esc_attr(Config::OPTION_EMAILS_ENABLED); ?>" value="1" <?php checked((bool) get_option(Config::OPTION_EMAILS_ENABLED, true)); ?>>
                                <?php esc_html_e('Send notifications by email.', 'tainacan-journal-manager'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Uncheck to disable all notifications (useful while testing).', 'tainacan-journal-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tjm-from-name"><?php esc_html_e('Email "From" name', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="text" id="tjm-from-name" name="<?php echo esc_attr(Config::OPTION_EMAIL_FROM_NAME); ?>" value="<?php echo esc_attr((string) get_option(Config::OPTION_EMAIL_FROM_NAME, Config::EMAIL_FROM_NAME)); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tjm-from-email"><?php esc_html_e('Email "From" address', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="email" id="tjm-from-email" name="<?php echo esc_attr(Config::OPTION_EMAIL_FROM_ADDRESS); ?>" value="<?php echo esc_attr((string) get_option(Config::OPTION_EMAIL_FROM_ADDRESS, '')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tjm-review-deadline"><?php esc_html_e('Default review deadline (days)', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="number" id="tjm-review-deadline" name="<?php echo esc_attr(Config::OPTION_REVIEW_DEADLINE_DAYS); ?>" value="<?php echo (int) get_option(Config::OPTION_REVIEW_DEADLINE_DAYS, Config::DEFAULT_REVIEW_DEADLINE); ?>" min="1" max="365" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tjm-token-validity"><?php esc_html_e('Token validity (days)', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="number" id="tjm-token-validity" name="<?php echo esc_attr(Config::OPTION_TOKEN_VALIDITY_DAYS); ?>" value="<?php echo (int) get_option(Config::OPTION_TOKEN_VALIDITY_DAYS, Config::DEFAULT_TOKEN_VALIDITY); ?>" min="1" max="365" class="small-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
