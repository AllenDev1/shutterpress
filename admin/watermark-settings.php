<?php
// File: includes/admin/watermark-settings.php

// Don't execute directly - only when called from admin page
if (!defined('ABSPATH')) {
    exit;
}

function shutterpress_render_watermark_settings_page()
{
    $message = '';
    $message_type = '';

    // Handle form submissions with a single nonce for both actions
    if (isset($_POST['action']) || isset($_POST['save_and_clear']) || isset($_POST['test_watermark']) || isset($_POST['debug_all_products'])) {
        // Verify nonce for security - using single nonce for all actions
        if (!isset($_POST['shutterpress_nonce']) || !wp_verify_nonce($_POST['shutterpress_nonce'], 'shutterpress_admin_action')) {
            wp_die('Security check failed');
        }

        // Determine which action to take
        $action = 'save_settings'; // default
        if (isset($_POST['save_and_clear'])) {
            $action = 'save_and_clear';
        } elseif (isset($_POST['test_watermark'])) {
            $action = 'test_watermark';
        } elseif (isset($_POST['debug_all_products'])) {
            $action = 'debug_all_products';
        } elseif (isset($_POST['action'])) {
            $action = $_POST['action'];
        }

        switch ($action) {
            case 'save_settings':
                if (isset($_POST['watermark_text'])) {
                    update_option('shutterpress_watermark_text', sanitize_text_field($_POST['watermark_text']));
                }
                if (isset($_POST['watermark_opacity'])) {
                    update_option('shutterpress_watermark_opacity', intval($_POST['watermark_opacity']));
                }
                if (isset($_POST['watermark_size'])) {
                    update_option('shutterpress_watermark_size', floatval($_POST['watermark_size']));
                }
                if (isset($_POST['watermark_angle'])) {
                    update_option('shutterpress_watermark_angle', intval($_POST['watermark_angle']));
                }
                if (isset($_POST['watermark_spacing'])) {
                    update_option('shutterpress_watermark_spacing', floatval($_POST['watermark_spacing']));
                }

                $message = 'Watermark settings saved successfully!';
                $message_type = 'success';
                break;

            case 'clear_cache':
                $upload_dir = wp_upload_dir();
                $watermark_dir = $upload_dir['basedir'] . '/shutterpress-watermarks/';

                $cleared = 0;
                if (is_dir($watermark_dir)) {
                    $files = glob($watermark_dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'htaccess') {
                            unlink($file);
                            $cleared++;
                        }
                    }
                }

                $message = 'Watermark cache cleared! ' . $cleared . ' files removed.';
                $message_type = 'success';
                break;

            case 'save_and_clear':
                // Save settings first
                if (isset($_POST['watermark_text'])) {
                    update_option('shutterpress_watermark_text', sanitize_text_field($_POST['watermark_text']));
                }
                if (isset($_POST['watermark_opacity'])) {
                    update_option('shutterpress_watermark_opacity', intval($_POST['watermark_opacity']));
                }
                if (isset($_POST['watermark_size'])) {
                    update_option('shutterpress_watermark_size', floatval($_POST['watermark_size']));
                }
                if (isset($_POST['watermark_angle'])) {
                    update_option('shutterpress_watermark_angle', intval($_POST['watermark_angle']));
                }
                if (isset($_POST['watermark_spacing'])) {
                    update_option('shutterpress_watermark_spacing', floatval($_POST['watermark_spacing']));
                }

                // Then clear cache
                $upload_dir = wp_upload_dir();
                $watermark_dir = $upload_dir['basedir'] . '/shutterpress-watermarks/';

                $cleared = 0;
                if (is_dir($watermark_dir)) {
                    $files = glob($watermark_dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'htaccess') {
                            unlink($file);
                            $cleared++;
                        }
                    }
                }

                $message = 'Settings saved and cache cleared! ' . $cleared . ' files removed.';
                $message_type = 'success';
                break;

            case 'test_watermark':
                // Just set a flag that we want to show test results
                $show_test_results = true;
                break;

            case 'debug_all_products':
                // Get watermark handler instance
                global $shutterpress_watermark_handler;
                if (!$shutterpress_watermark_handler && class_exists('ShutterPress_Watermark_Handler')) {
                    $shutterpress_watermark_handler = new ShutterPress_Watermark_Handler();
                }
                
                // Run the comprehensive debugger
                if ($shutterpress_watermark_handler && method_exists($shutterpress_watermark_handler, 'debug_all_products')) {
                    $shutterpress_watermark_handler->debug_all_products();
                    $message = 'Product debugging completed! Check your error logs for detailed results.';
                    $message_type = 'success';
                } else {
                    $message = 'Debug method not available. Please update your watermark-handler.php file.';
                    $message_type = 'error';
                }
                break;
        }
    }

    // Display message if any
    if (!empty($message)) {
        echo '<div class="notice notice-' . esc_attr($message_type) . '"><p>' . esc_html($message) . '</p></div>';
    }

    // Get current settings
    $watermark_text = get_option('shutterpress_watermark_text', 'ShutterPress');
    $watermark_opacity = get_option('shutterpress_watermark_opacity', 90);
    $watermark_size = get_option('shutterpress_watermark_size', 2.5);
    $watermark_angle = get_option('shutterpress_watermark_angle', 45);
    $watermark_spacing = get_option('shutterpress_watermark_spacing', 2.0);

    // Get watermark statistics
    $upload_dir = wp_upload_dir();
    $watermark_dir = $upload_dir['basedir'] . '/shutterpress-watermarks/';
    $watermark_count = 0;
    $watermark_size_total = 0;

    if (is_dir($watermark_dir)) {
        $files = glob($watermark_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'htaccess') {
                $watermark_count++;
                $watermark_size_total += filesize($file);
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>ShutterPress Watermark Settings</h1>

        <div class="shutterpress-admin-wrapper">
            <div class="shutterpress-admin-main">
                <div class="shutterpress-admin-card">
                    <h2>Watermark Configuration</h2>

                    <form method="post" action="" id="watermark-settings-form">
                        <?php wp_nonce_field('shutterpress_admin_action', 'shutterpress_nonce'); ?>
                        <input type="hidden" name="action" value="save_settings" />

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="watermark_text">Watermark Text</label>
                                </th>
                                <td>
                                    <input type="text" id="watermark_text" name="watermark_text"
                                        value="<?php echo esc_attr($watermark_text); ?>" class="regular-text"
                                        placeholder="ShutterPress" />
                                    <p class="description">The text that will appear on watermarked images</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="watermark_opacity">Opacity</label>
                                </th>
                                <td>
                                    <input type="range" id="watermark_opacity" name="watermark_opacity" min="30" max="127"
                                        value="<?php echo esc_attr($watermark_opacity); ?>" class="regular-text" />
                                    <span id="opacity_value"><?php echo esc_html($watermark_opacity); ?></span>
                                    <p class="description">Lower values = more transparent (30-127, default: 90)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="watermark_size">Size (% of image)</label>
                                </th>
                                <td>
                                    <input type="number" id="watermark_size" name="watermark_size" min="1" max="10"
                                        step="0.1" value="<?php echo esc_attr($watermark_size); ?>" class="small-text" />%
                                    <p class="description">Watermark size as percentage of image dimensions (1-10%, default:
                                        2.5%)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="watermark_angle">Angle</label>
                                </th>
                                <td>
                                    <input type="number" id="watermark_angle" name="watermark_angle" min="-90" max="90"
                                        value="<?php echo esc_attr($watermark_angle); ?>" class="small-text" />°
                                    <p class="description">Rotation angle for watermark text (-90 to 90 degrees, default:
                                        45°)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="watermark_spacing">Spacing Multiplier</label>
                                </th>
                                <td>
                                    <input type="number" id="watermark_spacing" name="watermark_spacing" min="1" max="5"
                                        step="0.1" value="<?php echo esc_attr($watermark_spacing); ?>"
                                        class="small-text" />x
                                    <p class="description">Spacing between watermark repetitions (1-5x, default: 2x)</p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                            <input type="submit" name="save_and_clear" id="save-and-clear-btn" class="button button-secondary" value="Save & Clear Cache" style="margin-left: 10px;" />
                        </p>
                    </form>
                </div>

                <div class="shutterpress-admin-card">
                    <h2>Cache Management</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Cached Watermarks</th>
                            <td>
                                <strong><?php echo number_format($watermark_count); ?></strong> files
                                <p class="description">Total watermarked images currently cached</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Cache Size</th>
                            <td>
                                <strong><?php echo size_format($watermark_size_total); ?></strong>
                                <p class="description">Total disk space used by watermark cache</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Cache Location</th>
                            <td>
                                <code><?php echo esc_html($watermark_dir); ?></code>
                                <p class="description">Directory where watermarked images are stored</p>
                            </td>
                        </tr>
                    </table>

                    <form method="post" action="" id="clear-cache-form">
                        <?php wp_nonce_field('shutterpress_admin_action', 'shutterpress_nonce'); ?>
                        <input type="hidden" name="action" value="clear_cache" />
                        <p class="description">
                            Clear the watermark cache to force regeneration of all watermarked images.
                            This is useful when you change watermark settings.
                        </p>
                        <?php submit_button('Clear Watermark Cache', 'secondary'); ?>
                    </form>
                </div>

                <div class="shutterpress-admin-card">
                    <h2>System Diagnostics</h2>
                    
                    <?php
                    // Get watermark handler instance for diagnostics
                    global $shutterpress_watermark_handler;
                    if (!$shutterpress_watermark_handler) {
                        // Try to manually instantiate if not available
                        if (class_exists('ShutterPress_Watermark_Handler')) {
                            $shutterpress_watermark_handler = new ShutterPress_Watermark_Handler();
                        }
                    }
                    
                    // Check if we have the debug method
                    if (method_exists($shutterpress_watermark_handler, 'get_system_status')) {
                        $system_status = $shutterpress_watermark_handler->get_system_status();
                        ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">GD Extension</th>
                                <td>
                                    <?php 
                                    if ($system_status['gd_extension']['available']) {
                                        echo '<span style="color: green;">✓ Available</span>';
                                    } else {
                                        echo '<span style="color: red;">✗ Not Available</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Watermark Directory</th>
                                <td>
                                    <?php 
                                    if ($system_status['directories']['exists'] && $system_status['directories']['writable']) {
                                        echo '<span style="color: green;">✓ Writable</span>';
                                    } elseif ($system_status['directories']['exists']) {
                                        echo '<span style="color: orange;">⚠ Exists but not writable</span>';
                                    } else {
                                        echo '<span style="color: red;">✗ Does not exist</span>';
                                    }
                                    ?>
                                    <br><small><?php echo esc_html($system_status['directories']['watermark_dir']); ?></small>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Font Support</th>
                                <td>
                                    <?php 
                                    if ($system_status['fonts']['font_available']) {
                                        echo '<span style="color: green;">✓ TTF Font Available</span>';
                                        echo '<br><small>' . esc_html($system_status['fonts']['font_path']) . '</small>';
                                    } else {
                                        echo '<span style="color: orange;">⚠ Using Built-in Font</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                        
                        <?php 
                        if (isset($show_test_results) && $show_test_results) {
                            ?>
                            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                                <h4>Watermark Test Results:</h4>
                                <?php
                                global $wpdb;
                                // Find a product image to test with
                                $test_attachment = $wpdb->get_var("
                                    SELECT p.ID FROM {$wpdb->posts} p 
                                    WHERE p.post_type = 'attachment' 
                                    AND p.post_mime_type LIKE 'image/%' 
                                    LIMIT 1
                                ");
                                
                                if ($test_attachment && method_exists($shutterpress_watermark_handler, 'debug_watermark_generation')) {
                                    $debug_result = $shutterpress_watermark_handler->debug_watermark_generation($test_attachment);
                                    
                                    if (isset($debug_result['success']) && $debug_result['success']) {
                                        echo '<p style="color: green;"><strong>✓ Watermark generation test PASSED</strong></p>';
                                    } else {
                                        echo '<p style="color: red;"><strong>✗ Watermark generation test FAILED</strong></p>';
                                        if (isset($debug_result['error'])) {
                                            echo '<p><strong>Error:</strong> ' . esc_html($debug_result['error']) . '</p>';
                                        }
                                    }
                                    
                                    echo '<details><summary>View detailed test results</summary>';
                                    echo '<pre style="background: white; padding: 10px; margin: 10px 0; overflow-x: auto;">';
                                    print_r($debug_result);
                                    echo '</pre></details>';
                                } else {
                                    echo '<p style="color: orange;">No test image found or debug method not available.</p>';
                                }
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                        
                        <form method="post" action="">
                            <?php wp_nonce_field('shutterpress_admin_action', 'shutterpress_nonce'); ?>
                            <input type="hidden" name="test_watermark" value="1" />
                            <p>
                                <input type="submit" class="button button-secondary" value="Test Watermark Generation" />
                            </p>
                        </form>

                        <form method="post" action="" style="margin-top: 15px;">
                            <?php wp_nonce_field('shutterpress_admin_action', 'shutterpress_nonce'); ?>
                            <input type="hidden" name="debug_all_products" value="1" />
                            <p>
                                <input type="submit" class="button button-primary" value="Debug All Products" style="background-color: #e74c3c; border-color: #c0392b;" />
                            </p>
                            <p class="description">
                                This will check ALL products and log detailed information about which products get watermarks and why. 
                                Check your error logs after running this. <strong>Use only for debugging!</strong>
                            </p>
                        </form>
                        
                        <?php
                    } else {
                        echo '<p style="color: orange;">⚠ Diagnostic methods not available. Please update your watermark-handler.php file.</p>';
                    }
                    ?>
                </div>
            </div>

            <div class="shutterpress-admin-sidebar">
                <div class="shutterpress-admin-card">
                    <h3>How Watermarks Work</h3>
                    <ul>
                        <li><strong>Automatic:</strong> Watermarks are automatically applied to all product images</li>
                        <li><strong>Product Types:</strong> Applied to free, subscription, and premium products</li>
                        <li><strong>Downloads Protected:</strong> Downloadable files remain unwatermarked</li>
                        <li><strong>Cached:</strong> Watermarked images are cached for performance</li>
                        <li><strong>Responsive:</strong> Watermarks scale with image size</li>
                    </ul>
                </div>

                <div class="shutterpress-admin-card">
                    <h3>Watermark Preview</h3>
                    <div id="watermark-preview" style="
                        background: #f0f0f0; 
                        height: 200px; 
                        position: relative; 
                        overflow: hidden;
                        border: 1px solid #ddd;
                        margin-bottom: 10px;
                    ">
                        <div id="preview-text" style="
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%) rotate(<?php echo esc_attr($watermark_angle); ?>deg);
                            color: rgba(255, 255, 255, <?php echo esc_attr((127 - $watermark_opacity) / 127); ?>);
                            font-size: 16px;
                            font-weight: bold;
                            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
                        ">
                            <?php echo esc_html($watermark_text); ?>
                        </div>
                    </div>
                    <p class="description">Preview of how your watermark will appear</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const opacitySlider = document.getElementById('watermark_opacity');
            const opacityValue = document.getElementById('opacity_value');
            const previewText = document.getElementById('preview-text');

            // Update opacity display
            opacitySlider.addEventListener('input', function () {
                opacityValue.textContent = this.value;

                // Update preview (convert from 0-127 to 0-1)
                const opacity = (127 - this.value) / 127;
                previewText.style.color = `rgba(255, 255, 255, ${opacity})`;
            });

            // Update preview text
            const textInput = document.getElementById('watermark_text');
            textInput.addEventListener('input', function () {
                previewText.textContent = this.value || 'ShutterPress';
            });

            // Update preview angle
            const angleInput = document.getElementById('watermark_angle');
            angleInput.addEventListener('input', function () {
                previewText.style.transform = `translate(-50%, -50%) rotate(${this.value}deg)`;
            });
        });
    </script>

    <style>
        .shutterpress-admin-wrapper {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .shutterpress-admin-main {
            flex: 2;
        }

        .shutterpress-admin-sidebar {
            flex: 1;
        }

        .shutterpress-admin-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            margin-bottom: 20px;
        }

        .shutterpress-admin-card h2,
        .shutterpress-admin-card h3 {
            margin-top: 0;
        }

        .shutterpress-admin-card ul {
            padding-left: 20px;
        }

        .shutterpress-admin-card li {
            margin-bottom: 8px;
        }

        #watermark_opacity {
            width: 200px;
            margin-right: 10px;
        }

        #opacity_value {
            font-weight: bold;
            color: #0073aa;
        }
    </style>
    <?php
}
?>