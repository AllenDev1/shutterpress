<?php
if (!function_exists('wp_get_current_user')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

require_once plugin_dir_path(__FILE__) . 'class-shutterpress-download-logs-table.php';

global $wpdb;
$logs_table = $wpdb->prefix . 'shutterpress_download_logs';

// Handle single delete action
if (isset($_GET['action'], $_GET['log_id']) && $_GET['action'] === 'delete') {
    $log_id = absint($_GET['log_id']);
    check_admin_referer('delete_log_' . $log_id);

    $result = $wpdb->delete($logs_table, ['id' => $log_id], ['%d']);

    if ($result !== false) {
        echo '<div class="notice notice-success"><p>Download log deleted successfully.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Error deleting download log.</p></div>';
    }
}

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['log_ids'])) {
    $log_ids = array_map('absint', $_POST['log_ids']);

    if (!empty($log_ids)) {
        $ids_placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM $logs_table WHERE id IN ($ids_placeholders)", ...$log_ids));

        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . count($log_ids) . ' download logs deleted successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error deleting download logs.</p></div>';
        }
    }
}

// Handle bulk actions from dropdown
if (isset($_POST['action2']) && $_POST['action2'] === 'delete' && isset($_POST['log_ids'])) {
    $log_ids = array_map('absint', $_POST['log_ids']);

    if (!empty($log_ids)) {
        $ids_placeholders = implode(',', array_fill(0, count($log_ids), '%d'));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM $logs_table WHERE id IN ($ids_placeholders)", ...$log_ids));

        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . count($log_ids) . ' download logs deleted successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error deleting download logs.</p></div>';
        }
    }
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'download-logs-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['ID', 'User', 'Email', 'Product', 'Download Time', 'IP Address', 'User Agent']);

    // Get all logs (apply same filters as table)
    $where_clauses = [];
    $params = [];

    if (!empty($_GET['filter_user'])) {
        $user_search = sanitize_text_field($_GET['filter_user']);
        $user = get_user_by('login', $user_search);
        if (!$user) {
            $user = get_user_by('email', $user_search);
        }
        if ($user) {
            $where_clauses[] = 'l.user_id = %d';
            $params[] = $user->ID;
        } else {
            $where_clauses[] = '(u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
            $params[] = '%' . $user_search . '%';
            $params[] = '%' . $user_search . '%';
            $params[] = '%' . $user_search . '%';
        }
    }

    if (!empty($_GET['filter_product'])) {
        $product_search = sanitize_text_field($_GET['filter_product']);
        if (is_numeric($product_search)) {
            $where_clauses[] = 'l.product_id = %d';
            $params[] = absint($product_search);
        } else {
            $where_clauses[] = 'p.post_title LIKE %s';
            $params[] = '%' . $product_search . '%';
        }
    }

    $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $sql = "
        SELECT l.*, u.display_name, u.user_login, u.user_email, p.post_title
        FROM $logs_table l
        LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
        LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
        $where_sql
        ORDER BY l.download_time DESC
    ";

    if (!empty($params)) {
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $results = $wpdb->get_results($sql);
    }

    foreach ($results as $row) {
        fputcsv($output, [
            $row->id,
            $row->display_name ?: $row->user_login ?: 'User #' . $row->user_id,
            $row->user_email ?: 'N/A',
            $row->post_title ?: 'Product #' . $row->product_id,
            $row->download_time,
            $row->ip_address ?? 'N/A',
            $row->user_agent ?? 'N/A'
        ]);
    }

    fclose($output);
    exit;
}

$table = new ShutterPress_Download_Logs_Table();
$table->prepare_items();

// Get statistics
$stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total_downloads,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT product_id) as unique_products,
        COUNT(CASE WHEN download_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as downloads_last_week
    FROM $logs_table
");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Download Logs</h1>

    <a href="<?php echo admin_url('admin.php?page=shutterpress_download_logs&export=csv&' . http_build_query($_GET)); ?>"
        class="page-title-action">Export CSV</a>

    <hr class="wp-header-end">

    <!-- Statistics Dashboard -->
    <div class="postbox" style="margin-top: 20px;">
        <h3 class="hndle">Statistics</h3>
        <div class="inside">
            <div style="display: flex; gap: 20px;">
                <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px; flex: 1;">
                    <h4>Total Downloads</h4>
                    <p style="font-size: 24px; margin: 0;"><?php echo number_format($stats->total_downloads ?? 0); ?>
                    </p>
                </div>
                <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px; flex: 1;">
                    <h4>Unique Users</h4>
                    <p style="font-size: 24px; margin: 0;"><?php echo number_format($stats->unique_users ?? 0); ?></p>
                </div>
                <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px; flex: 1;">
                    <h4>Unique Products</h4>
                    <p style="font-size: 24px; margin: 0;"><?php echo number_format($stats->unique_products ?? 0); ?>
                    </p>
                </div>
                <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px; flex: 1;">
                    <h4>Downloads (Last 7 Days)</h4>
                    <p style="font-size: 24px; margin: 0;">
                        <?php echo number_format($stats->downloads_last_week ?? 0); ?></p>
                </div>
            </div>
        </div>
    </div>

    <form method="get" id="download-logs-filter">
        <input type="hidden" name="page" value="shutterpress_download_logs" />
        <?php $table->views(); ?>
        <?php $table->display(); ?>
    </form>
</div>

<style>
    .stat-box h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #666;
    }

    .tablenav .alignleft.actions {
        margin-bottom: 10px;
    }

    .tablenav .alignleft.actions input[type="text"],
    .tablenav .alignleft.actions input[type="date"],
    .tablenav .alignleft.actions select {
        margin-right: 5px;
        margin-bottom: 5px;
    }
</style>

<script>
    jQuery(document).ready(function ($) {
        // Auto-submit form when filters change
        $('#download-logs-filter input[type="date"], #download-logs-filter select[name="filter_status"]').on('change', function () {
            $('#download-logs-filter').submit();
        });

        // Clear form when clear button is clicked
        $('.clear-filters').on('click', function (e) {
            e.preventDefault();
            $('#download-logs-filter')[0].reset();
            window.location.href = '<?php echo admin_url('admin.php?page=shutterpress_download_logs'); ?>';
        });
    });
</script>