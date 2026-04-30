<?php

declare(strict_types=1);

namespace TainacanJournalManager\Audit;

/**
 * Persistent audit log of editorial / administrative actions.
 *
 * Stored in a custom table `{$wpdb->prefix}tjm_audit_log` so we keep a
 * tamper-resistant, queryable history independent of post meta. Each row:
 *
 *   id             BIGINT
 *   created_at     DATETIME (UTC)
 *   user_id        BIGINT (0 = system / anonymous)
 *   user_login     VARCHAR (snapshot — survives user deletion)
 *   ip             VARCHAR
 *   event          VARCHAR (snake_case key, e.g. 'submission.submit')
 *   object_type    VARCHAR ('submission' | 'review' | 'issue' | 'user' | ...)
 *   object_id      BIGINT
 *   data           LONGTEXT (JSON, optional payload)
 *
 * The table is created on plugin activation (Activator) and dropped on
 * uninstall (uninstall.php). The class also subscribes to plugin hooks
 * to record events automatically.
 */
final class AuditLog
{
    public const SCHEMA_VERSION = '1';
    public const SCHEMA_OPTION  = 'tjm_audit_schema_version';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'tjm_audit_log';
    }

    public static function install_table(): void
    {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at  DATETIME NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_login  VARCHAR(60) NOT NULL DEFAULT '',
            ip          VARCHAR(45) NOT NULL DEFAULT '',
            event       VARCHAR(80) NOT NULL,
            object_type VARCHAR(40) NOT NULL DEFAULT '',
            object_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            data        LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY idx_event (event),
            KEY idx_object (object_type, object_id),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
    }

    public static function maybe_upgrade(): void
    {
        $current = (string) get_option(self::SCHEMA_OPTION, '');
        if ($current !== self::SCHEMA_VERSION) {
            self::install_table();
        }
    }

    public static function drop_table(): void
    {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
        delete_option(self::SCHEMA_OPTION);
    }

    /**
     * Insert one log row. Returns the inserted id (0 on failure).
     *
     * @param array<string, mixed> $data
     */
    public static function record(string $event, string $object_type = '', int $object_id = 0, array $data = []): int
    {
        global $wpdb;

        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $login   = '';
        if ($user_id > 0) {
            $u = get_userdata($user_id);
            $login = $u ? (string) ($u->user_login ?: '') : '';
        }

        $ok = $wpdb->insert(
            self::table_name(),
            [
                'created_at'  => gmdate('Y-m-d H:i:s'),
                'user_id'     => $user_id,
                'user_login'  => mb_substr($login, 0, 60),
                'ip'          => mb_substr(self::detect_ip(), 0, 45),
                'event'       => mb_substr($event, 0, 80),
                'object_type' => mb_substr($object_type, 0, 40),
                'object_id'   => $object_id,
                'data'        => $data ? wp_json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Query the log.
     *
     * @param array{event?:string, object_type?:string, object_id?:int, user_id?:int, since?:string, search?:string, per_page?:int, page?:int} $args
     * @return array{rows: array<int, object>, total: int}
     */
    public static function query(array $args = []): array
    {
        global $wpdb;

        $where = [];
        $params = [];

        if (! empty($args['event'])) {
            $where[] = 'event = %s';
            $params[] = (string) $args['event'];
        }
        if (! empty($args['object_type'])) {
            $where[] = 'object_type = %s';
            $params[] = (string) $args['object_type'];
        }
        if (! empty($args['object_id'])) {
            $where[] = 'object_id = %d';
            $params[] = (int) $args['object_id'];
        }
        if (! empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if (! empty($args['since'])) {
            $where[] = 'created_at >= %s';
            $params[] = gmdate('Y-m-d H:i:s', strtotime((string) $args['since']) ?: time());
        }
        if (! empty($args['search'])) {
            $where[] = '(event LIKE %s OR data LIKE %s OR user_login LIKE %s)';
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        $table = self::table_name();

        $per_page = max(1, min(500, (int) ($args['per_page'] ?? 50)));
        $page     = max(1, (int) ($args['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $rows_sql  = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC, id DESC LIMIT {$per_page} OFFSET {$offset}";

        $total = (int) (empty($params)
            ? $wpdb->get_var($count_sql)
            : $wpdb->get_var($wpdb->prepare($count_sql, ...$params)));

        $rows = empty($params)
            ? $wpdb->get_results($rows_sql)
            : $wpdb->get_results($wpdb->prepare($rows_sql, ...$params));

        return [
            'rows'  => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }

    /**
     * Subscribe to plugin events and record them automatically.
     */
    public function register(): void
    {
        add_action('tjm_status_transition',      [$this, 'on_status_transition'], 10, 3);
        add_action('tjm_decision_recorded',      [$this, 'on_decision'], 10, 3);
        add_action('tjm_submission_submitted',   [$this, 'on_submission'], 10, 1);
        add_action('tjm_review_invited',         [$this, 'on_review_invited'], 10, 3);
        add_action('tjm_review_accepted',        [$this, 'on_review_accepted'], 10, 1);
        add_action('tjm_review_declined',        [$this, 'on_review_declined'], 10, 1);
        add_action('tjm_review_submitted',       [$this, 'on_review_submitted'], 10, 1);
        add_action('tjm_article_published',      [$this, 'on_article_published'], 10, 2);
        add_action('tjm_issue_published',        [$this, 'on_issue_published'], 10, 2);
        add_action('tjm_proof_approved',         [$this, 'on_proof_approved'], 10, 2);
        add_action('tjm_proof_changes_requested',[$this, 'on_proof_changes'], 10, 3);
        add_action('tjm_galley_added',           [$this, 'on_galley_added'], 10, 3);
        add_action('tjm_copyediting_version_uploaded', [$this, 'on_copyediting_uploaded'], 10, 4);
    }

    public function on_status_transition(int $sid, string $from, string $to): void
    {
        self::record('submission.transition', 'submission', $sid, ['from' => $from, 'to' => $to]);
    }
    public function on_decision(int $sid, string $decision, int $editor_id): void
    {
        self::record('submission.decision', 'submission', $sid, ['decision' => $decision, 'editor_id' => $editor_id]);
    }
    public function on_submission(int $sid): void
    {
        self::record('submission.submit', 'submission', $sid);
    }
    public function on_review_invited(int $rid, int $sid, int $reviewer_id): void
    {
        self::record('review.invited', 'review', $rid, ['submission_id' => $sid, 'reviewer_id' => $reviewer_id]);
    }
    public function on_review_accepted(int $rid): void  { self::record('review.accepted',  'review', $rid); }
    public function on_review_declined(int $rid): void  { self::record('review.declined',  'review', $rid); }
    public function on_review_submitted(int $rid): void { self::record('review.submitted', 'review', $rid); }
    public function on_article_published(int $sid, int $item_id): void
    {
        self::record('article.published', 'submission', $sid, ['tainacan_item_id' => $item_id]);
    }
    public function on_issue_published(int $iid, int $user_id): void
    {
        self::record('issue.published', 'issue', $iid, ['user_id' => $user_id]);
    }
    public function on_proof_approved(int $sid, int $user_id): void
    {
        self::record('proof.approved', 'submission', $sid, ['user_id' => $user_id]);
    }
    public function on_proof_changes(int $sid, int $user_id, string $note): void
    {
        self::record('proof.changes_requested', 'submission', $sid, ['user_id' => $user_id, 'note' => mb_substr($note, 0, 1000)]);
    }
    public function on_galley_added(int $sid, int $att_id, string $format): void
    {
        self::record('galley.added', 'submission', $sid, ['attachment_id' => $att_id, 'format' => $format]);
    }
    public function on_copyediting_uploaded(int $sid, int $att_id, int $user_id, string $role): void
    {
        self::record('copyediting.uploaded', 'submission', $sid, ['attachment_id' => $att_id, 'user_id' => $user_id, 'role' => $role]);
    }

    private static function detect_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
            if (! empty($_SERVER[$h])) {
                $val = (string) $_SERVER[$h];
                if (str_contains($val, ',')) {
                    $val = trim(explode(',', $val)[0]);
                }
                return $val;
            }
        }
        return '';
    }
}
