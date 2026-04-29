<?php

declare(strict_types=1);

namespace TainacanJournalManager\Review;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;

/**
 * Suggests least-loaded reviewers for assignment.
 *
 * Considers only PENDING reviews (status = invited or accepted, not submitted/declined)
 * — same fix lesson learned from Pontos de Memoria.
 */
final class ReviewerAssignmentService
{
    /**
     * @return int User ID of the least-loaded reviewer, or 0 if none available.
     */
    public static function suggest_least_loaded(): int
    {
        $cache_key = 'tjm_least_loaded_reviewer';
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return (int) $cached;
        }

        $reviewers = PluginRole::get_users_by_role(PluginRole::REVIEWER);
        if (empty($reviewers)) {
            return 0;
        }

        $loads = [];
        foreach ($reviewers as $reviewer) {
            $count = (new \WP_Query([
                'post_type'      => Config::CPT_REVIEW,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'author'         => $reviewer->ID,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => Config::META_PREFIX . 'review_status',
                        'value'   => [Config::REVIEW_INVITED, Config::REVIEW_ACCEPTED],
                        'compare' => 'IN',
                    ],
                ],
            ]))->found_posts;

            $loads[$reviewer->ID] = $count;
        }

        if (empty($loads)) {
            return 0;
        }

        asort($loads);
        $result = (int) array_key_first($loads);

        set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        return $result;
    }
}
