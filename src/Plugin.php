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
use TainacanJournalManager\Frontend\RoleManagement;
use TainacanJournalManager\Frontend\CopyeditingDashboard;
use TainacanJournalManager\Frontend\PublicArticle;
use TainacanJournalManager\Frontend\Ajax\SubmissionAjax;
use TainacanJournalManager\Frontend\Ajax\EditorialAjax;
use TainacanJournalManager\Frontend\Ajax\ReviewAjax;
use TainacanJournalManager\Frontend\Ajax\RolesAjax;
use TainacanJournalManager\Frontend\Ajax\ProductionAjax;
use TainacanJournalManager\Frontend\Ajax\IssueAjax;
use TainacanJournalManager\Frontend\Ajax\IndicatorsAjax;
use TainacanJournalManager\Frontend\Ajax\IntegrationsAjax;
use TainacanJournalManager\Indicators\StatsService;
use TainacanJournalManager\Integrations\OrcidOAuthService;
use TainacanJournalManager\Integrations\OaiPmhProvider;
use TainacanJournalManager\Integrations\ScholarMetadata;
use TainacanJournalManager\Audit\AuditLog;
use TainacanJournalManager\Notifications\TemplateOverrides;
use TainacanJournalManager\Reports\ReportRenderer;
use TainacanJournalManager\Roles\RoleManager;
use TainacanJournalManager\Admin\SettingsRegistry;

/**
 * Main plugin orchestrator (singleton).
 *
 * The plugin lives entirely inside the Tainacan admin shell — its admin
 * pages extend `\Tainacan\Pages` and are loaded only when Tainacan is
 * active (the bootstrap aborts otherwise). The frontend portals
 * (author, reviewer, editorial, copyediting, public article) keep the
 * shortcode-driven approach so they render in any theme.
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

        // ── Frontend (shortcodes used in any theme) ─────────────────
        (new AuthGuard())->register();
        (new LoginPage())->register();
        (new AuthorPortal())->register();
        (new ReviewerDashboard())->register();
        (new EditorialDashboard())->register();
        (new PublicJournal())->register();
        (new IndicatorsDashboard())->register();
        (new RoleManagement())->register();
        (new CopyeditingDashboard())->register();
        (new PublicArticle())->register();

        // ── AJAX handlers ──────────────────────────────────────────
        (new SubmissionAjax())->register();
        (new EditorialAjax())->register();
        (new ReviewAjax())->register();
        (new RolesAjax())->register();
        (new ProductionAjax())->register();
        (new IssueAjax())->register();
        (new IndicatorsAjax())->register();
        (new IntegrationsAjax())->register();

        // ── Phase 5 integrations ───────────────────────────────────
        (new OrcidOAuthService())->register();
        (new OaiPmhProvider())->register();
        (new ScholarMetadata())->register();

        // ── Phase 6 — audit, email overrides, manager reports ──────
        AuditLog::maybe_upgrade();
        (new AuditLog())->register();
        (new TemplateOverrides())->register();
        (new ReportRenderer())->register();

        // Invalidate stats cache on workflow events
        add_action('tjm_status_transition',  [StatsService::class, 'invalidate_cache']);
        add_action('tjm_review_submitted',   [StatsService::class, 'invalidate_cache']);
        add_action('tjm_decision_recorded',  [StatsService::class, 'invalidate_cache']);
        add_action('tjm_article_published',  [StatsService::class, 'invalidate_cache']);
        add_action('tjm_issue_published',    [StatsService::class, 'invalidate_cache']);

        // ── Admin (Tainacan-integrated) ────────────────────────────
        if (is_admin()) {
            // Pure WP Settings API registrations (works without Tainacan)
            (new SettingsRegistry())->register();

            // Page classes extend \Tainacan\Pages — already verified in
            // the bootstrap. Each one is a Singleton (Tainacan trait).
            $this->boot_tainacan_pages();
        }

        // ── Assets ──────────────────────────────────────────────────
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // ── Cron ────────────────────────────────────────────────────
        $this->register_cron();
    }

    /**
     * Instantiate every Tainacan-integrated admin page singleton.
     * Each call also registers the page's hooks (admin_menu etc.).
     *
     * Order matters because each call adds a row to WP's $submenu and
     * Tainacan's nav iterates that array. We register the Dashboard
     * first (the entry point), then the 4 CPT redirector pages so the
     * editorial workflow order shows in the sidebar (Journals →
     * Submissions → Reviews → Issues), and finally the 4 admin-config
     * pages under Tainacan's "Other links" group.
     */
    private function boot_tainacan_pages(): void
    {
        \TainacanJournalManager\Admin\Tainacan\DashboardPage::get_instance();

        // CPT shortcuts (each redirects to edit.php?post_type=...)
        \TainacanJournalManager\Admin\Tainacan\Links\JournalsLinkPage::get_instance();
        \TainacanJournalManager\Admin\Tainacan\Links\SubmissionsLinkPage::get_instance();
        \TainacanJournalManager\Admin\Tainacan\Links\ReviewsLinkPage::get_instance();
        \TainacanJournalManager\Admin\Tainacan\Links\IssuesLinkPage::get_instance();

        // Configuration pages (Other links group)
        \TainacanJournalManager\Admin\Tainacan\SettingsPage::get_instance();
        \TainacanJournalManager\Admin\Tainacan\IntegrationsPage::get_instance();
        \TainacanJournalManager\Admin\Tainacan\EmailTemplatesPage::get_instance();
        \TainacanJournalManager\Admin\Tainacan\AuditLogPage::get_instance();
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

    private function register_cron(): void
    {
        if (! wp_next_scheduled('tjm_send_review_reminders')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'tjm_send_review_reminders');
        }

        add_action('tjm_send_review_reminders', [\TainacanJournalManager\Review\ReviewDeadlineService::class, 'send_reminders']);
    }
}
