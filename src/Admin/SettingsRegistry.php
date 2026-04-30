<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin;

use TainacanJournalManager\Config;
use TainacanJournalManager\Integrations\CrossrefDeposit;
use TainacanJournalManager\Integrations\CrossrefExporter;
use TainacanJournalManager\Integrations\DoajExporter;
use TainacanJournalManager\Integrations\OrcidOAuthService;

/**
 * Pure settings registration via WordPress Settings API.
 *
 * No menu, no rendering — those are handled by the Tainacan-integrated
 * page classes (Admin\Tainacan\*). Decoupling registration lets settings
 * be registered even if the menu is hidden or moved.
 */
final class SettingsRegistry
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings(): void
    {
        // Core
        register_setting('tjm_settings', Config::OPTION_EMAILS_ENABLED,       ['type' => 'boolean', 'default' => true]);
        register_setting('tjm_settings', Config::OPTION_EMAIL_FROM_NAME,      ['type' => 'string',  'default' => Config::EMAIL_FROM_NAME]);
        register_setting('tjm_settings', Config::OPTION_EMAIL_FROM_ADDRESS,   ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings', Config::OPTION_REVIEW_DEADLINE_DAYS, ['type' => 'integer', 'default' => Config::DEFAULT_REVIEW_DEADLINE]);
        register_setting('tjm_settings', Config::OPTION_TOKEN_VALIDITY_DAYS,  ['type' => 'integer', 'default' => Config::DEFAULT_TOKEN_VALIDITY]);

        // Integrations (Phase 5)
        register_setting('tjm_settings_integrations', OrcidOAuthService::OPT_CLIENT_ID,     ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', OrcidOAuthService::OPT_CLIENT_SECRET, ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', OrcidOAuthService::OPT_USE_SANDBOX,   ['type' => 'boolean', 'default' => false]);
        register_setting('tjm_settings_integrations', CrossrefDeposit::OPT_USERNAME,        ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', CrossrefDeposit::OPT_PASSWORD,        ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', CrossrefDeposit::OPT_USE_TEST,        ['type' => 'boolean', 'default' => false]);
        register_setting('tjm_settings_integrations', CrossrefExporter::OPT_DEPOSITOR_NAME, ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', CrossrefExporter::OPT_DEPOSITOR_EMAIL,['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', CrossrefExporter::OPT_REGISTRANT,     ['type' => 'string',  'default' => '']);
        register_setting('tjm_settings_integrations', DoajExporter::OPT_API_KEY,            ['type' => 'string',  'default' => '']);
    }
}
