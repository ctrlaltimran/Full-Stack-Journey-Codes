<?php
if (!defined('ABSPATH')) exit;

class SMD_DB {
    public static function tables() {
        global $wpdb;
        $p = $wpdb->prefix . 'smd_';
        return [
            'plans' => $p . 'plans',
            'clients' => $p . 'clients',
            'sessions' => $p . 'sessions',
            'logs' => $p . 'logs',
        ];
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = self::tables();

        $sql_plans = "CREATE TABLE {$t['plans']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            monthly_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
            price VARCHAR(50) NULL,
            features TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset;";

        $sql_clients = "CREATE TABLE {$t['clients']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NULL,
            site_name VARCHAR(190) NULL,
            site_url VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_maint_date DATETIME NULL,
            next_check_date DATETIME NULL,
            health_status VARCHAR(30) NOT NULL DEFAULT 'good',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY plan_id (plan_id),
            KEY status (status),
            KEY health_status (health_status)
        ) $charset;";

        $sql_sessions = "CREATE TABLE {$t['sessions']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            admin_user_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(60) NOT NULL,
            description TEXT NULL,
            started_at DATETIME NOT NULL,
            ended_at DATETIME NULL,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'in_progress',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY admin_user_id (admin_user_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset;";

        $sql_logs = "CREATE TABLE {$t['logs']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            session_id BIGINT UNSIGNED NULL,
            task_date DATE NOT NULL,
            category VARCHAR(60) NOT NULL,
            task_summary VARCHAR(255) NOT NULL,
            result VARCHAR(60) NOT NULL DEFAULT 'success',
            minutes INT UNSIGNED NOT NULL DEFAULT 0,
            is_automated TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY task_date (task_date),
            KEY category (category)
        ) $charset;";

        dbDelta($sql_plans);
        dbDelta($sql_clients);
        dbDelta($sql_sessions);
        dbDelta($sql_logs);
    }

    public static function has_any_plans() {
        global $wpdb;
        $t = self::tables();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['plans']}");
        return $count > 0;
    }

    public static function insert_plan($data) {
        global $wpdb;
        $t = self::tables();
        $wpdb->insert($t['plans'], [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'monthly_hours' => floatval($data['monthly_hours'] ?? 0),
            'price' => sanitize_text_field($data['price'] ?? ''),
            'features' => wp_kses_post($data['features'] ?? ''),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function update_plan($id, $data) {
        global $wpdb;
        $t = self::tables();
        return $wpdb->update($t['plans'], [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'monthly_hours' => floatval($data['monthly_hours'] ?? 0),
            'price' => sanitize_text_field($data['price'] ?? ''),
            'features' => wp_kses_post($data['features'] ?? ''),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ], ['id' => (int)$id]);
    }

    public static function get_plans($active_only = false) {
        global $wpdb;
        $t = self::tables();
        if ($active_only) {
            return $wpdb->get_results("SELECT * FROM {$t['plans']} WHERE is_active=1 ORDER BY name ASC", ARRAY_A);
        }
        return $wpdb->get_results("SELECT * FROM {$t['plans']} ORDER BY is_active DESC, name ASC", ARRAY_A);
    }

    public static function get_plan($id) {
        global $wpdb;
        $t = self::tables();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['plans']} WHERE id=%d", (int)$id), ARRAY_A);
    }

    public static function upsert_client($user_id, $data) {
        global $wpdb;
        $t = self::tables();
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['clients']} WHERE user_id=%d", (int)$user_id));
        $payload = [
            'plan_id' => !empty($data['plan_id']) ? (int)$data['plan_id'] : null,
            'site_name' => sanitize_text_field($data['site_name'] ?? ''),
            'site_url' => esc_url_raw($data['site_url'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'last_maint_date' => !empty($data['last_maint_date']) ? sanitize_text_field($data['last_maint_date']) : null,
            'next_check_date' => !empty($data['next_check_date']) ? sanitize_text_field($data['next_check_date']) : null,
            'health_status' => sanitize_text_field($data['health_status'] ?? 'good'),
        ];
        if ($existing) {
            $wpdb->update($t['clients'], $payload, ['id' => (int)$existing]);
            return (int)$existing;
        }
        $payload['user_id'] = (int)$user_id;
        $wpdb->insert($t['clients'], $payload);
        return (int)$wpdb->insert_id;
    }

    public static function get_client_by_user($user_id) {
        global $wpdb;
        $t = self::tables();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['clients']} WHERE user_id=%d", (int)$user_id), ARRAY_A);
    }

    public static function get_client($client_id) {
        global $wpdb;
        $t = self::tables();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['clients']} WHERE id=%d", (int)$client_id), ARRAY_A);
    }

    public static function list_clients() {
        global $wpdb;
        $t = self::tables();
        $sql = "SELECT c.*, u.user_email, u.display_name
                FROM {$t['clients']} c
                LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
                ORDER BY c.created_at DESC";
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public static function get_active_session_for_client($client_id) {
        global $wpdb;
        $t = self::tables();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t['sessions']} WHERE client_id=%d AND status='in_progress' ORDER BY started_at DESC LIMIT 1",
            (int)$client_id
        ), ARRAY_A);
    }

    public static function start_session($client_id, $admin_user_id, $category, $description) {
        global $wpdb;
        $t = self::tables();

        // only one active session per client
        $active = self::get_active_session_for_client($client_id);
        if ($active) return new WP_Error('smd_active_session', 'This client already has an active session.');

        $wpdb->insert($t['sessions'], [
            'client_id' => (int)$client_id,
            'admin_user_id' => (int)$admin_user_id,
            'category' => sanitize_text_field($category),
            'description' => wp_kses_post($description),
            'started_at' => current_time('mysql'),
            'status' => 'in_progress',
        ]);
        return (int)$wpdb->insert_id;
    }

    public static function stop_session($session_id, $result = 'success', $task_summary = '') {
        global $wpdb;
        $t = self::tables();
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['sessions']} WHERE id=%d", (int)$session_id), ARRAY_A);
        if (!$session) return new WP_Error('smd_session_missing', 'Session not found.');
        if ($session['status'] !== 'in_progress') return new WP_Error('smd_session_closed', 'Session already closed.');

        $end = current_time('mysql');
        $started = strtotime($session['started_at']);
        $ended = strtotime($end);
        $mins = max(0, (int) round(($ended - $started) / 60));

        $wpdb->update($t['sessions'], [
            'ended_at' => $end,
            'duration_minutes' => $mins,
            'status' => 'completed',
        ], ['id' => (int)$session_id]);

        // create log entry
        $summary = $task_summary ? sanitize_text_field($task_summary) : wp_trim_words(wp_strip_all_tags($session['description']), 18, '…');
        if (!$summary) $summary = $session['category'];

        $wpdb->insert($t['logs'], [
            'client_id' => (int)$session['client_id'],
            'session_id' => (int)$session_id,
            'task_date' => current_time('Y-m-d'),
            'category' => sanitize_text_field($session['category']),
            'task_summary' => $summary,
            'result' => sanitize_text_field($result),
            'minutes' => $mins,
            'is_automated' => 0,
        ]);

        // update last maintenance date
        $wpdb->update($t['clients'], [
            'last_maint_date' => current_time('mysql'),
        ], ['id' => (int)$session['client_id']]);

        return [
            'minutes' => $mins,
            'ended_at' => $end,
        ];
    }

    public static function add_automated_log($client_id, $category, $summary, $result='success') {
        global $wpdb;
        $t = self::tables();
        $wpdb->insert($t['logs'], [
            'client_id' => (int)$client_id,
            'session_id' => null,
            'task_date' => current_time('Y-m-d'),
            'category' => sanitize_text_field($category),
            'task_summary' => sanitize_text_field($summary),
            'result' => sanitize_text_field($result),
            'minutes' => 0,
            'is_automated' => 1,
        ]);
        return (int)$wpdb->insert_id;
    }

    public static function list_logs($client_id, $limit = 100) {
        global $wpdb;
        $t = self::tables();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t['logs']} WHERE client_id=%d ORDER BY task_date DESC, id DESC LIMIT %d",
            (int)$client_id,
            (int)$limit
        ), ARRAY_A);
    }

    public static function month_minutes($client_id, $ym) {
        global $wpdb;
        $t = self::tables();
        $start = $ym . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));
        $mins = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(minutes),0) FROM {$t['logs']} WHERE client_id=%d AND task_date >= %s AND task_date < %s",
            (int)$client_id, $start, $end
        ));
        return $mins;
    }

    public static function month_counts($client_id, $ym) {
        global $wpdb;
        $t = self::tables();
        $start = $ym . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
               COUNT(*) as total_tasks,
               SUM(CASE WHEN is_automated=1 THEN 1 ELSE 0 END) as automated_tasks,
               SUM(CASE WHEN category LIKE 'Security%' THEN 1 ELSE 0 END) as security_tasks,
               SUM(CASE WHEN category LIKE 'Backup%' THEN 1 ELSE 0 END) as backup_tasks
             FROM {$t['logs']}
             WHERE client_id=%d AND task_date >= %s AND task_date < %s",
            (int)$client_id, $start, $end
        ), ARRAY_A);

        return $row ?: ['total_tasks'=>0,'automated_tasks'=>0,'security_tasks'=>0,'backup_tasks'=>0];
    }
}
