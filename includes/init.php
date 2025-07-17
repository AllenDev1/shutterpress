<?php
// init.php

function shutterpress_create_custom_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $plans = $wpdb->prefix . 'shutterpress_subscription_plans';
    $quotas = $wpdb->prefix . 'shutterpress_user_quotas';
    $logs   = $wpdb->prefix . 'shutterpress_download_logs';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE $plans (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        total_downloads INT,
        duration_days INT,
        woocommerce_product_id BIGINT
    ) $charset_collate;");

    dbDelta("CREATE TABLE $quotas (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        plan_id BIGINT,
        downloads_remaining INT,
        downloads_used INT DEFAULT 0,
        expiration_date DATETIME
    ) $charset_collate;");

    dbDelta("CREATE TABLE $logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        product_id BIGINT,
        download_time DATETIME
    ) $charset_collate;");
}

function shutterpress_add_endpoints() {
    add_rewrite_endpoint('shutterpress-subscription', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('shutterpress-downloads', EP_ROOT | EP_PAGES);
}
add_action('init', 'shutterpress_add_endpoints');

function shutterpress_activate() {
    shutterpress_create_custom_tables();
    shutterpress_add_endpoints();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'shutterpress_activate');

// Handle order completion
add_action('woocommerce_order_status_completed', function ($order_id) {
    include_once plugin_dir_path(__FILE__) . 'download-handler.php';
    shutterpress_handle_order_completed($order_id);
});

// Shortcode support logic
include_once plugin_dir_path(__FILE__) . 'download-handler.php';
