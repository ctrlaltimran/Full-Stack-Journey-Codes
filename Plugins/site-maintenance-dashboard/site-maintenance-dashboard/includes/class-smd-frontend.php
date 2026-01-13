<?php
if (!defined('ABSPATH')) exit;

class SMD_Frontend {
    public static function init() {
        add_shortcode('smd_signup', [__CLASS__, 'shortcode_signup']);
        add_shortcode('smd_login', [__CLASS__, 'shortcode_login']);
        add_shortcode('smd_dashboard', [__CLASS__, 'shortcode_dashboard']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_post_nopriv_smd_signup', [__CLASS__, 'handle_signup']);
        add_action('admin_post_nopriv_smd_logout', [__CLASS__, 'handle_logout']);
        add_action('init', [__CLASS__, 'register_report_handler']);
    }

    public static function enqueue() {
        if (is_singular()) {
            wp_enqueue_style('smd-frontend');
            wp_enqueue_script('smd-frontend');
        }
    }

    public static function shortcode_login() {
        if (is_user_logged_in()) {
            return self::render_notice('You are already logged in.', 'success');
        }

        $out = '<div class="smd-app">';
        $out .= '<div class="smd-card">';
        $out .= '<h2 class="smd-h2">Client login</h2>';
        $out .= wp_login_form([
            'echo' => false,
            'remember' => true,
            'form_id' => 'smd-loginform',
            'label_username' => 'Email',
            'label_password' => 'Password',
            'label_remember' => 'Remember me',
            'label_log_in' => 'Login',
        ]);
        $out .= '</div></div>';
        return $out;
    }

    public static function shortcode_signup() {
        if (is_user_logged_in()) return self::render_notice('You are already logged in.', 'success');

        $out = '<div class="smd-app">';
        if (!empty($_GET['smd_signup']) && $_GET['smd_signup'] === 'success') {
            $out .= self::render_notice('Account created. You can login now.', 'success');
        } elseif (!empty($_GET['smd_error'])) {
            $out .= self::render_notice(esc_html($_GET['smd_error']), 'error');
        }

        $out .= '<div class="smd-card">';
        $out .= '<h2 class="smd-h2">Create your client account</h2>';
        $out .= '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        $out .= '<input type="hidden" name="action" value="smd_signup" />';
        $out .= wp_nonce_field('smd_signup', 'smd_nonce', true, false);
        $out .= '<div class="smd-grid2">';
        $out .= '<div class="smd-field"><label>Full name</label><input name="display_name" type="text" required></div>';
        $out .= '<div class="smd-field"><label>Email</label><input name="user_email" type="email" required></div>';
        $out .= '<div class="smd-field"><label>Password</label><input name="user_pass" type="password" required></div>';
        $out .= '<div class="smd-field"><label>Website URL</label><input name="site_url" type="url" placeholder="https://" required></div>';
        $out .= '</div>';
        $out .= '<button class="smd-btn smd-btn--primary" type="submit">Create account</button>';
        $out .= '</form>';
        $out .= '<p class="smd-muted">Already have an account? Use the login page.</p>';
        $out .= '</div></div>';
        return $out;
    }

    public static function handle_signup() {
        if (!isset($_POST['smd_nonce']) || !wp_verify_nonce($_POST['smd_nonce'], 'smd_signup')) {
            wp_safe_redirect(add_query_arg('smd_error', rawurlencode('Invalid request.'), wp_get_referer() ?: home_url('/')));
            exit;
        }

        $email = sanitize_email($_POST['user_email'] ?? '');
        $pass = (string) ($_POST['user_pass'] ?? '');
        $name = sanitize_text_field($_POST['display_name'] ?? '');
        $site_url = esc_url_raw($_POST['site_url'] ?? '');

        if (!$email || !$pass || !$site_url) {
            wp_safe_redirect(add_query_arg('smd_error', rawurlencode('Please fill in all fields.'), wp_get_referer() ?: home_url('/')));
            exit;
        }
        if (email_exists($email)) {
            wp_safe_redirect(add_query_arg('smd_error', rawurlencode('Email already exists.'), wp_get_referer() ?: home_url('/')));
            exit;
        }

        $user_id = wp_create_user($email, $pass, $email);
        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('smd_error', rawurlencode($user_id->get_error_message()), wp_get_referer() ?: home_url('/')));
            exit;
        }

        wp_update_user(['ID'=>$user_id,'display_name'=>$name]);
        $u = get_user_by('id', $user_id);
        if ($u) $u->set_role(SMD_Auth::ROLE_CLIENT);

        // assign default plan (first active plan)
        $plans = SMD_DB::get_plans(true);
        $default_plan = $plans ? (int)$plans[0]['id'] : null;

        SMD_DB::upsert_client($user_id, [
            'plan_id' => $default_plan,
            'site_name' => parse_url($site_url, PHP_URL_HOST),
            'site_url' => $site_url,
            'status' => 'pending', // admin can switch to active
            'health_status' => 'attention',
        ]);

        $ref = wp_get_referer() ?: home_url('/');
        wp_safe_redirect(add_query_arg('smd_signup', 'success', $ref));
        exit;
    }

    public static function handle_logout() {
        wp_logout();
        wp_safe_redirect(home_url('/'));
        exit;
    }

    public static function shortcode_dashboard() {
        if (!is_user_logged_in()) {
            return self::render_notice('Please login to view your dashboard.', 'info');
        }

        // Admins can see a small shortcut panel
        if (current_user_can('manage_options')) {
            $out = '<div class="smd-app">';
            $out .= '<div class="smd-card"><h2 class="smd-h2">Admin shortcuts</h2>';
            $out .= '<a class="smd-btn smd-btn--primary" href="'.esc_url(admin_url('admin.php?page=smd_dashboard')).'">Open admin panel</a>';
            $out .= '</div></div>';
            return $out;
        }

        if (!SMD_Auth::is_client()) return self::render_notice('You do not have access to this dashboard.', 'error');

        $client = SMD_DB::get_client_by_user(get_current_user_id());
        if (!$client) return self::render_notice('Client profile not found. Please contact support.', 'error');

        $plan = $client['plan_id'] ? SMD_DB::get_plan((int)$client['plan_id']) : null;
        $ym = date('Y-m');
        $mins_used = SMD_DB::month_minutes((int)$client['id'], $ym);
        $hours_used = round($mins_used / 60, 2);
        $hours_included = $plan ? floatval($plan['monthly_hours']) : 0;
        $remaining = max(0, round($hours_included - $hours_used, 2));

        $health = $client['health_status'] ?: 'good';
        $health_label = $health === 'critical' ? 'Critical' : ($health === 'attention' ? 'Needs attention' : 'Good');
        $health_class = $health === 'critical' ? 'bad' : ($health === 'attention' ? 'warn' : 'ok');

        $logs = SMD_DB::list_logs((int)$client['id'], 60);

        $out = '<div class="smd-app">';
        $out .= '<div class="smd-topbar">';
        $out .= '<div><div class="smd-kicker">Maintenance dashboard</div><h1 class="smd-h1">'.esc_html($client['site_name'] ?: 'Your website').'</h1>';
        $out .= '<div class="smd-links">'.($client['site_url'] ? '<a href="'.esc_url($client['site_url']).'" target="_blank" rel="noopener">Open website</a>' : '').'</div></div>';
        $out .= '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="smd_logout" /><button class="smd-linkbtn" type="submit">Logout</button></form>';
        $out .= '</div>';

        $out .= '<div class="smd-grid3">';
        $out .= self::card_stat('Plan', esc_html($plan['name'] ?? '—'), 'Included: '.esc_html($hours_included).'h/mo');
        $out .= self::card_stat('Status', esc_html(ucfirst($client['status'])), 'Renewals handled by your developer');
        $out .= self::card_stat('Site health', '<span class="smd-pill smd-pill--'.$health_class.'">'.esc_html($health_label).'</span>', 'Quick check indicator');
        $out .= '</div>';

        $out .= '<div class="smd-grid2">';
        $out .= '<div class="smd-card"><h2 class="smd-h2">Hours this month</h2>';
        $out .= '<div class="smd-hours">
                    <div><div class="smd-kicker">Used</div><div class="smd-big">'.esc_html($hours_used).'h</div></div>
                    <div><div class="smd-kicker">Remaining</div><div class="smd-big">'.esc_html($remaining).'h</div></div>
                 </div>';
        $out .= '<p class="smd-muted">Manual work is time-tracked. Automated tasks show 0 hours.</p>';
        $out .= '</div>';

        $out .= '<div class="smd-card"><h2 class="smd-h2">Maintenance schedule</h2>';
        $out .= '<ul class="smd-list">';
        $out .= '<li><strong>Last maintenance:</strong> '.($client['last_maint_date'] ? esc_html(date_i18n('M j, Y H:i', strtotime($client['last_maint_date']))) : '—').'</li>';
        $out .= '<li><strong>Next scheduled check:</strong> '.($client['next_check_date'] ? esc_html(date_i18n('M j, Y H:i', strtotime($client['next_check_date']))) : '—').'</li>';
        $out .= '</ul>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="smd-card"><div class="smd-row">';
        $out .= '<h2 class="smd-h2">Work log</h2>';
        $out .= '<div class="smd-actions">';
        $out .= '<a class="smd-btn" href="'.esc_url(add_query_arg(['smd_action'=>'report','client_id'=>(int)$client['id'],'ym'=>$ym,'format'=>'html'], home_url('/'))).'" target="_blank" rel="noopener">View monthly report</a>';
        $out .= '<a class="smd-btn" href="'.esc_url(add_query_arg(['smd_action'=>'report','client_id'=>(int)$client['id'],'ym'=>$ym,'format'=>'csv'], home_url('/'))).'" target="_blank" rel="noopener">Download CSV</a>';
        $out .= '</div></div>';

        $out .= '<div class="smd-tablewrap"><table class="smd-table"><thead><tr><th>Date</th><th>Task</th><th>Result</th><th>Time</th></tr></thead><tbody>';
        foreach ($logs as $l) {
            $time = $l['is_automated'] ? 'Automated' : (round(((int)$l['minutes'])/60, 2).'h');
            $out .= '<tr>';
            $out .= '<td>'.esc_html(date_i18n('M j, Y', strtotime($l['task_date']))).'</td>';
            $out .= '<td><strong>'.esc_html($l['category']).'</strong><div class="smd-muted">'.esc_html($l['task_summary']).'</div></td>';
            $out .= '<td>'.esc_html(ucfirst($l['result'])).'</td>';
            $out .= '<td>'.esc_html($time).'</td>';
            $out .= '</tr>';
        }
        if (!$logs) {
            $out .= '<tr><td colspan="4" class="smd-muted">No work logged yet.</td></tr>';
        }
        $out .= '</tbody></table></div>';
        $out .= '</div>';

        $out .= '</div>';
        return $out;
    }

    private static function card_stat($label, $value, $hint='') {
        $out = '<div class="smd-card"><div class="smd-kicker">'.esc_html($label).'</div>';
        $out .= '<div class="smd-stat">'.$value.'</div>';
        if ($hint) $out .= '<div class="smd-muted">'.esc_html($hint).'</div>';
        $out .= '</div>';
        return $out;
    }

    private static function render_notice($msg, $type='info') {
        $cls = 'smd-notice smd-notice--' . esc_attr($type);
        return '<div class="smd-app"><div class="'.$cls.'">'.esc_html($msg).'</div></div>';
    }

    public static function register_report_handler() {
        add_action('init', function () {
            if (!isset($_GET['smd_action']) || $_GET['smd_action'] !== 'report') return;

            $client_id = (int)($_GET['client_id'] ?? 0);
            $ym = sanitize_text_field($_GET['ym'] ?? date('Y-m'));
            $format = sanitize_text_field($_GET['format'] ?? 'html');

            if (!$client_id) wp_die('Missing client.');

            // permission: client can only access own report, admin can access all
            if (current_user_can('manage_options')) {
                // ok
            } else {
                if (!SMD_Auth::is_client()) wp_die('Forbidden');
                $client = SMD_DB::get_client_by_user(get_current_user_id());
                if (!$client || (int)$client['id'] !== $client_id) wp_die('Forbidden');
            }

            SMD_Reports::output_monthly_report($client_id, $ym, $format);
            exit;
        });
    }
}
