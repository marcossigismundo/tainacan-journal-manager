<?php

declare(strict_types=1);

namespace TainacanJournalManager\Submission;

use TainacanJournalManager\Config;
use TainacanJournalManager\Editorial\WorkflowManager;
use TainacanJournalManager\Integrations\OrcidService;
use TainacanJournalManager\Notifications\Mailer;
use TainacanJournalManager\Roles\PluginRole;

/**
 * High-level operations for submissions.
 *
 * Phase 2: full multi-step wizard data persistence + transitions to
 * Submitted with email notifications to author and editors.
 */
final class SubmissionService
{
    /**
     * Create a new draft submission.
     *
     * @param array{title: string, journal_id: int, abstract?: string} $data
     * @return int New submission ID, or 0 on failure.
     */
    public static function create_draft(int $author_id, array $data): int
    {
        if ($author_id <= 0 || empty($data['title']) || empty($data['journal_id'])) {
            return 0;
        }

        $post_id = wp_insert_post([
            'post_type'    => Config::CPT_SUBMISSION,
            'post_status'  => 'publish',
            'post_author'  => $author_id,
            'post_title'   => sanitize_text_field((string) $data['title']),
            'post_content' => isset($data['abstract']) ? sanitize_textarea_field((string) $data['abstract']) : '',
        ], true);

        if (is_wp_error($post_id)) {
            return 0;
        }

        update_post_meta($post_id, Config::META_PREFIX . 'journal_id', (int) $data['journal_id']);
        update_post_meta($post_id, Config::META_PREFIX . 'status', Config::STATUS_DRAFT);
        update_post_meta($post_id, Config::META_PREFIX . 'submitted_at', '');
        update_post_meta($post_id, Config::META_PREFIX . 'coauthors', []);

        return (int) $post_id;
    }

    /**
     * Update step-1 metadata (title, abstract, journal, section, language, keywords).
     *
     * @param array<string, mixed> $data
     */
    public static function update_metadata(int $submission_id, array $data): bool
    {
        if ($submission_id <= 0) {
            return false;
        }

        $update = ['ID' => $submission_id];
        if (isset($data['title'])) {
            $update['post_title'] = sanitize_text_field((string) $data['title']);
        }
        if (isset($data['abstract'])) {
            $update['post_content'] = sanitize_textarea_field((string) $data['abstract']);
        }
        if (count($update) > 1) {
            wp_update_post($update);
        }

        if (isset($data['journal_id'])) {
            update_post_meta($submission_id, Config::META_PREFIX . 'journal_id', (int) $data['journal_id']);
        }
        if (isset($data['language'])) {
            update_post_meta($submission_id, Config::META_PREFIX . Config::META_LANGUAGE, sanitize_text_field((string) $data['language']));
        }
        if (isset($data['references'])) {
            update_post_meta($submission_id, Config::META_PREFIX . Config::META_REFERENCES, sanitize_textarea_field((string) $data['references']));
        }
        if (isset($data['funding'])) {
            update_post_meta($submission_id, Config::META_PREFIX . Config::META_FUNDING, sanitize_text_field((string) $data['funding']));
        }
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $clean = array_values(array_filter(array_map('sanitize_text_field', $data['keywords']), fn($k) => $k !== ''));
            update_post_meta($submission_id, Config::META_PREFIX . Config::META_KEYWORDS, $clean);
            // Mirror to taxonomy as well
            wp_set_object_terms($submission_id, $clean, Config::TAX_KEYWORD, false);
        }
        if (isset($data['section_term_id']) && (int) $data['section_term_id'] > 0) {
            wp_set_object_terms($submission_id, [(int) $data['section_term_id']], Config::TAX_SECTION, false);
            update_post_meta($submission_id, Config::META_PREFIX . Config::META_SECTION_TERM, (int) $data['section_term_id']);
        }

        return true;
    }

    /**
     * Persist coauthors. Each entry: ['name' => , 'email' => , 'affiliation' => , 'orcid' => ].
     *
     * @param array<int, array<string, string>> $coauthors
     */
    public static function set_coauthors(int $submission_id, array $coauthors): bool
    {
        if ($submission_id <= 0) {
            return false;
        }

        $clean = [];
        foreach ($coauthors as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $email = sanitize_email((string) ($entry['email'] ?? ''));
            $aff   = sanitize_text_field((string) ($entry['affiliation'] ?? ''));
            $orcid = OrcidService::format((string) ($entry['orcid'] ?? ''));

            $clean[] = [
                'name'        => sanitize_text_field($name),
                'email'       => $email,
                'affiliation' => $aff,
                'orcid'       => $orcid,
            ];
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'coauthors', $clean);
        return true;
    }

    /**
     * Persist declarations (originality, COI, copyright, ethics). Stored as bool meta.
     *
     * @param array<string, bool> $declarations
     */
    public static function set_declarations(int $submission_id, array $declarations): bool
    {
        if ($submission_id <= 0) {
            return false;
        }
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_ORIGINAL, ! empty($declarations['original']));
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_COI, ! empty($declarations['coi']));
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_COPYRIGHT, ! empty($declarations['copyright']));
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_DECLARATION_ETHICS, ! empty($declarations['ethics']));
        return true;
    }

    /**
     * Submit (transition draft → submitted).
     */
    public static function submit(int $submission_id, int $user_id): bool
    {
        if (! self::is_complete($submission_id)) {
            return false;
        }

        if (! WorkflowManager::transition($submission_id, Config::STATUS_SUBMITTED, $user_id)) {
            return false;
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'submitted_at', current_time('mysql'));

        self::notify_submission_received($submission_id);

        do_action('tjm_submission_submitted', $submission_id);
        return true;
    }

    /**
     * Verify all required pieces are in place before allowing submit.
     */
    public static function is_complete(int $submission_id): bool
    {
        $post = get_post($submission_id);
        if (! $post || $post->post_type !== Config::CPT_SUBMISSION) {
            return false;
        }
        if (trim((string) $post->post_title) === '' || trim((string) $post->post_content) === '') {
            return false;
        }

        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        if ($journal_id <= 0) {
            return false;
        }

        $att_id = (int) get_post_meta($submission_id, Config::META_PREFIX . Config::META_MANUSCRIPT_FILE_ID, true);
        if ($att_id <= 0) {
            return false;
        }

        // All four declarations required
        foreach ([
            Config::META_DECLARATION_ORIGINAL,
            Config::META_DECLARATION_COI,
            Config::META_DECLARATION_COPYRIGHT,
        ] as $decl) {
            if (! get_post_meta($submission_id, Config::META_PREFIX . $decl, true)) {
                return false;
            }
        }

        return true;
    }

    private static function notify_submission_received(int $submission_id): void
    {
        $post = get_post($submission_id);
        if (! $post) {
            return;
        }

        $author = get_userdata((int) $post->post_author);
        $journal_id = (int) get_post_meta($submission_id, Config::META_PREFIX . 'journal_id', true);
        $journal_name = $journal_id ? (string) get_the_title($journal_id) : '';

        $mailer = new Mailer();

        // To author
        if ($author && is_email($author->user_email)) {
            $mailer->send($author->user_email, 'submission-received', [
                'author_name'   => $author->display_name ?: $author->user_login,
                'title'         => (string) $post->post_title,
                'journal_name'  => $journal_name,
                'submission_id' => $submission_id,
            ]);
        }

        // To editors of the journal
        if ($journal_id > 0) {
            $editor_users = self::collect_journal_editors($journal_id);
            foreach ($editor_users as $editor) {
                if (! is_email($editor->user_email)) {
                    continue;
                }
                $mailer->send($editor->user_email, 'editor-new-submission', [
                    'editor_name' => $editor->display_name ?: $editor->user_login,
                    'title'       => (string) $post->post_title,
                    'author_name' => $author ? ($author->display_name ?: $author->user_login) : '',
                    'journal_name' => $journal_name,
                    'submission_id' => $submission_id,
                ]);
            }
        }
    }

    /**
     * @return \WP_User[]
     */
    private static function collect_journal_editors(int $journal_id): array
    {
        $editors = [];
        $users = get_users([
            'meta_key' => '_tjm_journal_roles',
            'fields'   => ['ID', 'user_email', 'display_name', 'user_login'],
        ]);
        foreach ($users as $u) {
            if (PluginRole::is_editor((int) $u->ID, $journal_id)) {
                $editors[] = $u;
            }
        }
        return $editors;
    }
}
