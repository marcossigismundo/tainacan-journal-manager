<?php

declare(strict_types=1);

namespace TainacanJournalManager\Editorial;

use TainacanJournalManager\Config;

/**
 * Records editorial decisions on submissions.
 *
 * Decisions are NEVER automatic based on review scores — the editor
 * always makes the final call. This class records the decision and
 * triggers the appropriate workflow transition.
 */
final class DecisionManager
{
    private const ALLOWED_DECISIONS = [
        Config::DECISION_ACCEPT,
        Config::DECISION_REVISIONS,
        Config::DECISION_RESUBMIT,
        Config::DECISION_REJECT,
    ];

    /**
     * Record an editor's decision and transition the submission.
     */
    public static function record(int $submission_id, string $decision, int $editor_id, string $justification = ''): bool
    {
        if (! in_array($decision, self::ALLOWED_DECISIONS, true)) {
            return false;
        }

        $decisions = get_post_meta($submission_id, Config::META_PREFIX . 'decisions', true);
        if (! is_array($decisions)) {
            $decisions = [];
        }
        $decisions[] = [
            'decision'      => $decision,
            'editor_id'     => $editor_id,
            'justification' => $justification,
            'date'          => current_time('mysql'),
        ];
        update_post_meta($submission_id, Config::META_PREFIX . 'decisions', $decisions);

        // Map decision to workflow transition
        $next = match ($decision) {
            Config::DECISION_ACCEPT     => Config::STATUS_COPYEDITING,
            Config::DECISION_REVISIONS  => Config::STATUS_REVISION,
            Config::DECISION_RESUBMIT   => Config::STATUS_REVIEW,
            Config::DECISION_REJECT     => Config::STATUS_REJECTED,
        };

        WorkflowManager::transition($submission_id, $next, $editor_id, $justification);

        do_action('tjm_decision_recorded', $submission_id, $decision, $editor_id);

        return true;
    }
}
