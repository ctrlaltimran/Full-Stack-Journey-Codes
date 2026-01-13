<?php
/**
 * Plugin Name: Site Maintenance Dashboard (Client + Admin)
 * Description: A lightweight maintenance-package dashboard for clients with time tracking, work logs, and monthly reports, plus an admin panel to manage clients and plans.
 * Version: 0.1.0
 * Author: Your Name
 * License: GPLv2 or later
 * Text Domain: site-maintenance-dashboard
 */

if (!defined('ABSPATH')) exit;

define('SMD_VERSION', '0.1.0');
define('SMD_PATH', plugin_dir_path(__FILE__));
define('SMD_URL', plugin_dir_url(__FILE__));

require_once SMD_PATH . 'includes/class-smd-plugin.php';

register_activation_hook(__FILE__, ['SMD_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SMD_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    SMD_Plugin::instance();
});
