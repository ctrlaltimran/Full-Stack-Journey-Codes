<?php
if (!defined('ABSPATH')) exit;

class SMD_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_smd_save_client', [__CLASS__, 'save_client']);
        add_action('admin_post_smd_save_plan', [__CLASS__, 'save_plan']);
        add_action('admin_post_smd_generate_report', [__CLASS__, 'generate_report']);
        add_action('wp_ajax_smd_start_timer', [__CLASS__, 'ajax_start_timer']);
        add_action('wp_ajax_smd_stop_timer', [__CLASS__, 'ajax_stop_timer']);
        add_action('wp_ajax_smd_add_auto_log', [__CLASS__, 'ajax_add_auto_log']);
    }

    public static function menu() {
        add_menu_page(
            'Maintenance Dashboard',
            'Maintenance',
            'manage_options',
            'smd_dashboard',
            [__CLASS__, 'page_dashboard'],
            'dashicons-shield',
            56
        );
        add_submenu_page('smd_dashboard', 'Clients', 'Clients', 'manage_options', 'smd_clients', [__CLASS__, 'page_clients']);
        add_submenu_page('smd_dashboard', 'Plans', 'Plans', 'manage_options', 'smd_plans', [__CLASS__, 'page_plans']);
        add_submenu_page('smd_dashboard', 'Logs', 'Logs', 'manage_options', 'smd_logs', [__CLASS__, 'page_logs']);
        add_submenu_page('smd_dashboard', 'Reports', 'Reports', 'manage_options', 'smd_reports', [__CLASS__, 'page_reports']);
        add_submenu_page('smd_dashboard', 'Settings', 'Settings', 'manage_options', 'smd_settings', [__CLASS__, 'page_settings']);
    }

    private static function header($title, $subtitle='') {
        echo '<div class="wrap smd-wrap">';
        echo '<h1 class="smd-title">' . esc_html($title) . '</h1>';
        if ($subtitle) echo '<p class="smd-subtitle">' . esc_html($subtitle) . '</p>';
    }

    private static function footer() {
        echo '</div>';
    }

    public static function page_dashboard() {
        self::header('Maintenance Dashboard', 'Quick overview of clients and active work sessions.');
        $clients = SMD_DB::list_clients();
        $active_count = 0;
        foreach ($clients as $c) {
            $active = SMD_DB::get_active_session_for_client((int)$c['id']);
            if ($active) $active_count++;
        }
        echo '<div class="smd-grid">';
        echo '<div class="smd-card"><div class="smd-card__label">Clients</div><div class="smd-card__value">'.count($clients).'</div></div>';
        echo '<div class="smd-card"><div class="smd-card__label">Active Timers</div><div class="smd-card__value">'.$active_count.'</div></div>';
        echo '<div class="smd-card"><div class="smd-card__label">Plans</div><div class="smd-card__value">'.count(SMD_DB::get_plans(false)).'</div></div>';
        echo '</div>';
        echo '<p class="smd-hint">Tip: Use the Clients page to start and stop timers, and to assign plans.</p>';
        self::footer();
    }

    public static function page_clients() {
        self::header('Clients', 'Create clients, assign plans, and track work time.');
        $plans = SMD_DB::get_plans(true);
        $clients = SMD_DB::list_clients();

        // Create client form
        echo '<div class="smd-split">';
        echo '<div class="smd-card smd-card--pad">';
        echo '<h2>Create client</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('smd_save_client');
        echo '<input type="hidden" name="action" value="smd_save_client" />';
        echo '<div class="smd-field"><label>Name</label><input name="display_name" type="text" required></div>';
        echo '<div class="smd-field"><label>Email</label><input name="user_email" type="email" required></div>';
        echo '<div class="smd-field"><label>Password</label><input name="user_pass" type="text" placeholder="Leave blank to auto-generate"></div>';
        echo '<div class="smd-field"><label>Site name</label><input name="site_name" type="text"></div>';
        echo '<div class="smd-field"><label>Site URL</label><input name="site_url" type="url" placeholder="https://"></div>';
        echo '<div class="smd-field"><label>Plan</label><select name="plan_id">';
        echo '<option value="">Select</option>';
        foreach ($plans as $p) echo '<option value="'.(int)$p['id'].'">'.esc_html($p['name']).' ('.esc_html($p['monthly_hours']).'h/mo)</option>';
        echo '</select></div>';
        echo '<div class="smd-field"><label>Status</label><select name="status"><option value="active">Active</option><option value="pending">Pending</option><option value="paused">Paused</option></select></div>';
        echo '<button class="button button-primary">Create client</button>';
        echo '</form>';
        echo '</div>';

        // Client list
        echo '<div class="smd-card smd-card--pad">';
        echo '<h2>All clients</h2>';
        echo '<table class="widefat striped smd-table"><thead><tr>
                <th>Client</th><th>Site</th><th>Plan</th><th>Status</th><th>Health</th><th>Timer</th><th>Actions</th>
              </tr></thead><tbody>';
        foreach ($clients as $c) {
            $plan = $c['plan_id'] ? SMD_DB::get_plan((int)$c['plan_id']) : null;
            $active = SMD_DB::get_active_session_for_client((int)$c['id']);
            $timer_html = $active
                ? '<span class="smd-badge smd-badge--warn">Running</span> <small>since '.esc_html(date_i18n('M j, H:i', strtotime($active['started_at']))).'</small>'
                : '<span class="smd-badge smd-badge--ok">Idle</span>';

            echo '<tr>';
            echo '<td><strong>'.esc_html($c['display_name'] ?: $c['user_email']).'</strong><br><small>'.esc_html($c['user_email']).'</small></td>';
            echo '<td>'.($c['site_url'] ? '<a href="'.esc_url($c['site_url']).'" target="_blank" rel="noopener">'.esc_html($c['site_name'] ?: $c['site_url']).'</a>' : '<em>—</em>').'</td>';
            echo '<td>'.esc_html($plan['name'] ?? '—').'</td>';
            echo '<td>'.esc_html($c['status']).'</td>';
            echo '<td>'.self::health_badge($c['health_status']).'</td>';
            echo '<td>'.$timer_html.'</td>';
            echo '<td>
                <button class="button smd-start" data-client="'.(int)$c['id'].'">Start</button>
                <button class="button smd-stop" data-client="'.(int)$c['id'].'" '.(!$active?'disabled':'').'>Stop</button>
                <a class="button" href="'.esc_url(admin_url('admin.php?page=smd_logs&client_id='.(int)$c['id'])).'">Logs</a>
              </td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="smd-modal" id="smdTimerModal" aria-hidden="true">
                <div class="smd-modal__panel">
                  <div class="smd-modal__head">
                    <strong>Start timer</strong>
                    <button class="smd-modal__close" type="button">×</button>
                  </div>
                  <div class="smd-modal__body">
                    <div class="smd-field"><label>Category</label>
                      <select id="smd_category">
                        <option>Core update</option>
                        <option>Plugin update</option>
                        <option>Theme update</option>
                        <option>Bug fix</option>
                        <option>Content change</option>
                        <option>Security scan</option>
                        <option>Backup check</option>
                        <option>Performance work</option>
                        <option>Other</option>
                      </select>
                    </div>
                    <div class="smd-field"><label>Short description</label>
                      <textarea id="smd_desc" rows="4" placeholder="What are you doing?"></textarea>
                    </div>
                    <p class="smd-hint">When you stop the timer, a log entry is created automatically for the client.</p>
                  </div>
                  <div class="smd-modal__foot">
                    <button class="button button-primary" id="smdTimerStartConfirm">Start</button>
                  </div>
                </div>
              </div>';
        echo '</div>'; // card
        echo '</div>'; // split

        self::footer();
    }

    public static function page_plans() {
        self::header('Plans', 'Manage your maintenance packages and included hours.');
        $plans = SMD_DB::get_plans(false);
        echo '<div class="smd-card smd-card--pad">';
        echo '<h2>Add plan</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('smd_save_plan');
        echo '<input type="hidden" name="action" value="smd_save_plan" />';
        echo '<div class="smd-grid2">';
        echo '<div class="smd-field"><label>Plan name</label><input name="name" type="text" required></div>';
        echo '<div class="smd-field"><label>Monthly hours</label><input name="monthly_hours" type="number" step="0.25" min="0" required></div>';
        echo '<div class="smd-field"><label>Price (optional)</label><input name="price" type="text" placeholder="$199/mo"></div>';
        echo '<div class="smd-field"><label>Active</label><select name="is_active"><option value="1">Yes</option><option value="0">No</option></select></div>';
        echo '</div>';
        echo '<div class="smd-field"><label>Features (one per line)</label><textarea name="features" rows="4"></textarea></div>';
        echo '<button class="button button-primary">Save plan</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="smd-card smd-card--pad">';
        echo '<h2>Existing plans</h2>';
        echo '<table class="widefat striped smd-table"><thead><tr><th>Name</th><th>Hours/mo</th><th>Price</th><th>Status</th></tr></thead><tbody>';
        foreach ($plans as $p) {
            echo '<tr>';
            echo '<td><strong>'.esc_html($p['name']).'</strong><br><small>'.nl2br(esc_html(trim((string)$p['features']))).'</small></td>';
            echo '<td>'.esc_html($p['monthly_hours']).'</td>';
            echo '<td>'.esc_html($p['price']).'</td>';
            echo '<td>'.($p['is_active'] ? '<span class="smd-badge smd-badge--ok">Active</span>' : '<span class="smd-badge">Inactive</span>').'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        self::footer();
    }

    public static function page_logs() {
        $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
        self::header('Logs', 'All client work logs (time-tracked and automated).');
        $clients = SMD_DB::list_clients();

        echo '<div class="smd-card smd-card--pad">';
        echo '<form method="get"><input type="hidden" name="page" value="smd_logs" />';
        echo '<div class="smd-grid2">';
        echo '<div class="smd-field"><label>Client</label><select name="client_id"><option value="0">All</option>';
        foreach ($clients as $c) {
            $sel = $client_id === (int)$c['id'] ? 'selected' : '';
            echo '<option value="'.(int)$c['id'].'" '.$sel.'>'.esc_html($c['display_name'] ?: $c['user_email']).'</option>';
        }
        echo '</select></div>';
        echo '<div class="smd-field"><label>&nbsp;</label><button class="button">Filter</button></div>';
        echo '</div></form>';

        global $wpdb;
        $t = SMD_DB::tables();
        if ($client_id) {
            $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['logs']} WHERE client_id=%d ORDER BY task_date DESC, id DESC LIMIT 200", $client_id), ARRAY_A);
        } else {
            $logs = $wpdb->get_results("SELECT * FROM {$t['logs']} ORDER BY task_date DESC, id DESC LIMIT 200", ARRAY_A);
        }

        echo '<table class="widefat striped smd-table"><thead><tr><th>Date</th><th>Client</th><th>Task</th><th>Result</th><th>Minutes</th><th>Type</th></tr></thead><tbody>';
        foreach ($logs as $l) {
            $c = SMD_DB::get_client((int)$l['client_id']);
            $u = $c ? get_user_by('id', (int)$c['user_id']) : null;
            echo '<tr>';
            echo '<td>'.esc_html(date_i18n('M j, Y', strtotime($l['task_date']))).'</td>';
            echo '<td>'.esc_html($u ? ($u->display_name ?: $u->user_email) : '#'.$l['client_id']).'</td>';
            echo '<td><strong>'.esc_html($l['category']).'</strong><br><small>'.esc_html($l['task_summary']).'</small></td>';
            echo '<td>'.esc_html($l['result']).'</td>';
            echo '<td>'.esc_html($l['minutes']).'</td>';
            echo '<td>'.($l['is_automated'] ? '<span class="smd-badge">Automated</span>' : '<span class="smd-badge smd-badge--ok">Manual</span>').'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        self::footer();
    }

    public static function page_reports() {
        self::header('Reports', 'Generate client monthly summaries (print-ready and CSV export).');
        $clients = SMD_DB::list_clients();
        $ym = isset($_GET['ym']) ? sanitize_text_field($_GET['ym']) : date('Y-m');
        $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

        echo '<div class="smd-card smd-card--pad">';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('smd_generate_report');
        echo '<input type="hidden" name="action" value="smd_generate_report" />';
        echo '<div class="smd-grid2">';
        echo '<div class="smd-field"><label>Client</label><select name="client_id" required><option value="">Select</option>';
        foreach ($clients as $c) echo '<option value="'.(int)$c['id'].'" '.selected($client_id, (int)$c['id'], false).'>'.esc_html($c['display_name'] ?: $c['user_email']).'</option>';
        echo '</select></div>';
        echo '<div class="smd-field"><label>Month</label><input name="ym" type="month" value="'.esc_attr($ym).'" required></div>';
        echo '</div>';
        echo '<p class="smd-hint">HTML report is print-ready, clients can “Print to PDF”. CSV is for your records.</p>';
        echo '<button class="button button-primary">Generate</button>';
        echo '</form>';
        echo '</div>';

        self::footer();
    }

    public static function page_settings() {
        self::header('Settings', 'Configure the client dashboard page for redirects.');
        if (isset($_POST['smd_settings_nonce']) && wp_verify_nonce($_POST['smd_settings_nonce'], 'smd_save_settings') && current_user_can('manage_options')) {
            update_option('smd_dashboard_page_id', (int)($_POST['dashboard_page_id'] ?? 0));
            echo '<div class="notice notice-success"><p>Saved.</p></div>';
        }
        $dashboard_page_id = (int) get_option('smd_dashboard_page_id', 0);
        echo '<div class="smd-card smd-card--pad">';
        echo '<form method="post">';
        wp_nonce_field('smd_save_settings', 'smd_settings_nonce');
        echo '<div class="smd-field"><label>Client Dashboard Page</label>';
        wp_dropdown_pages(['name'=>'dashboard_page_id','selected'=>$dashboard_page_id,'show_option_none'=>'Select a page']);
        echo '</div>';
        echo '<button class="button button-primary">Save</button>';
        echo '</form>';
        echo '<p class="smd-hint">Create a page with shortcode <code>[smd_dashboard]</code> and set it here.</p>';
        echo '</div>';
        self::footer();
    }

    public static function save_client() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('smd_save_client');

        $email = sanitize_email($_POST['user_email'] ?? '');
        $display = sanitize_text_field($_POST['display_name'] ?? '');
        $pass = (string) ($_POST['user_pass'] ?? '');
        if (!$email) wp_redirect(admin_url('admin.php?page=smd_clients')); exit;

        $user_id = email_exists($email);
        if (!$user_id) {
            if (!$pass) $pass = wp_generate_password(12, true, true);
            $user_id = wp_create_user($email, $pass, $email);
            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
            }
            wp_update_user(['ID'=>$user_id,'display_name'=>$display]);
            $u = get_user_by('id', $user_id);
            if ($u) $u->set_role(SMD_Auth::ROLE_CLIENT);
        }

        SMD_DB::upsert_client($user_id, [
            'plan_id' => (int)($_POST['plan_id'] ?? 0),
            'site_name' => sanitize_text_field($_POST['site_name'] ?? ''),
            'site_url' => esc_url_raw($_POST['site_url'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
        ]);

        wp_redirect(admin_url('admin.php?page=smd_clients&created=1'));
        exit;
    }

    public static function save_plan() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('smd_save_plan');
        SMD_DB::insert_plan([
            'name' => $_POST['name'] ?? '',
            'monthly_hours' => $_POST['monthly_hours'] ?? 0,
            'price' => $_POST['price'] ?? '',
            'features' => $_POST['features'] ?? '',
            'is_active' => (int)($_POST['is_active'] ?? 1),
        ]);
        wp_redirect(admin_url('admin.php?page=smd_plans&saved=1'));
        exit;
    }

    public static function generate_report() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('smd_generate_report');

        $client_id = (int)($_POST['client_id'] ?? 0);
        $ym = sanitize_text_field($_POST['ym'] ?? date('Y-m'));
        if (!$client_id) wp_redirect(admin_url('admin.php?page=smd_reports')); exit;

        $url = add_query_arg([
            'smd_action' => 'report',
            'client_id' => $client_id,
            'ym' => $ym,
            'format' => 'html',
        ], home_url('/'));

        wp_redirect($url);
        exit;
    }

    public static function ajax_start_timer() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
        check_ajax_referer('smd_admin_nonce', 'nonce');

        $client_id = (int)($_POST['client_id'] ?? 0);
        $category = sanitize_text_field($_POST['category'] ?? 'Other');
        $desc = wp_kses_post($_POST['description'] ?? '');

        $res = SMD_DB::start_session($client_id, get_current_user_id(), $category, $desc);
        if (is_wp_error($res)) {
            wp_send_json_error(['message'=>$res->get_error_message()], 400);
        }
        wp_send_json_success(['session_id'=>$res]);
    }

    public static function ajax_stop_timer() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
        check_ajax_referer('smd_admin_nonce', 'nonce');

        $client_id = (int)($_POST['client_id'] ?? 0);
        $result = sanitize_text_field($_POST['result'] ?? 'success');
        $summary = sanitize_text_field($_POST['task_summary'] ?? '');

        $active = SMD_DB::get_active_session_for_client($client_id);
        if (!$active) wp_send_json_error(['message'=>'No active session for this client.'], 400);

        $res = SMD_DB::stop_session((int)$active['id'], $result, $summary);
        if (is_wp_error($res)) {
            wp_send_json_error(['message'=>$res->get_error_message()], 400);
        }
        wp_send_json_success($res);
    }

    public static function ajax_add_auto_log() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
        check_ajax_referer('smd_admin_nonce', 'nonce');

        $client_id = (int)($_POST['client_id'] ?? 0);
        $category = sanitize_text_field($_POST['category'] ?? 'Automated');
        $summary = sanitize_text_field($_POST['summary'] ?? '');
        $result = sanitize_text_field($_POST['result'] ?? 'success');

        if (!$client_id || !$summary) wp_send_json_error(['message'=>'Missing fields.'], 400);

        $id = SMD_DB::add_automated_log($client_id, $category, $summary, $result);
        wp_send_json_success(['log_id'=>$id]);
    }

    private static function health_badge($status) {
        $status = $status ?: 'good';
        if ($status === 'critical') return '<span class="smd-badge smd-badge--bad">Critical</span>';
        if ($status === 'attention') return '<span class="smd-badge smd-badge--warn">Needs attention</span>';
        return '<span class="smd-badge smd-badge--ok">Good</span>';
    }
}
