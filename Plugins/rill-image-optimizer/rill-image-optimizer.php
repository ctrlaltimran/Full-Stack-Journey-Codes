<?php
/**
 * Plugin Name: Rill Image Optimizer (Free)
 * Description: Safe image optimization for JPEG/PNG/WebP with a dashboard and bulk optimizer. Replaces files only if the new one is smaller.
 * Version: 1.1.0
 * Author: ctrlaltimran.com
 * Author URI: https://ctrlaltimran.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

define('RILL_IMGOPT_VERSION', '1.1.0');
define('RILL_IMGOPT_SLUG', 'rill-image-optimizer');
define('RILL_IMGOPT_PATH', plugin_dir_path(__FILE__));
define('RILL_IMGOPT_URL', plugin_dir_url(__FILE__));

require_once RILL_IMGOPT_PATH . 'includes/class-rill-imgopt.php';

register_activation_hook(__FILE__, ['Rill_Image_Optimizer', 'activate']);

add_action('plugins_loaded', function () {
    Rill_Image_Optimizer::instance();
});
