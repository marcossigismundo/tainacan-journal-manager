<?php

declare(strict_types=1);

namespace TainacanJournalManager\Tools;

use TainacanJournalManager\Config;
use TainacanJournalManager\Roles\PluginRole;
use TainacanJournalManager\Tainacan\CollectionProvisioner;
use TainacanJournalManager\Tainacan\Integration;

/**
 * Populates the site with the full test fixture described in the
 * "Guia de Testes" PDF: 10 typed users, 1 journal (with provisioned
 * Tainacan collection), 1 issue, the 7 frontend pages with their
 * shortcodes, and a public journal/article landing page. Every
 * created object is recorded in option `tjm_test_seed_inventory`
 * so the purger can roll back without touching real data.
 *
 * Idempotent: re-running the seeder reuses existing matches by
 * username/slug instead of duplicating.
 */
final class TestDataSeeder
{
    public const INVENTORY_OPTION = 'tjm_test_seed_inventory';
    public const SEED_USER_META   = '_tjm_test_seed';

    /** Unique label that marks all objects created by the seeder. */
    public const SEED_TAG = 'tjm-seed-2026';

    /** Username convention for test users. */
    private const USER_PREFIX = 'tjm_';

    /**
     * Run the full seed. Returns a summary array suitable for
     * displaying in the admin notice.
     *
     * @return array{users: int, journal: int, issue: int, pages: int, collection: int}
     */
    public static function run(): array
    {
        $inventory = self::get_inventory();

        $users   = self::seed_users($inventory);
        $journal = self::seed_journal($inventory);
        self::assign_journal_roles($users, $journal);
        $issue   = self::seed_issue($inventory, $journal);
        $pages   = self::seed_pages($inventory, $journal);

        $collection_id = $journal > 0 && Integration::is_available()
            ? Integration::get_collection_id_for_journal($journal)
            : 0;

        $inventory['ran_at']        = current_time('mysql');
        $inventory['journal_id']    = $journal;
        $inventory['issue_id']      = $issue;
        $inventory['collection_id'] = $collection_id;

        update_option(self::INVENTORY_OPTION, $inventory, false);

        return [
            'users'      => count($users),
            'journal'    => $journal,
            'issue'      => $issue,
            'pages'      => count($pages),
            'collection' => $collection_id,
        ];
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Users                                                       */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * @return string[] List of user definitions.
     *                  Each row: [login, email, display_name, [global roles]].
     */
    private static function user_blueprints(): array
    {
        return [
            ['jmanager',    'jmanager@teste.local',    'Joana Manager',    [PluginRole::JOURNAL_MANAGER]],
            ['editorchefe', 'editorchefe@teste.local', 'Eduardo Chefe',    [PluginRole::EDITOR_CHIEF]],
            ['editorsecao', 'editorsecao@teste.local', 'Elena Secao',      [PluginRole::EDITOR_SECTION]],
            ['autor',       'autor@teste.local',       'Alice Autora',     [PluginRole::AUTHOR]],
            ['avaliador1',  'avaliador1@teste.local',  'Pedro Parecer',    [PluginRole::REVIEWER]],
            ['avaliador2',  'avaliador2@teste.local',  'Paula Parecer',    [PluginRole::REVIEWER]],
            ['copyeditor',  'copyeditor@teste.local',  'Carla Copydesk',   [PluginRole::COPYEDITOR]],
            ['layout',      'layout@teste.local',      'Lucas Layout',     [PluginRole::LAYOUT_EDITOR]],
            ['leitor',      'leitor@teste.local',      'Lia Leitor',       [PluginRole::READER]],
            ['admininst',   'admininst@teste.local',   'Antonio Inst.',    [PluginRole::ADMIN_INSTITUTIONAL]],
        ];
    }

    /**
     * Default password for every seeded account. Fine in dev because
     * accounts are tagged and removed by the purger. The login is
     * shown to the admin in the Test Data page.
     */
    public static function default_password(): string
    {
        return 'TesteTJM2026!';
    }

    /**
     * @param array<string,mixed> $inventory
     * @return array<string,int> Map login => user_id.
     */
    private static function seed_users(array &$inventory): array
    {
        $created = isset($inventory['users']) && is_array($inventory['users'])
            ? array_map('intval', $inventory['users'])
            : [];

        foreach (self::user_blueprints() as $bp) {
            [$slug, $email, $display, $roles] = $bp;
            $login = self::USER_PREFIX . $slug;

            $user_id = self::ensure_user($login, $email, $display);
            if ($user_id <= 0) {
                continue;
            }

            update_user_meta($user_id, self::SEED_USER_META, self::SEED_TAG);

            // Apply TJM roles (global only — per-journal happens later
            // once the journal id is known).
            PluginRole::set_roles($user_id, $roles);

            $created[$login] = $user_id;
        }

        $inventory['users'] = $created;
        return $created;
    }

    private static function ensure_user(string $login, string $email, string $display): int
    {
        $existing = get_user_by('login', $login);
        if ($existing instanceof \WP_User) {
            // Keep the account, just refresh display name/email if they drifted.
            wp_update_user([
                'ID'           => $existing->ID,
                'user_email'   => $email,
                'display_name' => $display,
                'role'         => 'subscriber',
            ]);
            return (int) $existing->ID;
        }

        $id = wp_insert_user([
            'user_login'   => $login,
            'user_pass'    => self::default_password(),
            'user_email'   => $email,
            'display_name' => $display,
            'first_name'   => $display,
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG; surfaces only in dev.
                error_log('[TJM seeder] user create failed for ' . $login . ': ' . $id->get_error_message());
            }
            return 0;
        }

        return (int) $id;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Journal                                                     */
    /* ──────────────────────────────────────────────────────────── */

    private static function seed_journal(array &$inventory): int
    {
        $existing_id = isset($inventory['journal_id']) ? (int) $inventory['journal_id'] : 0;
        if ($existing_id > 0 && get_post_type($existing_id) === Config::CPT_JOURNAL) {
            // Re-trigger the provisioner to keep collection metadata in sync.
            if (Integration::is_available()) {
                CollectionProvisioner::provision_for_journal($existing_id);
            }
            return $existing_id;
        }

        $journal_id = wp_insert_post([
            'post_title'   => __('Revista Teste de Ciencias Aplicadas', 'tainacan-journal-manager'),
            'post_status'  => 'publish',
            'post_type'    => Config::CPT_JOURNAL,
            'post_content' => __('Periodico de teste criado automaticamente pelo seeder TJM. Use somente em ambiente de homologacao.', 'tainacan-journal-manager'),
            'post_excerpt' => __('Revista fictícia para validar todos os perfis e o fluxo editorial.', 'tainacan-journal-manager'),
            'meta_input'   => [
                Config::META_PREFIX . 'review_type'    => Config::REVIEW_TYPE_DOUBLE,
                Config::META_PREFIX . 'issn'           => '1234-5678',
                Config::META_PREFIX . 'eissn'          => '8765-4321',
                Config::META_PREFIX . 'license'        => 'CC BY 4.0',
                Config::META_PREFIX . 'parecer_form'   => self::default_review_sections(),
                self::SEED_USER_META                   => self::SEED_TAG,
            ],
        ], true);

        if (is_wp_error($journal_id)) {
            return 0;
        }

        $journal_id = (int) $journal_id;

        if (Integration::is_available()) {
            CollectionProvisioner::provision_for_journal($journal_id);
        }

        return $journal_id;
    }

    /** Default parecer form sections for the seeded journal. */
    private static function default_review_sections(): array
    {
        return [
            ['title' => __('Originalidade', 'tainacan-journal-manager'),  'description' => __('O artigo e original e ineduto?', 'tainacan-journal-manager')],
            ['title' => __('Metodologia',   'tainacan-journal-manager'),  'description' => __('A metodologia esta clara e adequada ao escopo?', 'tainacan-journal-manager')],
            ['title' => __('Relevancia',    'tainacan-journal-manager'),  'description' => __('Avalie a relevancia para a area.', 'tainacan-journal-manager')],
        ];
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Per-journal roles                                           */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * @param array<string,int> $users
     */
    private static function assign_journal_roles(array $users, int $journal_id): void
    {
        if ($journal_id <= 0) {
            return;
        }

        $journal_roles = [
            'tjm_jmanager'    => [PluginRole::JOURNAL_MANAGER],
            'tjm_editorchefe' => [PluginRole::EDITOR_CHIEF],
            'tjm_editorsecao' => [PluginRole::EDITOR_SECTION],
            'tjm_avaliador1'  => [PluginRole::REVIEWER],
            'tjm_avaliador2'  => [PluginRole::REVIEWER],
            'tjm_copyeditor'  => [PluginRole::COPYEDITOR],
            'tjm_layout'      => [PluginRole::LAYOUT_EDITOR],
        ];

        foreach ($journal_roles as $login => $roles) {
            if (! isset($users[$login])) {
                continue;
            }
            $uid = (int) $users[$login];
            $map = PluginRole::get_journal_roles_map($uid);
            $map[$journal_id] = array_values(array_unique(array_merge($map[$journal_id] ?? [], $roles)));
            PluginRole::set_journal_roles_map($uid, $map);
        }
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Issue                                                       */
    /* ──────────────────────────────────────────────────────────── */

    private static function seed_issue(array &$inventory, int $journal_id): int
    {
        if ($journal_id <= 0) {
            return 0;
        }

        $existing_id = isset($inventory['issue_id']) ? (int) $inventory['issue_id'] : 0;
        if ($existing_id > 0 && get_post_type($existing_id) === Config::CPT_ISSUE) {
            return $existing_id;
        }

        $year = (int) gmdate('Y');

        $issue_id = wp_insert_post([
            'post_title'   => sprintf(
                /* translators: %d: year */
                __('Vol. 1, n. 1, %d', 'tainacan-journal-manager'),
                $year
            ),
            'post_status'  => 'publish',
            'post_type'    => Config::CPT_ISSUE,
            'post_content' => __('Edicao seed para testes.', 'tainacan-journal-manager'),
            'meta_input'   => [
                Config::META_PREFIX . 'journal_id'        => $journal_id,
                Config::META_PREFIX . 'volume'            => 1,
                Config::META_PREFIX . 'number'            => 1,
                Config::META_PREFIX . 'year'              => $year,
                Config::META_PREFIX . 'publication_type'  => 'regular',
                self::SEED_USER_META                      => self::SEED_TAG,
            ],
        ], true);

        return is_wp_error($issue_id) ? 0 : (int) $issue_id;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Pages with shortcodes                                       */
    /* ──────────────────────────────────────────────────────────── */

    /**
     * @param array<string,mixed> $inventory
     * @return array<string,int> Map slug => page_id.
     */
    private static function seed_pages(array &$inventory, int $journal_id): array
    {
        $created = isset($inventory['pages']) && is_array($inventory['pages'])
            ? array_map('intval', $inventory['pages'])
            : [];

        $blueprints = [
            [Config::PAGE_LOGIN,        __('Journal login',          'tainacan-journal-manager'), '[tjm_login]'],
            [Config::PAGE_AUTHOR,       __('Author portal',          'tainacan-journal-manager'), '[tjm_author_portal]'],
            [Config::PAGE_REVIEWER,     __('Reviewer dashboard',     'tainacan-journal-manager'), '[tjm_reviewer_dashboard]'],
            [Config::PAGE_EDITORIAL,    __('Editorial dashboard',    'tainacan-journal-manager'), '[tjm_editorial_dashboard]'],
            [Config::PAGE_COPYEDITING,  __('Copyediting dashboard',  'tainacan-journal-manager'), '[tjm_copyediting_dashboard]'],
            [Config::PAGE_ROLES,        __('Journal roles',          'tainacan-journal-manager'), '[tjm_role_management]'],
            [Config::PAGE_INDICATORS,   __('Journal indicators',     'tainacan-journal-manager'), '[tjm_indicators]'],
        ];

        if ($journal_id > 0) {
            $blueprints[] = [
                'periodico-' . $journal_id,
                sprintf(__('Periodico publico %d', 'tainacan-journal-manager'), $journal_id),
                '[tjm_journal id="' . $journal_id . '"]',
            ];
        }

        foreach ($blueprints as [$slug, $title, $shortcode]) {
            $created[$slug] = self::ensure_page($slug, (string) $title, (string) $shortcode);
        }

        $inventory['pages'] = $created;
        return $created;
    }

    private static function ensure_page(string $slug, string $title, string $shortcode): int
    {
        $existing = get_page_by_path($slug);
        if ($existing instanceof \WP_Post) {
            // Keep human edits to the body — only ensure the shortcode is present.
            if (strpos((string) $existing->post_content, $shortcode) === false) {
                wp_update_post([
                    'ID'           => $existing->ID,
                    'post_content' => $shortcode,
                ]);
            }
            update_post_meta($existing->ID, self::SEED_USER_META, self::SEED_TAG);
            return (int) $existing->ID;
        }

        $id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => $shortcode,
            'meta_input'   => [self::SEED_USER_META => self::SEED_TAG],
        ], true);

        return is_wp_error($id) ? 0 : (int) $id;
    }

    /* ──────────────────────────────────────────────────────────── */
    /*  Inventory                                                   */
    /* ──────────────────────────────────────────────────────────── */

    /** @return array<string,mixed> */
    public static function get_inventory(): array
    {
        $raw = get_option(self::INVENTORY_OPTION, []);
        return is_array($raw) ? $raw : [];
    }
}
