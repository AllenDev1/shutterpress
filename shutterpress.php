<?php
/**
 * Plugin Name: ShutterPress
 * Plugin URI: https://github.com/AllenDev1/shutterpress
 * Description: A complete Shutterstock-style image marketplace for WordPress. Includes quota-based subscriptions, automatic image watermarking, secure downloads, and Dokan multi-vendor support.
 * Version: 1.2.0
 * Author: Narayan Dev
 * Author URI: https://thebrilliantideas.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shutterpress
 * Domain Path: /languages
 */


defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

define('SHUTTERPRESS_PATH', trailingslashit(plugin_dir_path(__FILE__)));

// Global flag to prevent infinite loops
global $shutterpress_processing_lock;
$shutterpress_processing_lock = false;

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

function shutterpress_register_plan_tag()
{
    if (!term_exists('shutterpress-plan-product', 'product_tag')) {
        wp_insert_term('ShutterPress Plan Product', 'product_tag', [
            'slug' => 'shutterpress-plan-product',
        ]);
    }
}
register_activation_hook(__FILE__, 'shutterpress_register_plan_tag');

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

add_action('woocommerce_process_product_meta', 'shutterpress_handle_downloadable_files', 25);
function shutterpress_handle_downloadable_files($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (wp_is_post_revision($post_id))
        return;

    $product = wc_get_product($post_id);
    if (!$product || !$product->is_downloadable())
        return;

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

/**
 * Handle product images (featured image + gallery) deletion after Wasabi upload
 */
add_action('woocommerce_process_product_meta', 'shutterpress_handle_product_images', 30);
function shutterpress_handle_product_images($post_id)
{
    global $shutterpress_processing_lock;
    
    // Prevent infinite loops
    if ($shutterpress_processing_lock) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (wp_is_post_revision($post_id))
        return;

    $product = wc_get_product($post_id);
    if (!$product)
        return;

    // Set processing lock
    $shutterpress_processing_lock = true;
    
    // Increase memory and time limits
    @ini_set('memory_limit', '512M');
    @set_time_limit(300);

    try {
        // Handle Featured Image
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            shutterpress_process_product_image($featured_image_id);
        }

        // Handle Gallery Images
        $gallery_image_ids = $product->get_gallery_image_ids();
        if (!empty($gallery_image_ids)) {
            foreach ($gallery_image_ids as $image_id) {
                shutterpress_process_product_image($image_id);
            }
        }
    } catch (Exception $e) {
        error_log("ShutterPress: Error processing product images: " . $e->getMessage());
    } finally {
        // Always release the lock
        $shutterpress_processing_lock = false;
    }
}

/**
 * Process individual product image - upload to Wasabi, create watermark, and update WordPress
 */
function shutterpress_process_product_image($attachment_id)
{
    // Skip if already processed
    if (get_post_meta($attachment_id, '_shutterpress_product_image_processed', true)) {
        return true;
    }

    // Additional safety check
    if (!$attachment_id || !is_numeric($attachment_id)) {
        return false;
    }

    error_log("ShutterPress: Starting to process image ID: $attachment_id");

    // STEP 1: Create watermarked version BEFORE uploading to Wasabi
    $watermarked_path = shutterpress_create_watermarked_image($attachment_id);
    
    // STEP 2: Upload to Wasabi (this will use the original file)
    $upload_success = shutterpress_force_upload_file($attachment_id);
    
    if ($upload_success) {
        // STEP 3: Update WordPress to use watermarked images for display (simplified)
        if ($watermarked_path) {
            shutterpress_update_attachment_to_watermarked_simple($attachment_id, $watermarked_path);
        }
        
        // STEP 4: Mark as processed and delete originals
        update_post_meta($attachment_id, '_shutterpress_product_image_processed', 1);
        shutterpress_delete_wordpress_image_files($attachment_id);
        
        error_log("ShutterPress: Successfully processed product image ID: $attachment_id");
        return true;
    }
    
    return false;
}

/**
 * Create watermarked image and return the path
 */
function shutterpress_create_watermarked_image($attachment_id)
{
    // Check if watermark handler exists
    if (!isset($GLOBALS['shutterpress_watermark_handler']) || 
        !method_exists($GLOBALS['shutterpress_watermark_handler'], 'get_watermarked_image_url')) {
        error_log("ShutterPress: Watermark handler not available for attachment $attachment_id");
        return false;
    }
    
    // Generate watermarked image
    $watermarked_url = $GLOBALS['shutterpress_watermark_handler']->get_watermarked_image_url($attachment_id);
    
    if (!$watermarked_url) {
        error_log("ShutterPress: Failed to create watermarked image for attachment $attachment_id");
        return false;
    }
    
    // Convert URL to file path
    $upload_dir = wp_upload_dir();
    $watermarked_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $watermarked_url);
    
    if (file_exists($watermarked_path)) {
        error_log("ShutterPress: Watermarked image created at: $watermarked_path");
        return $watermarked_path;
    }
    
    return false;
}

/**
 * Update attachment metadata to use watermarked image for all sizes
 */
function shutterpress_update_attachment_to_watermarked_simple($attachment_id, $watermarked_path)
{
    $upload_dir = wp_upload_dir();
    $watermarked_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $watermarked_path);
    $watermarked_filename = basename($watermarked_path);
    $relative_path = str_replace($upload_dir['basedir'] . '/', '', $watermarked_path);
    
    // Store original file info BEFORE updating metadata
    $original_file = get_attached_file($attachment_id);
    update_post_meta($attachment_id, '_shutterpress_original_file', $original_file);
    
    // Update the main attachment file to point to watermarked version
    update_post_meta($attachment_id, '_wp_attached_file', $relative_path);
    
    // Update the GUID (main URL)
    wp_update_post(array(
        'ID' => $attachment_id,
        'guid' => $watermarked_url
    ));
    
    // Get original metadata to preserve dimensions
    $original_meta = wp_get_attachment_metadata($attachment_id);
    
    // Get watermarked image dimensions
    $watermarked_size = @getimagesize($watermarked_path);
    $width = $watermarked_size ? $watermarked_size[0] : 1024;
    $height = $watermarked_size ? $watermarked_size[1] : 1024;
    
    // Create new metadata pointing all sizes to the main watermarked image
    $new_meta = array(
        'width' => $width,
        'height' => $height,
        'file' => $relative_path,
        'filesize' => filesize($watermarked_path),
        'sizes' => array()
    );
    
    // Point all common thumbnail sizes to the main watermarked image
    $common_sizes = array(
        'thumbnail' => array('width' => 150, 'height' => 150),
        'medium' => array('width' => 300, 'height' => 300),
        'medium_large' => array('width' => 768, 'height' => 768),
        'large' => array('width' => 1024, 'height' => 1024),
        'woocommerce_thumbnail' => array('width' => 300, 'height' => 300),
        'woocommerce_single' => array('width' => 600, 'height' => 600),
        'woocommerce_gallery_thumbnail' => array('width' => 100, 'height' => 100),
        'shop_catalog' => array('width' => 300, 'height' => 300),
        'shop_single' => array('width' => 600, 'height' => 600),
        'shop_thumbnail' => array('width' => 100, 'height' => 100),
    );
    
    // Add original sizes from metadata if they exist
    if (isset($original_meta['sizes']) && is_array($original_meta['sizes'])) {
        foreach ($original_meta['sizes'] as $size_name => $size_data) {
            $common_sizes[$size_name] = array(
                'width' => $size_data['width'],
                'height' => $size_data['height']
            );
        }
    }
    
    // Make all sizes point to the main watermarked image
    foreach ($common_sizes as $size_name => $size_data) {
        $new_meta['sizes'][$size_name] = array(
            'file' => $watermarked_filename,
            'width' => $size_data['width'],
            'height' => $size_data['height'],
            'mime-type' => 'image/png',
            'filesize' => filesize($watermarked_path)
        );
    }
    
    // Update attachment metadata
    wp_update_attachment_metadata($attachment_id, $new_meta);
    
    // Store watermarked info
    update_post_meta($attachment_id, '_shutterpress_watermarked_url', $watermarked_url);
    update_post_meta($attachment_id, '_shutterpress_watermarked_path', $watermarked_path);
    update_post_meta($attachment_id, '_shutterpress_using_watermark', 1);
    
    error_log("ShutterPress: Updated attachment metadata for $attachment_id to use watermarked image: $watermarked_url");
    error_log("ShutterPress: Stored original file for deletion: $original_file");
}

/**
 * Hook to serve watermarked images on frontend - with recursion protection
 * This is now mainly a backup since we update metadata directly
 */
add_filter('wp_get_attachment_url', 'shutterpress_filter_attachment_url', 10, 2);
function shutterpress_filter_attachment_url($url, $attachment_id)
{
    // Prevent infinite loops
    static $processing_attachments = array();
    
    if (isset($processing_attachments[$attachment_id])) {
        return $url;
    }
    
    // Only filter on frontend and for processed product images
    if (is_admin() || !get_post_meta($attachment_id, '_shutterpress_product_image_processed', true)) {
        return $url;
    }
    
    $processing_attachments[$attachment_id] = true;
    
    try {
        // Since we update metadata directly, this is mainly a backup
        $watermarked_url = get_post_meta($attachment_id, '_shutterpress_watermarked_url', true);
        if ($watermarked_url && shutterpress_is_product_image_cached($attachment_id)) {
            unset($processing_attachments[$attachment_id]);
            return $watermarked_url;
        }
    } catch (Exception $e) {
        error_log("ShutterPress: Error in URL filter: " . $e->getMessage());
    }
    
    unset($processing_attachments[$attachment_id]);
    return $url;
}

/**
 * Cached version of product image check to avoid repeated DB queries
 */
function shutterpress_is_product_image_cached($attachment_id)
{
    static $cache = array();
    
    if (isset($cache[$attachment_id])) {
        return $cache[$attachment_id];
    }
    
    // Check if it's marked as a product image during processing
    $is_product_image = get_post_meta($attachment_id, '_shutterpress_product_image_processed', true);
    
    $cache[$attachment_id] = !empty($is_product_image);
    return $cache[$attachment_id];
}

/**
 * Remove srcset filter - we now handle this through metadata
 * All thumbnail sizes point to the main watermarked image via attachment metadata
 */

/**
 * Delete ONLY original image files, preserve watermarked images
 */
function shutterpress_delete_wordpress_image_files($attachment_id)
{
    // Get the watermarked path to preserve it
    $watermarked_path = get_post_meta($attachment_id, '_shutterpress_watermarked_path', true);
    
    // Get original file path (before we updated the metadata)
    $original_file = get_post_meta($attachment_id, '_shutterpress_original_file', true);
    if (!$original_file) {
        // Fallback to current attached file if we don't have original stored
        $original_file = get_attached_file($attachment_id);
    }
    
    if (!$original_file || !file_exists($original_file)) {
        error_log("ShutterPress: No original file found to delete for attachment $attachment_id");
        return false;
    }

    $deleted_files = [];
    
    // Get original metadata (before watermark update)
    $meta = wp_get_attachment_metadata($attachment_id);
    
    // Delete original thumbnail sizes (but NOT the watermarked image)
    if (!empty($meta['sizes'])) {
        foreach ($meta['sizes'] as $size) {
            $thumb_path = path_join(dirname($original_file), $size['file']);
            
            // Skip if this is the watermarked image
            if ($watermarked_path && $thumb_path === $watermarked_path) {
                error_log("ShutterPress: Preserving watermarked file: $thumb_path");
                continue;
            }
            
            if (file_exists($thumb_path)) {
                @unlink($thumb_path);
                $deleted_files[] = $thumb_path;
            }
        }
    }
    
    // Delete original file (but NOT if it's the watermarked image)
    if ($watermarked_path && $original_file === $watermarked_path) {
        error_log("ShutterPress: Preserving watermarked original file: $original_file");
    } else {
        if (file_exists($original_file)) {
            @unlink($original_file);
            $deleted_files[] = $original_file;
        }
    }
    
    // Additional cleanup - scan for related original files but preserve watermarked
    $original_filename = basename($original_file);
    $upload_dir = wp_upload_dir();
    $file_dir = dirname($original_file);
    $base_name = pathinfo($original_filename, PATHINFO_FILENAME);
    $watermarked_filename = $watermarked_path ? basename($watermarked_path) : '';
    
    if (is_dir($file_dir)) {
        $files = glob($file_dir . '/' . $base_name . '*');
        foreach ($files as $file) {
            // Skip if this is the watermarked image
            if ($watermarked_filename && basename($file) === $watermarked_filename) {
                error_log("ShutterPress: Preserving watermarked file during cleanup: $file");
                continue;
            }
            
            // Skip if this file is in the shutterpress-watermarks directory
            if (strpos($file, 'shutterpress-watermarks') !== false) {
                error_log("ShutterPress: Preserving watermark directory file: $file");
                continue;
            }
            
            if (file_exists($file)) {
                @unlink($file);
                $deleted_files[] = $file;
            }
        }
    }
    
    if (!empty($deleted_files)) {
        error_log("ShutterPress: Deleted original image files (preserved watermarks):\n" . implode("\n", $deleted_files));
    }
    
    return true;
}

function shutterpress_force_upload_file($attachment_id)
{
    if (get_post_meta($attachment_id, '_shutterpress_wasabi_uploaded', true)) {
        return true;
    }

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path))
        return false;

    $access_key = defined('DBI_AWS_ACCESS_KEY_ID') ? DBI_AWS_ACCESS_KEY_ID : '';
    $secret_key = defined('DBI_AWS_SECRET_ACCESS_KEY') ? DBI_AWS_SECRET_ACCESS_KEY : '';
    if (!$access_key || !$secret_key)
        return false;

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

            $deleted_files = [];
            $meta = wp_get_attachment_metadata($attachment_id);

            if (!empty($meta['sizes'])) {
                foreach ($meta['sizes'] as $size) {
                    $thumb_path = path_join(dirname($file_path), $size['file']);
                    if (file_exists($thumb_path)) {
                        @unlink($thumb_path);
                        $deleted_files[] = $thumb_path;
                    }
                }
            }

            @unlink($file_path);
            $deleted_files[] = $file_path;

            $original_filename = get_post_meta($attachment_id, '_wp_attached_file', true);
            $original_basename = basename($original_filename);
            $base_filename = preg_replace('/(-\d+x\d+)?\.[^.]+$/', '', $original_basename);
            error_log("ShutterPress: Base filename for deletion: $base_filename");
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            $upload_base = wp_upload_dir();
            $year_month_dir = trailingslashit($upload_base['basedir']) . date('Y') . '/' . date('m');

            if (file_exists($year_month_dir)) {
                $files = scandir($year_month_dir);
                foreach ($files as $file) {
                    foreach ($extensions as $ext) {
                        if (preg_match('/^' . preg_quote($base_filename, '/') . '(-\d+x\d+)?\.' . $ext . '$/i', $file) ||
                            preg_match('/^' . preg_quote($base_filename, '/') . '\.' . $ext . '$/i', $file)) {
                            $full_path = trailingslashit($year_month_dir) . $file;
                            if (file_exists($full_path)) {
                                @unlink($full_path);
                                $deleted_files[] = $full_path;
                                error_log("ShutterPress: Deleted file from uploads dir: $full_path");
                            }
                        }
                    }
                }
            }

            if (!empty($deleted_files)) {
                error_log("ShutterPress: Deleted files:\n" . implode("\n", $deleted_files));
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
    if ($stored_key)
        return $stored_key;

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

add_filter('manage_media_columns', 'shutterpress_add_wasabi_column');
function shutterpress_add_wasabi_column($columns)
{
    $columns['shutterpress_wasabi'] = 'Wasabi';
    $columns['shutterpress_product_image'] = 'Product Image Status';
    return $columns;
}

add_action('manage_media_custom_column', 'shutterpress_show_wasabi_column', 10, 2);
function shutterpress_show_wasabi_column($column_name, $post_id)
{
    if ($column_name === 'shutterpress_wasabi') {
        $uploaded = get_post_meta($post_id, '_shutterpress_wasabi_uploaded', true);
        echo $uploaded ? '<span style="color: green;">&#10003; Uploaded</span>' : '<span style="color: #666;">Local</span>';
    }
    
    if ($column_name === 'shutterpress_product_image') {
        $processed = get_post_meta($post_id, '_shutterpress_product_image_processed', true);
        $wasabi_uploaded = get_post_meta($post_id, '_shutterpress_wasabi_uploaded', true);
        $using_watermark = get_post_meta($post_id, '_shutterpress_using_watermark', true);
        
        if ($processed && $wasabi_uploaded && $using_watermark) {
            echo '<span style="color: green;">&#10003; Watermarked & Uploaded</span>';
        } elseif ($processed && $wasabi_uploaded) {
            echo '<span style="color: orange;">Uploaded Only</span>';
        } elseif ($wasabi_uploaded) {
            echo '<span style="color: orange;">Wasabi Only</span>';
        } else {
            echo '<span style="color: #666;">Local Only</span>';
        }
    }
}

/**
 * Add meta box for manual product image processing
 */
add_action('add_meta_boxes', 'shutterpress_add_product_meta_box');
function shutterpress_add_product_meta_box()
{
    add_meta_box(
        'shutterpress_product_images',
        'ShutterPress Image Management',
        'shutterpress_product_images_meta_box_callback',
        'product',
        'side',
        'default'
    );
}

function shutterpress_product_images_meta_box_callback($post)
{
    $featured_image_id = get_post_thumbnail_id($post->ID);
    $gallery_image_ids = get_post_meta($post->ID, '_product_image_gallery', true);
    
    echo '<p><strong>Image Processing Status:</strong></p>';
    
    if ($featured_image_id) {
        $processed = get_post_meta($featured_image_id, '_shutterpress_product_image_processed', true);
        $using_watermark = get_post_meta($featured_image_id, '_shutterpress_using_watermark', true);
        $status = $processed && $using_watermark ? '✅ Watermarked & Processed' : '❌ Not Processed';
        echo '<p>Featured Image: ' . $status . '</p>';
    }
    
    if ($gallery_image_ids) {
        $gallery_ids = explode(',', $gallery_image_ids);
        $processed_count = 0;
        foreach ($gallery_ids as $id) {
            if (get_post_meta($id, '_shutterpress_product_image_processed', true) && 
                get_post_meta($id, '_shutterpress_using_watermark', true)) {
                $processed_count++;
            }
        }
        echo '<p>Gallery Images: ' . $processed_count . '/' . count($gallery_ids) . ' watermarked</p>';
    }
    
    echo '<button type="button" class="button" onclick="shutterpress_process_images(' . $post->ID . ')">Process All Images</button>';
    echo '<script>
    function shutterpress_process_images(postId) {
        if (confirm("This will create watermarks, upload to Wasabi, and delete local copies. Continue?")) {
            alert("Processing... Check error logs for progress.");
        }
    }
    </script>';
}
add_action('admin_head', function () {
    if (!function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    if (!$screen) return;

    // Only hide notices on ShutterPress plugin pages
    if (strpos($screen->id, 'shutterpress') !== false) {
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }
});


require_once SHUTTERPRESS_PATH . 'includes/init.php';
require_once SHUTTERPRESS_PATH . 'admin/admin-init.php';
require_once SHUTTERPRESS_PATH . 'public/public-init.php';
require_once SHUTTERPRESS_PATH . 'includes/wasabi.php';