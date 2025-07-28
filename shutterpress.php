<?php
/**
 * Plugin Name: ShutterPress
 * Description: Shutterstock-style image download system using WooCommerce, Dokan, and custom quotas.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

// Load Composer dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin path constant
define('SHUTTERPRESS_PATH', trailingslashit(plugin_dir_path(__FILE__)));

// Enqueue frontend styles
add_action('wp_enqueue_scripts', 'shutterpress_enqueue_styles');
function shutterpress_enqueue_styles()
{
    wp_enqueue_style(
        'shutterpress-style',
        plugin_dir_url(__FILE__) . 'assets/style.css',
        [],
        '1.0.0'
    );
}

// Register custom product tag on plugin activation
function shutterpress_register_plan_tag()
{
    if (!term_exists('shutterpress-plan-product', 'product_tag')) {
        wp_insert_term('ShutterPress Plan Product', 'product_tag', [
            'slug' => 'shutterpress-plan-product',
        ]);
    }
}
register_activation_hook(__FILE__, 'shutterpress_register_plan_tag');

// Enable Wasabi upload only in product admin pages
add_filter('as3cf_get_setting', 'shutterpress_enable_wasabi_for_products', 10, 2);
function shutterpress_enable_wasabi_for_products($value, $key)
{
    if ($key === 'copy-to-s3') {
        if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'product') {
            return true;
        }
        if (is_admin() && isset($_POST['post_id']) && get_post_type($_POST['post_id']) === 'product') {
            return true;
        }
        return false;
    }
    return $value;
}

// When product is saved, force upload downloadable files and store keys
add_action('woocommerce_process_product_meta', 'shutterpress_handle_downloadable_files', 25);
function shutterpress_handle_downloadable_files($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $product = wc_get_product($post_id);
    if (!$product || !$product->is_downloadable()) return;

    delete_post_meta($post_id, '_wasabi_object_key');

    if (isset($_POST['_wc_file_urls']) && is_array($_POST['_wc_file_urls'])) {
        foreach ($_POST['_wc_file_urls'] as $file_url) {
            if (!empty($file_url)) {
                $attachment_id = shutterpress_get_attachment_id_from_url($file_url);
                if ($attachment_id) {
                    shutterpress_force_upload_file($attachment_id);
                    $wasabi_key = shutterpress_get_wasabi_key($attachment_id);
                    if ($wasabi_key) {
                        update_post_meta($post_id, '_wasabi_object_key', $wasabi_key);
                        break;
                    }
                }
            }
        }
    }

    $downloads = $product->get_downloads();
    if (!empty($downloads) && !get_post_meta($post_id, '_wasabi_object_key', true)) {
        $first_download = current($downloads);
        $file_url = $first_download->get_file();
        $attachment_id = shutterpress_get_attachment_id_from_url($file_url);
        if ($attachment_id) {
            shutterpress_force_upload_file($attachment_id);
            $wasabi_key = shutterpress_get_wasabi_key($attachment_id);
            if ($wasabi_key) {
                update_post_meta($post_id, '_wasabi_object_key', $wasabi_key);
            }
        }
    }
}

// Force upload a file to Wasabi and delete local file + thumbnails
function shutterpress_force_upload_file($attachment_id)
{
    if (get_post_meta($attachment_id, '_shutterpress_wasabi_uploaded', true)) return true;

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) return false;

    $access_key = defined('DBI_AWS_ACCESS_KEY_ID') ? DBI_AWS_ACCESS_KEY_ID : '';
    $secret_key = defined('DBI_AWS_SECRET_ACCESS_KEY') ? DBI_AWS_SECRET_ACCESS_KEY : '';
    if (!$access_key || !$secret_key) return false;

    try {
        $s3 = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'ap-northeast-2',
            'endpoint' => 'https://s3.ap-northeast-2.wasabisys.com',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $access_key,
                'secret' => $secret_key,
            ],
        ]);

        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);
        $key = 'stockimg/' . $relative_path;

        $result = $s3->putObject([
            'Bucket' => 'designfabricmedia',
            'Key' => $key,
            'Body' => fopen($file_path, 'rb'),
            'ACL' => 'private'
        ]);

        if ($result) {
            update_post_meta($attachment_id, '_shutterpress_wasabi_uploaded', 1);
            update_post_meta($attachment_id, '_wasabi_object_key', $key);

            @unlink($file_path);

            $meta = wp_get_attachment_metadata($attachment_id);
            if (!empty($meta['sizes'])) {
                foreach ($meta['sizes'] as $size) {
                    $thumb_path = path_join(dirname($file_path), $size['file']);
                    if (file_exists($thumb_path)) {
                        @unlink($thumb_path);
                    }
                }
            }
            return true;
        }

    } catch (Exception $e) {
        error_log('Wasabi upload error: ' . $e->getMessage());
    }

    return false;
}

function shutterpress_get_wasabi_key($attachment_id)
{
    $stored_key = get_post_meta($attachment_id, '_wasabi_object_key', true);
    if ($stored_key) return $stored_key;

    if (function_exists('as3cf_get_attachment_s3_info')) {
        $s3_info = as3cf_get_attachment_s3_info($attachment_id);
        if ($s3_info && isset($s3_info['key'])) {
            update_post_meta($attachment_id, '_wasabi_object_key', $s3_info['key']);
            return $s3_info['key'];
        }
    }
    return false;
}

function shutterpress_get_attachment_id_from_url($url)
{
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE guid = %s LIMIT 1",
        esc_url_raw($url)
    ));
}

// Add media library column to show Wasabi status
add_filter('manage_media_columns', 'shutterpress_add_wasabi_column');
function shutterpress_add_wasabi_column($columns)
{
    $columns['shutterpress_wasabi'] = 'Wasabi';
    return $columns;
}

add_action('manage_media_custom_column', 'shutterpress_show_wasabi_column', 10, 2);
function shutterpress_show_wasabi_column($column_name, $post_id)
{
    if ($column_name === 'shutterpress_wasabi') {
        $uploaded = get_post_meta($post_id, '_shutterpress_wasabi_uploaded', true);
        echo $uploaded ? '<span style="color: green;">&#10003; Uploaded</span>' : '<span style="color: #666;">Local</span>';
    }
}

// Load plugin modules
require_once SHUTTERPRESS_PATH . 'includes/init.php';
require_once SHUTTERPRESS_PATH . 'admin/admin-init.php';
require_once SHUTTERPRESS_PATH . 'public/public-init.php';
require_once SHUTTERPRESS_PATH . 'includes/wasabi.php';
