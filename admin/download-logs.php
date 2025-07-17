<?php
// admin/download-logs.php

// Ensure WordPress functions are loaded
if (!function_exists('wp_get_current_user')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;
$logs_table   = $wpdb->prefix . 'shutterpress_download_logs';
$users_table  = $wpdb->users;
$posts_table  = $wpdb->posts;

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['log_id'])) {
    $log_id = absint($_GET['log_id']);
    check_admin_referer('delete_log_' . $log_id);
    $wpdb->delete($logs_table, ['id' => $log_id]);
    echo '<div class="notice notice-success"><p>Download log deleted.</p></div>';
}

// Fetch logs
$logs = $wpdb->get_results("SELECT l.*, u.display_name, p.post_title
    FROM $logs_table l
    LEFT JOIN $users_table u ON l.user_id = u.ID
    LEFT JOIN $posts_table p ON l.product_id = p.ID
    ORDER BY l.download_time DESC
    LIMIT 100");
?>

<div class="wrap">
    <h1>Download Logs</h1>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Product</th>
                <th>Download Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->display_name ?: 'User #' . $log->user_id); ?></td>
                        <td><?php echo esc_html($log->post_title ?: 'Product #' . $log->product_id); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->download_time))); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=shutterpress_download_logs&action=delete&log_id=' . $log->id), 'delete_log_' . $log->id); ?>"
                               class="button button-small delete">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No downloads logged yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
