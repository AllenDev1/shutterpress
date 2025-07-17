<?php
// File: includes/admin/watermark-settings.php

// Don't execute directly - only when called from admin page
if (!defined('ABSPATH')) {
    exit;
}

function shutterpress_render_watermark_settings_page()
{
    // Handle form submissions
    if (isset($_POST['submit'])) {
        // Verify nonce for security
        if (!isset($_POST['watermark_settings_nonce']) || !wp_verify_nonce($_POST['watermark_settings_nonce'], 'watermark_settings_action')) {
            wp_die('Security check failed');
        }

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

        echo '<div class="notice notice-success"><p>Watermark settings saved successfully!</p></div>';
    }

    // Handle cache clearing
    if (isset($_POST['clear_cache'])) {
        // Verify nonce for security
        if (!isset($_POST['clear_cache_nonce']) || !wp_verify_nonce($_POST['clear_cache_nonce'], 'clear_cache_action')) {
            wp_die('Security check failed');
        }

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

        echo '<div class="notice notice-success"><p>Watermark cache cleared! ' . $cleared . ' files removed.</p></div>';
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

                    <form method="post" action="">
                        <?php wp_nonce_field('watermark_settings_action', 'watermark_settings_nonce'); ?>

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

                        <?php submit_button('Save Watermark Settings'); ?>
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

                    <form method="post" action="">
                        <?php wp_nonce_field('clear_cache_action', 'clear_cache_nonce'); ?>
                        <input type="hidden" name="clear_cache" value="1" />
                        <p class="description">
                            Clear the watermark cache to force regeneration of all watermarked images.
                            This is useful when you change watermark settings.
                        </p>
                        <?php submit_button('Clear Watermark Cache', 'secondary'); ?>
                    </form>
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