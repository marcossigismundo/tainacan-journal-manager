<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Config;
use TainacanJournalManager\Indicators\StatsService;

/**
 * Tainacan-integrated landing page for the Journal Manager.
 *
 * The 4 CPTs (Journals, Submissions, Reviews, Issues) are registered as
 * separate Tainacan-style "redirect pages" under
 * `src/Admin/Tainacan/Links/` so the Tainacan navigation sidebar can
 * render proper `?page=tjm_*_link` URLs (Tainacan's PHP nav builder
 * doesn't support raw `edit.php?post_type=...` URLs as menu slugs and
 * mangles them via `add_query_arg('page', $slug)`). Each link page just
 * does an early `wp_safe_redirect` to the native CPT admin screen.
 */
class DashboardPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    private const POSITION = 8;

    protected function get_page_slug(): string
    {
        return 'tjm_dashboard';
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_root_menu_slug,
            __('Journal Manager', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('reports') . '</span>'
                . '<span class="menu-text">' . __('Journal Manager', 'tainacan-journal-manager') . '</span>',
            'read',
            $this->get_page_slug(),
            [&$this, 'render_page'],
            self::POSITION
        );
        add_action('load-' . $page_suffix, [&$this, 'load_page']);
    }

    public function admin_enqueue_css(): void
    {
        wp_enqueue_style('tjm-tainacan-admin', TJM_URL . 'assets/css/admin-tainacan.css', [], TJM_VERSION);
    }

    public function render_page_content(): void
    {
        $stats   = StatsService::get_overview();
        $totals  = $stats['total'] ?? [];
        $status  = $stats['submissions_per_status'] ?? [];
        $ar      = $stats['acceptance_rate'] ?? [];

        // Counts of work items waiting for action — drives the "needs
        // attention" badges so editors know where to click first.
        $needs_triage    = (int) ($status[Config::STATUS_SUBMITTED]  ?? 0);
        $in_review       = (int) ($status[Config::STATUS_REVIEW]     ?? 0);
        $awaiting_dec    = (int) ($status[Config::STATUS_DECISION]   ?? 0);
        $in_copyediting  = (int) ($status[Config::STATUS_COPYEDITING] ?? 0);
        $in_production   = (int) ($status[Config::STATUS_PRODUCTION] ?? 0);
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager', 'tainacan-journal-manager'); ?></h1>
                <p class="tjm-page-subtitle">
                    <?php esc_html_e('Editorial workflow for scientific journals — central command for everything in your repository.', 'tainacan-journal-manager'); ?>
                </p>
            </div>

            <!-- KPI cards: high-level numbers --------------------------- -->
            <section class="tjm-tn-section">
                <h2 class="tjm-tn-section-heading"><?php esc_html_e('Overview', 'tainacan-journal-manager'); ?></h2>
                <div class="tjm-tn-cards">
                    <a class="tjm-tn-card" href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION)); ?>">
                        <span class="tjm-tn-card-num"><?php echo (int) ($totals['submissions'] ?? 0); ?></span>
                        <span class="tjm-tn-card-label"><?php esc_html_e('Submissions', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <div class="tjm-tn-card tjm-tn-card--success">
                        <span class="tjm-tn-card-num"><?php echo (int) ($totals['published'] ?? 0); ?></span>
                        <span class="tjm-tn-card-label"><?php esc_html_e('Published', 'tainacan-journal-manager'); ?></span>
                    </div>
                    <a class="tjm-tn-card" href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_REVIEW)); ?>">
                        <span class="tjm-tn-card-num"><?php echo (int) ($totals['reviews'] ?? 0); ?></span>
                        <span class="tjm-tn-card-label"><?php esc_html_e('Reviews', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <a class="tjm-tn-card" href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_JOURNAL)); ?>">
                        <span class="tjm-tn-card-num"><?php echo (int) ($totals['journals'] ?? 0); ?></span>
                        <span class="tjm-tn-card-label"><?php esc_html_e('Journals', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <a class="tjm-tn-card" href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_ISSUE)); ?>">
                        <span class="tjm-tn-card-num"><?php echo (int) ($totals['issues'] ?? 0); ?></span>
                        <span class="tjm-tn-card-label"><?php esc_html_e('Issues', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <div class="tjm-tn-card tjm-tn-card--accent">
                        <span class="tjm-tn-card-num"><?php echo esc_html((string) ($ar['rate'] ?? 0)); ?>%</span>
                        <span class="tjm-tn-card-label"><?php esc_html_e('Acceptance', 'tainacan-journal-manager'); ?></span>
                    </div>
                </div>
            </section>

            <!-- Needs attention: actionable queue ---------------------- -->
            <section class="tjm-tn-section">
                <h2 class="tjm-tn-section-heading"><?php esc_html_e('Needs your attention', 'tainacan-journal-manager'); ?></h2>
                <div class="tjm-tn-queue">
                    <?php
                    $queue = [
                        [
                            'count' => $needs_triage,
                            'label' => __('To triage', 'tainacan-journal-manager'),
                            'desc'  => __('Submitted manuscripts waiting for first editorial review.', 'tainacan-journal-manager'),
                            'href'  => admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION . '&tjm_status=' . Config::STATUS_SUBMITTED),
                            'tone'  => 'warning',
                        ],
                        [
                            'count' => $in_review,
                            'label' => __('In peer review', 'tainacan-journal-manager'),
                            'desc'  => __('Reviewers working on assigned papers.', 'tainacan-journal-manager'),
                            'href'  => admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION . '&tjm_status=' . Config::STATUS_REVIEW),
                            'tone'  => 'info',
                        ],
                        [
                            'count' => $awaiting_dec,
                            'label' => __('Awaiting decision', 'tainacan-journal-manager'),
                            'desc'  => __('Reviews complete; editor must accept, request revisions or reject.', 'tainacan-journal-manager'),
                            'href'  => admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION . '&tjm_status=' . Config::STATUS_DECISION),
                            'tone'  => 'warning',
                        ],
                        [
                            'count' => $in_copyediting,
                            'label' => __('Copyediting', 'tainacan-journal-manager'),
                            'desc'  => __('Accepted papers being edited.', 'tainacan-journal-manager'),
                            'href'  => admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION . '&tjm_status=' . Config::STATUS_COPYEDITING),
                            'tone'  => 'info',
                        ],
                        [
                            'count' => $in_production,
                            'label' => __('In production', 'tainacan-journal-manager'),
                            'desc'  => __('Galleys, proof approval, ready to publish.', 'tainacan-journal-manager'),
                            'href'  => admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION . '&tjm_status=' . Config::STATUS_PRODUCTION),
                            'tone'  => 'info',
                        ],
                    ];
                    foreach ($queue as $q) :
                        $is_zero = ((int) $q['count']) === 0;
                    ?>
                        <a class="tjm-tn-queue-item tjm-tn-queue-item--<?php echo esc_attr($q['tone']); ?> <?php echo $is_zero ? 'is-empty' : ''; ?>"
                           href="<?php echo esc_url($q['href']); ?>">
                            <span class="tjm-tn-queue-count"><?php echo (int) $q['count']; ?></span>
                            <span class="tjm-tn-queue-body">
                                <strong><?php echo esc_html($q['label']); ?></strong>
                                <span class="tjm-tn-queue-desc"><?php echo esc_html($q['desc']); ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Quick actions: get-started buttons --------------------- -->
            <section class="tjm-tn-section">
                <h2 class="tjm-tn-section-heading"><?php esc_html_e('Quick actions', 'tainacan-journal-manager'); ?></h2>
                <div class="tjm-tn-actions">
                    <a class="tjm-tn-action" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . Config::CPT_JOURNAL)); ?>">
                        <strong>+ <?php esc_html_e('New journal', 'tainacan-journal-manager'); ?></strong>
                        <span><?php esc_html_e('Set up a new periodical with sections, review type and editorial team.', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <a class="tjm-tn-action" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . Config::CPT_ISSUE)); ?>">
                        <strong>+ <?php esc_html_e('New issue', 'tainacan-journal-manager'); ?></strong>
                        <span><?php esc_html_e('Create a volume / number / dossier and assign accepted articles.', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <a class="tjm-tn-action" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_report=editorial')); ?>">
                        <strong><?php esc_html_e('Editorial report', 'tainacan-journal-manager'); ?></strong>
                        <span><?php esc_html_e('Open the print-ready PDF report with KPIs across all journals.', 'tainacan-journal-manager'); ?></span>
                    </a>
                    <a class="tjm-tn-action" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=Identify')); ?>">
                        <strong><?php esc_html_e('OAI-PMH endpoint', 'tainacan-journal-manager'); ?></strong>
                        <span><?php esc_html_e('Public harvest endpoint for indexers (Google Scholar, BASE, OpenAIRE).', 'tainacan-journal-manager'); ?></span>
                    </a>
                </div>
            </section>

            <!-- Configuration shortcuts -------------------------------- -->
            <section class="tjm-tn-section">
                <h2 class="tjm-tn-section-heading"><?php esc_html_e('Configuration', 'tainacan-journal-manager'); ?></h2>
                <div class="tjm-tn-grid">
                    <div class="tjm-tn-block">
                        <h3><?php esc_html_e('Editorial setup', 'tainacan-journal-manager'); ?></h3>
                        <ul>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_settings')); ?>"><?php esc_html_e('General settings', 'tainacan-journal-manager'); ?></a></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_email_templates')); ?>"><?php esc_html_e('Email templates', 'tainacan-journal-manager'); ?></a></li>
                        </ul>
                    </div>
                    <div class="tjm-tn-block">
                        <h3><?php esc_html_e('Interoperability', 'tainacan-journal-manager'); ?></h3>
                        <ul>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_integrations')); ?>"><?php esc_html_e('ORCID, Crossref, DOAJ', 'tainacan-journal-manager'); ?></a></li>
                            <li><a target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=ListSets')); ?>"><?php esc_html_e('OAI-PMH sets', 'tainacan-journal-manager'); ?></a></li>
                        </ul>
                    </div>
                    <div class="tjm-tn-block">
                        <h3><?php esc_html_e('Audit &amp; reports', 'tainacan-journal-manager'); ?></h3>
                        <ul>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_audit_log')); ?>"><?php esc_html_e('Audit log', 'tainacan-journal-manager'); ?></a></li>
                            <li><a target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_report=editorial')); ?>"><?php esc_html_e('Editorial PDF report', 'tainacan-journal-manager'); ?></a></li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }
}
