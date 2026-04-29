<?php

declare(strict_types=1);

namespace TainacanJournalManager\Editorial;

use TainacanJournalManager\Config;

/**
 * Helpers to query and label submission statuses.
 */
final class StatusManager
{
    public static function get_label(string $status): string
    {
        return Config::get_status_label($status);
    }

    public static function is_active(string $status): bool
    {
        return in_array($status, [
            Config::STATUS_SUBMITTED, Config::STATUS_TRIAGE, Config::STATUS_REVIEW,
            Config::STATUS_REVISION, Config::STATUS_DECISION, Config::STATUS_COPYEDITING,
            Config::STATUS_PRODUCTION,
        ], true);
    }

    public static function is_terminal(string $status): bool
    {
        return in_array($status, [Config::STATUS_PUBLISHED, Config::STATUS_REJECTED, Config::STATUS_WITHDRAWN], true);
    }
}
