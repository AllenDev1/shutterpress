<?php
/**
 * Plugin Name: ShutterPress
 * Description: Shutterstock-style image download system using WooCommerce, Dokan, and custom quotas.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure the path always ends in a slash
define('SHUTTERPRESS_PATH', trailingslashit(plugin_dir_path(__FILE__)));

require_once SHUTTERPRESS_PATH . 'includes/init.php';
require_once SHUTTERPRESS_PATH . 'admin/admin-init.php';
require_once SHUTTERPRESS_PATH . 'public/public-init.php';
