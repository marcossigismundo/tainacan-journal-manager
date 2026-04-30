<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Config;
use TainacanJournalManager\Indicators\StatsService;

/**
 * Tainacan-integrated landing page for the Journal Manager.
 *
 * This class extends Tainacan's `\Tainacan\Pages` to render inside the
 * Tainacan admin shell (sidebar + theme + asset loader). The class is
 * declared in the `Tainacan` namespace as Tainacan's docs require.
 *
 * Loaded conditionally by Plugin::init when class_exists('\Tainacan\Pages').
 */
class DashboardPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string
    {
        return 'tjm_dashboard';
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_root_menu_slug,
            __('Journal Manager', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('book') . '</span>'
                . '<span class="menu-text">' . __('Journals', 'tainacan-journal-manager') . '</span>',
            'read',
            $this->get_page_slug(),
            [&$this, 'render_page'],
            8
        );
        add_action('load-' . $page_suffix, [&$this, 'load_page']);
    }

    public function admin_enqueue_css(): void
    {
        wp_enqueue_style('tjm-tainacan-admin', TJM_URL . 'assets/css/admin-tainacan.css', [], TJM_VERSION);
    }

    public function render_page_content(): void
    {
        $stats = StatsService::get_overview();
        $totals = $stats['total'] ?? [];
        $ar     = $stats['acceptance_rate'] ?? [];
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager', 'tainacan-journal-manager'); ?></h1>
                <p class="tjm-page-subtitle">
                    <?php esc_html_e('Editorial workflow for scientific journals on top of Tainacan.', 'tainacan-journal-manager'); ?>
                </p>
            </div>

            <section class="tjm-tn-cards">
                <?php
                $cards = [
                    ['label' => __('Submissions', 'tainacan-journal-manager'),  'num' => (int) ($totals['submissions'] ?? 0),  'href' => admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION)],
                    ['label' => __('Published',   'tainacan-journal-manager'),  'num' => (int) ($totals['published']   ?? 0),  'href' => ''],
                    ['label' => __('Reviews',     'tainacan-journal-manager'),  'num' => (int) ($totals['reviews']     ?? 0),  'href' => admin_url('edit.php?post_type=' . Config::CPT_REVIEW)],
                    ['label' => __('Journals',    'tainacan-journal-manager'),  'num' => (int) ($totals['journals']    ?? 0),  'href' => admin_url('edit.php?post_type=' . Config::CPT_JOURNAL)],
                    ['label' => __('Issues',      'tainacan-journal-manager'),  'num' => (int) ($totals['issues']      ?? 0),  'href' => admin_url('edit.php?post_type=' . Config::CPT_ISSUE)],
                    ['label' => __('Acceptance',  'tainacan-journal-manager'),  'num' => ((string) ($ar['rate'] ?? 0)) . '%',   'href' => ''],
                ];
                foreach ($cards as $c) :
                    $tag = $c['href'] ? 'a' : 'div';
                ?>
                    <<?php echo $tag; ?> class="tjm-tn-card" <?php echo $c['href'] ? 'href="' . esc_url($c['href']) . '"' : ''; ?>>
                        <span class="tjm-tn-card-num"><?php echo esc_html((string) $c['num']); ?></span>
                        <span class="tjm-tn-card-label"><?php echo esc_html($c['label']); ?></span>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </section>

            <section class="tjm-tn-grid">
                <div class="tjm-tn-block">
                    <h2><?php esc_html_e('Editorial workflow', 'tainacan-journal-manager'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_JOURNAL)); ?>"><?php esc_html_e('Manage Journals', 'tainacan-journal-manager'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_SUBMISSION)); ?>"><?php esc_html_e('Manage Submissions', 'tainacan-journal-manager'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_REVIEW)); ?>"><?php esc_html_e('Manage Reviews', 'tainacan-journal-manager'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Config::CPT_ISSUE)); ?>"><?php esc_html_e('Manage Issues', 'tainacan-journal-manager'); ?></a></li>
                    </ul>
                </div>

                <div class="tjm-tn-block">
                    <h2><?php esc_html_e('Configuration', 'tainacan-journal-manager'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_settings')); ?>"><?php esc_html_e('General settings', 'tainacan-journal-manager'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_integrations')); ?>"><?php esc_html_e('Integrations (ORCID, Crossref, DOAJ)', 'tainacan-journal-manager'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_email_templates')); ?>"><?php esc_html_e('Email templates', 'tainacan-journal-manager'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=tjm_audit_log')); ?>"><?php esc_html_e('Audit log', 'tainacan-journal-manager'); ?></a></li>
                    </ul>
                </div>

                <div class="tjm-tn-block">
                    <h2><?php esc_html_e('Public pages', 'tainacan-journal-manager'); ?></h2>
                    <ul>
                        <li><a target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_report=editorial')); ?>"><?php esc_html_e('Editorial PDF report', 'tainacan-journal-manager'); ?></a></li>
                        <li><a target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/?tjm_oai=1&verb=Identify')); ?>"><?php esc_html_e('OAI-PMH endpoint', 'tainacan-journal-manager'); ?></a></li>
                    </ul>
                </div>
            </section>
        </div>
        <?php
    }
}
