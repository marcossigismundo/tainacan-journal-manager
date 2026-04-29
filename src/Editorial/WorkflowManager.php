<?php

declare(strict_types=1);

namespace TainacanJournalManager\Editorial;

use TainacanJournalManager\Config;

/**
 * Manages submission status transitions.
 *
 * Allowed transitions are explicitly listed — prevents inconsistent states
 * (e.g., jumping from draft directly to published).
 */
final class WorkflowManager
{
    /** @var array<string, string[]> Allowed: status => [next statuses] */
    private const TRANSITIONS = [
        Config::STATUS_DRAFT       => [Config::STATUS_SUBMITTED, Config::STATUS_WITHDRAWN],
        Config::STATUS_SUBMITTED   => [Config::STATUS_TRIAGE, Config::STATUS_REJECTED, Config::STATUS_WITHDRAWN],
        Config::STATUS_TRIAGE      => [Config::STATUS_REVIEW, Config::STATUS_REVISION, Config::STATUS_REJECTED],
        Config::STATUS_REVIEW      => [Config::STATUS_DECISION, Config::STATUS_REVISION, Config::STATUS_REJECTED],
        Config::STATUS_REVISION    => [Config::STATUS_TRIAGE, Config::STATUS_REVIEW, Config::STATUS_WITHDRAWN],
        Config::STATUS_DECISION    => [Config::STATUS_COPYEDITING, Config::STATUS_REVISION, Config::STATUS_REJECTED],
        Config::STATUS_COPYEDITING => [Config::STATUS_PRODUCTION, Config::STATUS_REVISION],
        Config::STATUS_PRODUCTION  => [Config::STATUS_PUBLISHED, Config::STATUS_COPYEDITING],
        Config::STATUS_PUBLISHED   => [],
        Config::STATUS_REJECTED    => [],
        Config::STATUS_WITHDRAWN   => [],
    ];

    public static function get_status(int $submission_id): string
    {
        return (string) (get_post_meta($submission_id, Config::META_PREFIX . 'status', true) ?: Config::STATUS_DRAFT);
    }

    public static function can_transition(string $from, string $to): bool
    {
        return isset(self::TRANSITIONS[$from]) && in_array($to, self::TRANSITIONS[$from], true);
    }

    /**
     * Transition a submission to a new status.
     * Records the transition in `_tjm_status_history`.
     */
    public static function transition(int $submission_id, string $new_status, int $user_id = 0, string $note = ''): bool
    {
        $current = self::get_status($submission_id);

        if (! self::can_transition($current, $new_status)) {
            return false;
        }

        update_post_meta($submission_id, Config::META_PREFIX . 'status', $new_status);

        // Append to history
        $history = get_post_meta($submission_id, Config::META_PREFIX . 'status_history', true);
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = [
            'from'    => $current,
            'to'      => $new_status,
            'user_id' => $user_id ?: get_current_user_id(),
            'date'    => current_time('mysql'),
            'note'    => $note,
        ];
        update_post_meta($submission_id, Config::META_PREFIX . 'status_history', $history);

        // Fire transition hook for other components (notifications, integrations)
        do_action('tjm_status_transition', $submission_id, $current, $new_status);

        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function get_allowed_next_statuses(int $submission_id): array
    {
        $current = self::get_status($submission_id);
        return self::TRANSITIONS[$current] ?? [];
    }
}
