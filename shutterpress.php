<?php
/**
 * Plugin Name: ShutterPress
 * Description: Shutterstock-style image download system using WooCommerce, Dokan, and custom quotas.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure the path always ends in a slash
define('SHUTTERPRESS_PATH', trailingslashit(plugin_dir_path(__FILE__)));



add_action('wp_enqueue_scripts', 'shutterpress_enqueue_styles');

function shutterpress_enqueue_styles() {
    wp_enqueue_style(
        'shutterpress-style',
        plugin_dir_url(__FILE__) . 'assets/style.css',
        [],
        '1.0.0'
    );
}



require_once SHUTTERPRESS_PATH . 'includes/init.php';
require_once SHUTTERPRESS_PATH . 'admin/admin-init.php';
require_once SHUTTERPRESS_PATH . 'public/public-init.php';
