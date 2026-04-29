<?php

/**
 * Uninstall Tainacan Journal Manager.
 * Runs only when the plugin is deleted via the WP admin.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove options
$options = [
    'tjm_version',
    'tjm_activated_at',
    'tjm_emails_enabled',
    'tjm_email_from_name',
    'tjm_email_from_address',
    'tjm_review_deadline_days',
    'tjm_token_validity_days',
];

foreach ($options as $opt) {
    delete_option($opt);
}

// Remove tokens
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tjm_token_%'");

// Remove transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tjm_%' OR option_name LIKE '_transient_timeout_tjm_%'");

// Remove user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('_tjm_roles', '_tjm_journal_roles')");

// Remove admin caps
$admin = get_role('administrator');
if ($admin) {
    foreach (['edit_tjm_journals', 'edit_tjm_submissions', 'edit_tjm_reviews', 'edit_tjm_issues', 'manage_tjm_settings'] as $cap) {
        $admin->remove_cap($cap);
    }
}

// NOTE: CPT posts (journals, submissions, reviews, issues) and Tainacan
// collections are NOT deleted automatically. Administrators must delete
// them manually if desired.
