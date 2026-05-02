<?php

declare(strict_types=1);

namespace TainacanJournalManager\Tools;

use TainacanJournalManager\Config;
use TainacanJournalManager\Tainacan\Integration;

/**
 * Removes everything created by `TestDataSeeder`. Reads the inventory
 * option to know exactly what to delete — safe to run on a database
 * that contains real data alongside the seed.
 *
 * What we delete:
 *  - Test users (matched by both inventory id AND `_tjm_test_seed` meta)
 *  - Test journal post and any submissions/reviews still attached to it
 *  - Test issue post
 *  - Pages we created (slug + meta tag must both match)
 *  - Tainacan collection provisioned for the test journal
 *  - The inventory option itself
 *
 * Anything that does not bear the seed marker is left untouched.
 */
final class TestDataPurger
{
    /**
     * Run the purge. Returns counts so the admin notice can summarise it.
     *
     * @return array{users: int, posts: int, pages: int, collection: int}
     */
    public static function run(): array
    {
        $inventory = TestDataSeeder::get_inventory();

        $deleted_users  = self::delete_users($inventory);
        $deleted_pages  = self::delete_pages($inventory);
        $deleted_posts  = self::delete_journal_and_dependents($inventory);
        $deleted_coll   = self::delete_collection($inventory);

        delete_option(TestDataSeeder::INVENTORY_OPTION);

        return [
            'users'      => $deleted_users,
            'posts'      => $deleted_posts,
            'pages'      => $deleted_pages,
            'collection' => $deleted_coll,
        ];
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Users                                                       */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * @param array<string,mixed> $inventory
     */
    private static function delete_users(array $inventory): int
    {
        if (! function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $count = 0;
        $users = isset($inventory['users']) && is_array($inventory['users'])
            ? $inventory['users']
            : [];

        // Reassign content to the current admin so we never orphan posts.
        $reassign_to = get_current_user_id() ?: null;

        foreach ($users as $login => $user_id) {
            $user_id = (int) $user_id;
            if ($user_id <= 0) {
                continue;
            }

            // Double-check via meta: avoids deleting a real user who happens
            // to have the same id by accident (highly unlikely but cheap).
            $marker = (string) get_user_meta($user_id, TestDataSeeder::SEED_USER_META, true);
            if ($marker !== TestDataSeeder::SEED_TAG) {
                continue;
            }

            if (wp_delete_user($user_id, $reassign_to)) {
                $count++;
            }
        }

        return $count;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Pages                                                       */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * @param array<string,mixed> $inventory
     */
    private static function delete_pages(array $inventory): int
    {
        $count = 0;
        $pages = isset($inventory['pages']) && is_array($inventory['pages'])
            ? $inventory['pages']
            : [];

        foreach ($pages as $slug => $page_id) {
            $page_id = (int) $page_id;
            if ($page_id <= 0 || get_post_type($page_id) !== 'page') {
                continue;
            }

            $marker = (string) get_post_meta($page_id, TestDataSeeder::SEED_USER_META, true);
            if ($marker !== TestDataSeeder::SEED_TAG) {
                continue;
            }

            if (wp_delete_post($page_id, true)) {
                $count++;
            }
        }

        return $count;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Journal + dependents                                        */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * Delete the test journal AND any submission/review/issue that points
     * at it, so reviewing a fresh seed does not leave dangling rows.
     *
     * @param array<string,mixed> $inventory
     */
    private static function delete_journal_and_dependents(array $inventory): int
    {
        $journal_id = isset($inventory['journal_id']) ? (int) $inventory['journal_id'] : 0;
        if ($journal_id <= 0) {
            return 0;
        }

        $count = 0;

        // Submissions tied to this journal
        $submission_ids = get_posts([
            'post_type'      => Config::CPT_SUBMISSION,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => Config::META_PREFIX . 'journal_id',
                    'value' => $journal_id,
                ],
            ],
        ]);

        $review_ids = [];
        foreach ($submission_ids as $sid) {
            $review_ids = array_merge($review_ids, get_posts([
                'post_type'      => Config::CPT_REVIEW,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => Config::META_PREFIX . 'submission_id',
                        'value' => (int) $sid,
                    ],
                ],
            ]));
        }

        $issue_ids = get_posts([
            'post_type'      => Config::CPT_ISSUE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => Config::META_PREFIX . 'journal_id',
                    'value' => $journal_id,
                ],
            ],
        ]);

        foreach (array_unique(array_map('intval', $review_ids)) as $rid) {
            if (wp_delete_post($rid, true)) {
                $count++;
            }
        }
        foreach (array_unique(array_map('intval', $submission_ids)) as $sid) {
            if (wp_delete_post($sid, true)) {
                $count++;
            }
        }
        foreach (array_unique(array_map('intval', $issue_ids)) as $iid) {
            if (wp_delete_post($iid, true)) {
                $count++;
            }
        }
        if (wp_delete_post($journal_id, true)) {
            $count++;
        }

        return $count;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Tainacan collection                                         */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * @param array<string,mixed> $inventory
     */
    private static function delete_collection(array $inventory): int
    {
        if (! Integration::is_available()) {
            return 0;
        }

        $journal_id    = isset($inventory['journal_id']) ? (int) $inventory['journal_id'] : 0;
        $collection_id = isset($inventory['collection_id']) ? (int) $inventory['collection_id'] : 0;

        if ($collection_id <= 0 && $journal_id > 0) {
            $collection_id = Integration::get_collection_id_for_journal($journal_id);
        }

        if ($collection_id <= 0) {
            return 0;
        }

        try {
            $col_repo = \Tainacan\Repositories\Collections::get_instance();
            $entity   = $col_repo->fetch($collection_id);
            if ($entity instanceof \Tainacan\Entities\Collection) {
                // True deletion — collection is freshly seeded so this is safe.
                $col_repo->delete($entity);
                if ($journal_id > 0) {
                    delete_option('tjm_collection_for_journal_' . $journal_id);
                }
                return 1;
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG; surfaces only in dev.
                error_log('[TJM purger] collection delete failed: ' . $e->getMessage());
            }
        }

        return 0;
    }
}
