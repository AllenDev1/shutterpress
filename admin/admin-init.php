<?php

// Register ShutterPress admin menu
add_action('admin_menu', 'shutterpress_register_admin_menu');

function shutterpress_register_admin_menu()
{
    add_menu_page(
        'ShutterPress',
        'ShutterPress',
        'manage_options',
        'shutterpress',
        'shutterpress_render_create_plan_page',
        'dashicons-admin-generic',
        56
    );

    add_submenu_page(
        'shutterpress',
        'User Quotas',
        'User Quotas',
        'manage_options',
        'shutterpress_user_quotas',
        'shutterpress_render_user_quotas_page'
    );

    add_submenu_page(
        'shutterpress',
        'All Plans',
        'All Plans',
        'manage_options',
        'shutterpress_all_plans',
        'shutterpress_render_all_plans_page'
    );

    add_submenu_page(
        'shutterpress',
        'Download Logs',
        'Download Logs',
        'manage_options',
        'shutterpress_download_logs',
        'shutterpress_render_download_logs_page'
    );

    add_submenu_page(
        'shutterpress',
        'Watermark Settings',
        'Watermark Settings',
        'manage_options',
        'shutterpress-watermark',
        'shutterpress_render_watermark_settings_page'
    );

    add_submenu_page(
        'shutterpress',
        'Wasabi Management',
        'Wasabi Management',
        'manage_options',
        'shutterpress-wasabi',
        'shutterpress_render_wasabi_management_page'
    );

    add_submenu_page(
        'shutterpress',
        'ShutterPress Settings',
        'Settings',
        'manage_options',
        'shutterpress-settings',
        'shutterpress_settings_page_callback'
    );
}

// Include admin logic files
require_once plugin_dir_path(__FILE__) . 'plans.php';
require_once plugin_dir_path(__FILE__) . 'user-quotas.php';
require_once plugin_dir_path(__FILE__) . 'product-meta.php';
require_once plugin_dir_path(__FILE__) . 'settings.php';
require_once plugin_dir_path(__FILE__) . 'watermark-settings.php';

// Hooked callbacks for submenus
function shutterpress_render_download_logs_page()
{
    require_once plugin_dir_path(__FILE__) . 'download-logs.php';
}

// Wasabi Management page callback
function shutterpress_render_wasabi_management_page()
{
    global $wpdb;

    // Get statistics
    $total_media = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
    $wasabi_uploads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_shutterpress_wasabi_uploaded' AND meta_value = '1'");
    $local_files = $total_media - $wasabi_uploads;

    ?>
    <div class="wrap">
        <h1>Wasabi Storage Management</h1>

        <div class="card" style="max-width: 600px;">
            <h2>Storage Overview</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Total Media Files:</strong></td>
                    <td><?php echo number_format($total_media); ?></td>
                </tr>
                <tr>
                    <td><strong>Files on Wasabi:</strong></td>
                    <td><?php echo number_format($wasabi_uploads); ?></td>
                </tr>
                <tr>
                    <td><strong>Local Files:</strong></td>
                    <td><?php echo number_format($local_files); ?></td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>How It Works</h2>
            <ul>
                <li><strong>Preview Images:</strong> Stay local for watermarking</li>
                <li><strong>Downloadable Files:</strong> Automatically uploaded to Wasabi when assigned to products</li>
                <li><strong>Manual Control:</strong> Check "Upload to Wasabi" in media library for specific files</li>
                <li><strong>Bulk Actions:</strong> Select multiple files and use "Upload to Wasabi" bulk action</li>
            </ul>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Usage Guidelines</h2>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0;">✅ Upload to Wasabi:</h4>
                <ul>
                    <li>High-resolution downloadable files for products</li>
                    <li>Files that need secure, private storage</li>
                    <li>Large files to save local server space</li>
                </ul>

                <h4>❌ Keep Local:</h4>
                <ul>
                    <li>Product preview images (for watermarking)</li>
                    <li>Featured images and gallery images</li>
                    <li>Images used in posts/pages</li>
                    <li>Theme assets and logos</li>
                </ul>
            </div>
        </div>

        <?php if ($wasabi_uploads > 0): ?>
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Recent Wasabi Uploads</h2>
                <?php
                $recent_uploads = $wpdb->get_results("
                SELECT p.ID, p.post_title, p.post_date, pm.meta_value
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_shutterpress_wasabi_uploaded' AND pm.meta_value = '1'
                AND p.post_type = 'attachment'
                ORDER BY p.post_date DESC
                LIMIT 10
            ");

                if ($recent_uploads): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Upload Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_uploads as $upload): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($upload->post_title ?: 'Untitled'); ?></strong>
                                        <br><small>ID: <?php echo $upload->ID; ?></small>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($upload->post_date)); ?></td>
                                    <td><span style="color: green;">✓ On Wasabi</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Remove the direct function call - it's now handled by the include
// The function shutterpress_render_watermark_settings_page() is now available from the include
