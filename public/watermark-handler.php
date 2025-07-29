<?php
// File: public/watermark-handler.php

class ShutterPress_Watermark_Handler {
    
    private $watermark_text;
    private $watermark_opacity;
    private $watermark_size;
    private $watermark_angle;
    private $watermark_spacing;
    private $watermark_dir;
    private $watermark_url;
    private $gd_available;
    
    public function __construct() {
        $this->check_gd_availability();
        $this->load_settings();
        $this->init_watermark_directory();
        add_action('init', array($this, 'init'));
    }
    
    private function check_gd_availability() {
        $this->gd_available = extension_loaded('gd') && function_exists('imagecreatefromjpeg');
        
        if (!$this->gd_available) {
            add_action('admin_notices', array($this, 'gd_extension_notice'));
        }
    }
    
    public function gd_extension_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>ShutterPress:</strong> GD extension is not available. Watermarking functionality is disabled. ';
            echo 'Please enable the GD extension in your PHP installation to use watermarks.';
            echo '</p></div>';
        }
    }
    
    private function load_settings() {
        $this->watermark_text = get_option('shutterpress_watermark_text', 'ShutterPress');
        $this->watermark_opacity = get_option('shutterpress_watermark_opacity', 90);
        $this->watermark_size = get_option('shutterpress_watermark_size', 2.5);
        $this->watermark_angle = get_option('shutterpress_watermark_angle', 45);
        $this->watermark_spacing = get_option('shutterpress_watermark_spacing', 2.0);
    }
    
    private function init_watermark_directory() {
        $upload_dir = wp_upload_dir();
        $this->watermark_dir = $upload_dir['basedir'] . '/shutterpress-watermarks/';
        $this->watermark_url = $upload_dir['baseurl'] . '/shutterpress-watermarks/';
        
        if (!file_exists($this->watermark_dir)) {
            wp_mkdir_p($this->watermark_dir);
            
            // Create .htaccess file to prevent direct access
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "Order allow,deny\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($this->watermark_dir . '.htaccess', $htaccess_content);
        }
    }
    
    public function init() {
        // Only initialize watermarking if GD is available
        if (!$this->gd_available) {
            return;
        }
        
        // Hook into image display for product images
        add_filter('woocommerce_single_product_image_thumbnail_html', array($this, 'watermark_product_image'), 10, 2);
        add_filter('woocommerce_single_product_image_html', array($this, 'watermark_main_image'), 10, 2);
        
        // Hook into product gallery
        add_filter('woocommerce_single_product_image_gallery_html', array($this, 'watermark_gallery_html'), 10, 2);
        
        // Hook into attachment image source
        add_filter('wp_get_attachment_image_src', array($this, 'maybe_watermark_attachment'), 10, 4);
        
        // Hook into shop thumbnails
        add_filter('post_thumbnail_html', array($this, 'watermark_shop_thumbnail'), 10, 5);
        
        // Hook into WooCommerce product thumbnails in shop
        add_filter('woocommerce_product_get_image', array($this, 'watermark_product_thumbnail'), 10, 6);
        
        // Clean up old watermarks periodically
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_watermarks'));
        
        // Add settings update hook to clear cache and run debugger
        add_action('update_option_shutterpress_watermark_text', array($this, 'on_settings_updated'));
        add_action('update_option_shutterpress_watermark_opacity', array($this, 'on_settings_updated'));
        add_action('update_option_shutterpress_watermark_size', array($this, 'on_settings_updated'));
        add_action('update_option_shutterpress_watermark_angle', array($this, 'on_settings_updated'));
        add_action('update_option_shutterpress_watermark_spacing', array($this, 'on_settings_updated'));
    }
    
    public function watermark_product_image($html, $post_thumbnail_id) {
        global $product;
        
        if (!$this->gd_available || !$this->should_watermark_product($product)) {
            return $html;
        }
        
        $watermarked_url = $this->get_watermarked_image_url($post_thumbnail_id);
        if ($watermarked_url) {
            $html = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($watermarked_url) . '" data-original-src="$1"', $html);
        } else {
            // Log the failure but still show the original image
            error_log("ShutterPress: Failed to get watermarked URL for thumbnail ID: " . $post_thumbnail_id);
        }
        
        return $html;
    }
    
    public function watermark_main_image($html, $post_thumbnail_id) {
        global $product;
        
        if (!$this->gd_available || !$this->should_watermark_product($product)) {
            return $html;
        }
        
        $watermarked_url = $this->get_watermarked_image_url($post_thumbnail_id);
        if ($watermarked_url) {
            $html = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($watermarked_url) . '" data-original-src="$1"', $html);
            $html = preg_replace('/srcset="([^"]*)"/', 'data-original-srcset="$1"', $html);
        } else {
            error_log("ShutterPress: Failed to get watermarked URL for main image ID: " . $post_thumbnail_id);
        }
        
        return $html;
    }
    
    public function watermark_gallery_html($html, $post_thumbnail_id) {
        global $product;
        
        if (!$this->gd_available || !$this->should_watermark_product($product)) {
            return $html;
        }
        
        // Get gallery image IDs
        $gallery_image_ids = $product->get_gallery_image_ids();
        
        if (!empty($gallery_image_ids)) {
            foreach ($gallery_image_ids as $image_id) {
                $watermarked_url = $this->get_watermarked_image_url($image_id);
                if ($watermarked_url) {
                    $original_url = wp_get_attachment_url($image_id);
                    $html = str_replace($original_url, $watermarked_url, $html);
                } else {
                    error_log("ShutterPress: Failed to get watermarked URL for gallery image ID: " . $image_id);
                }
            }
        }
        
        return $html;
    }
    
    public function watermark_shop_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (!$this->gd_available) {
            return $html;
        }
        
        $product = wc_get_product($post_id);
        
        if (!$this->should_watermark_product($product)) {
            return $html;
        }
        
        $watermarked_url = $this->get_watermarked_image_url($post_thumbnail_id, $size);
        if ($watermarked_url) {
            $html = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($watermarked_url) . '" data-original-src="$1"', $html);
        } else {
            error_log("ShutterPress: Failed to get watermarked URL for shop thumbnail ID: " . $post_thumbnail_id);
        }
        
        return $html;
    }
    
    public function watermark_product_thumbnail($image, $product, $size, $attr, $placeholder, $image_id) {
        if (!$this->gd_available || !$this->should_watermark_product($product)) {
            return $image;
        }
        
        $attachment_id = $product->get_image_id();
        if (!$attachment_id) {
            return $image;
        }
        
        $watermarked_url = $this->get_watermarked_image_url($attachment_id, $size);
        if ($watermarked_url) {
            $image = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($watermarked_url) . '" data-original-src="$1"', $image);
        } else {
            error_log("ShutterPress: Failed to get watermarked URL for product thumbnail ID: " . $attachment_id);
        }
        
        return $image;
    }
    
    public function maybe_watermark_attachment($image, $attachment_id, $size, $icon) {
        if (!$this->gd_available) {
            return $image;
        }
        
        // Check if this attachment is associated with a ShutterPress product
        $product = $this->get_product_from_attachment($attachment_id);
        
        if (!$this->should_watermark_product($product)) {
            return $image;
        }
        
        $watermarked_url = $this->get_watermarked_image_url($attachment_id, $size);
        if ($watermarked_url && isset($image[0])) {
            $image[0] = $watermarked_url;
        } else {
            error_log("ShutterPress: Failed to get watermarked URL for attachment ID: " . $attachment_id);
        }
        
        return $image;
    }
    
    private function should_watermark_product($product) {
        if (!$product || !is_object($product)) {
            error_log('ShutterPress: should_watermark_product - No product or invalid product object');
            return false;
        }
        
        $product_id = $product->get_id();
        $product_type = get_post_meta($product_id, '_shutterpress_product_type', true);
        
        // Debug logging
        error_log('ShutterPress: Checking watermark for product ID: ' . $product_id);
        error_log('ShutterPress: Product type: ' . $product_type);
        
        // Watermark all ShutterPress product types
        $should_watermark = in_array($product_type, ['free', 'subscription', 'premium']);
        
        // If no specific product type is set, check if this might be a ShutterPress product by other means
        if (!$should_watermark && empty($product_type)) {
            // Check if product has the ShutterPress tag
            $product_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
            if (!is_wp_error($product_tags) && in_array('shutterpress-plan-product', $product_tags)) {
                $should_watermark = true;
                error_log('ShutterPress: Product has shutterpress-plan-product tag, enabling watermark');
            }
            
            // For debugging: Temporarily watermark ALL products (remove this later)
            // Uncomment the line below if you want to test watermarking on all products
            // $should_watermark = true;
        }
        
        error_log('ShutterPress: Should watermark product ' . $product_id . '? ' . ($should_watermark ? 'YES' : 'NO'));
        
        return $should_watermark;
    }
    
    private function get_product_from_attachment($attachment_id) {
        global $wpdb;
        
        // Check if this attachment is a product featured image
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_thumbnail_id' AND meta_value = %d
        ", $attachment_id));
        
        if ($product_id) {
            return wc_get_product($product_id);
        }
        
        // Check if it's in product gallery
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s
        ", '%' . $attachment_id . '%'));
        
        if ($product_id) {
            return wc_get_product($product_id);
        }
        
        return null;
    }
    
    public function get_watermarked_image_url($attachment_id, $size = 'full') {
        if (!$this->gd_available) {
            error_log("ShutterPress: GD extension not available for watermarking");
            return false;
        }
        
        $original_file = get_attached_file($attachment_id);
        if (!$original_file || !file_exists($original_file)) {
            error_log("ShutterPress: Original file not found for attachment ID: " . $attachment_id . " - File: " . $original_file);
            return false;
        }
        
        // Handle different image sizes
        $size_suffix = 'full';
        $source_file = $original_file;
        
        if ($size !== 'full') {
            if (is_array($size)) {
                // Handle array format like [width, height]
                $size_suffix = $size[0] . 'x' . $size[1];
            } else {
                // Handle string format like 'thumbnail', 'medium', etc.
                $size_suffix = $size;
                
                // Get the actual sized image file
                $image_meta = wp_get_attachment_metadata($attachment_id);
                if ($image_meta && isset($image_meta['sizes']) && isset($image_meta['sizes'][$size])) {
                    $source_file = path_join(dirname($original_file), $image_meta['sizes'][$size]['file']);
                }
            }
        }
        
        // Generate unique filename for watermarked version
        $pathinfo = pathinfo($source_file);
        $settings_hash = md5($this->watermark_text . $this->watermark_opacity . $this->watermark_size . $this->watermark_angle . $this->watermark_spacing);
        $watermarked_filename = $pathinfo['filename'] . '_watermarked_' . $size_suffix . '_' . $settings_hash . '.' . $pathinfo['extension'];
        $watermarked_file = $this->watermark_dir . $watermarked_filename;
        $watermarked_url = $this->watermark_url . $watermarked_filename;
        
        // Check if watermarked version exists and is newer than original
        if (file_exists($watermarked_file) && filemtime($watermarked_file) >= filemtime($source_file)) {
            return $watermarked_url;
        }
        
        // Check if directory is writable
        if (!is_writable($this->watermark_dir)) {
            error_log("ShutterPress: Watermark directory not writable: " . $this->watermark_dir);
            return false;
        }
        
        // Generate watermarked version
        $generation_result = $this->generate_watermarked_image($source_file, $watermarked_file);
        
        if ($generation_result && file_exists($watermarked_file)) {
            error_log("ShutterPress: Successfully generated watermark for: " . $watermarked_filename);
            return $watermarked_url;
        } else {
            error_log("ShutterPress: Failed to generate watermark for: " . $source_file . " -> " . $watermarked_file);
            return false;
        }
    }
    
    private function generate_watermarked_image($source_file, $destination_file) {
        if (!$this->gd_available || !file_exists($source_file)) {
            error_log("ShutterPress: Cannot generate watermark - GD not available or source file missing: " . $source_file);
            return false;
        }
        
        $image_info = getimagesize($source_file);
        if (!$image_info) {
            error_log("ShutterPress: Cannot get image info for: " . $source_file);
            return false;
        }
        
        $mime_type = $image_info['mime'];
        $width = $image_info[0];
        $height = $image_info[1];
        
        // Skip very small images
        if ($width < 100 || $height < 100) {
            error_log("ShutterPress: Image too small for watermarking: " . $width . "x" . $height);
            return false;
        }
        
        // Create image resource
        $source_image = $this->create_image_resource($source_file, $mime_type);
        if (!$source_image) {
            error_log("ShutterPress: Failed to create image resource for: " . $source_file);
            return false;
        }
        
        // Add Shutterstock-style watermark pattern
        $this->add_shutterstock_watermark($source_image, $width, $height);
        
        // Save watermarked image
        $result = $this->save_image_resource($source_image, $destination_file, $mime_type);
        
        imagedestroy($source_image);
        
        if ($result) {
            error_log("ShutterPress: Successfully saved watermarked image: " . $destination_file);
        } else {
            error_log("ShutterPress: Failed to save watermarked image: " . $destination_file);
        }
        
        return $result;
    }
    
    private function create_image_resource($source_file, $mime_type) {
        if (!$this->gd_available) {
            return false;
        }
        
        try {
            switch ($mime_type) {
                case 'image/jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        return imagecreatefromjpeg($source_file);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagecreatefrompng')) {
                        $image = imagecreatefrompng($source_file);
                        if ($image) {
                            // Preserve transparency
                            imagealphablending($image, false);
                            imagesavealpha($image, true);
                        }
                        return $image;
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagecreatefromgif')) {
                        return imagecreatefromgif($source_file);
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        return imagecreatefromwebp($source_file);
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("ShutterPress: Error creating image resource: " . $e->getMessage());
        }
        
        return false;
    }
    
    private function save_image_resource($image, $destination_file, $mime_type) {
        if (!$this->gd_available) {
            return false;
        }
        
        try {
            switch ($mime_type) {
                case 'image/jpeg':
                    if (function_exists('imagejpeg')) {
                        return imagejpeg($image, $destination_file, 85);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagepng')) {
                        return imagepng($image, $destination_file, 6);
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagegif')) {
                        return imagegif($image, $destination_file);
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        return imagewebp($image, $destination_file, 85);
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("ShutterPress: Error saving image: " . $e->getMessage());
        }
        
        return false;
    }
    
    private function add_shutterstock_watermark($image, $width, $height) {
        if (!$this->gd_available) {
            return;
        }
        
        // Calculate appropriate font size based on image dimensions and admin settings
        $base_font_size = min($width, $height) * ($this->watermark_size / 100);
        $font_size = max(8, min(32, $base_font_size));
        
        // Create semi-transparent white color using admin opacity setting
        $text_color = imagecolorallocatealpha($image, 255, 255, 255, $this->watermark_opacity);
        
        // Add diagonal repeating pattern
        $this->add_diagonal_pattern($image, $width, $height, $font_size, $text_color);
        
        // Add central watermark (slightly larger)
        $this->add_central_watermark($image, $width, $height, $font_size * 1.5, $text_color);
    }
    
    private function add_diagonal_pattern($image, $width, $height, $font_size, $color) {
        if (!$this->gd_available) {
            return;
        }
        
        $pattern_text = $this->watermark_text;
        
        // Calculate text dimensions
        $text_width = $font_size * strlen($pattern_text) * 0.6;
        $text_height = $font_size;
        
        // Use admin settings for spacing
        $spacing_x = $text_width * $this->watermark_spacing;
        $spacing_y = $text_height * ($this->watermark_spacing + 1);
        
        // Calculate how many repetitions we need
        $diagonal_length = sqrt($width * $width + $height * $height);
        $steps = ceil($diagonal_length / ($spacing_x * 0.7));
        
        for ($i = -$steps; $i <= $steps; $i++) {
            for ($j = -$steps; $j <= $steps; $j++) {
                $x = $i * $spacing_x + ($j % 2) * ($spacing_x / 2);
                $y = $j * $spacing_y;
                
                // Skip if outside image bounds (with some margin)
                if ($x < -$text_width || $x > $width + $text_width || 
                    $y < -$text_height || $y > $height + $text_height) {
                    continue;
                }
                
                // Add rotated text with admin angle setting
                if (function_exists('imagettftext') && $this->get_font_path()) {
                    imagettftext($image, $font_size, $this->watermark_angle, $x, $y, $color, $this->get_font_path(), $pattern_text);
                } else {
                    // Fallback to built-in font (no rotation)
                    if (function_exists('imagestring')) {
                        imagestring($image, 3, $x, $y, $pattern_text, $color);
                    }
                }
            }
        }
    }
    
    private function add_central_watermark($image, $width, $height, $font_size, $color) {
        if (!$this->gd_available) {
            return;
        }
        
        $central_text = $this->watermark_text;
        
        if (function_exists('imagettftext') && $this->get_font_path()) {
            // Calculate text position for center
            $text_box = imagettfbbox($font_size, 0, $this->get_font_path(), $central_text);
            $text_width = $text_box[2] - $text_box[0];
            $text_height = $text_box[1] - $text_box[7];
            
            $x = ($width - $text_width) / 2;
            $y = ($height + $text_height) / 2;
            
            imagettftext($image, $font_size, 0, $x, $y, $color, $this->get_font_path(), $central_text);
        } else {
            // Fallback to built-in font
            if (function_exists('imagestring') && function_exists('imagefontwidth') && function_exists('imagefontheight')) {
                $text_width = strlen($central_text) * imagefontwidth(5);
                $text_height = imagefontheight(5);
                
                $x = ($width - $text_width) / 2;
                $y = ($height - $text_height) / 2;
                
                imagestring($image, 5, $x, $y, $central_text, $color);
            }
        }
    }
    
    private function get_font_path() {
        // Try to use a custom font from plugin assets
        $font_path = plugin_dir_path(__FILE__) . '../assets/fonts/DejaVuSans.ttf';
        
        if (file_exists($font_path)) {
            return $font_path;
        }
        
        // Try system fonts
        $system_fonts = [
            '/System/Library/Fonts/DejaVuSans.ttf',                    // macOS
            '/System/Library/Fonts/Helvetica.ttc',               // macOS
            '/Windows/Fonts/arial.ttf',                          // Windows
            '/Windows/Fonts/calibri.ttf',                        // Windows
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',   // Linux
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', // Linux
        ];
        
        foreach ($system_fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        return false; // Use built-in font
    }
    
    public function cleanup_old_watermarks() {
        if (!is_dir($this->watermark_dir)) {
            return;
        }
        
        $files = glob($this->watermark_dir . '*');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'htaccess') {
                // Delete files older than 30 days
                if (($now - filemtime($file)) > (30 * 24 * 60 * 60)) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        // Log cleanup if files were deleted
        if ($deleted > 0) {
            error_log("ShutterPress: Cleaned up {$deleted} old watermark files");
        }
    }
    
    
    // Handle settings updates - clear cache and run debugger
    public function on_settings_updated() {
        // Clear cache first
        $cleared = $this->clear_watermark_cache();
        error_log("ShutterPress: Settings updated - cleared {$cleared} cached files");
        
        // Reload settings
        $this->load_settings();
        
        // Run comprehensive product debugger
        $this->debug_all_products();
    }
    
    // Comprehensive debugger that checks all products
    public function debug_all_products() {
        error_log("ShutterPress: =============== WATERMARK DEBUGGER START ===============");
        error_log("ShutterPress: Running comprehensive product watermark check...");
        
        // Get all products
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish', 
            'numberposts' => -1
        ]);
        
        if (empty($products)) {
            error_log("ShutterPress: No products found in database");
            return;
        }
        
        error_log("ShutterPress: Found " . count($products) . " products to check");
        
        $watermarked_count = 0;
        $not_watermarked_count = 0;
        $error_count = 0;
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) {
                error_log("ShutterPress: Could not load WooCommerce product for ID: " . $product_post->ID);
                $error_count++;
                continue;
            }
            
            $result = $this->debug_single_product($product);
            
            if ($result['should_watermark']) {
                $watermarked_count++;
            } else {
                $not_watermarked_count++;
            }
            
            if (isset($result['error'])) {
                $error_count++;
            }
        }
        
        // Summary
        error_log("ShutterPress: =============== WATERMARK DEBUGGER SUMMARY ===============");
        error_log("ShutterPress: Total products checked: " . count($products));
        error_log("ShutterPress: Products that WILL get watermarks: " . $watermarked_count);
        error_log("ShutterPress: Products that will NOT get watermarks: " . $not_watermarked_count);
        error_log("ShutterPress: Products with errors: " . $error_count);
        error_log("ShutterPress: =============== WATERMARK DEBUGGER END ===============");
    }
    
    // Debug a single product in detail
    public function debug_single_product($product) {
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        $result = array();
        
        error_log("ShutterPress: --- Checking Product ID: {$product_id} | Name: {$product_name} ---");
        
        // Check product type
        $product_type = get_post_meta($product_id, '_shutterpress_product_type', true);
        error_log("ShutterPress: Product type meta: '" . $product_type . "'");
        
        // Check if product has featured image
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            error_log("ShutterPress: Has featured image: ID {$thumbnail_id}");
            $image_file = get_attached_file($thumbnail_id);
            if ($image_file && file_exists($image_file)) {
                error_log("ShutterPress: Featured image file exists: {$image_file}");
            } else {
                error_log("ShutterPress: WARNING - Featured image file missing: {$image_file}");
            }
        } else {
            error_log("ShutterPress: No featured image set");
        }
        
        // Check product tags
        $product_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
        if (!is_wp_error($product_tags) && !empty($product_tags)) {
            error_log("ShutterPress: Product tags: " . implode(', ', $product_tags));
            if (in_array('shutterpress-plan-product', $product_tags)) {
                error_log("ShutterPress: Has shutterpress-plan-product tag");
            }
        } else {
            error_log("ShutterPress: No product tags");
        }
        
        // Check WooCommerce product type
        $wc_product_type = $product->get_type();
        error_log("ShutterPress: WooCommerce product type: {$wc_product_type}");
        
        // Check if downloadable/virtual
        $is_downloadable = $product->is_downloadable();
        $is_virtual = $product->is_virtual();
        error_log("ShutterPress: Is downloadable: " . ($is_downloadable ? 'YES' : 'NO'));
        error_log("ShutterPress: Is virtual: " . ($is_virtual ? 'YES' : 'NO'));
        
        // Check watermark decision
        $should_watermark = $this->should_watermark_product($product);
        $result['should_watermark'] = $should_watermark;
        
        if ($should_watermark) {
            error_log("ShutterPress: ✅ WILL WATERMARK - Product qualifies for watermarking");
            
            // Test watermark generation if has featured image
            if ($thumbnail_id) {
                $watermark_url = $this->get_watermarked_image_url($thumbnail_id);
                if ($watermark_url) {
                    error_log("ShutterPress: ✅ Watermark generation test PASSED");
                } else {
                    error_log("ShutterPress: ❌ Watermark generation test FAILED");
                    $result['error'] = 'Watermark generation failed';
                }
            }
        } else {
            error_log("ShutterPress: ❌ WILL NOT WATERMARK");
            
            // Explain why not
            if (empty($product_type)) {
                error_log("ShutterPress: Reason: No _shutterpress_product_type meta field set");
            } elseif (!in_array($product_type, ['free', 'subscription', 'premium'])) {
                error_log("ShutterPress: Reason: Product type '{$product_type}' not in allowed types (free, subscription, premium)");
            } else {
                error_log("ShutterPress: Reason: Unknown - should_watermark_product() returned false");
            }
        }
        
        error_log("ShutterPress: --- End Product {$product_id} Check ---");
        
        return $result;
    }
    
    public function clear_watermark_cache() {
        if (!is_dir($this->watermark_dir)) {
            return 0;
        }
        
        $files = glob($this->watermark_dir . '*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'htaccess') {
                unlink($file);
                $deleted++;
            }
        }
        
        error_log("ShutterPress: Cleared watermark cache - {$deleted} files deleted");
        return $deleted;
    }
    
    // Public method to get watermark statistics
    public function get_watermark_stats() {
        $stats = [
            'count' => 0,
            'size' => 0,
            'directory' => $this->watermark_dir,
            'gd_available' => $this->gd_available
        ];
        
        if (is_dir($this->watermark_dir)) {
            $files = glob($this->watermark_dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'htaccess') {
                    $stats['count']++;
                    $stats['size'] += filesize($file);
                }
            }
        }
        
        return $stats;
    }
    
    // Public method to check if watermarking is available
    public function is_watermarking_available() {
        return $this->gd_available;
    }
    
    // Debug method for testing watermark generation
    public function debug_watermark_generation($attachment_id, $size = 'full') {
        $debug_info = array();
        
        // Check GD availability
        $debug_info['gd_available'] = $this->gd_available;
        if (!$this->gd_available) {
            $debug_info['error'] = 'GD extension not available';
            return $debug_info;
        }
        
        // Check original file
        $original_file = get_attached_file($attachment_id);
        $debug_info['original_file'] = $original_file;
        $debug_info['original_file_exists'] = file_exists($original_file);
        
        if (!$original_file || !file_exists($original_file)) {
            $debug_info['error'] = 'Original file not found: ' . $original_file;
            return $debug_info;
        }
        
        // Check file permissions
        $debug_info['original_file_readable'] = is_readable($original_file);
        $debug_info['original_file_size'] = filesize($original_file);
        
        // Check watermark directory
        $debug_info['watermark_dir'] = $this->watermark_dir;
        $debug_info['watermark_dir_exists'] = is_dir($this->watermark_dir);
        $debug_info['watermark_dir_writable'] = is_writable($this->watermark_dir);
        
        // Check image info
        $image_info = getimagesize($original_file);
        $debug_info['image_info'] = $image_info;
        
        if (!$image_info) {
            $debug_info['error'] = 'Cannot get image info for: ' . $original_file;
            return $debug_info;
        }
        
        $debug_info['mime_type'] = $image_info['mime'];
        $debug_info['width'] = $image_info[0];
        $debug_info['height'] = $image_info[1];
        
        // Check if image is too small
        if ($image_info[0] < 100 || $image_info[1] < 100) {
            $debug_info['error'] = 'Image too small for watermarking: ' . $image_info[0] . 'x' . $image_info[1];
            return $debug_info;
        }
        
        // Test image resource creation
        $source_image = $this->create_image_resource($original_file, $image_info['mime']);
        $debug_info['image_resource_created'] = ($source_image !== false);
        
        if (!$source_image) {
            $debug_info['error'] = 'Failed to create image resource for: ' . $image_info['mime'];
            return $debug_info;
        }
        
        // Test watermark file generation
        $pathinfo = pathinfo($original_file);
        $settings_hash = md5($this->watermark_text . $this->watermark_opacity . $this->watermark_size . $this->watermark_angle . $this->watermark_spacing);
        $size_suffix = is_array($size) ? $size[0] . 'x' . $size[1] : $size;
        $watermarked_filename = $pathinfo['filename'] . '_watermarked_' . $size_suffix . '_' . $settings_hash . '.' . $pathinfo['extension'];
        $watermarked_file = $this->watermark_dir . $watermarked_filename;
        
        $debug_info['watermarked_filename'] = $watermarked_filename;
        $debug_info['watermarked_file'] = $watermarked_file;
        
        // Try to generate watermark
        $generation_result = $this->generate_watermarked_image($original_file, $watermarked_file);
        $debug_info['generation_successful'] = $generation_result;
        $debug_info['watermarked_file_exists'] = file_exists($watermarked_file);
        
        if ($generation_result && file_exists($watermarked_file)) {
            $debug_info['watermarked_file_size'] = filesize($watermarked_file);
            $debug_info['success'] = true;
        } else {
            $debug_info['error'] = 'Watermark generation failed';
        }
        
        // Clean up
        if ($source_image) {
            imagedestroy($source_image);
        }
        
        return $debug_info;
    }
    
    // System status check method
    public function get_system_status() {
        $status = array();
        
        // GD Extension
        $status['gd_extension'] = array(
            'available' => extension_loaded('gd'),
            'functions' => array(
                'imagecreatefromjpeg' => function_exists('imagecreatefromjpeg'),
                'imagecreatefrompng' => function_exists('imagecreatefrompng'),
                'imagecreatefromgif' => function_exists('imagecreatefromgif'),
                'imagecreatefromwebp' => function_exists('imagecreatefromwebp'),
                'imagettftext' => function_exists('imagettftext'),
            )
        );
        
        // Directory Status
        $status['directories'] = array(
            'watermark_dir' => $this->watermark_dir,
            'exists' => is_dir($this->watermark_dir),
            'writable' => is_writable($this->watermark_dir),
            'permissions' => is_dir($this->watermark_dir) ? substr(sprintf('%o', fileperms($this->watermark_dir)), -4) : 'N/A'
        );
        
        // Font Status
        $status['fonts'] = array(
            'font_path' => $this->get_font_path(),
            'font_available' => ($this->get_font_path() !== false)
        );
        
        // Settings
        $status['settings'] = array(
            'text' => $this->watermark_text,
            'opacity' => $this->watermark_opacity,
            'size' => $this->watermark_size,
            'angle' => $this->watermark_angle,
            'spacing' => $this->watermark_spacing
        );
        
        return $status;
    }
}

// Initialize the watermark handler
global $shutterpress_watermark_handler;
$shutterpress_watermark_handler = new ShutterPress_Watermark_Handler();