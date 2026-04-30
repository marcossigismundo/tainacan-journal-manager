<?php

declare(strict_types=1);

namespace TainacanJournalManager\Notifications;

/**
 * In-database overrides for email subject + body.
 *
 * If the admin saves a custom subject/body for a template_key, the Mailer
 * uses it instead of the default file in templates/emails/. The custom
 * body supports placeholder tokens like {{author_name}}, {{title}},
 * {{note}} which are replaced from the data array passed to Mailer::send.
 *
 * Storage: a single option `tjm_email_overrides` keyed by template_key:
 *   ['submission-received' => ['subject' => '...', 'body' => '...'], ...]
 *
 * The `tjm_mailer_subject` and `tjm_mailer_body` filters in Mailer let
 * this class hook in without further coupling.
 */
final class TemplateOverrides
{
    public const OPTION = 'tjm_email_overrides';

    /** @var string[] Keys recognised across the plugin (centralized for the UI). */
    public const KNOWN_KEYS = [
        'submission-received',
        'submission-in-triage',
        'submission-in-review',
        'review-invitation',
        'review-reminder',
        'review-overdue',
        'review-thanks',
        'decision-accept',
        'decision-reject',
        'decision-revisions',
        'submission-published',
        'editor-new-submission',
        'editor-review-received',
        'copyediting-version',
        'proof-request',
    ];

    public function register(): void
    {
        add_filter('tjm_mailer_subject', [$this, 'maybe_override_subject'], 10, 3);
        add_filter('tjm_mailer_body',    [$this, 'maybe_override_body'], 10, 3);
    }

    /**
     * @return array<string, array{subject:string, body:string}>
     */
    public static function all_overrides(): array
    {
        $raw = get_option(self::OPTION, []);
        if (! is_array($raw)) return [];
        $out = [];
        foreach ($raw as $key => $entry) {
            if (! is_array($entry)) continue;
            $out[(string) $key] = [
                'subject' => (string) ($entry['subject'] ?? ''),
                'body'    => (string) ($entry['body'] ?? ''),
            ];
        }
        return $out;
    }

    public static function get(string $key): ?array
    {
        $all = self::all_overrides();
        return $all[$key] ?? null;
    }

    public static function save(string $key, string $subject, string $body): void
    {
        $all = self::all_overrides();
        $subject = trim($subject);
        $body    = trim($body);
        if ($subject === '' && $body === '') {
            unset($all[$key]);
        } else {
            $all[$key] = ['subject' => $subject, 'body' => $body];
        }
        update_option(self::OPTION, $all, false);
    }

    public static function delete(string $key): void
    {
        $all = self::all_overrides();
        unset($all[$key]);
        update_option(self::OPTION, $all, false);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function maybe_override_subject(string $subject, string $key, array $data): string
    {
        $o = self::get($key);
        if ($o && $o['subject'] !== '') {
            return self::interpolate($o['subject'], $data);
        }
        return $subject;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function maybe_override_body(string $body, string $key, array $data): string
    {
        $o = self::get($key);
        if ($o && $o['body'] !== '') {
            // Wrap user body in the base layout so it gets the same header/footer
            $content = self::interpolate($o['body'], $data);
            ob_start();
            $content_var = $content;
            $content = $content_var;
            include TJM_PATH . 'templates/emails/base-layout.php';
            return (string) ob_get_clean();
        }
        return $body;
    }

    /**
     * Replace {{token}} placeholders with the matching value from $data.
     * Unknown tokens are left in place — useful for debugging missing data.
     *
     * @param array<string, mixed> $data
     */
    private static function interpolate(string $template, array $data): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/',
            static function ($m) use ($data) {
                $key = $m[1];
                if (! array_key_exists($key, $data)) {
                    return $m[0];
                }
                $val = $data[$key];
                if (is_scalar($val)) {
                    return esc_html((string) $val);
                }
                return $m[0];
            },
            $template
        );
    }
}
