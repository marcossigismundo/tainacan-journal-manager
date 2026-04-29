<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Shortcode [tjm_login] — custom login form for editorial portal.
 *
 * Redirects logged-in users to the appropriate dashboard based on
 * their primary role.
 */
final class LoginPage
{
    public function register(): void
    {
        add_shortcode('tjm_login', [$this, 'render']);
        add_action('wp_ajax_nopriv_tjm_login', [$this, 'handle_login']);
        add_action('wp_ajax_tjm_logout', [$this, 'handle_logout']);
    }

    public function render(): string
    {
        wp_enqueue_style('tjm-frontend');
        wp_enqueue_script('tjm-frontend');

        if (is_user_logged_in()) {
            $url = $this->dashboard_url_for_user(get_current_user_id());
            return '<div class="tjm-info">' .
                sprintf(
                    /* translators: %s: dashboard URL */
                    esc_html__('You are already logged in. %s', 'tainacan-journal-manager'),
                    '<a href="' . esc_url($url) . '">' . esc_html__('Go to dashboard', 'tainacan-journal-manager') . '</a>'
                ) .
                '</div>';
        }

        $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash((string) $_GET['redirect_to'])) : '';

        ob_start();
        include TJM_PATH . 'templates/frontend/login.php';
        return ob_get_clean() ?: '';
    }

    public function handle_login(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');

        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash((string) $_POST['username'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_POST['redirect_to'])) : '';

        if (! $username || ! $password) {
            wp_send_json_error(__('Please fill in both fields.', 'tainacan-journal-manager'));
        }

        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(__('Invalid username or password.', 'tainacan-journal-manager'));
        }

        $url = $redirect ?: $this->dashboard_url_for_user($user->ID);
        wp_send_json_success(['redirect' => $url]);
    }

    public function handle_logout(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        wp_logout();
        wp_send_json_success(['redirect' => Config::page_url(Config::PAGE_LOGIN)]);
    }

    private function dashboard_url_for_user(int $user_id): string
    {
        $roles = PluginRole::get_roles($user_id);

        if (in_array(PluginRole::JOURNAL_MANAGER, $roles, true)
            || in_array(PluginRole::EDITOR_CHIEF, $roles, true)
            || in_array(PluginRole::EDITOR_SECTION, $roles, true)
            || in_array(PluginRole::ADMIN_INSTITUTIONAL, $roles, true)) {
            return Config::page_url(Config::PAGE_EDITORIAL);
        }
        if (in_array(PluginRole::REVIEWER, $roles, true)) {
            return Config::page_url(Config::PAGE_REVIEWER);
        }
        if (in_array(PluginRole::AUTHOR, $roles, true)) {
            return Config::page_url(Config::PAGE_AUTHOR);
        }
        return home_url('/');
    }
}
