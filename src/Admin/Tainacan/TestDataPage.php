<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Config;
use TainacanJournalManager\Tools\TestDataPurger;
use TainacanJournalManager\Tools\TestDataSeeder;

/**
 * Tainacan-integrated "Test data" page.
 *
 * Two big buttons drive the seeder/purger described in the
 * "Guia de Testes" PDF — populate the site with the full fixture
 * (10 typed users, 1 journal + Tainacan collection + 17 metadata,
 * 1 issue, 7 frontend pages with shortcodes), or remove only the
 * objects we created (tracked via `tjm_test_seed_inventory`).
 *
 * Saves are POSTed to `admin-post.php` so we can run the seeder
 * before headers go out and redirect with a flash message.
 */
class TestDataPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    public const ACTION_SEED  = 'tjm_test_data_seed';
    public const ACTION_PURGE = 'tjm_test_data_purge';
    public const NONCE_NAME   = 'tjm_test_data_nonce';
    public const NONCE_ACTION = 'tjm_test_data_action';

    protected function get_page_slug(): string
    {
        return 'tjm_test_data';
    }

    public function init(): void
    {
        parent::init();
        add_action('admin_post_' . self::ACTION_SEED,  [$this, 'handle_seed']);
        add_action('admin_post_' . self::ACTION_PURGE, [$this, 'handle_purge']);
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_other_links_slug,
            __('Journal Manager — Test data', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('processes') . '</span>'
                . '<span class="menu-text">' . __('Journal Manager — Test data', 'tainacan-journal-manager') . '</span>',
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

    /* ──────────────────────────────────────────────────────────── */
    /*  Handlers                                                    */
    /* ──────────────────────────────────────────────────────────── */

    public function handle_seed(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'tainacan-journal-manager'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $summary = TestDataSeeder::run();
        $this->redirect_back('seeded', $summary);
    }

    public function handle_purge(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'tainacan-journal-manager'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified one line above via check_admin_referer.
        $confirm = isset($_POST['tjm_purge_confirm'])
            ? sanitize_text_field(wp_unslash((string) $_POST['tjm_purge_confirm']))
            : '';

        if (strtoupper($confirm) !== 'APAGAR') {
            $this->redirect_back('purge_cancelled', []);
            return;
        }

        $summary = TestDataPurger::run();
        $this->redirect_back('purged', $summary);
    }

    /**
     * @param array<string,int> $summary
     */
    private function redirect_back(string $msg, array $summary): void
    {
        $args = [
            'page' => $this->get_page_slug(),
            'msg'  => $msg,
        ];
        foreach ($summary as $key => $value) {
            $args['s_' . $key] = (int) $value;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Render                                                      */
    /* ──────────────────────────────────────────────────────────── */

    public function render_page_content(): void
    {
        $inventory = TestDataSeeder::get_inventory();
        $has_seed  = ! empty($inventory) && ! empty($inventory['ran_at']);
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager — Test data', 'tainacan-journal-manager'); ?></h1>
                <p class="tjm-page-subtitle">
                    <?php esc_html_e('One-click fixture for the testing guide. Creates 10 typed users, 1 journal (with provisioned Tainacan collection and 17 metadata), 1 issue and the 7 frontend pages with their shortcodes.', 'tainacan-journal-manager'); ?>
                </p>
            </div>

            <?php $this->render_messages(); ?>

            <?php if ($has_seed) : ?>
                <?php $this->render_inventory_panel($inventory); ?>
            <?php endif; ?>

            <div class="tjm-tn-card tjm-test-data-card">
                <h2 class="tjm-tn-section-title">
                    <?php esc_html_e('1. Populate the site with the test fixture', 'tainacan-journal-manager'); ?>
                </h2>
                <p>
                    <?php esc_html_e('Idempotent: running this again only fills in missing pieces. Default password for every test user is shown below.', 'tainacan-journal-manager'); ?>
                </p>
                <p>
                    <code><?php echo esc_html(TestDataSeeder::default_password()); ?></code>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SEED); ?>">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                    <button type="submit" class="button button-primary button-hero">
                        <span class="dashicons dashicons-database-add" style="margin-top:4px;"></span>
                        &nbsp;<?php esc_html_e('Populate test data', 'tainacan-journal-manager'); ?>
                    </button>
                </form>
            </div>

            <div class="tjm-tn-card tjm-test-data-card tjm-test-data-card--danger" style="margin-top:18px;">
                <h2 class="tjm-tn-section-title">
                    <?php esc_html_e('2. Remove all seeded data', 'tainacan-journal-manager'); ?>
                </h2>
                <p>
                    <?php esc_html_e('Only the objects tagged by the seeder are removed (users, posts, pages and the Tainacan collection). Real content is left untouched.', 'tainacan-journal-manager'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('To confirm, type APAGAR (capital letters) in the field below.', 'tainacan-journal-manager'); ?></strong>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_PURGE); ?>">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                    <p>
                        <input type="text"
                               name="tjm_purge_confirm"
                               value=""
                               autocomplete="off"
                               placeholder="<?php esc_attr_e('type APAGAR', 'tainacan-journal-manager'); ?>"
                               class="regular-text"
                               required>
                    </p>
                    <button type="submit" class="button button-secondary button-hero" style="color:#a00;border-color:#a00;">
                        <span class="dashicons dashicons-trash" style="margin-top:4px;"></span>
                        &nbsp;<?php esc_html_e('Delete seeded data', 'tainacan-journal-manager'); ?>
                    </button>
                </form>
            </div>

            <hr style="margin:30px 0;">
            <details>
                <summary style="cursor:pointer;font-weight:600;">
                    <?php esc_html_e('What this seeder creates', 'tainacan-journal-manager'); ?>
                </summary>
                <ul style="margin-top:10px;line-height:1.6;">
                    <li><?php esc_html_e('10 users (1 per role): tjm_jmanager, tjm_editorchefe, tjm_editorsecao, tjm_autor, tjm_avaliador1, tjm_avaliador2, tjm_copyeditor, tjm_layout, tjm_leitor, tjm_admininst.', 'tainacan-journal-manager'); ?></li>
                    <li><?php esc_html_e('1 journal "Revista Teste de Ciencias Aplicadas" (review type: double blind, ISSN, license CC-BY).', 'tainacan-journal-manager'); ?></li>
                    <li><?php esc_html_e('1 Tainacan collection with 17 Dublin Core / OJS-compatible metadata (provisioned via CollectionProvisioner).', 'tainacan-journal-manager'); ?></li>
                    <li><?php esc_html_e('1 issue (Vol. 1, n. 1, current year).', 'tainacan-journal-manager'); ?></li>
                    <li><?php esc_html_e('7 frontend pages with the required slugs and shortcodes (login, author-portal, reviewer-dashboard, editorial-dashboard, copyediting-dashboard, journal-roles, journal-indicators) plus 1 public journal landing page.', 'tainacan-journal-manager'); ?></li>
                </ul>
            </details>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $inventory
     */
    private function render_inventory_panel(array $inventory): void
    {
        $journal_id    = isset($inventory['journal_id']) ? (int) $inventory['journal_id'] : 0;
        $issue_id      = isset($inventory['issue_id']) ? (int) $inventory['issue_id'] : 0;
        $collection_id = isset($inventory['collection_id']) ? (int) $inventory['collection_id'] : 0;
        $users         = isset($inventory['users']) && is_array($inventory['users']) ? $inventory['users'] : [];
        $pages         = isset($inventory['pages']) && is_array($inventory['pages']) ? $inventory['pages'] : [];
        $ran_at        = isset($inventory['ran_at']) ? (string) $inventory['ran_at'] : '';
        ?>
        <div class="tjm-tn-card" style="background:#f6fafa;border-left:4px solid #298596;">
            <h2 class="tjm-tn-section-title" style="margin-top:0;">
                <?php esc_html_e('Current seed inventory', 'tainacan-journal-manager'); ?>
            </h2>
            <p>
                <?php
                printf(
                    /* translators: %s: timestamp */
                    esc_html__('Last run: %s', 'tainacan-journal-manager'),
                    '<code>' . esc_html($ran_at) . '</code>'
                );
                ?>
            </p>
            <table class="widefat striped tjm-tn-table" style="max-width:760px;">
                <tbody>
                    <tr>
                        <th style="width:200px;"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th>
                        <td>
                            <?php if ($journal_id > 0) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tjm_journals_page&action=view&id=' . $journal_id)); ?>">
                                    <?php echo esc_html(get_the_title($journal_id) ?: ('#' . $journal_id)); ?>
                                </a> <small>(#<?php echo (int) $journal_id; ?>)</small>
                            <?php else : ?>
                                <em><?php esc_html_e('not seeded', 'tainacan-journal-manager'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Issue', 'tainacan-journal-manager'); ?></th>
                        <td>
                            <?php if ($issue_id > 0) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tjm_issues_page&action=view&id=' . $issue_id)); ?>">
                                    <?php echo esc_html(get_the_title($issue_id) ?: ('#' . $issue_id)); ?>
                                </a> <small>(#<?php echo (int) $issue_id; ?>)</small>
                            <?php else : ?>
                                <em><?php esc_html_e('not seeded', 'tainacan-journal-manager'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Tainacan collection', 'tainacan-journal-manager'); ?></th>
                        <td>
                            <?php if ($collection_id > 0) : ?>
                                <code>collection_id=<?php echo (int) $collection_id; ?></code>
                            <?php else : ?>
                                <em><?php esc_html_e('not provisioned', 'tainacan-journal-manager'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Users', 'tainacan-journal-manager'); ?></th>
                        <td>
                            <?php if (! empty($users)) : ?>
                                <ul style="margin:0;">
                                    <?php foreach ($users as $login => $uid) : ?>
                                        <li>
                                            <code><?php echo esc_html((string) $login); ?></code>
                                            &nbsp;
                                            <a href="<?php echo esc_url(get_edit_user_link((int) $uid)); ?>"><?php esc_html_e('edit', 'tainacan-journal-manager'); ?></a>
                                            <small>(#<?php echo (int) $uid; ?>)</small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <em><?php esc_html_e('none', 'tainacan-journal-manager'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Frontend pages', 'tainacan-journal-manager'); ?></th>
                        <td>
                            <?php if (! empty($pages)) : ?>
                                <ul style="margin:0;">
                                    <?php foreach ($pages as $slug => $pid) : ?>
                                        <li>
                                            <code>/<?php echo esc_html((string) $slug); ?>/</code>
                                            &nbsp;
                                            <?php
                                            $permalink = get_permalink((int) $pid);
                                            if ($permalink) :
                                            ?>
                                                <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener"><?php esc_html_e('open', 'tainacan-journal-manager'); ?></a>
                                            <?php endif; ?>
                                            <small>(#<?php echo (int) $pid; ?>)</small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <em><?php esc_html_e('none', 'tainacan-journal-manager'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_messages(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        if (! isset($_GET['msg'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $msg = sanitize_key((string) $_GET['msg']);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $users = isset($_GET['s_users']) ? (int) $_GET['s_users'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $journal = isset($_GET['s_journal']) ? (int) $_GET['s_journal'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $issue = isset($_GET['s_issue']) ? (int) $_GET['s_issue'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $pages = isset($_GET['s_pages']) ? (int) $_GET['s_pages'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $collection = isset($_GET['s_collection']) ? (int) $_GET['s_collection'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice; no state mutation.
        $posts = isset($_GET['s_posts']) ? (int) $_GET['s_posts'] : 0;

        $css = 'notice-success';
        $text = '';

        switch ($msg) {
            case 'seeded':
                $text = sprintf(
                    /* translators: 1: users, 2: journal id, 3: issue id, 4: pages, 5: collection id */
                    __('Seed complete: %1$d users, journal #%2$d, issue #%3$d, %4$d pages, collection #%5$d.', 'tainacan-journal-manager'),
                    $users,
                    $journal,
                    $issue,
                    $pages,
                    $collection
                );
                break;
            case 'purged':
                $text = sprintf(
                    /* translators: 1: users, 2: posts, 3: pages, 4: collection */
                    __('Cleanup complete: removed %1$d users, %2$d posts, %3$d pages and %4$d collection.', 'tainacan-journal-manager'),
                    $users,
                    $posts,
                    $pages,
                    $collection
                );
                break;
            case 'purge_cancelled':
                $css = 'notice-warning';
                $text = __('Cleanup cancelled — confirmation phrase did not match.', 'tainacan-journal-manager');
                break;
        }

        if ($text === '') {
            return;
        }
        echo '<div class="notice ' . esc_attr($css) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }
}
