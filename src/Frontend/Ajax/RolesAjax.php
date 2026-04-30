<?php

declare(strict_types=1);

namespace TainacanJournalManager\Frontend\Ajax;

use TainacanJournalManager\Roles\PluginRole;

/**
 * AJAX handlers for the journal manager / institutional admin role UI.
 *
 *   - tjm_roles_set_global    (set/replace global roles)
 *   - tjm_roles_set_journal   (set per-journal roles for a user)
 *
 * Permission: only journal_manager (for own journals) or institutional admin.
 */
final class RolesAjax
{
    public function register(): void
    {
        add_action('wp_ajax_tjm_roles_set_global',  [$this, 'set_global']);
        add_action('wp_ajax_tjm_roles_set_journal', [$this, 'set_journal']);
    }

    public function set_global(): void
    {
        $this->ensure_admin();

        $target_user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $roles_raw = isset($_POST['roles']) && is_array($_POST['roles']) ? wp_unslash($_POST['roles']) : [];

        if ($target_user_id <= 0 || ! get_userdata($target_user_id)) {
            wp_send_json_error(__('Invalid user.', 'tainacan-journal-manager'));
        }

        $roles = array_values(array_filter(array_map('sanitize_text_field', $roles_raw)));
        PluginRole::set_roles($target_user_id, $roles);
        wp_send_json_success();
    }

    public function set_journal(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }

        $target_user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $journal_id     = isset($_POST['journal_id']) ? (int) $_POST['journal_id'] : 0;
        $roles_raw      = isset($_POST['roles']) && is_array($_POST['roles']) ? wp_unslash($_POST['roles']) : [];

        if ($target_user_id <= 0 || $journal_id <= 0 || ! get_userdata($target_user_id)) {
            wp_send_json_error(__('Invalid user or journal.', 'tainacan-journal-manager'));
        }

        $actor = get_current_user_id();
        $is_admin       = PluginRole::is_admin_institutional($actor);
        $is_journal_mgr = PluginRole::has_role($actor, PluginRole::JOURNAL_MANAGER)
                       || PluginRole::has_journal_role($actor, $journal_id, PluginRole::JOURNAL_MANAGER);
        if (! $is_admin && ! $is_journal_mgr) {
            wp_send_json_error(__('You cannot manage roles for this journal.', 'tainacan-journal-manager'), 403);
        }

        $roles = array_values(array_filter(array_map('sanitize_text_field', $roles_raw)));
        $map = PluginRole::get_journal_roles_map($target_user_id);
        if (empty($roles)) {
            unset($map[$journal_id]);
        } else {
            $map[$journal_id] = $roles;
        }
        PluginRole::set_journal_roles_map($target_user_id, $map);
        wp_send_json_success();
    }

    private function ensure_admin(): void
    {
        check_ajax_referer('tjm_frontend_nonce', 'nonce');
        if (! is_user_logged_in()) {
            wp_send_json_error(__('Not logged in.', 'tainacan-journal-manager'), 401);
        }
        if (! PluginRole::is_admin_institutional(get_current_user_id())) {
            wp_send_json_error(__('Only institutional administrators can edit global roles.', 'tainacan-journal-manager'), 403);
        }
    }
}
