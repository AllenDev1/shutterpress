<?php
defined('ABSPATH') || exit;

function shutterpress_render_user_quotas_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_user_quotas';
    $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';

    // Include the table class
    require_once plugin_dir_path(__FILE__) . 'class-shutterpress-user-quotas-table.php';

    // Handle single delete action
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
        $id = intval($_GET['id']);
        check_admin_referer('delete_quota_' . $id);

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        if ($result !== false) {
            echo '<div class="notice notice-success"><p>Quota deleted successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error deleting quota.</p></div>';
        }
    }

    // Handle bulk actions
    if (isset($_POST['action']) && isset($_POST['quota_ids']) && !empty($_POST['quota_ids'])) {
        $quota_ids = array_map('absint', $_POST['quota_ids']);
        $action = sanitize_text_field($_POST['action']);

        if (!empty($quota_ids)) {
            switch ($action) {
                case 'delete':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas deleted successfully.</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Error deleting quotas.</p></div>';
                    }
                    break;

                case 'activate':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'active' WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas activated successfully.</p></div>';
                    }
                    break;

                case 'deactivate':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'expired' WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas deactivated successfully.</p></div>';
                    }
                    break;

                case 'reset_quota':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("UPDATE $table SET quota_used = 0 WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas reset successfully.</p></div>';
                    }
                    break;
            }
        }
    }

    // Handle bulk actions from dropdown (action2)
    if (isset($_POST['action2']) && isset($_POST['quota_ids']) && !empty($_POST['quota_ids'])) {
        $quota_ids = array_map('absint', $_POST['quota_ids']);
        $action = sanitize_text_field($_POST['action2']);

        if (!empty($quota_ids)) {
            switch ($action) {
                case 'delete':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas deleted successfully.</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Error deleting quotas.</p></div>';
                    }
                    break;

                case 'activate':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'active' WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas activated successfully.</p></div>';
                    }
                    break;

                case 'deactivate':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'expired' WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas deactivated successfully.</p></div>';
                    }
                    break;

                case 'reset_quota':
                    $ids_placeholders = implode(',', array_fill(0, count($quota_ids), '%d'));
                    $result = $wpdb->query($wpdb->prepare("UPDATE $table SET quota_used = 0 WHERE id IN ($ids_placeholders)", ...$quota_ids));

                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>' . count($quota_ids) . ' quotas reset successfully.</p></div>';
                    }
                    break;
            }
        }
    }

    // Handle form submission (update quota)
    if (isset($_POST['edit_quota_id'])) {
        $quota_id = intval($_POST['edit_quota_id']);
        $quota_total = intval($_POST['quota_total']);
        $quota_used = intval($_POST['quota_used']);
        $status = sanitize_text_field($_POST['status']);
        $renewal_date = sanitize_text_field($_POST['renewal_date']);

        $update_data = [
            'quota_total' => $quota_total,
            'quota_used' => $quota_used,
            'status' => $status,
        ];

        if ($renewal_date) {
            $update_data['quota_renewal_date'] = $renewal_date;
        }

        $result = $wpdb->update($table, $update_data, ['id' => $quota_id]);

        if ($result !== false) {
            echo '<div class="notice notice-success"><p>Quota updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error updating quota.</p></div>';
        }
    }

    // Handle edit form
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
        $id = intval($_GET['id']);
        $quota = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if ($quota) {
            ?>
            <div class="wrap">
                <h1>Edit User Quota</h1>
                <form method="post">
                    <input type="hidden" name="edit_quota_id" value="<?php echo esc_attr($quota->id); ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row">User</th>
                            <td>
                                <?php
                                $user = get_user_by('id', $quota->user_id);
                                echo $user ? esc_html($user->display_name . ' (' . $user->user_email . ')') : 'User #' . $quota->user_id;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Plan</th>
                            <td>
                                <?php
                                $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d", $quota->plan_id));
                                echo $plan ? esc_html($plan->plan_name) : 'Plan #' . $quota->plan_id;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Quota Total</th>
                            <td><input type="number" name="quota_total" value="<?php echo esc_attr($quota->quota_total); ?>"
                                    required></td>
                        </tr>
                        <tr>
                            <th scope="row">Quota Used</th>
                            <td><input type="number" name="quota_used" value="<?php echo esc_attr($quota->quota_used); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <select name="status">
                                    <option value="active" <?php selected($quota->status, 'active'); ?>>Active</option>
                                    <option value="expired" <?php selected($quota->status, 'expired'); ?>>Expired</option>
                                    <option value="cancelled" <?php selected($quota->status, 'cancelled'); ?>>Cancelled</option>
                                    <option value="pending" <?php selected($quota->status, 'pending'); ?>>Pending</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Renewal Date</th>
                            <td>
                                <input type="date" name="renewal_date"
                                    value="<?php echo esc_attr(date('Y-m-d', strtotime($quota->quota_renewal_date))); ?>">
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Update Quota'); ?>
                </form>

                <p><a href="<?php echo admin_url('admin.php?page=shutterpress_user_quotas'); ?>" class="button">← Back to User
                        Quotas</a></p>
            </div>
            <?php
            return;
        }
    }

    // Export functionality
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $filename = 'user-quotas-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'ID',
            'User',
            'Email',
            'Plan',
            'Quota Total',
            'Quota Used',
            'Quota Remaining',
            'Status',
            'Renewal Date',
            'Created Date',
            'Last Download'
        ]);

        // Get all quotas (apply same filters as table)
        $where_clauses = [];
        $params = [];

        if (!empty($_GET['filter_user'])) {
            $user_search = sanitize_text_field($_GET['filter_user']);
            $user = get_user_by('login', $user_search);
            if (!$user) {
                $user = get_user_by('email', $user_search);
            }
            if ($user) {
                $where_clauses[] = 'q.user_id = %d';
                $params[] = $user->ID;
            } else {
                $where_clauses[] = '(u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
                $params[] = '%' . $user_search . '%';
                $params[] = '%' . $user_search . '%';
                $params[] = '%' . $user_search . '%';
            }
        }

        if (!empty($_GET['filter_plan'])) {
            $where_clauses[] = 'q.plan_id = %d';
            $params[] = absint($_GET['filter_plan']);
        }

        if (!empty($_GET['filter_status'])) {
            $where_clauses[] = 'q.status = %s';
            $params[] = sanitize_text_field($_GET['filter_status']);
        }

        $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $sql = "
            SELECT q.*, u.user_login, u.user_email, u.display_name, p.plan_name,
                   (q.quota_total - q.quota_used) as quota_remaining
            FROM $table q
            LEFT JOIN {$wpdb->users} u ON q.user_id = u.ID
            LEFT JOIN $plans_table p ON q.plan_id = p.id
            $where_sql
            ORDER BY q.created_at DESC
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
                $row->plan_name ?: 'Plan #' . $row->plan_id,
                $row->quota_total,
                $row->quota_used,
                $row->quota_remaining,
                ucfirst($row->status),
                $row->quota_renewal_date ?: 'N/A',
                $row->created_at,
                $row->last_download ?: 'Never'
            ]);
        }

        fclose($output);
        exit;
    }

    // Get statistics
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_quotas,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT plan_id) as unique_plans,
            SUM(quota_total) as total_quota_allocated,
            SUM(quota_used) as total_quota_used,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_quotas,
            COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_quotas,
            COUNT(CASE WHEN quota_used >= quota_total THEN 1 END) as full_quotas,
            COUNT(CASE WHEN quota_renewal_date < NOW() THEN 1 END) as overdue_quotas
        FROM $table
    ");

    $quota_table = new ShutterPress_User_Quotas_Table();
    $quota_table->prepare_items();
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">User Quotas</h1>

        <a href="<?php echo admin_url('admin.php?page=shutterpress_user_quotas&export=csv&' . http_build_query($_GET)); ?>"
            class="page-title-action">Export CSV</a>

        <hr class="wp-header-end">

        <!-- Statistics Dashboard -->
        <div class="postbox" style="margin-top: 20px;">
            <h3 class="hndle">Statistics</h3>
            <div class="inside">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Total Quotas</h4>
                        <p style="font-size: 24px; margin: 0;"><?php echo number_format($stats->total_quotas ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Unique Users</h4>
                        <p style="font-size: 24px; margin: 0;"><?php echo number_format($stats->unique_users ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Active Quotas</h4>
                        <p style="font-size: 24px; margin: 0; color: #28a745;">
                            <?php echo number_format($stats->active_quotas ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Expired Quotas</h4>
                        <p style="font-size: 24px; margin: 0; color: #dc3545;">
                            <?php echo number_format($stats->expired_quotas ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Full Quotas</h4>
                        <p style="font-size: 24px; margin: 0; color: #fd7e14;">
                            <?php echo number_format($stats->full_quotas ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Overdue Renewals</h4>
                        <p style="font-size: 24px; margin: 0; color: #dc3545;">
                            <?php echo number_format($stats->overdue_quotas ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Total Quota Allocated</h4>
                        <p style="font-size: 24px; margin: 0;">
                            <?php echo number_format($stats->total_quota_allocated ?? 0); ?></p>
                    </div>
                    <div class="stat-box" style="background: #f1f1f1; padding: 15px; border-radius: 5px;">
                        <h4>Total Quota Used</h4>
                        <p style="font-size: 24px; margin: 0;"><?php echo number_format($stats->total_quota_used ?? 0); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form method="get" id="user-quotas-filter">
            <input type="hidden" name="page" value="shutterpress_user_quotas" />
            <?php $quota_table->display(); ?>
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

        .wp-list-table .column-quota_info {
            width: 200px;
        }

        .wp-list-table .column-user {
            width: 150px;
        }

        .wp-list-table .column-plan {
            width: 120px;
        }

        .wp-list-table .column-status {
            width: 80px;
        }

        .wp-list-table .column-actions {
            width: 120px;
        }
    </style>

    <script>
        jQuery(document).ready(function ($) {
            // Auto-submit form when filters change
            $('#user-quotas-filter select[name="filter_plan"], #user-quotas-filter select[name="filter_status"], #user-quotas-filter select[name="filter_quota_usage"], #user-quotas-filter select[name="filter_expiration"], #user-quotas-filter input[type="date"]').on('change', function () {
                $('#user-quotas-filter').submit();
            });
        });
    </script>
    <?php
}
?>