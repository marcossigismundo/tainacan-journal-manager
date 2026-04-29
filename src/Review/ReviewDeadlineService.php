<?php

declare(strict_types=1);

namespace TainacanJournalManager\Review;

use TainacanJournalManager\Config;
use TainacanJournalManager\Notifications\Mailer;

/**
 * Daily cron job: sends reminders for upcoming and overdue reviews.
 */
final class ReviewDeadlineService
{
    public static function send_reminders(): void
    {
        if (! Config::emails_enabled()) {
            return;
        }

        $today = current_time('Y-m-d');

        $reviews = get_posts([
            'post_type'      => Config::CPT_REVIEW,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => Config::META_PREFIX . 'review_status', 'value' => Config::REVIEW_ACCEPTED],
                ['key' => Config::META_PREFIX . 'deadline', 'compare' => 'EXISTS'],
            ],
        ]);

        $mailer = new Mailer();
        foreach ($reviews as $review) {
            $deadline = (string) get_post_meta($review->ID, Config::META_PREFIX . 'deadline', true);
            if (! $deadline) {
                continue;
            }

            $days_left = (int) ((strtotime($deadline) - strtotime($today)) / DAY_IN_SECONDS);
            $reviewer_id = (int) get_post_meta($review->ID, Config::META_PREFIX . 'reviewer_id', true);
            $reviewer = get_userdata($reviewer_id);
            if (! $reviewer) {
                continue;
            }

            if ($days_left === 7 || $days_left === 3 || $days_left === 1) {
                $mailer->send($reviewer->user_email, 'review-reminder', [
                    'reviewer_name' => $reviewer->display_name,
                    'days_left'     => $days_left,
                    'deadline'      => $deadline,
                    'review_id'     => $review->ID,
                ]);
            } elseif ($days_left < 0) {
                update_post_meta($review->ID, Config::META_PREFIX . 'review_status', Config::REVIEW_OVERDUE);
                $mailer->send($reviewer->user_email, 'review-overdue', [
                    'reviewer_name' => $reviewer->display_name,
                    'days_overdue'  => abs($days_left),
                    'review_id'     => $review->ID,
                ]);
            }
        }
    }
}
