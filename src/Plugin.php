<?php

declare(strict_types=1);

namespace TainacanJournalManager;

use TainacanJournalManager\PostTypes\Journal;
use TainacanJournalManager\PostTypes\Submission;
use TainacanJournalManager\PostTypes\Review;
use TainacanJournalManager\PostTypes\Issue;
use TainacanJournalManager\PostTypes\Taxonomies;
use TainacanJournalManager\Frontend\AuthGuard;
use TainacanJournalManager\Frontend\LoginPage;
use TainacanJournalManager\Frontend\AuthorPortal;
use TainacanJournalManager\Frontend\ReviewerDashboard;
use TainacanJournalManager\Frontend\EditorialDashboard;
use TainacanJournalManager\Frontend\PublicJournal;
use TainacanJournalManager\Frontend\IndicatorsDashboard;
use TainacanJournalManager\Roles\RoleManager;
use TainacanJournalManager\Notifications\Mailer;
use TainacanJournalManager\Tainacan\CollectionProvisioner;
use TainacanJournalManager\Admin\SettingsPage;

/**
 * Main plugin orchestrator (singleton).
 */
final class Plugin
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        // ── Custom Post Types & Taxonomies ──────────────────────────
        (new Journal())->register();
        (new Submission())->register();
        (new Review())->register();
        (new Issue())->register();
        (new Taxonomies())->register();

        // ── Roles upgrade safety check ──────────────────────────────
        add_action('admin_init', function (): void {
            $current = (string) get_option(Config::OPTION_VERSION, '');
            if ($current !== TJM_VERSION) {
                RoleManager::install();
                update_option(Config::OPTION_VERSION, TJM_VERSION);
            }
        }, 5);

        // ── Frontend ────────────────────────────────────────────────
        (new AuthGuard())->register();
        (new LoginPage())->register();
        (new AuthorPortal())->register();
        (new ReviewerDashboard())->register();
        (new EditorialDashboard())->register();
        (new PublicJournal())->register();
        (new IndicatorsDashboard())->register();

        // ── Admin ───────────────────────────────────────────────────
        if (is_admin()) {
            (new SettingsPage())->register();
        }

        // ── Assets ──────────────────────────────────────────────────
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // ── Cron ────────────────────────────────────────────────────
        $this->register_cron();
    }

    public function enqueue_frontend_assets(): void
    {
        wp_register_style(
            'tjm-frontend',
            TJM_URL . 'assets/css/frontend.css',
            [],
            TJM_VERSION
        );

        wp_register_script(
            'tjm-frontend',
            TJM_URL . 'assets/js/frontend.js',
            ['jquery'],
            TJM_VERSION,
            true
        );

        wp_localize_script('tjm-frontend', 'tjmFrontend', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('tjm_frontend_nonce'),
            'i18n'      => [
                'confirm_submit' => __('Are you sure you want to submit?', 'tainacan-journal-manager'),
                'loading'        => __('Loading...', 'tainacan-journal-manager'),
                'error'          => __('An error occurred. Please try again.', 'tainacan-journal-manager'),
            ],
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        // Load only on plugin pages
        if (! str_contains($hook, 'tjm-')) {
            return;
        }

        wp_enqueue_style(
            'tjm-admin',
            TJM_URL . 'assets/css/admin.css',
            [],
            TJM_VERSION
        );
    }

    private function register_cron(): void
    {
        if (! wp_next_scheduled('tjm_send_review_reminders')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'tjm_send_review_reminders');
        }

        add_action('tjm_send_review_reminders', [\TainacanJournalManager\Review\ReviewDeadlineService::class, 'send_reminders']);
    }
}
