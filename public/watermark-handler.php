<?php
// File: includes/public/watermark-handler.php

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
        
        // Add settings update hook to clear cache
        add_action('update_option_shutterpress_watermark_text', array($this, 'clear_watermark_cache'));
        add_action('update_option_shutterpress_watermark_opacity', array($this, 'clear_watermark_cache'));
        add_action('update_option_shutterpress_watermark_size', array($this, 'clear_watermark_cache'));
        add_action('update_option_shutterpress_watermark_angle', array($this, 'clear_watermark_cache'));
        add_action('update_option_shutterpress_watermark_spacing', array($this, 'clear_watermark_cache'));
    }
    
    public function watermark_product_image($html, $post_thumbnail_id) {
        global $product;
        
        if (!$this->gd_available || !$this->should_watermark_product($product)) {
            return $html;
        }
        
        $watermarked_url = $this->get_watermarked_image_url($post_thumbnail_id);
        if ($watermarked_url) {
            $html = preg_replace('/src="([^"]*)"/', 'src="' . esc_url($watermarked_url) . '" data-original-src="$1"', $html);
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
        }
        
        return $image;
    }
    
    private function should_watermark_product($product) {
        if (!$product || !is_object($product)) {
            return false;
        }
        
        $product_type = get_post_meta($product->get_id(), '_shutterpress_product_type', true);
        
        // Watermark all ShutterPress product types
        if (in_array($product_type, ['free', 'subscription', 'premium'])) {
            return true;
        }
        
        return false;
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
            return false;
        }
        
        $original_file = get_attached_file($attachment_id);
        if (!$original_file || !file_exists($original_file)) {
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
                    $upload_dir = wp_upload_dir();
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
        
        // Generate watermarked version
        if ($this->generate_watermarked_image($source_file, $watermarked_file)) {
            return $watermarked_url;
        }
        
        return false;
    }
    
    private function generate_watermarked_image($source_file, $destination_file) {
        if (!$this->gd_available || !file_exists($source_file)) {
            return false;
        }
        
        $image_info = getimagesize($source_file);
        if (!$image_info) {
            return false;
        }
        
        $mime_type = $image_info['mime'];
        $width = $image_info[0];
        $height = $image_info[1];
        
        // Skip very small images
        if ($width < 100 || $height < 100) {
            return false;
        }
        
        // Create image resource
        $source_image = $this->create_image_resource($source_file, $mime_type);
        if (!$source_image) {
            return false;
        }
        
        // Add Shutterstock-style watermark pattern
        $this->add_shutterstock_watermark($source_image, $width, $height);
        
        // Save watermarked image
        $result = $this->save_image_resource($source_image, $destination_file, $mime_type);
        
        imagedestroy($source_image);
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
        $font_path = plugin_dir_path(__FILE__) . '../assets/fonts/arial.ttf';
        
        if (file_exists($font_path)) {
            return $font_path;
        }
        
        // Try system fonts
        $system_fonts = [
            '/System/Library/Fonts/Arial.ttf',                    // macOS
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
}

// Initialize the watermark handler
new ShutterPress_Watermark_Handler();