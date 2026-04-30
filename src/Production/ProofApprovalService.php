<?php

declare(strict_types=1);

namespace TainacanJournalManager\Production;

use TainacanJournalManager\Config;
use TainacanJournalManager\Notifications\Mailer;

/**
 * Author proof approval. Once production attaches galleys, the author
 * gets a chance to approve or request changes before publication.
 *
 * Storage:
 *   _tjm_proof_status   = 'pending' | 'approved' | 'changes_requested'
 *   _tjm_proof_history  = [['user_id'=>, 'action'=>, 'note'=>, 'date'=>], ...]
 *   _tjm_proof_request_at  = mysql timestamp when sent to author
 */
final class ProofApprovalService
{
    public const STATUS_PENDING           = 'pending';
    public const STATUS_APPROVED          = 'approved';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    /**
     * Production sends the proof to the author.
     */
    public static function request_proof(int $submission_id, int $user_id): bool
    {
        if (empty(GalleyService::get_galleys($submission_id))) {
            return false;
        }

        update_post_meta($submission_id, Config::META_PREFIX . 'proof_status', self::STATUS_PENDING);
        update_post_meta($submission_id, Config::META_PREFIX . 'proof_request_at', current_time('mysql'));

        self::log($submission_id, $user_id, 'request', '');
        self::notify_author($submission_id);

        return true;
    }

    public static function approve(int $submission_id, int $user_id, string $note = ''): bool
    {
        if (! self::is_author_or_admin($submission_id, $user_id)) {
            return false;
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'proof_status', self::STATUS_APPROVED);
        self::log($submission_id, $user_id, 'approve', $note);
        do_action('tjm_proof_approved', $submission_id, $user_id);
        return true;
    }

    public static function request_changes(int $submission_id, int $user_id, string $note): bool
    {
        if (! self::is_author_or_admin($submission_id, $user_id)) {
            return false;
        }
        if (trim($note) === '') {
            return false;
        }
        update_post_meta($submission_id, Config::META_PREFIX . 'proof_status', self::STATUS_CHANGES_REQUESTED);
        self::log($submission_id, $user_id, 'request_changes', $note);
        do_action('tjm_proof_changes_requested', $submission_id, $user_id, $note);
        return true;
    }

    public static function get_status(int $submission_id): string
    {
        return (string) get_post_meta($submission_id, Config::META_PREFIX . 'proof_status', true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_history(int $submission_id): array
    {
        $h = get_post_meta($submission_id, Config::META_PREFIX . 'proof_history', true);
        return is_array($h) ? $h : [];
    }

    private static function log(int $submission_id, int $user_id, string $action, string $note): void
    {
        $h = self::get_history($submission_id);
        $h[] = [
            'user_id' => $user_id,
            'action'  => $action,
            'note'    => $note,
            'date'    => current_time('mysql'),
        ];
        update_post_meta($submission_id, Config::META_PREFIX . 'proof_history', $h);
    }

    private static function is_author_or_admin(int $submission_id, int $user_id): bool
    {
        $post = get_post($submission_id);
        if (! $post) return false;
        if ((int) $post->post_author === $user_id) return true;
        return user_can($user_id, 'manage_options');
    }

    private static function notify_author(int $submission_id): void
    {
        $post = get_post($submission_id);
        if (! $post) return;
        $author = get_userdata((int) $post->post_author);
        if (! $author || ! is_email($author->user_email)) return;

        (new Mailer())->send($author->user_email, 'proof-request', [
            'author_name'   => $author->display_name ?: $author->user_login,
            'title'         => (string) $post->post_title,
            'submission_id' => $submission_id,
        ]);
    }
}
