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

        add_submenu_page(
            'tjm-main',
            __('Integrations', 'tainacan-journal-manager'),
            __('Integrations', 'tainacan-journal-manager'),
            'manage_options',
            'tjm-integrations',
            [$this, 'render_integrations']
        );
    }

    public function register_settings(): void
    {
        register_setting('tjm_settings', Config::OPTION_EMAILS_ENABLED, ['type' => 'boolean', 'default' => true]);
        register_setting('tjm_settings', Config::OPTION_EMAIL_FROM_NAME, ['type' => 'string', 'default' => Config::EMAIL_FROM_NAME]);
        register_setting('tjm_settings', Config::OPTION_EMAIL_FROM_ADDRESS, ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings', Config::OPTION_REVIEW_DEADLINE_DAYS, ['type' => 'integer', 'default' => Config::DEFAULT_REVIEW_DEADLINE]);
        register_setting('tjm_settings', Config::OPTION_TOKEN_VALIDITY_DAYS, ['type' => 'integer', 'default' => Config::DEFAULT_TOKEN_VALIDITY]);

        // Phase 5 — integrations
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\OrcidOAuthService::OPT_CLIENT_ID,     ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\OrcidOAuthService::OPT_CLIENT_SECRET, ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\OrcidOAuthService::OPT_USE_SANDBOX,   ['type' => 'boolean','default' => false]);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\CrossrefDeposit::OPT_USERNAME,        ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\CrossrefDeposit::OPT_PASSWORD,        ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\CrossrefDeposit::OPT_USE_TEST,        ['type' => 'boolean','default' => false]);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\CrossrefExporter::OPT_DEPOSITOR_NAME, ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\CrossrefExporter::OPT_DEPOSITOR_EMAIL,['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\CrossrefExporter::OPT_REGISTRANT,     ['type' => 'string', 'default' => '']);
        register_setting('tjm_settings_integrations', \TainacanJournalManager\Integrations\DoajExporter::OPT_API_KEY,            ['type' => 'string', 'default' => '']);
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

    public function render_integrations(): void
    {
        $orcid_oauth   = \TainacanJournalManager\Integrations\OrcidOAuthService::class;
        $crossref_dep  = \TainacanJournalManager\Integrations\CrossrefDeposit::class;
        $crossref_exp  = \TainacanJournalManager\Integrations\CrossrefExporter::class;
        $doaj          = \TainacanJournalManager\Integrations\DoajExporter::class;

        $oai_url = home_url('/?tjm_oai=1&verb=Identify');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Journal Manager — Integrations', 'tainacan-journal-manager'); ?></h1>
            <p class="description">
                <?php esc_html_e('Credentials for ORCID OAuth, Crossref DOI deposit and DOAJ Article API. Each integration is optional; the editorial workflow runs without any of them.', 'tainacan-journal-manager'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('tjm_settings_integrations'); ?>

                <h2><?php esc_html_e('ORCID', 'tainacan-journal-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Register an application at https://orcid.org/developer-tools to obtain a client_id and client_secret. Set the redirect URI to:', 'tainacan-journal-manager'); ?>
                    <code><?php echo esc_html(home_url('/?tjm_orcid=callback')); ?></code></p>
                <table class="form-table">
                    <tr><th><?php esc_html_e('Client ID', 'tainacan-journal-manager'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr($orcid_oauth::OPT_CLIENT_ID); ?>" value="<?php echo esc_attr((string) get_option($orcid_oauth::OPT_CLIENT_ID, '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Client Secret', 'tainacan-journal-manager'); ?></th>
                        <td><input type="password" name="<?php echo esc_attr($orcid_oauth::OPT_CLIENT_SECRET); ?>" value="<?php echo esc_attr((string) get_option($orcid_oauth::OPT_CLIENT_SECRET, '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Use sandbox', 'tainacan-journal-manager'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr($orcid_oauth::OPT_USE_SANDBOX); ?>" value="1" <?php checked((bool) get_option($orcid_oauth::OPT_USE_SANDBOX, false)); ?>></td></tr>
                </table>

                <h2><?php esc_html_e('Crossref', 'tainacan-journal-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Crossref deposit account credentials. Use the test endpoint while validating XML output.', 'tainacan-journal-manager'); ?></p>
                <table class="form-table">
                    <tr><th><?php esc_html_e('Username', 'tainacan-journal-manager'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr($crossref_dep::OPT_USERNAME); ?>" value="<?php echo esc_attr((string) get_option($crossref_dep::OPT_USERNAME, '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Password', 'tainacan-journal-manager'); ?></th>
                        <td><input type="password" name="<?php echo esc_attr($crossref_dep::OPT_PASSWORD); ?>" value="<?php echo esc_attr((string) get_option($crossref_dep::OPT_PASSWORD, '')); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Use test endpoint', 'tainacan-journal-manager'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr($crossref_dep::OPT_USE_TEST); ?>" value="1" <?php checked((bool) get_option($crossref_dep::OPT_USE_TEST, false)); ?>></td></tr>
                    <tr><th><?php esc_html_e('Depositor name', 'tainacan-journal-manager'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr($crossref_exp::OPT_DEPOSITOR_NAME); ?>" value="<?php echo esc_attr((string) get_option($crossref_exp::OPT_DEPOSITOR_NAME, get_bloginfo('name'))); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Depositor email', 'tainacan-journal-manager'); ?></th>
                        <td><input type="email" name="<?php echo esc_attr($crossref_exp::OPT_DEPOSITOR_EMAIL); ?>" value="<?php echo esc_attr((string) get_option($crossref_exp::OPT_DEPOSITOR_EMAIL, get_option('admin_email', ''))); ?>" class="regular-text"></td></tr>
                    <tr><th><?php esc_html_e('Registrant', 'tainacan-journal-manager'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr($crossref_exp::OPT_REGISTRANT); ?>" value="<?php echo esc_attr((string) get_option($crossref_exp::OPT_REGISTRANT, '')); ?>" class="regular-text"></td></tr>
                </table>

                <h2><?php esc_html_e('DOAJ', 'tainacan-journal-manager'); ?></h2>
                <p class="description"><?php esc_html_e('API key for the DOAJ Articles endpoint (publisher account).', 'tainacan-journal-manager'); ?></p>
                <table class="form-table">
                    <tr><th><?php esc_html_e('API key', 'tainacan-journal-manager'); ?></th>
                        <td><input type="password" name="<?php echo esc_attr($doaj::OPT_API_KEY); ?>" value="<?php echo esc_attr((string) get_option($doaj::OPT_API_KEY, '')); ?>" class="regular-text"></td></tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('OAI-PMH endpoint', 'tainacan-journal-manager'); ?></h2>
            <p>
                <?php esc_html_e('Public OAI-PMH 2.0 endpoint:', 'tainacan-journal-manager'); ?>
                <code><?php echo esc_html(home_url('/?tjm_oai=1')); ?></code>
            </p>
            <p>
                <a class="button" href="<?php echo esc_url($oai_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Test Identify', 'tainacan-journal-manager'); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=ListMetadataFormats')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Test ListMetadataFormats', 'tainacan-journal-manager'); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=ListSets')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Test ListSets', 'tainacan-journal-manager'); ?></a>
            </p>
        </div>
        <?php
    }
}
