<?php

declare(strict_types=1);

namespace TainacanJournalManager\Notifications;

use TainacanJournalManager\Config;

/**
 * Email sending with templated HTML.
 *
 * Templates live in templates/emails/{template-key}.php and receive
 * the data array as $data.
 */
final class Mailer
{
    /**
     * @param array<string, mixed> $data
     */
    public function send(string $to, string $template_key, array $data = []): bool
    {
        if (! Config::emails_enabled()) {
            return true;
        }

        if (! is_email($to)) {
            return false;
        }

        $body = $this->render_template($template_key, $data);
        if (! $body) {
            return false;
        }

        $subject = $this->subject_for($template_key, $data);

        $from_email = Config::email_from_address();
        $from_name  = (string) get_option(Config::OPTION_EMAIL_FROM_NAME, Config::EMAIL_FROM_NAME);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];
        if ($from_email) {
            $headers[] = sprintf('From: %s <%s>', $from_name, $from_email);
            $headers[] = sprintf('Reply-To: %s', $from_email);
        }

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render_template(string $key, array $data): string
    {
        $file = TJM_PATH . 'templates/emails/' . sanitize_file_name($key) . '.php';
        if (! file_exists($file)) {
            error_log('[TJM] Email template not found: ' . $file);
            return '';
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $file;
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function subject_for(string $key, array $data): string
    {
        $prefix = '[' . get_bloginfo('name') . ']';
        return match ($key) {
            'submission-received'     => sprintf('%s %s', $prefix, __('Submission received', 'tainacan-journal-manager')),
            'submission-in-triage'    => sprintf('%s %s', $prefix, __('Your submission is in triage', 'tainacan-journal-manager')),
            'submission-in-review'    => sprintf('%s %s', $prefix, __('Your submission is in peer review', 'tainacan-journal-manager')),
            'review-invitation'       => sprintf('%s %s', $prefix, __('Invitation to review a submission', 'tainacan-journal-manager')),
            'review-reminder'         => sprintf('%s %s', $prefix, __('Review reminder', 'tainacan-journal-manager')),
            'review-overdue'          => sprintf('%s %s', $prefix, __('Review overdue', 'tainacan-journal-manager')),
            'review-thanks'           => sprintf('%s %s', $prefix, __('Thank you for your review', 'tainacan-journal-manager')),
            'decision-accept'         => sprintf('%s %s', $prefix, __('Your submission has been accepted', 'tainacan-journal-manager')),
            'decision-reject'         => sprintf('%s %s', $prefix, __('Decision on your submission', 'tainacan-journal-manager')),
            'decision-revisions'      => sprintf('%s %s', $prefix, __('Revisions requested', 'tainacan-journal-manager')),
            'submission-published'    => sprintf('%s %s', $prefix, __('Your article has been published', 'tainacan-journal-manager')),
            'editor-new-submission'   => sprintf('%s %s', $prefix, __('New submission received', 'tainacan-journal-manager')),
            'editor-review-received'  => sprintf('%s %s', $prefix, __('Review received', 'tainacan-journal-manager')),
            default                   => sprintf('%s %s', $prefix, __('Notification', 'tainacan-journal-manager')),
        };
    }
}
