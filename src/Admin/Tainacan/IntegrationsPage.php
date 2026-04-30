<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Integrations\CrossrefDeposit;
use TainacanJournalManager\Integrations\CrossrefExporter;
use TainacanJournalManager\Integrations\DoajExporter;
use TainacanJournalManager\Integrations\OrcidOAuthService;

/**
 * Tainacan-integrated page for ORCID, Crossref and DOAJ credentials.
 */
class IntegrationsPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string
    {
        return 'tjm_integrations';
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_other_links_slug,
            __('Journal Manager — Integrations', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('link') . '</span>'
                . '<span class="menu-text">' . __('TJM Integrations', 'tainacan-journal-manager') . '</span>',
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
        $orcid    = OrcidOAuthService::class;
        $cr_dep   = CrossrefDeposit::class;
        $cr_exp   = CrossrefExporter::class;
        $doaj     = DoajExporter::class;
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager — Integrations', 'tainacan-journal-manager'); ?></h1>
                <p class="tjm-page-subtitle">
                    <?php esc_html_e('Credentials for ORCID OAuth, Crossref DOI deposit and DOAJ Article API. Each integration is optional.', 'tainacan-journal-manager'); ?>
                </p>
            </div>

            <form method="post" action="options.php" class="tjm-tn-form">
                <?php settings_fields('tjm_settings_integrations'); ?>

                <h2 class="tjm-tn-section-title"><?php esc_html_e('ORCID', 'tainacan-journal-manager'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Register an application at https://orcid.org/developer-tools to obtain a client_id and client_secret. Set the redirect URI to:', 'tainacan-journal-manager'); ?>
                    <code><?php echo esc_html(home_url('/?tjm_orcid=callback')); ?></code>
                </p>
                <table class="form-table">
                    <tr><th scope="row"><label><?php esc_html_e('Client ID', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="text" name="<?php echo esc_attr($orcid::OPT_CLIENT_ID); ?>" value="<?php echo esc_attr((string) get_option($orcid::OPT_CLIENT_ID, '')); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Client Secret', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="password" name="<?php echo esc_attr($orcid::OPT_CLIENT_SECRET); ?>" value="<?php echo esc_attr((string) get_option($orcid::OPT_CLIENT_SECRET, '')); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Use sandbox', 'tainacan-journal-manager'); ?></label></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($orcid::OPT_USE_SANDBOX); ?>" value="1" <?php checked((bool) get_option($orcid::OPT_USE_SANDBOX, false)); ?>> <?php esc_html_e('Authenticate against sandbox.orcid.org', 'tainacan-journal-manager'); ?></label></td></tr>
                </table>

                <h2 class="tjm-tn-section-title"><?php esc_html_e('Crossref', 'tainacan-journal-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Crossref deposit credentials. Use the test endpoint while validating your XML.', 'tainacan-journal-manager'); ?></p>
                <table class="form-table">
                    <tr><th scope="row"><label><?php esc_html_e('Username', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="text" name="<?php echo esc_attr($cr_dep::OPT_USERNAME); ?>" value="<?php echo esc_attr((string) get_option($cr_dep::OPT_USERNAME, '')); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Password', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="password" name="<?php echo esc_attr($cr_dep::OPT_PASSWORD); ?>" value="<?php echo esc_attr((string) get_option($cr_dep::OPT_PASSWORD, '')); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Use test endpoint', 'tainacan-journal-manager'); ?></label></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($cr_dep::OPT_USE_TEST); ?>" value="1" <?php checked((bool) get_option($cr_dep::OPT_USE_TEST, false)); ?>> <?php esc_html_e('Send to test.crossref.org', 'tainacan-journal-manager'); ?></label></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Depositor name', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="text" name="<?php echo esc_attr($cr_exp::OPT_DEPOSITOR_NAME); ?>" value="<?php echo esc_attr((string) get_option($cr_exp::OPT_DEPOSITOR_NAME, get_bloginfo('name'))); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Depositor email', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="email" name="<?php echo esc_attr($cr_exp::OPT_DEPOSITOR_EMAIL); ?>" value="<?php echo esc_attr((string) get_option($cr_exp::OPT_DEPOSITOR_EMAIL, get_option('admin_email', ''))); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row"><label><?php esc_html_e('Registrant', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="text" name="<?php echo esc_attr($cr_exp::OPT_REGISTRANT); ?>" value="<?php echo esc_attr((string) get_option($cr_exp::OPT_REGISTRANT, '')); ?>" class="regular-text"></td></tr>
                </table>

                <h2 class="tjm-tn-section-title"><?php esc_html_e('DOAJ', 'tainacan-journal-manager'); ?></h2>
                <p class="description"><?php esc_html_e('API key for the DOAJ Articles endpoint (publisher account).', 'tainacan-journal-manager'); ?></p>
                <table class="form-table">
                    <tr><th scope="row"><label><?php esc_html_e('API key', 'tainacan-journal-manager'); ?></label></th>
                        <td><input type="password" name="<?php echo esc_attr($doaj::OPT_API_KEY); ?>" value="<?php echo esc_attr((string) get_option($doaj::OPT_API_KEY, '')); ?>" class="regular-text"></td></tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2 class="tjm-tn-section-title"><?php esc_html_e('OAI-PMH endpoint', 'tainacan-journal-manager'); ?></h2>
            <p>
                <?php esc_html_e('Public OAI-PMH 2.0 endpoint:', 'tainacan-journal-manager'); ?>
                <code><?php echo esc_html(home_url('/?tjm_oai=1')); ?></code>
            </p>
            <p>
                <a class="button button-secondary" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=Identify')); ?>"><?php esc_html_e('Test Identify', 'tainacan-journal-manager'); ?></a>
                <a class="button button-secondary" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=ListMetadataFormats')); ?>"><?php esc_html_e('Test ListMetadataFormats', 'tainacan-journal-manager'); ?></a>
                <a class="button button-secondary" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=ListSets')); ?>"><?php esc_html_e('Test ListSets', 'tainacan-journal-manager'); ?></a>
            </p>
        </div>
        <?php
    }
}
