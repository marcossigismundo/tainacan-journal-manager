<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;

/**
 * ORCID OAuth 2.0 sign-in flow (3-legged Authorization Code).
 *
 * Configuration is stored in WordPress options:
 *   tjm_orcid_client_id
 *   tjm_orcid_client_secret
 *   tjm_orcid_use_sandbox  (bool)
 *
 * Endpoints registered:
 *   /?tjm_orcid=connect   → redirect to ORCID auth
 *   /?tjm_orcid=callback  → exchange code for token, attach to current user
 *
 * On success the user's ORCID iD is stored in user meta `_tjm_orcid` and
 * the OAuth access token in `_tjm_orcid_token` (encrypted only by WP option
 * storage; treat as sensitive — do NOT echo).
 *
 * NOTE: this is a complete OAuth scaffold but won't work until the admin
 * registers the application at https://orcid.org/developer-tools and saves
 * the credentials in the plugin settings page (added in Phase 5).
 */
final class OrcidOAuthService
{
    public const OPT_CLIENT_ID     = 'tjm_orcid_client_id';
    public const OPT_CLIENT_SECRET = 'tjm_orcid_client_secret';
    public const OPT_USE_SANDBOX   = 'tjm_orcid_use_sandbox';

    public const USER_META_ORCID = '_tjm_orcid';
    public const USER_META_TOKEN = '_tjm_orcid_token';

    public function register(): void
    {
        add_action('init', [$this, 'handle_endpoints']);
    }

    public function handle_endpoints(): void
    {
        if (! isset($_GET['tjm_orcid'])) {
            return;
        }
        $action = (string) $_GET['tjm_orcid'];

        if ($action === 'connect') {
            $this->begin_authorization();
            return;
        }
        if ($action === 'callback') {
            $this->handle_callback();
            return;
        }
    }

    public static function is_configured(): bool
    {
        return (bool) get_option(self::OPT_CLIENT_ID, '')
            && (bool) get_option(self::OPT_CLIENT_SECRET, '');
    }

    public static function authorize_url(): string
    {
        return home_url('/?tjm_orcid=connect');
    }

    private function base_url(): string
    {
        return get_option(self::OPT_USE_SANDBOX, false)
            ? 'https://sandbox.orcid.org'
            : 'https://orcid.org';
    }

    private function api_base_url(): string
    {
        return get_option(self::OPT_USE_SANDBOX, false)
            ? 'https://api.sandbox.orcid.org'
            : 'https://api.orcid.org';
    }

    private function begin_authorization(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(Config::page_url(Config::PAGE_LOGIN) . '?redirect_to=' . urlencode(home_url('/?tjm_orcid=connect')));
            exit;
        }
        if (! self::is_configured()) {
            wp_die(esc_html__('ORCID is not configured. Ask the administrator to set client_id / client_secret.', 'tainacan-journal-manager'), '', ['response' => 503]);
        }

        $state = wp_generate_password(32, false, false);
        set_transient('tjm_orcid_state_' . $state, get_current_user_id(), 10 * MINUTE_IN_SECONDS);

        $url = $this->base_url() . '/oauth/authorize?' . http_build_query([
            'client_id'     => (string) get_option(self::OPT_CLIENT_ID, ''),
            'response_type' => 'code',
            'scope'         => '/authenticate',
            'redirect_uri'  => home_url('/?tjm_orcid=callback'),
            'state'         => $state,
        ]);

        wp_redirect($url);
        exit;
    }

    private function handle_callback(): void
    {
        $code  = isset($_GET['code']) ? sanitize_text_field((string) $_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field((string) $_GET['state']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field((string) $_GET['error']) : '';

        if ($error !== '' || $code === '' || $state === '') {
            wp_die(esc_html(sprintf(__('ORCID authorization failed: %s', 'tainacan-journal-manager'), $error ?: 'missing parameters')), '', ['response' => 400]);
        }

        $user_id = (int) get_transient('tjm_orcid_state_' . $state);
        delete_transient('tjm_orcid_state_' . $state);
        if ($user_id <= 0) {
            wp_die(esc_html__('Invalid or expired state token.', 'tainacan-journal-manager'), '', ['response' => 400]);
        }

        $resp = wp_remote_post($this->api_base_url() . '/oauth/token', [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
            'body'    => [
                'client_id'     => (string) get_option(self::OPT_CLIENT_ID, ''),
                'client_secret' => (string) get_option(self::OPT_CLIENT_SECRET, ''),
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => home_url('/?tjm_orcid=callback'),
            ],
        ]);

        if (is_wp_error($resp)) {
            wp_die(esc_html__('ORCID token exchange failed: ', 'tainacan-journal-manager') . esc_html($resp->get_error_message()), '', ['response' => 502]);
        }

        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (! is_array($data) || empty($data['orcid'])) {
            wp_die(esc_html__('ORCID returned an unexpected response.', 'tainacan-journal-manager'), '', ['response' => 502]);
        }

        $orcid = (string) $data['orcid'];
        if (! OrcidService::is_valid($orcid)) {
            wp_die(esc_html__('Returned ORCID iD failed checksum validation.', 'tainacan-journal-manager'), '', ['response' => 502]);
        }

        update_user_meta($user_id, self::USER_META_ORCID, OrcidService::format($orcid));
        update_user_meta($user_id, self::USER_META_TOKEN, [
            'access_token'  => (string) ($data['access_token'] ?? ''),
            'refresh_token' => (string) ($data['refresh_token'] ?? ''),
            'expires_in'    => (int) ($data['expires_in'] ?? 0),
            'received_at'   => time(),
        ]);

        wp_safe_redirect(add_query_arg('tjm_msg', 'orcid_connected', Config::page_url(Config::PAGE_AUTHOR)));
        exit;
    }
}
