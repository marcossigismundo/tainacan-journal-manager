<?php

declare(strict_types=1);

namespace TainacanJournalManager\Production;

use TainacanJournalManager\Config;

/**
 * Manages galleys: production-ready files in PDF, HTML, XML or other
 * delivery formats. Each galley is an attachment plus a label.
 *
 * Storage:
 *   _tjm_galleys = [
 *     ['attachment_id' => 99, 'format' => 'pdf', 'label' => 'PDF (Portuguese)',
 *      'language' => 'pt-br', 'uploaded_at' => '...', 'uploaded_by' => 7],
 *     ...
 *   ]
 */
final class GalleyService
{
    public const ALLOWED_FORMATS = ['pdf', 'html', 'xml', 'epub', 'jats'];

    public const FORMAT_MIME = [
        'pdf'  => ['application/pdf'],
        'html' => ['text/html'],
        'xml'  => ['application/xml', 'text/xml'],
        'epub' => ['application/epub+zip'],
        'jats' => ['application/xml', 'text/xml'],
    ];

    /**
     * @param array<string, mixed> $file
     * @return array{attachment_id: int, error?: string}
     */
    public static function add_galley(
        int $submission_id,
        int $user_id,
        string $format,
        array $file,
        string $label = '',
        string $language = ''
    ): array {
        if (! in_array($format, self::ALLOWED_FORMATS, true)) {
            return ['attachment_id' => 0, 'error' => __('Unsupported galley format.', 'tainacan-journal-manager')];
        }
        if (! isset($file['tmp_name']) || ! is_uploaded_file((string) $file['tmp_name'])) {
            return ['attachment_id' => 0, 'error' => __('No file received.', 'tainacan-journal-manager')];
        }

        $allowed_mimes = self::FORMAT_MIME[$format] ?? [];
        // wp_handle_upload's mimes argument is keyed by extension. Build accordingly.
        $mimes_arg = [$format => $allowed_mimes[0] ?? 'application/octet-stream'];
        if ($format === 'jats') {
            $mimes_arg = ['xml' => 'application/xml'];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $movefile = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => $mimes_arg,
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
            return ['attachment_id' => 0, 'error' => __('Could not attach galley.', 'tainacan-journal-manager')];
        }
        wp_update_attachment_metadata((int) $att_id, wp_generate_attachment_metadata((int) $att_id, (string) $movefile['file']));

        $galleys = self::get_galleys($submission_id);
        $galleys[] = [
            'attachment_id' => (int) $att_id,
            'format'        => $format,
            'label'         => sanitize_text_field($label) ?: strtoupper($format),
            'language'      => sanitize_text_field($language),
            'uploaded_at'   => current_time('mysql'),
            'uploaded_by'   => $user_id,
        ];
        update_post_meta($submission_id, Config::META_PREFIX . 'galleys', $galleys);

        do_action('tjm_galley_added', $submission_id, (int) $att_id, $format);
        return ['attachment_id' => (int) $att_id];
    }

    public static function remove_galley(int $submission_id, int $attachment_id): bool
    {
        $galleys = self::get_galleys($submission_id);
        $next = array_values(array_filter($galleys, fn($g) => (int) ($g['attachment_id'] ?? 0) !== $attachment_id));
        if (count($next) === count($galleys)) {
            return false;
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'galleys', $next);
        wp_delete_attachment($attachment_id, true);
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_galleys(int $submission_id): array
    {
        $g = get_post_meta($submission_id, Config::META_PREFIX . 'galleys', true);
        return is_array($g) ? $g : [];
    }

    /**
     * @return array<int, array<string, mixed>> Same data plus 'url' and 'mime'.
     */
    public static function get_galleys_with_urls(int $submission_id): array
    {
        $out = [];
        foreach (self::get_galleys($submission_id) as $g) {
            $att_id = (int) ($g['attachment_id'] ?? 0);
            if ($att_id <= 0) continue;
            $url = wp_get_attachment_url($att_id);
            if (! $url) continue;
            $g['url']  = (string) $url;
            $g['mime'] = (string) get_post_mime_type($att_id);
            $out[] = $g;
        }
        return $out;
    }
}
