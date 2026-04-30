<?php

declare(strict_types=1);

namespace TainacanJournalManager\Production;

use TainacanJournalManager\Config;
use TainacanJournalManager\Editorial\WorkflowManager;
use TainacanJournalManager\Notifications\Mailer;

/**
 * Manages copyediting versions of an accepted manuscript.
 *
 * Each upload becomes a versioned attachment listed in
 * `_tjm_copyediting_versions` (newest last). Notes per round are stored
 * alongside in `_tjm_copyediting_notes`. Once the copyeditor marks the
 * round as ready, control transitions to production.
 *
 * Storage:
 *   _tjm_copyediting_versions = [
 *     ['attachment_id' => 12, 'uploaded_by' => 5, 'uploaded_at' => '...',
 *      'filename' => '...', 'note' => '...', 'role' => 'copyeditor|author'],
 *     ...
 *   ]
 *   _tjm_copyediting_status   = 'in_progress' | 'ready_for_production'
 */
final class CopyeditingService
{
    public const STATUS_IN_PROGRESS         = 'in_progress';
    public const STATUS_READY_FOR_PRODUCTION = 'ready_for_production';

    /**
     * Upload a new copyediting version.
     *
     * @param array<string, mixed> $file $_FILES entry.
     * @return array{attachment_id: int, error?: string}
     */
    public static function upload_version(int $submission_id, int $user_id, string $role, array $file, string $note = ''): array
    {
        if (! isset($file['tmp_name']) || ! is_uploaded_file((string) $file['tmp_name'])) {
            return ['attachment_id' => 0, 'error' => __('No file received.', 'tainacan-journal-manager')];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $movefile = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => Config::ALLOWED_MIME_TYPES,
        ]);
        if (! is_array($movefile) || isset($movefile['error'])) {
            $msg = is_array($movefile) && isset($movefile['error']) ? (string) $movefile['error'] : __('Upload failed.', 'tainacan-journal-manager');
            return ['attachment_id' => 0, 'error' => $msg];
        }

        $att_id = wp_insert_attachment([
            'post_mime_type' => (string) $movefile['type'],
            'post_title'     => sanitize_file_name(basename((string) $movefile['file'])),
            'post_status'    => 'private',
            'post_author'    => $user_id,
            'post_parent'    => $submission_id,
        ], (string) $movefile['file'], $submission_id, true);

        if (is_wp_error($att_id) || (int) $att_id <= 0) {
            return ['attachment_id' => 0, 'error' => __('Could not attach file.', 'tainacan-journal-manager')];
        }
        wp_update_attachment_metadata((int) $att_id, wp_generate_attachment_metadata((int) $att_id, (string) $movefile['file']));

        $versions = get_post_meta($submission_id, Config::META_PREFIX . 'copyediting_versions', true);
        if (! is_array($versions)) {
            $versions = [];
        }
        $versions[] = [
            'attachment_id' => (int) $att_id,
            'uploaded_by'   => $user_id,
            'uploaded_at'   => current_time('mysql'),
            'filename'      => sanitize_file_name(basename((string) $movefile['file'])),
            'mime'          => (string) $movefile['type'],
            'note'          => sanitize_textarea_field($note),
            'role'          => in_array($role, ['copyeditor', 'author', 'editor'], true) ? $role : 'copyeditor',
        ];
        update_post_meta($submission_id, Config::META_PREFIX . 'copyediting_versions', $versions);

        do_action('tjm_copyediting_version_uploaded', $submission_id, (int) $att_id, $user_id, $role);

        return ['attachment_id' => (int) $att_id];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_versions(int $submission_id): array
    {
        $v = get_post_meta($submission_id, Config::META_PREFIX . 'copyediting_versions', true);
        return is_array($v) ? $v : [];
    }

    public static function get_status(int $submission_id): string
    {
        $s = (string) get_post_meta($submission_id, Config::META_PREFIX . 'copyediting_status', true);
        return $s ?: self::STATUS_IN_PROGRESS;
    }

    /**
     * Mark this submission's copyediting round as ready for production.
     * Triggers the workflow transition copyediting → production.
     */
    public static function mark_ready_for_production(int $submission_id, int $user_id, string $note = ''): bool
    {
        if (WorkflowManager::get_status($submission_id) !== Config::STATUS_COPYEDITING) {
            return false;
        }
        if (empty(self::get_versions($submission_id))) {
            return false;
        }

        update_post_meta($submission_id, Config::META_PREFIX . 'copyediting_status', self::STATUS_READY_FOR_PRODUCTION);

        $ok = WorkflowManager::transition($submission_id, Config::STATUS_PRODUCTION, $user_id, $note);
        if (! $ok) {
            return false;
        }

        do_action('tjm_copyediting_ready', $submission_id);
        return true;
    }

    /**
     * Send the latest copyediting version to the author for review.
     * Sends an email but does NOT transition status (author keeps editing
     * via the same version-upload flow).
     */
    public static function notify_author_of_version(int $submission_id): void
    {
        $post = get_post($submission_id);
        if (! $post) {
            return;
        }
        $author = get_userdata((int) $post->post_author);
        if (! $author || ! is_email($author->user_email)) {
            return;
        }

        $versions = self::get_versions($submission_id);
        $latest   = end($versions);

        (new Mailer())->send($author->user_email, 'copyediting-version', [
            'author_name' => $author->display_name ?: $author->user_login,
            'title'       => (string) $post->post_title,
            'note'        => is_array($latest) ? (string) ($latest['note'] ?? '') : '',
            'submission_id' => $submission_id,
        ]);
    }
}
