<?php
add_action('init', 'shutterpress_handle_secure_download');

function shutterpress_handle_secure_download()
{
    if (!isset($_GET['shutterpress_download'])) {
        return;
    }

    $product_id = absint($_GET['shutterpress_download']);
    $nonce = $_GET['_wpnonce'] ?? '';

    if (!wp_verify_nonce($nonce, 'shutterpress_download_' . $product_id)) {
        wp_die('Invalid or expired download link.');
    }

    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }

    $user_id = get_current_user_id();
    $product = wc_get_product($product_id);

    if (!$product || !$product->is_downloadable() || !$product->is_virtual()) {
        wp_die('Invalid product.');
    }

    $type = get_post_meta($product_id, '_shutterpress_product_type', true);

    if ($type === 'premium') {
        return; // Let Woo handle premium
    }

    if (!in_array($type, ['free', 'subscription'])) {
        wp_die('This product is not downloadable through ShutterPress.');
    }

    global $wpdb;

    // ✅ Quota check for subscription type
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

        $is_unlimited = !empty($quota->is_unlimited);

        if (!$is_unlimited && $quota->quota_used >= $quota->quota_total) {
            wp_die('You have reached your download limit.');
        }

        if (!$is_unlimited) {
            $wpdb->update(
                $table,
                ['quota_used' => $quota->quota_used + 1],
                ['id' => $quota->id],
                ['%d'],
                ['%d']
            );
        }
    }

    // ✅ Log download
    $log_table = $wpdb->prefix . 'shutterpress_download_logs';
    $wpdb->insert($log_table, [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'download_time' => current_time('mysql'),
        'download_type' => $type,
    ], ['%d', '%d', '%s', '%s']);

    // ✅ Get Wasabi key
    // ✅ SIMPLE: Get Wasabi key - try multiple ways
    $wasabi_key = null;
    
    // Method 1: Check stored product key
    $wasabi_key = get_post_meta($product_id, '_wasabi_object_key', true);
    error_log("Product stored key: " . ($wasabi_key ?: 'none'));
    
    // Method 2: If no stored key, get from actual downloadable file
    if (!$wasabi_key) {
        $downloads = $product->get_downloads();
        if (!empty($downloads)) {
            $first_download = current($downloads);
            $file_url = $first_download->get_file();
            $attachment_id = shutterpress_get_attachment_id_from_url($file_url);
            
            error_log("Downloadable file URL: $file_url");
            error_log("Attachment ID: $attachment_id");
            
            if ($attachment_id) {
                // Try to get from attachment meta
                $wasabi_key = get_post_meta($attachment_id, '_wasabi_object_key', true);
                error_log("Attachment stored key: " . ($wasabi_key ?: 'none'));
                
                // Try to get from S3 info
                if (!$wasabi_key && function_exists('as3cf_get_attachment_s3_info')) {
                    $s3_info = as3cf_get_attachment_s3_info($attachment_id);
                    if ($s3_info && isset($s3_info['key'])) {
                        $wasabi_key = $s3_info['key'];
                        error_log("S3 info key: $wasabi_key");
                        
                        // Store for next time
                        update_post_meta($attachment_id, '_wasabi_object_key', $wasabi_key);
                        update_post_meta($product_id, '_wasabi_object_key', $wasabi_key);
                    }
                }
            }
        }
    }
    
    error_log("Final Wasabi key: " . ($wasabi_key ?: 'NONE FOUND'));
    
    if (!$wasabi_key) {
        wp_die('Download not configured for this product. Please contact support.');
    }

    error_log("Final Wasabi key to use: " . $wasabi_key);

    if (!$wasabi_key) {
        wp_die('Download not configured for this product.');
    }

    if (!function_exists('shutterpress_generate_temp_download_url')) {
        require_once plugin_dir_path(__FILE__) . 'includes/wasabi.php';
    }

    $signed_url = shutterpress_generate_temp_download_url($wasabi_key);
    if (!$signed_url) {
        wp_die('Unable to generate secure download URL.');
    }

    error_log("Wasabi Key: $wasabi_key");
    error_log("Signed URL: $signed_url");

    // ✅ Fetch headers first to get content-type, content-length
    $headers = @get_headers($signed_url, 1);
    if (!$headers || stripos($headers[0], '200') === false) {
        wp_die('Failed to access remote file.');
    }

    $content_type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
    $content_length = is_array($headers['Content-Length'] ?? null) ? $headers['Content-Length'][0] : ($headers['Content-Length'] ?? null);

    // ✅ Begin streaming download
    $stream = @fopen($signed_url, 'rb');
    if (!$stream) {
        wp_die('Unable to retrieve file from Wasabi.');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . esc_attr($content_type ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($wasabi_key) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    if ($content_length) {
        header('Content-Length: ' . $content_length);
    }

    // Output the stream
    fpassthru($stream);
    fclose($stream);
    exit;
}
