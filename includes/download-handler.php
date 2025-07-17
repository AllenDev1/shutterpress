<?php
add_action('init', 'shutterpress_handle_secure_download');

function shutterpress_handle_secure_download()
{
    if (!isset($_GET['shutterpress_download'])) {
        return;
    }

    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }

    $product_id = absint($_GET['shutterpress_download']);
    $user_id = get_current_user_id();

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_downloadable() || !$product->is_virtual()) {
        wp_die('Invalid product.');
    }

    $type = get_post_meta($product_id, '_shutterpress_product_type', true);
    if (!in_array($type, ['free', 'subscription'])) {
        wp_die('This product is not downloadable through ShutterPress.');
    }

    global $wpdb;
    $downloads = $product->get_downloads();
    if (empty($downloads)) {
        wp_die('No file found.');
    }

    $file = reset($downloads)['file'];

    // For subscription, check and update quota
    if ($type === 'subscription') {
        $table = $wpdb->prefix . 'shutterpress_user_quotas';

        $quota = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table
            WHERE user_id = %d AND status = 'active'
            ORDER BY created_at DESC LIMIT 1
        ", $user_id));

        if (!$quota) {
            wp_die('You do not have an active subscription.');
        }

        $is_unlimited = isset($quota->is_unlimited) ? (bool) $quota->is_unlimited : false;
        if (!$is_unlimited) {
            if ($quota->quota_used >= $quota->quota_total) {
                wp_die('You have reached your download limit.');
            }

            $wpdb->update(
                $table,
                ['quota_used' => $quota->quota_used + 1],
                ['id' => $quota->id],
                ['%d'],
                ['%d']
            );
        }
    }

    // ✅ Log the download
    $log_table = $wpdb->prefix . 'shutterpress_download_logs';
    $wpdb->insert($log_table, [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'download_time' => current_time('mysql'),
        'download_type' => $type,
    ], ['%d', '%d', '%s', '%s']);


    // ✅ Serve the file
    wp_redirect($file);
    exit;
}
