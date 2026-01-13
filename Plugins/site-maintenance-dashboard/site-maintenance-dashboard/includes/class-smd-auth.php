<?php
if (!defined('ABSPATH')) exit;

class SMD_Auth {
    const ROLE_CLIENT = 'smd_client';

    public static function init() {
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);
        add_action('init', [__CLASS__, 'register_query_vars']);
        add_action('template_redirect', [__CLASS__, 'handle_report_routes']);
    }

    public static function add_roles() {
        add_role(self::ROLE_CLIENT, 'Maintenance Client', [
            'read' => true,
        ]);
    }

    public static function is_client() {
        return is_user_logged_in() && current_user_can('read') && in_array(self::ROLE_CLIENT, (array) wp_get_current_user()->roles, true);
    }

    public static function login_redirect($redirect_to, $requested, $user) {
        if (is_wp_error($user) || !is_object($user)) return $redirect_to;
        $roles = isset($user->roles) ? (array)$user->roles : [];
        if (in_array(self::ROLE_CLIENT, $roles, true)) {
            $page_id = (int) get_option('smd_dashboard_page_id', 0);
            if ($page_id) return get_permalink($page_id);
        }
        return $redirect_to;
    }

    public static function register_query_vars() {
        add_rewrite_tag('%smd_report%', '([0-9]+)');
        add_rewrite_tag('%smd_format%', '([a-zA-Z0-9_-]+)');
    }

    public static function handle_report_routes() {
        $report_id = get_query_var('smd_report');
        if (!$report_id) return;
        // reserved for future pretty routes; currently handled via admin-post for simplicity.
    }
}
