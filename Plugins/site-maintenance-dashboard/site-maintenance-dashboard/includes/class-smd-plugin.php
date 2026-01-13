<?php
if (!defined('ABSPATH')) exit;

require_once SMD_PATH . 'includes/class-smd-db.php';
require_once SMD_PATH . 'includes/class-smd-auth.php';
require_once SMD_PATH . 'includes/class-smd-admin.php';
require_once SMD_PATH . 'includes/class-smd-frontend.php';
require_once SMD_PATH . 'includes/class-smd-reports.php';

final class SMD_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    public static function activate() {
        SMD_DB::create_tables();
        SMD_Auth::add_roles();
        // Default plan (only if none)
        if (!SMD_DB::has_any_plans()) {
            SMD_DB::insert_plan([
                'name' => 'Basic',
                'monthly_hours' => 3.0,
                'price' => '',
                'features' => "Core/Plugin/Theme updates\nBackups & security scans\nSmall fixes (up to hours included)",
                'is_active' => 1,
            ]);
        }
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private function init() {
        SMD_Auth::init();
        SMD_Admin::init();
        SMD_Frontend::init();
        SMD_Reports::init();

        add_action('wp_enqueue_scripts', function () {
            wp_register_style('smd-frontend', SMD_URL . 'assets/css/frontend.css', [], SMD_VERSION);
            wp_register_script('smd-frontend', SMD_URL . 'assets/js/frontend.js', ['jquery'], SMD_VERSION, true);
        });

        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'smd_') === false) return;
            wp_enqueue_style('smd-admin', SMD_URL . 'assets/css/admin.css', [], SMD_VERSION);
            wp_enqueue_script('smd-admin', SMD_URL . 'assets/js/admin.js', ['jquery'], SMD_VERSION, true);
            wp_localize_script('smd-admin', 'SMD_ADMIN', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smd_admin_nonce'),
            ]);
        });
    }
}
