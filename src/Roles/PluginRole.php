<?php

declare(strict_types=1);

namespace TainacanJournalManager\Roles;

/**
 * Editorial roles management. Unlike single-role plugins, TJM allows
 * MULTIPLE roles per user (a person can be editor of journal A, author
 * of journal B, and reviewer of journal C).
 *
 * Storage:
 * - Global roles: user meta `_tjm_roles` (JSON array of role keys)
 * - Per-journal roles: user meta `_tjm_journal_roles` (JSON map: journal_id => [roles])
 */
final class PluginRole
{
    // ── Role keys ────────────────────────────────────────────────────
    public const JOURNAL_MANAGER = 'journal_manager';
    public const EDITOR_CHIEF    = 'editor_chefe';
    public const EDITOR_SECTION  = 'editor_secao';
    public const AUTHOR          = 'autor';
    public const REVIEWER        = 'avaliador';
    public const COPYEDITOR      = 'copyeditor';
    public const LAYOUT_EDITOR   = 'layout_editor';
    public const READER          = 'leitor';
    public const ADMIN_INSTITUTIONAL = 'admin_institucional';

    public const ALL_ROLES = [
        self::JOURNAL_MANAGER,
        self::EDITOR_CHIEF,
        self::EDITOR_SECTION,
        self::AUTHOR,
        self::REVIEWER,
        self::COPYEDITOR,
        self::LAYOUT_EDITOR,
        self::READER,
        self::ADMIN_INSTITUTIONAL,
    ];

    // ── Meta keys ────────────────────────────────────────────────────
    private const META_GLOBAL_ROLES   = '_tjm_roles';
    private const META_JOURNAL_ROLES  = '_tjm_journal_roles';

    // ── Public API: global roles ─────────────────────────────────────

    /**
     * @return string[] List of global role keys for the user.
     */
    public static function get_roles(int $user_id): array
    {
        $raw = get_user_meta($user_id, self::META_GLOBAL_ROLES, true);
        if (is_array($raw)) {
            return array_values(array_filter($raw, fn($r) => in_array($r, self::ALL_ROLES, true)));
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_values(array_filter($decoded, fn($r) => in_array($r, self::ALL_ROLES, true))) : [];
        }
        return [];
    }

    /**
     * @param string[] $roles
     */
    public static function set_roles(int $user_id, array $roles): void
    {
        $valid = array_values(array_unique(array_filter($roles, fn($r) => in_array($r, self::ALL_ROLES, true))));
        update_user_meta($user_id, self::META_GLOBAL_ROLES, $valid);
    }

    public static function add_role(int $user_id, string $role): void
    {
        $current = self::get_roles($user_id);
        if (! in_array($role, $current, true)) {
            $current[] = $role;
            self::set_roles($user_id, $current);
        }
    }

    public static function remove_role(int $user_id, string $role): void
    {
        $current = self::get_roles($user_id);
        $filtered = array_values(array_filter($current, fn($r) => $r !== $role));
        self::set_roles($user_id, $filtered);
    }

    public static function has_role(int $user_id, string $role): bool
    {
        return in_array($role, self::get_roles($user_id), true);
    }

    // ── Public API: per-journal roles ────────────────────────────────

    /**
     * @return array<int, string[]> Map journal_id => [role keys]
     */
    public static function get_journal_roles_map(int $user_id): array
    {
        $raw = get_user_meta($user_id, self::META_JOURNAL_ROLES, true);
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $journal_id => $roles) {
            $journal_id = (int) $journal_id;
            if ($journal_id <= 0 || ! is_array($roles)) {
                continue;
            }
            $valid = array_values(array_filter($roles, fn($r) => in_array($r, self::ALL_ROLES, true)));
            if (! empty($valid)) {
                $clean[$journal_id] = $valid;
            }
        }
        return $clean;
    }

    /**
     * @return string[] Roles for a user in a specific journal.
     */
    public static function get_roles_for_journal(int $user_id, int $journal_id): array
    {
        $map = self::get_journal_roles_map($user_id);
        return $map[$journal_id] ?? [];
    }

    /**
     * @param array<int, string[]> $map
     */
    public static function set_journal_roles_map(int $user_id, array $map): void
    {
        $clean = [];
        foreach ($map as $journal_id => $roles) {
            $journal_id = (int) $journal_id;
            if ($journal_id <= 0 || ! is_array($roles)) {
                continue;
            }
            $valid = array_values(array_unique(array_filter(
                $roles,
                fn($r) => in_array($r, self::ALL_ROLES, true)
            )));
            if (! empty($valid)) {
                $clean[$journal_id] = $valid;
            }
        }
        update_user_meta($user_id, self::META_JOURNAL_ROLES, $clean);
    }

    public static function add_journal_role(int $user_id, int $journal_id, string $role): void
    {
        $map = self::get_journal_roles_map($user_id);
        $current = $map[$journal_id] ?? [];
        if (! in_array($role, $current, true)) {
            $current[] = $role;
            $map[$journal_id] = $current;
            self::set_journal_roles_map($user_id, $map);
        }
    }

    public static function remove_journal_role(int $user_id, int $journal_id, string $role): void
    {
        $map = self::get_journal_roles_map($user_id);
        if (! isset($map[$journal_id])) {
            return;
        }
        $map[$journal_id] = array_values(array_filter($map[$journal_id], fn($r) => $r !== $role));
        if (empty($map[$journal_id])) {
            unset($map[$journal_id]);
        }
        self::set_journal_roles_map($user_id, $map);
    }

    public static function has_journal_role(int $user_id, int $journal_id, string $role): bool
    {
        return in_array($role, self::get_roles_for_journal($user_id, $journal_id), true);
    }

    // ── Convenience checks ───────────────────────────────────────────

    public static function is_editor(int $user_id, ?int $journal_id = null): bool
    {
        $editor_roles = [self::JOURNAL_MANAGER, self::EDITOR_CHIEF, self::EDITOR_SECTION];

        if ($journal_id) {
            $roles = self::get_roles_for_journal($user_id, $journal_id);
            foreach ($editor_roles as $r) {
                if (in_array($r, $roles, true)) {
                    return true;
                }
            }
            return false;
        }

        $globals = self::get_roles($user_id);
        foreach ($editor_roles as $r) {
            if (in_array($r, $globals, true)) {
                return true;
            }
        }
        return false;
    }

    public static function is_admin_institutional(int $user_id): bool
    {
        return self::has_role($user_id, self::ADMIN_INSTITUTIONAL)
            || user_can($user_id, 'manage_options');
    }

    /**
     * @return \WP_User[]
     */
    public static function get_users_by_role(string $role): array
    {
        return get_users([
            'meta_query' => [
                [
                    'key'     => self::META_GLOBAL_ROLES,
                    'value'   => sprintf('"%s"', $role),
                    'compare' => 'LIKE',
                ],
            ],
        ]);
    }
}
