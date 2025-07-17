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

    // Create download logs table
    $sql3 = "CREATE TABLE $logs_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        download_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(100),
        user_agent TEXT
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
}

// Hook: Add user quota when WooCommerce order is completed
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

