<?php
defined('ABSPATH') || exit;

function shutterpress_render_user_quotas_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_user_quotas';
    $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';

    // Handle delete action
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
        $id = intval($_GET['id']);
        $wpdb->delete($table, ['id' => $id]);
        echo '<div class="notice notice-success"><p>Quota deleted successfully.</p></div>';
    }

    // Handle form submission (update quota)
    if (isset($_POST['edit_quota_id'])) {
        $wpdb->update(
            $table,
            [
                'quota_total' => intval($_POST['quota_total']),
                'quota_used'  => intval($_POST['quota_used']),
                'status'      => sanitize_text_field($_POST['status']),
            ],
            ['id' => intval($_POST['edit_quota_id'])]
        );
        echo '<div class="notice notice-success"><p>Quota updated successfully.</p></div>';
    }

    // Handle edit form
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
        $id = intval($_GET['id']);
        $quota = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if ($quota) {
            ?>
            <h2>Edit Quota</h2>
            <form method="post">
                <input type="hidden" name="edit_quota_id" value="<?php echo esc_attr($quota->id); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row">Quota Total</th>
                        <td><input type="number" name="quota_total" value="<?php echo esc_attr($quota->quota_total); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Quota Used</th>
                        <td><input type="number" name="quota_used" value="<?php echo esc_attr($quota->quota_used); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <select name="status">
                                <option value="active" <?php selected($quota->status, 'active'); ?>>Active</option>
                                <option value="expired" <?php selected($quota->status, 'expired'); ?>>Expired</option>
                                <option value="cancelled" <?php selected($quota->status, 'cancelled'); ?>>Cancelled</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Update Quota'); ?>
            </form>
            <?php
            return;
        }
    }

    // Fetch all quotas
    $results = $wpdb->get_results("
        SELECT q.*, u.user_login, u.user_email, p.plan_name 
        FROM $table q
        LEFT JOIN {$wpdb->users} u ON q.user_id = u.ID
        LEFT JOIN $plans_table p ON q.plan_id = p.id
        ORDER BY q.created_at DESC
    ");
    ?>

    <div class="wrap">
        <h1>User Quotas</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Total</th>
                    <th>Used</th>
                    <th>Remaining</th>
                    <th>Status</th>
                    <th>Renewal Date</th>
                    <th>Last Download</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->user_login); ?></td>
                        <td><?php echo esc_html($row->user_email); ?></td>
                        <td><?php echo esc_html($row->plan_name); ?></td>
                        <td><?php echo esc_html($row->quota_total); ?></td>
                        <td><?php echo esc_html($row->quota_used); ?></td>
                        <td><?php echo esc_html($row->quota_total - $row->quota_used); ?></td>
                        <td><?php echo esc_html(ucfirst($row->status)); ?></td>
                        <td><?php echo esc_html($row->quota_renewal_date); ?></td>
                        <td><?php echo esc_html($row->last_download); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=shutterpress_user_quotas&action=edit&id=' . $row->id); ?>">Edit</a> |
                            <a href="<?php echo admin_url('admin.php?page=shutterpress_user_quotas&action=delete&id=' . $row->id); ?>" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
}
