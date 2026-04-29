<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin;

use TainacanJournalManager\Config;

/**
 * Plugin admin menu and settings page.
 */
final class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Journal Manager', 'tainacan-journal-manager'),
            __('Journal Manager', 'tainacan-journal-manager'),
            'manage_options',
            'tjm-main',
            [$this, 'render_dashboard'],
            'dashicons-book-alt',
            25
        );

        add_submenu_page(
            'tjm-main',
            __('Settings', 'tainacan-journal-manager'),
            __('Settings', 'tainacan-journal-manager'),
            'manage_options',
            'tjm-settings',
            [$this, 'render_settings']
        );
    }

    public function register_settings(): void
    {
        register_setting('tjm_settings', Config::OPTION_EMAILS_ENABLED, ['type' => 'boolean', 'default' => true]);
        register_setting('tjm_settings', Config::OPTION_EMAIL_FROM_NAME, ['type' => 'string', 'default' => Config::EMAIL_FROM_NAME]);
        register_setting('tjm_settings', Config::OPTION_EMAIL_FROM_ADDRESS, ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings', Config::OPTION_REVIEW_DEADLINE_DAYS, ['type' => 'integer', 'default' => Config::DEFAULT_REVIEW_DEADLINE]);
        register_setting('tjm_settings', Config::OPTION_TOKEN_VALIDITY_DAYS, ['type' => 'integer', 'default' => Config::DEFAULT_TOKEN_VALIDITY]);
    }

    public function render_dashboard(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Tainacan Journal Manager', 'tainacan-journal-manager'); ?></h1>
            <p><?php esc_html_e('Welcome to the Tainacan Journal Manager. Use the menu to manage journals, submissions, reviews and issues.', 'tainacan-journal-manager'); ?></p>

            <h2><?php esc_html_e('Quick links', 'tainacan-journal-manager'); ?></h2>
            <ul>
                <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_JOURNAL)); ?>"><?php esc_html_e('Manage Journals', 'tainacan-journal-manager'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION)); ?>"><?php esc_html_e('Manage Submissions', 'tainacan-journal-manager'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_ISSUE)); ?>"><?php esc_html_e('Manage Issues', 'tainacan-journal-manager'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm-settings')); ?>"><?php esc_html_e('Settings', 'tainacan-journal-manager'); ?></a></li>
            </ul>
        </div>
        <?php
    }

    public function render_settings(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Journal Manager — Settings', 'tainacan-journal-manager'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('tjm_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tjm-emails-enabled"><?php esc_html_e('Enable emails', 'tainacan-journal-manager'); ?></label></th>
                        <td>
                            <input type="checkbox" id="tjm-emails-enabled" name="<?php echo esc_attr(Config::OPTION_EMAILS_ENABLED); ?>" value="1" <?php checked((bool) get_option(Config::OPTION_EMAILS_ENABLED, true)); ?>>
                            <p class="description"><?php esc_html_e('Uncheck to disable all email notifications (useful during testing).', 'tainacan-journal-manager'); ?></p>
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
