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
    // Phase 5 — integrations
    'tjm_orcid_client_id',
    'tjm_orcid_client_secret',
    'tjm_orcid_use_sandbox',
    'tjm_crossref_username',
    'tjm_crossref_password',
    'tjm_crossref_use_test',
    'tjm_crossref_depositor_name',
    'tjm_crossref_depositor_email',
    'tjm_crossref_registrant',
    'tjm_doaj_api_key',
    // Phase 6
    'tjm_email_overrides',
    'tjm_audit_schema_version',
];

foreach ($options as $opt) {
    delete_option($opt);
}

// Phase 6 — drop audit log table
$audit_table = $wpdb->prefix . 'tjm_audit_log';
$wpdb->query("DROP TABLE IF EXISTS {$audit_table}");

// Remove tokens
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tjm_token_%'");

// Remove transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tjm_%' OR option_name LIKE '_transient_timeout_tjm_%'");

// Remove user meta (incl. ORCID-linked user data from Phase 5)
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('_tjm_roles', '_tjm_journal_roles', '_tjm_orcid', '_tjm_orcid_token', '_tjm_affiliation')");

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
