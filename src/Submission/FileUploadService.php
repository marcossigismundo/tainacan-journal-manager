<?php

declare(strict_types=1);

namespace TainacanJournalManager\Submission;

use TainacanJournalManager\Config;

/**
 * Handles manuscript and supplementary file uploads.
 *
 * Files are stored as WordPress attachments (so they get the standard
 * uploads pipeline) but their post_status is 'private'. The submission
 * keeps a versioned history in `_tjm_manuscript_history` so authors can
 * upload revised versions without losing previous ones.
 */
final class FileUploadService
{
    /**
     * Handle manuscript upload from a $_FILES entry.
     *
     * @param array<string, mixed> $file $_FILES['manuscript'] entry.
     * @return array{attachment_id: int, error?: string}
     */
    public static function upload_manuscript(int $submission_id, int $author_id, array $file): array
    {
        $check = self::validate_file($file);
        if ($check !== '') {
            return ['attachment_id' => 0, 'error' => $check];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = [
            'test_form' => false,
            'mimes'     => Config::ALLOWED_MIME_TYPES,
        ];

        $movefile = wp_handle_upload($file, $overrides);
        if (! is_array($movefile) || isset($movefile['error'])) {
            $msg = is_array($movefile) && isset($movefile['error']) ? (string) $movefile['error'] : __('Upload failed.', 'tainacan-journal-manager');
            return ['attachment_id' => 0, 'error' => $msg];
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => (string) $movefile['type'],
            'post_title'     => sanitize_file_name(basename((string) $movefile['file'])),
            'post_status'    => 'private',
            'post_author'    => $author_id,
            'post_parent'    => $submission_id,
        ], (string) $movefile['file'], $submission_id, true);

        if (is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
            return ['attachment_id' => 0, 'error' => __('Could not attach file.', 'tainacan-journal-manager')];
        }

        wp_update_attachment_metadata(
            (int) $attachment_id,
            wp_generate_attachment_metadata((int) $attachment_id, (string) $movefile['file'])
        );

        // Append to history (versioning)
        $history = get_post_meta($submission_id, Config::META_PREFIX . Config::META_MANUSCRIPT_HISTORY, true);
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = [
            'attachment_id' => (int) $attachment_id,
            'uploaded_at'   => current_time('mysql'),
            'uploaded_by'   => $author_id,
            'filename'      => sanitize_file_name(basename((string) $movefile['file'])),
            'mime'          => (string) $movefile['type'],
        ];
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_MANUSCRIPT_HISTORY, $history);
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_MANUSCRIPT_FILE_ID, (int) $attachment_id);

        return ['attachment_id' => (int) $attachment_id];
    }

    /**
     * Add a supplementary file to a submission.
     *
     * @param array<string, mixed> $file
     * @return array{attachment_id: int, error?: string}
     */
    public static function upload_supplementary(int $submission_id, int $author_id, array $file): array
    {
        $check = self::validate_file($file);
        if ($check !== '') {
            return ['attachment_id' => 0, 'error' => $check];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $movefile = wp_handle_upload($file, ['test_form' => false]);
        if (! is_array($movefile) || isset($movefile['error'])) {
            $msg = is_array($movefile) && isset($movefile['error']) ? (string) $movefile['error'] : __('Upload failed.', 'tainacan-journal-manager');
            return ['attachment_id' => 0, 'error' => $msg];
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => (string) $movefile['type'],
            'post_title'     => sanitize_file_name(basename((string) $movefile['file'])),
            'post_status'    => 'private',
            'post_author'    => $author_id,
            'post_parent'    => $submission_id,
        ], (string) $movefile['file'], $submission_id, true);

        if (is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
            return ['attachment_id' => 0, 'error' => __('Could not attach file.', 'tainacan-journal-manager')];
        }

        $list = get_post_meta($submission_id, Config::META_PREFIX . Config::META_SUPPLEMENTARY_FILES, true);
        if (! is_array($list)) {
            $list = [];
        }
        $list[] = (int) $attachment_id;
        update_post_meta($submission_id, Config::META_PREFIX . Config::META_SUPPLEMENTARY_FILES, $list);

        return ['attachment_id' => (int) $attachment_id];
    }

    /**
     * @param array<string, mixed> $file
     * @return string Empty string if valid, otherwise translated error message.
     */
    private static function validate_file(array $file): string
    {
        if (! isset($file['tmp_name']) || ! is_uploaded_file((string) $file['tmp_name'])) {
            return __('No file received.', 'tainacan-journal-manager');
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return __('Upload error.', 'tainacan-journal-manager');
        }
        if ((int) ($file['size'] ?? 0) > Config::MAX_UPLOAD_SIZE) {
            return sprintf(
                /* translators: %d: max size in MB */
                __('File too large. Maximum %d MB.', 'tainacan-journal-manager'),
                (int) (Config::MAX_UPLOAD_SIZE / 1024 / 1024)
            );
        }

        $check = wp_check_filetype_and_ext(
            (string) $file['tmp_name'],
            (string) ($file['name'] ?? ''),
            Config::ALLOWED_MIME_TYPES
        );

        if (empty($check['type']) || ! in_array($check['type'], Config::ALLOWED_MIME_TYPES, true)) {
            return __('File type not allowed. Use PDF, DOC, DOCX, ODT, RTF or TEX.', 'tainacan-journal-manager');
        }

        return '';
    }

    /**
     * @return array{url: string, filename: string}|null
     */
    public static function get_manuscript_info(int $submission_id): ?array
    {
        $att_id = (int) get_post_meta($submission_id, Config::META_PREFIX . Config::META_MANUSCRIPT_FILE_ID, true);
        if ($att_id <= 0) {
            return null;
        }
        $url = wp_get_attachment_url($att_id);
        if (! $url) {
            return null;
        }
        $filename = get_the_title($att_id) ?: basename((string) get_attached_file($att_id));
        return ['url' => (string) $url, 'filename' => (string) $filename];
    }
}
