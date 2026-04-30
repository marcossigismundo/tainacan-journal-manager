<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

use TainacanJournalManager\Audit\AuditLog;

/**
 * Tainacan-integrated audit log viewer.
 */
class AuditLogPage extends \Tainacan\Pages
{
    use \Tainacan\Traits\Singleton_Instance;

    protected function get_page_slug(): string
    {
        return 'tjm_audit_log';
    }

    public function add_admin_menu(): void
    {
        $page_suffix = add_submenu_page(
            $this->tainacan_other_links_slug,
            __('Journal Manager — Audit log', 'tainacan-journal-manager'),
            '<span class="icon">' . $this->get_svg_icon('list') . '</span>'
                . '<span class="menu-text">' . __('TJM Audit log', 'tainacan-journal-manager') . '</span>',
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
        $args = [
            'event'       => isset($_GET['event']) ? sanitize_text_field((string) $_GET['event']) : '',
            'object_type' => isset($_GET['object_type']) ? sanitize_text_field((string) $_GET['object_type']) : '',
            'object_id'   => isset($_GET['object_id']) ? (int) $_GET['object_id'] : 0,
            'user_id'     => isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0,
            'since'       => isset($_GET['since']) ? sanitize_text_field((string) $_GET['since']) : '',
            'search'      => isset($_GET['search']) ? sanitize_text_field((string) $_GET['search']) : '',
            'per_page'    => 50,
            'page'        => max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1),
        ];

        $result    = AuditLog::query($args);
        $rows      = $result['rows'];
        $total     = $result['total'];
        $total_pgs = max(1, (int) ceil($total / $args['per_page']));
        $cur       = (int) $args['page'];
        $base_url  = admin_url('admin.php?page=' . $this->get_page_slug());
        ?>
        <div class="wrap tainacan-page-container-content tjm-tainacan-page">
            <div class="tainacan-fixed-subheader">
                <h1 class="tainacan-page-title"><?php esc_html_e('Journal Manager — Audit log', 'tainacan-journal-manager'); ?></h1>
                <p class="tjm-page-subtitle">
                    <?php
                    printf(
                        /* translators: %d: number of rows */
                        esc_html(_n('%d entry', '%d entries', $total, 'tainacan-journal-manager')),
                        $total
                    );
                    ?>
                </p>
            </div>

            <form method="get" class="tjm-tn-filters">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->get_page_slug()); ?>">
                <p>
                    <label><?php esc_html_e('Event', 'tainacan-journal-manager'); ?>
                        <input type="text" name="event" value="<?php echo esc_attr((string) $args['event']); ?>" placeholder="submission.transition">
                    </label>
                    <label><?php esc_html_e('Object type', 'tainacan-journal-manager'); ?>
                        <input type="text" name="object_type" value="<?php echo esc_attr((string) $args['object_type']); ?>" placeholder="submission">
                    </label>
                    <label><?php esc_html_e('Object ID', 'tainacan-journal-manager'); ?>
                        <input type="number" name="object_id" value="<?php echo $args['object_id'] ? (int) $args['object_id'] : ''; ?>">
                    </label>
                    <label><?php esc_html_e('User ID', 'tainacan-journal-manager'); ?>
                        <input type="number" name="user_id" value="<?php echo $args['user_id'] ? (int) $args['user_id'] : ''; ?>">
                    </label>
                    <label><?php esc_html_e('Since', 'tainacan-journal-manager'); ?>
                        <input type="date" name="since" value="<?php echo esc_attr((string) $args['since']); ?>">
                    </label>
                    <label><?php esc_html_e('Search', 'tainacan-journal-manager'); ?>
                        <input type="text" name="search" value="<?php echo esc_attr((string) $args['search']); ?>">
                    </label>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'tainacan-journal-manager'); ?></button>
                    <a class="button" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Reset', 'tainacan-journal-manager'); ?></a>
                </p>
            </form>

            <table class="widefat striped tjm-tn-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('When (UTC)', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('User', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('IP', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Event', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Object', 'tainacan-journal-manager'); ?></th>
                        <th><?php esc_html_e('Data', 'tainacan-journal-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="6"><em><?php esc_html_e('No entries match.', 'tainacan-journal-manager'); ?></em></td></tr>
                    <?php else : foreach ($rows as $r) : ?>
                        <tr>
                            <td><code><?php echo esc_html((string) $r->created_at); ?></code></td>
                            <td>
                                <?php if ((int) $r->user_id > 0) : ?>
                                    <strong><?php echo esc_html((string) $r->user_login); ?></strong>
                                    <small>#<?php echo (int) $r->user_id; ?></small>
                                <?php else : ?>
                                    <em><?php esc_html_e('system', 'tainacan-journal-manager'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html((string) $r->ip); ?></code></td>
                            <td><code><?php echo esc_html((string) $r->event); ?></code></td>
                            <td>
                                <?php echo esc_html((string) $r->object_type); ?>
                                <?php if ((int) $r->object_id > 0) : ?> #<?php echo (int) $r->object_id; ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if (! empty($r->data)) : ?>
                                    <details>
                                        <summary><?php esc_html_e('view', 'tainacan-journal-manager'); ?></summary>
                                        <pre><?php echo esc_html((string) $r->data); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($total_pgs > 1) : ?>
            <p class="tjm-tn-pagination">
                <?php
                $base = add_query_arg($args, $base_url);
                if ($cur > 1) {
                    echo '<a class="button" href="' . esc_url(add_query_arg('paged', $cur - 1, $base)) . '">&larr; ' . esc_html__('Previous', 'tainacan-journal-manager') . '</a> ';
                }
                printf(
                    /* translators: 1: current page 2: total pages */
                    esc_html__('Page %1$d of %2$d', 'tainacan-journal-manager'),
                    $cur, $total_pgs
                );
                if ($cur < $total_pgs) {
                    echo ' <a class="button" href="' . esc_url(add_query_arg('paged', $cur + 1, $base)) . '">' . esc_html__('Next', 'tainacan-journal-manager') . ' &rarr;</a>';
                }
                ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
