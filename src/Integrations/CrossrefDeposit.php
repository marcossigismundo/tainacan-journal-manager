<?php

declare(strict_types=1);

namespace TainacanJournalManager\Integrations;

use TainacanJournalManager\Config;

/**
 * Submits a Crossref deposit (XML body) to the Crossref deposit API.
 *
 * Configuration in WP options:
 *   tjm_crossref_username
 *   tjm_crossref_password
 *   tjm_crossref_use_test  (bool — sandbox vs production)
 *
 * The submit endpoint receives multipart/form-data with the XML as the
 * `mdFile` field plus operation=doMDUpload + login_id/login_passwd.
 *
 * Returns an array with the HTTP status and Crossref's response body.
 * Editors should poll the returned `submission_id` (or check email) to
 * confirm acceptance — Crossref processes deposits asynchronously.
 */
final class CrossrefDeposit
{
    public const OPT_USERNAME = 'tjm_crossref_username';
    public const OPT_PASSWORD = 'tjm_crossref_password';
    public const OPT_USE_TEST = 'tjm_crossref_use_test';

    public static function is_configured(): bool
    {
        return (bool) get_option(self::OPT_USERNAME, '')
            && (bool) get_option(self::OPT_PASSWORD, '');
    }

    /**
     * @return array{ok:bool, status:int, body:string, error?:string}
     */
    public static function submit(int $submission_id): array
    {
        if (! self::is_configured()) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Crossref credentials not configured.'];
        }

        $xml = CrossrefExporter::export_article($submission_id);
        if ($xml === '') {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not build deposit XML.'];
        }

        $endpoint = get_option(self::OPT_USE_TEST, false)
            ? 'https://test.crossref.org/servlet/deposit'
            : 'https://doi.crossref.org/servlet/deposit';

        // multipart/form-data with the XML as mdFile
        $boundary = wp_generate_password(24, false, false);
        $body  = "--{$boundary}\r\nContent-Disposition: form-data; name=\"operation\"\r\n\r\ndoMDUpload\r\n";
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"login_id\"\r\n\r\n" . (string) get_option(self::OPT_USERNAME, '') . "\r\n";
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"login_passwd\"\r\n\r\n" . (string) get_option(self::OPT_PASSWORD, '') . "\r\n";
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"mdFile\"; filename=\"tjm-{$submission_id}.xml\"\r\nContent-Type: application/xml\r\n\r\n{$xml}\r\n";
        $body .= "--{$boundary}--\r\n";

        $resp = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $resp->get_error_message()];
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $body_r = (string) wp_remote_retrieve_body($resp);

        // Record the deposit attempt on the submission for audit
        $log = get_post_meta($submission_id, Config::META_PREFIX . 'crossref_log', true);
        if (! is_array($log)) {
            $log = [];
        }
        $log[] = [
            'date'   => current_time('mysql'),
            'status' => $status,
            'body'   => mb_substr($body_r, 0, 4000),
        ];
        update_post_meta($submission_id, Config::META_PREFIX . 'crossref_log', $log);

        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => $body_r,
        ];
    }
}
