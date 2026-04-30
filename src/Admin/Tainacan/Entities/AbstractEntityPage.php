<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan\Entities;

/**
 * Base for Tainacan-integrated entity management pages.
 *
 * Each concrete subclass renders one of TJM's entities (Journal,
 * Submission, Review, Issue) inside the Tainacan admin shell — fully
 * replacing the WordPress native CPT screens (post list / Gutenberg
 * editor) so the editorial workflow lives end-to-end inside Tainacan.
 *
 * Routing happens via `?action=` query string on the same page slug:
 *
 *   ?page=tjm_journals_page                      → list
 *   ?page=tjm_journals_page&action=view&id=123   → read-only detail
 *   ?page=tjm_journals_page&action=edit&id=123   → edit form
 *   ?page=tjm_journals_page&action=new           → create form
 *
 * Saves are POSTed to admin-post.php via the standard
 * `tjm_entity_save_{slug}` action with nonce protection.
 */
abstract class AbstractEntityPage extends \Tainacan\Pages
{
    /** Tainacan SVG icon slug (e.g. 'repository', 'processes'). */
    abstract protected function get_icon(): string;

    /** Plural display name (e.g. "Journals"). */
    abstract protected function get_label_plural(): string;

    /** Singular display name (e.g. "Journal"). */
    abstract protected function get_label_singular(): string;

    /** Position inside Tainacan root menu. */
    abstract protected function get_position(): int;

    /** Whether this entity supports create/edit forms (false = read-only). */
    abstract protected function supports_editing(): bool;

    /** Render the list of entities (table). */
    abstract protected function render_list(): void;

    /** Render the read-only detail view for one entity. */
    abstract protected function render_view(int $id): void;

    /** Render the create/edit form. Default: notice that editing is unsupported. */
    protected function render_form(?int $id): void
    {
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('This entity is managed via the editorial portals — editing here is not supported.', 'tainacan-journal-manager')
            . '</p></div>';
    }

    /** Process the POSTed form. Override for full-CRUD entities. */
    public function handle_save(): void
    {
        wp_die(esc_html__('This entity does not support direct editing.', 'tainacan-journal-manager'), '', ['response' => 405]);
    }

    /** Process the POSTed delete. Override if delete is supported. */
    public function handle_delete(): void
    {
        wp_die(esc_html__('This entity does not support direct deletion.', 'tainacan-journal-manager'), '', ['response' => 405]);
    }

    public function init(): void
    {
        parent::init();
        if ($this->supports_editing()) {
            add_action('admin_post_tjm_entity_save_'   . $this->get_page_slug(), [$this, 'handle_save']);
            add_action('admin_post_tjm_entity_delete_' . $this->get_page_slug(), [$this, 'handle_delete']);
        }
    }

    public function add_admin_menu(): void
    {
        $label = $this->get_label_plural();
        $page_suffix = add_submenu_page(
            $this->tainacan_root_menu_slug,
            $label,
            '<span class="icon">' . $this->get_svg_icon($this->get_icon()) . '</span>'
                . '<span class="menu-text">' . esc_html($label) . '</span>',
            'edit_posts',
            $this->get_page_slug(),
            [&$this, 'render_page'],
            $this->get_position()
        );
        add_action('load-' . $page_suffix, [&$this, 'load_page']);
    }

    public function admin_enqueue_css(): void
    {
        wp_enqueue_style('tjm-tainacan-admin', TJM_URL . 'assets/css/admin-tainacan.css', [], TJM_VERSION);
    }

    public function render_page_content(): void
    {
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'list';
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        echo '<div class="wrap tainacan-page-container-content tjm-tainacan-page tjm-entity-page">';

        match ($action) {
            'view' => $this->section_view($id),
            'edit' => $this->section_edit($id),
            'new'  => $this->section_new(),
            default => $this->section_list(),
        };

        echo '</div>';
    }

    private function section_list(): void
    {
        ?>
        <div class="tainacan-fixed-subheader">
            <h1 class="tainacan-page-title"><?php echo esc_html($this->get_label_plural()); ?></h1>
            <?php if ($this->supports_editing()) : ?>
                <a class="page-title-action tjm-entity-new-btn" href="<?php echo esc_url($this->url_for('new')); ?>">
                    + <?php
                        printf(
                            /* translators: %s: singular entity name (e.g. "Journal") */
                            esc_html__('New %s', 'tainacan-journal-manager'),
                            esc_html($this->get_label_singular())
                        );
                    ?>
                </a>
            <?php endif; ?>
        </div>

        <?php $this->render_messages(); ?>
        <?php $this->render_list(); ?>
        <?php
    }

    private function section_view(int $id): void
    {
        if ($id <= 0) {
            $this->section_list();
            return;
        }
        ?>
        <div class="tainacan-fixed-subheader">
            <h1 class="tainacan-page-title">
                <?php echo esc_html($this->get_label_singular()); ?>
                <small style="font-weight: 400; color: var(--tjm-tn-text-muted);">#<?php echo (int) $id; ?></small>
            </h1>
            <a class="page-title-action" href="<?php echo esc_url($this->url_for('list')); ?>">&larr; <?php esc_html_e('Back to list', 'tainacan-journal-manager'); ?></a>
            <?php if ($this->supports_editing()) : ?>
                <a class="page-title-action" href="<?php echo esc_url($this->url_for('edit', $id)); ?>"><?php esc_html_e('Edit', 'tainacan-journal-manager'); ?></a>
            <?php endif; ?>
        </div>

        <?php $this->render_messages(); ?>
        <?php $this->render_view($id); ?>
        <?php
    }

    private function section_edit(int $id): void
    {
        ?>
        <div class="tainacan-fixed-subheader">
            <h1 class="tainacan-page-title"><?php
                printf(
                    /* translators: %s: singular entity name */
                    esc_html__('Edit %s', 'tainacan-journal-manager'),
                    esc_html($this->get_label_singular())
                );
            ?></h1>
            <a class="page-title-action" href="<?php echo esc_url($this->url_for('list')); ?>">&larr; <?php esc_html_e('Back to list', 'tainacan-journal-manager'); ?></a>
        </div>

        <?php $this->render_messages(); ?>
        <?php $this->render_form($id > 0 ? $id : null); ?>
        <?php
    }

    private function section_new(): void
    {
        ?>
        <div class="tainacan-fixed-subheader">
            <h1 class="tainacan-page-title"><?php
                printf(
                    /* translators: %s: singular entity name */
                    esc_html__('New %s', 'tainacan-journal-manager'),
                    esc_html($this->get_label_singular())
                );
            ?></h1>
            <a class="page-title-action" href="<?php echo esc_url($this->url_for('list')); ?>">&larr; <?php esc_html_e('Back to list', 'tainacan-journal-manager'); ?></a>
        </div>

        <?php $this->render_messages(); ?>
        <?php $this->render_form(null); ?>
        <?php
    }

    /* ── helpers used by subclasses ───────────────────────────── */

    /**
     * Build a URL pointing back at this page with a given action / id.
     *
     * @param array<string, scalar> $extra
     */
    final protected function url_for(string $action, int $id = 0, array $extra = []): string
    {
        $args = ['page' => $this->get_page_slug()];
        if ($action !== 'list') {
            $args['action'] = $action;
        }
        if ($id > 0) {
            $args['id'] = $id;
        }
        return admin_url('admin.php?' . http_build_query(array_merge($args, $extra)));
    }

    /** URL for the form-save admin-post action. */
    final protected function save_action_url(): string
    {
        return admin_url('admin-post.php');
    }

    final protected function save_action_name(): string
    {
        return 'tjm_entity_save_' . $this->get_page_slug();
    }

    final protected function delete_action_name(): string
    {
        return 'tjm_entity_delete_' . $this->get_page_slug();
    }

    /** Standardized nonce name for create/edit forms. */
    final protected function nonce_name(): string
    {
        return 'tjm_entity_nonce_' . $this->get_page_slug();
    }

    final protected function nonce_action(): string
    {
        return 'tjm_entity_action_' . $this->get_page_slug();
    }

    /** Read a notice from the URL (?msg=created|updated|deleted|error) and render it. */
    private function render_messages(): void
    {
        if (! isset($_GET['msg'])) {
            return;
        }
        $msg = sanitize_key((string) $_GET['msg']);
        $text = match ($msg) {
            'created' => __('Created.', 'tainacan-journal-manager'),
            'updated' => __('Updated.', 'tainacan-journal-manager'),
            'deleted' => __('Deleted.', 'tainacan-journal-manager'),
            'error'   => __('Could not save. Please review the form and try again.', 'tainacan-journal-manager'),
            default   => '',
        };
        if ($text === '') return;
        $css = $msg === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($css) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    /** Redirect helper — used by subclass save handlers. */
    final protected function redirect_after_save(string $msg, int $id = 0): void
    {
        $url = $this->url_for($id > 0 ? 'view' : 'list', $id, ['msg' => $msg]);
        wp_safe_redirect($url);
        exit;
    }
}
