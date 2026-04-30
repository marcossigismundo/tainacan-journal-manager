<?php

declare(strict_types=1);

namespace TainacanJournalManager\Admin\Tainacan;

/**
 * Abstract Tainacan-integrated page that, when clicked, redirects to a
 * native CPT admin screen (`edit.php?post_type=...`).
 *
 * Why: Tainacan's navigation sidebar (rendered server-side in
 * class-tainacan-pages.php::render_navigation_menu) builds menu links
 * with `add_query_arg('page', $slug)`, which assumes the menu_slug is
 * a valid Tainacan-page slug. Passing a CPT URL (like
 * `edit.php?post_type=tjm_review`) as the menu_slug to add_submenu_page
 * breaks both Tainacan's URL builder AND the breadcrumb logic.
 *
 * The fix is to register a real Tainacan-style page slug (e.g.
 * `tjm_journals_link`) so Tainacan happily renders it in the sidebar,
 * and use the `load-{$page_suffix}` action to wp_redirect early to the
 * actual CPT admin URL — before any HTML is sent.
 *
 * Concrete subclasses provide the CPT, label, icon and menu position.
 */
abstract class CptLinkPage extends \Tainacan\Pages
{
    /** Tainacan SVG icon slug from the bundled set in tainacan/assets/icons/ */
    abstract protected function get_icon(): string;

    /** Target post type to redirect to. */
    abstract protected function get_cpt(): string;

    /** Human label rendered both in the sidebar entry and the (unused) page title. */
    abstract protected function get_label(): string;

    /** Position in the Tainacan root menu (lower = higher up). */
    abstract protected function get_position(): int;

    public function add_admin_menu(): void
    {
        $label = $this->get_label();
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

        // Hook BEFORE rendering — fires right when the admin page loads.
        // Sending a redirect here avoids any HTML output and keeps the
        // navigation snappy (no Tainacan shell flash).
        add_action('load-' . $page_suffix, [&$this, 'redirect_to_cpt']);
    }

    public function redirect_to_cpt(): void
    {
        wp_safe_redirect(admin_url('edit.php?post_type=' . $this->get_cpt()));
        exit;
    }

    public function render_page_content(): void
    {
        // Unreachable in practice — load-{page} fires before render and exits.
        // Defined because the parent class declares it abstract.
    }
}
