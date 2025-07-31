<?php
defined('ABSPATH') || exit;

// Hook: Create custom tables on plugin activation
register_activation_hook(__FILE__, 'shutterpress_activate_plugin');

function shutterpress_activate_plugin()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';
    $quotas_table = $wpdb->prefix . 'shutterpress_user_quotas';
    $logs_table = $wpdb->prefix . 'shutterpress_download_logs';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Create subscription plans table
    $sql1 = "CREATE TABLE $plans_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        plan_name VARCHAR(255) NOT NULL,
        quota INT DEFAULT 0,
        price DECIMAL(10,2) NOT NULL,
        billing_cycle ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
        is_unlimited TINYINT(1) DEFAULT 0,
        woocommerce_product_id BIGINT UNSIGNED,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Create user quotas table
    $sql2 = "CREATE TABLE $quotas_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        plan_id BIGINT UNSIGNED NOT NULL,
        quota_total INT DEFAULT 0,
        quota_used INT DEFAULT 0,
        is_unlimited TINYINT(1) DEFAULT 0,
        status ENUM('active', 'expired', 'cancelled', 'pending') DEFAULT 'active',
        quota_renewal_date DATE DEFAULT NULL,
        cancel_reason TEXT,
        cancelled_by VARCHAR(50),
        cancelled_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_download DATETIME DEFAULT NULL
    ) $charset_collate;";

    // Create download logs table - FIXED SYNTAX
    $sql3 = "CREATE TABLE $logs_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        download_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        download_type VARCHAR(50) DEFAULT 'free',
        ip_address VARCHAR(100),
        user_agent TEXT
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);

    shutterpress_create_plans_page();
}

function shutterpress_create_plans_page()
{
    $existing = get_pages([
        'post_type' => 'page',
        'post_status' => 'publish',
        'suppress_filters' => true,
    ]);

    foreach ($existing as $page) {
        if (has_shortcode($page->post_content, 'shutterpress_plans')) {
            update_option('shutterpress_plans_page_id', $page->ID);
            return;
        }
    }

    $page_id = wp_insert_post([
        'post_title' => 'Subscription Plans',
        'post_content' => '[shutterpress_plans]',
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);

    if ($page_id && !is_wp_error($page_id)) {
        update_option('shutterpress_plans_page_id', $page_id);
    }
}

add_action('woocommerce_order_status_completed', 'shutterpress_handle_subscription_purchase');

function shutterpress_handle_subscription_purchase($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order)
        return;

    $user_id = $order->get_user_id();
    if (!$user_id)
        return;

    global $wpdb;
    $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';
    $quotas_table = $wpdb->prefix . 'shutterpress_user_quotas';

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $plans_table WHERE woocommerce_product_id = %d",
            $product_id
        ));

        if ($plan) {
            $is_unlimited = (int) $plan->is_unlimited;
            $quota_total = $is_unlimited ? 0 : (int) $plan->quota;

            $wpdb->insert($quotas_table, [
                'user_id' => $user_id,
                'plan_id' => $plan->id,
                'quota_total' => $quota_total,
                'quota_used' => 0,
                'is_unlimited' => $is_unlimited,
                'status' => 'active',
                'quota_renewal_date' => date('Y-m-d', strtotime('+1 month')),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
        }
    }
}

// ========================================
// AUTOMATIC SUBSCRIPTION EXPIRATION SYSTEM
// ========================================

// Schedule expiration check on plugin activation
register_activation_hook(__FILE__, 'shutterpress_schedule_expiration_check');

function shutterpress_schedule_expiration_check() {
    if (!wp_next_scheduled('shutterpress_check_expired_subscriptions')) {
        wp_schedule_event(time(), 'daily', 'shutterpress_check_expired_subscriptions');
    }
}

// Clean up scheduled event on deactivation
register_deactivation_hook(__FILE__, 'shutterpress_clear_expiration_schedule');

function shutterpress_clear_expiration_schedule() {
    $timestamp = wp_next_scheduled('shutterpress_check_expired_subscriptions');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'shutterpress_check_expired_subscriptions');
    }
}

// Hook for the scheduled event - runs daily
add_action('shutterpress_check_expired_subscriptions', 'shutterpress_expire_old_subscriptions');

function shutterpress_expire_old_subscriptions() {
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_user_quotas';
    
    // Update expired subscriptions
    $updated = $wpdb->query("
        UPDATE $table 
        SET status = 'expired' 
        WHERE status = 'active' 
        AND quota_renewal_date IS NOT NULL 
        AND quota_renewal_date < CURDATE()
    ");
    
    if ($updated > 0) {
        error_log("ShutterPress: Automatically expired $updated subscriptions on " . date('Y-m-d H:i:s'));
    }
    
    return $updated;
}

// Also check on page loads (backup system) - runs occasionally 
add_action('init', 'shutterpress_check_expiration_realtime');

function shutterpress_check_expiration_realtime() {
    // Only run 2% of the time to avoid performance issues
    if (rand(1, 100) <= 2) {
        shutterpress_expire_old_subscriptions();
    }
}

// Manual trigger for testing (admin only)
add_action('wp_ajax_shutterpress_manual_expire_check', 'shutterpress_manual_expire_check');

function shutterpress_manual_expire_check() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $expired_count = shutterpress_expire_old_subscriptions();
    wp_send_json_success([
        'message' => "Checked for expired subscriptions. Updated: $expired_count",
        'expired_count' => $expired_count
    ]);
}

require_once plugin_dir_path(__FILE__) . 'download-handler.php';