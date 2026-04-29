<?php

declare(strict_types=1);

namespace TainacanJournalManager;

/**
 * Centralized constants for meta keys, post types, taxonomies and options.
 * All hardcoded values live here to avoid scattered magic strings.
 */
final class Config
{
    // ── Meta prefix ─────────────────────────────────────────────────
    public const META_PREFIX = '_tjm_';

    // ── Custom Post Types ───────────────────────────────────────────
    public const CPT_JOURNAL    = 'tjm_journal';
    public const CPT_SUBMISSION = 'tjm_submission';
    public const CPT_REVIEW     = 'tjm_review';
    public const CPT_ISSUE      = 'tjm_issue';

    // ── Taxonomies ──────────────────────────────────────────────────
    public const TAX_SECTION  = 'tjm_section';   // Sections (article, review, dossier...)
    public const TAX_KEYWORD  = 'tjm_keyword';
    public const TAX_LANGUAGE = 'tjm_language';

    // ── Submission status ───────────────────────────────────────────
    public const STATUS_DRAFT          = 'draft';
    public const STATUS_SUBMITTED      = 'submitted';
    public const STATUS_TRIAGE         = 'triage';
    public const STATUS_REVIEW         = 'review';
    public const STATUS_REVISION       = 'revision';
    public const STATUS_DECISION       = 'decision';
    public const STATUS_COPYEDITING    = 'copyediting';
    public const STATUS_PRODUCTION     = 'production';
    public const STATUS_PUBLISHED      = 'published';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_WITHDRAWN      = 'withdrawn';

    public const SUBMISSION_STATUSES = [
        self::STATUS_DRAFT       => 'Draft',
        self::STATUS_SUBMITTED   => 'Submitted',
        self::STATUS_TRIAGE      => 'In Triage',
        self::STATUS_REVIEW      => 'In Review',
        self::STATUS_REVISION    => 'Revision Required',
        self::STATUS_DECISION    => 'Awaiting Decision',
        self::STATUS_COPYEDITING => 'Copyediting',
        self::STATUS_PRODUCTION  => 'Production',
        self::STATUS_PUBLISHED   => 'Published',
        self::STATUS_REJECTED    => 'Rejected',
        self::STATUS_WITHDRAWN   => 'Withdrawn',
    ];

    // ── Review status ───────────────────────────────────────────────
    public const REVIEW_INVITED   = 'invited';
    public const REVIEW_ACCEPTED  = 'accepted';
    public const REVIEW_DECLINED  = 'declined';
    public const REVIEW_SUBMITTED = 'submitted';
    public const REVIEW_OVERDUE   = 'overdue';

    // ── Review recommendation ───────────────────────────────────────
    public const RECOMMEND_ACCEPT             = 'accept';
    public const RECOMMEND_REVISIONS_MINOR    = 'revisions_minor';
    public const RECOMMEND_REVISIONS_MAJOR    = 'revisions_major';
    public const RECOMMEND_RESUBMIT_REVIEW    = 'resubmit_review';
    public const RECOMMEND_REJECT             = 'reject';

    // ── Editorial decision ──────────────────────────────────────────
    public const DECISION_ACCEPT     = 'accept';
    public const DECISION_REVISIONS  = 'request_revisions';
    public const DECISION_RESUBMIT   = 'resubmit';
    public const DECISION_REJECT     = 'reject';

    // ── Review type (per journal) ───────────────────────────────────
    public const REVIEW_TYPE_OPEN       = 'open';
    public const REVIEW_TYPE_BLIND      = 'blind';
    public const REVIEW_TYPE_DOUBLE     = 'double_blind';
    public const REVIEW_TYPE_EDITORIAL  = 'editorial';

    // ── Journal options ─────────────────────────────────────────────
    public const OPTION_VERSION             = 'tjm_version';
    public const OPTION_EMAILS_ENABLED      = 'tjm_emails_enabled';
    public const OPTION_EMAIL_FROM_NAME     = 'tjm_email_from_name';
    public const OPTION_EMAIL_FROM_ADDRESS  = 'tjm_email_from_address';
    public const OPTION_REVIEW_DEADLINE_DAYS = 'tjm_review_deadline_days';
    public const OPTION_TOKEN_VALIDITY_DAYS = 'tjm_token_validity_days';

    // ── Default values ──────────────────────────────────────────────
    public const DEFAULT_REVIEW_DEADLINE = 30;
    public const DEFAULT_TOKEN_VALIDITY  = 60;
    public const EMAIL_FROM_NAME    = 'Tainacan Journal Manager';

    // ── Page slugs (frontend) ───────────────────────────────────────
    public const PAGE_LOGIN       = 'journal-login';
    public const PAGE_AUTHOR      = 'author-portal';
    public const PAGE_REVIEWER    = 'reviewer-dashboard';
    public const PAGE_EDITORIAL   = 'editorial-dashboard';
    public const PAGE_INDICATORS  = 'journal-indicators';

    // ── Helpers ─────────────────────────────────────────────────────

    public static function get_status_label(string $status): string
    {
        $labels = self::SUBMISSION_STATUSES;
        return isset($labels[$status])
            ? __($labels[$status], 'tainacan-journal-manager')
            : $status;
    }

    public static function page_url(string $slug): string
    {
        $page = get_page_by_path($slug);
        return $page ? (get_permalink($page) ?: '') : site_url('/' . $slug . '/');
    }

    public static function emails_enabled(): bool
    {
        return (bool) get_option(self::OPTION_EMAILS_ENABLED, true);
    }

    public static function email_from_address(): string
    {
        $email = get_option(self::OPTION_EMAIL_FROM_ADDRESS, '');
        if (! $email) {
            $email = get_option('admin_email', '');
        }
        return is_string($email) ? $email : '';
    }
}
