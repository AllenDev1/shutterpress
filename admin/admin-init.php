<?php

// Register ShutterPress admin menu
add_action('admin_menu', 'shutterpress_register_admin_menu');

function shutterpress_register_admin_menu()
{
    add_menu_page(
        'ShutterPress',
        'ShutterPress',
        'manage_options',
        'shutterpress',
        'shutterpress_render_create_plan_page',
        'dashicons-admin-generic',
        56
    );

    add_submenu_page(
        'shutterpress',
        'User Quotas',
        'User Quotas',
        'manage_options',
        'shutterpress_user_quotas',
        'shutterpress_render_user_quotas_page'
    );

    add_submenu_page(
        'shutterpress',
        'All Plans',
        'All Plans',
        'manage_options',
        'shutterpress_all_plans',
        'shutterpress_render_all_plans_page'
    );

    add_submenu_page(
        'shutterpress',
        'Download Logs',
        'Download Logs',
        'manage_options',
        'shutterpress_download_logs',
        'shutterpress_render_download_logs_page'
    );

    add_submenu_page(
        'shutterpress',
        'Watermark Settings',
        'Watermark Settings',
        'manage_options',
        'shutterpress-watermark',
        'shutterpress_render_watermark_settings_page'
    );

    add_submenu_page(
        'shutterpress',
        'ShutterPress Settings',
        'Settings',
        'manage_options',
        'shutterpress-settings',
        'shutterpress_settings_page_callback'
    );
}

// Include admin logic files
require_once plugin_dir_path(__FILE__) . 'plans.php';
require_once plugin_dir_path(__FILE__) . 'user-quotas.php';
require_once plugin_dir_path(__FILE__) . 'product-meta.php';
require_once plugin_dir_path(__FILE__) . 'settings.php';
require_once plugin_dir_path(__FILE__) . 'watermark-settings.php';

// Hooked callbacks for submenus
function shutterpress_render_download_logs_page()
{
    require_once plugin_dir_path(__FILE__) . 'download-logs.php';
}

// Remove the direct function call - it's now handled by the include
// The function shutterpress_render_watermark_settings_page() is now available from the include
?>