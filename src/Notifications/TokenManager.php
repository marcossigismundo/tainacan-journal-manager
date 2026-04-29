<?php

declare(strict_types=1);

namespace TainacanJournalManager\Notifications;

use TainacanJournalManager\Config;

/**
 * Generates and validates secure tokens for review invitations
 * (one-click email links).
 *
 * Stores tokens in wp_options with expiration. NOT JWT — server-side
 * validation only, no signature complexity.
 */
final class TokenManager
{
    private const OPTION_PREFIX = 'tjm_token_';

    public static function generate(int $user_id, string $purpose, array $payload = []): string
    {
        $token = wp_generate_password(48, false, false);
        $key   = self::OPTION_PREFIX . $token;

        $days  = (int) get_option(Config::OPTION_TOKEN_VALIDITY_DAYS, Config::DEFAULT_TOKEN_VALIDITY);
        $expires = time() + ($days * DAY_IN_SECONDS);

        update_option($key, [
            'user_id'  => $user_id,
            'purpose'  => $purpose,
            'payload'  => $payload,
            'expires'  => $expires,
        ], false);

        return $token;
    }

    /**
     * @return array{user_id: int, purpose: string, payload: array, expires: int}|null
     */
    public static function validate(string $token): ?array
    {
        $key  = self::OPTION_PREFIX . $token;
        $data = get_option($key);

        if (! is_array($data) || ! isset($data['expires'])) {
            return null;
        }

        if (time() > (int) $data['expires']) {
            delete_option($key);
            return null;
        }

        return $data;
    }

    public static function consume(string $token): void
    {
        delete_option(self::OPTION_PREFIX . $token);
    }

    public static function cleanup(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . self::OPTION_PREFIX . "%'");
    }
}
