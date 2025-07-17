<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ShutterPress_User_Quotas_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'user_quota',
            'plural' => 'user_quotas',
            'ajax' => false,
        ]);
    }

    public function prepare_items()
    {
        global $wpdb;
        $quotas_table = $wpdb->prefix . 'shutterpress_user_quotas';
        $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';
        $users_table = $wpdb->users;

        $per_page = $this->get_items_per_page('user_quotas_per_page', 20);
        $paged = $this->get_pagenum();
        $offset = ($paged - 1) * $per_page;

        $where_clauses = [];
        $params = [];

        // Filter by user
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
                // Search by display name, login, or email
                $where_clauses[] = '(u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
                $params[] = '%' . $user_search . '%';
                $params[] = '%' . $user_search . '%';
                $params[] = '%' . $user_search . '%';
            }
        }

        // Filter by plan
        if (!empty($_GET['filter_plan'])) {
            $plan_search = sanitize_text_field($_GET['filter_plan']);
            if (is_numeric($plan_search)) {
                $where_clauses[] = 'q.plan_id = %d';
                $params[] = absint($plan_search);
            } else {
                $where_clauses[] = 'p.plan_name LIKE %s';
                $params[] = '%' . $plan_search . '%';
            }
        }

        // Filter by status
        if (!empty($_GET['filter_status'])) {
            $status = sanitize_text_field($_GET['filter_status']);
            if (in_array($status, ['active', 'expired', 'cancelled', 'pending'])) {
                $where_clauses[] = 'q.status = %s';
                $params[] = $status;
            }
        }

        // Filter by quota usage
        if (!empty($_GET['filter_quota_usage'])) {
            $usage_filter = sanitize_text_field($_GET['filter_quota_usage']);
            switch ($usage_filter) {
                case 'full':
                    $where_clauses[] = 'q.quota_used >= q.quota_total';
                    break;
                case 'high':
                    $where_clauses[] = 'q.quota_used >= (q.quota_total * 0.8)';
                    break;
                case 'medium':
                    $where_clauses[] = 'q.quota_used >= (q.quota_total * 0.5) AND q.quota_used < (q.quota_total * 0.8)';
                    break;
                case 'low':
                    $where_clauses[] = 'q.quota_used < (q.quota_total * 0.5)';
                    break;
            }
        }

        // Filter by expiration
        if (!empty($_GET['filter_expiration'])) {
            $expiration_filter = sanitize_text_field($_GET['filter_expiration']);
            switch ($expiration_filter) {
                case 'expired':
                    $where_clauses[] = 'q.quota_renewal_date < NOW()';
                    break;
                case 'expires_soon':
                    $where_clauses[] = 'q.quota_renewal_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'expires_month':
                    $where_clauses[] = 'q.quota_renewal_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)';
                    break;
            }
        }

        // Filter by date range
        if (!empty($_GET['filter_date_from']) || !empty($_GET['filter_date_to'])) {
            if (!empty($_GET['filter_date_from'])) {
                $date_from = sanitize_text_field($_GET['filter_date_from']);
                $date_from_obj = DateTime::createFromFormat('Y-m-d', $date_from);
                if ($date_from_obj) {
                    $where_clauses[] = 'q.created_at >= %s';
                    $params[] = $date_from_obj->format('Y-m-d') . ' 00:00:00';
                }
            }

            if (!empty($_GET['filter_date_to'])) {
                $date_to = sanitize_text_field($_GET['filter_date_to']);
                $date_to_obj = DateTime::createFromFormat('Y-m-d', $date_to);
                if ($date_to_obj) {
                    $where_clauses[] = 'q.created_at <= %s';
                    $params[] = $date_to_obj->format('Y-m-d') . ' 23:59:59';
                }
            }
        }

        $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        // Order by
        $orderby = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = !empty($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        $allowed_orderby = ['id', 'user_id', 'plan_id', 'quota_total', 'quota_used', 'status', 'created_at', 'quota_renewal_date'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }

        $sql_quotas = "
            SELECT q.*, u.user_login, u.user_email, u.display_name, p.plan_name, p.price, p.billing_cycle,
                   (q.quota_total - q.quota_used) as quota_remaining,
                   CASE 
                       WHEN q.quota_renewal_date < NOW() THEN 'expired'
                       WHEN q.quota_renewal_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'expires_soon'
                       ELSE 'active'
                   END as expiration_status
            FROM $quotas_table q
            LEFT JOIN $users_table u ON q.user_id = u.ID
            LEFT JOIN $plans_table p ON q.plan_id = p.id
            $where_sql
            ORDER BY q.$orderby $order
            LIMIT %d OFFSET %d
        ";

        $sql_count = "
            SELECT COUNT(*)
            FROM $quotas_table q
            LEFT JOIN $users_table u ON q.user_id = u.ID
            LEFT JOIN $plans_table p ON q.plan_id = p.id
            $where_sql
        ";

        $params_quotas = array_merge($params, [$per_page, $offset]);

        if (!empty($params)) {
            $this->items = $wpdb->get_results($wpdb->prepare($sql_quotas, ...$params_quotas));
            $total_items = $wpdb->get_var($wpdb->prepare($sql_count, ...$params));
        } else {
            $this->items = $wpdb->get_results($wpdb->prepare("
                SELECT q.*, u.user_login, u.user_email, u.display_name, p.plan_name, p.price, p.billing_cycle,
                       (q.quota_total - q.quota_used) as quota_remaining,
                       CASE 
                           WHEN q.quota_renewal_date < NOW() THEN 'expired'
                           WHEN q.quota_renewal_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'expires_soon'
                           ELSE 'active'
                       END as expiration_status
                FROM $quotas_table q
                LEFT JOIN $users_table u ON q.user_id = u.ID
                LEFT JOIN $plans_table p ON q.plan_id = p.id
                ORDER BY q.$orderby $order
                LIMIT %d OFFSET %d
            ", $per_page, $offset));
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $quotas_table q");
        }

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => 'ID',
            'user' => 'User',
            'plan' => 'Plan',
            'quota_info' => 'Quota Usage',
            'status' => 'Status',
            'renewal_date' => 'Renewal Date',
            'created_at' => 'Created',
            'last_download' => 'Last Download',
            'actions' => 'Actions',
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'id' => ['id', true],
            'user' => ['user_id', false],
            'plan' => ['plan_id', false],
            'quota_info' => ['quota_used', false],
            'status' => ['status', false],
            'renewal_date' => ['quota_renewal_date', false],
            'created_at' => ['created_at', true],
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="quota_ids[]" value="%s" />',
            $item->id
        );
    }

    public function column_id($item)
    {
        return $item->id;
    }

    public function column_user($item)
    {
        $user_info = '';
        if ($item->display_name) {
            $user_info = '<strong>' . esc_html($item->display_name) . '</strong>';
        } elseif ($item->user_login) {
            $user_info = '<strong>' . esc_html($item->user_login) . '</strong>';
        } else {
            $user_info = '<strong>User #' . $item->user_id . '</strong>';
        }

        if ($item->user_email) {
            $user_info .= '<br><small>' . esc_html($item->user_email) . '</small>';
        }

        return $user_info;
    }

    public function column_plan($item)
    {
        $plan_info = '';
        if ($item->plan_name) {
            $plan_info = '<strong>' . esc_html($item->plan_name) . '</strong>';
        } else {
            $plan_info = '<strong>Plan #' . $item->plan_id . '</strong>';
        }

        if ($item->price && $item->billing_cycle) {
            $plan_info .= '<br><small>$' . esc_html($item->price) . ' / ' . esc_html($item->billing_cycle) . '</small>';
        }

        return $plan_info;
    }

    public function column_quota_info($item)
    {
        $percentage = $item->quota_total > 0 ? round(($item->quota_used / $item->quota_total) * 100, 1) : 0;
        $color = $percentage >= 100 ? '#dc3545' : ($percentage >= 80 ? '#fd7e14' : '#28a745');

        $info = '<div>';
        $info .= '<strong>' . esc_html($item->quota_used) . ' / ' . esc_html($item->quota_total) . '</strong>';
        $info .= '<br><small>Remaining: ' . esc_html($item->quota_remaining) . '</small>';
        $info .= '<br><div style="background: #f0f0f0; height: 8px; border-radius: 4px; overflow: hidden;">';
        $info .= '<div style="background: ' . $color . '; height: 100%; width: ' . $percentage . '%;"></div>';
        $info .= '</div>';
        $info .= '<small>' . $percentage . '% used</small>';
        $info .= '</div>';

        return $info;
    }

    public function column_status($item)
    {
        $status_colors = [
            'active' => '#28a745',
            'expired' => '#dc3545',
            'cancelled' => '#6c757d',
            'pending' => '#fd7e14'
        ];

        $color = $status_colors[$item->status] ?? '#6c757d';

        return '<span style="color: ' . $color . '; font-weight: bold;">' .
            esc_html(ucfirst($item->status)) .
            '</span>';
    }

    public function column_renewal_date($item)
    {
        if (!$item->quota_renewal_date) {
            return 'N/A';
        }

        $date = new DateTime($item->quota_renewal_date);
        $now = new DateTime();

        $output = $date->format('Y-m-d');

        if ($item->expiration_status === 'expired') {
            $output .= '<br><small style="color: #dc3545;">Expired</small>';
        } elseif ($item->expiration_status === 'expires_soon') {
            $output .= '<br><small style="color: #fd7e14;">Expires Soon</small>';
        }

        return $output;
    }

    public function column_created_at($item)
    {
        $date = new DateTime($item->created_at);
        return $date->format('Y-m-d H:i:s');
    }

    public function column_last_download($item)
    {
        return $item->last_download ?
            (new DateTime($item->last_download))->format('Y-m-d H:i:s') :
            'Never';
    }

    public function column_actions($item)
    {
        $edit_url = admin_url('admin.php?page=shutterpress_user_quotas&action=edit&id=' . $item->id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=shutterpress_user_quotas&action=delete&id=' . $item->id),
            'delete_quota_' . $item->id
        );

        $actions = '';
        $actions .= '<a href="' . esc_url($edit_url) . '" class="button button-small">Edit</a> ';
        $actions .= '<a href="' . esc_url($delete_url) . '" class="button button-small" onclick="return confirm(\'Are you sure you want to delete this quota?\')">Delete</a>';

        return $actions;
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete',
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
            'reset_quota' => 'Reset Quota Usage',
        ];
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top')
            return;

        global $wpdb;

        $filter_user = esc_attr($_GET['filter_user'] ?? '');
        $filter_plan = esc_attr($_GET['filter_plan'] ?? '');
        $filter_status = esc_attr($_GET['filter_status'] ?? '');
        $filter_quota_usage = esc_attr($_GET['filter_quota_usage'] ?? '');
        $filter_expiration = esc_attr($_GET['filter_expiration'] ?? '');
        $filter_date_from = esc_attr($_GET['filter_date_from'] ?? '');
        $filter_date_to = esc_attr($_GET['filter_date_to'] ?? '');

        $base_url = admin_url('admin.php?page=shutterpress_user_quotas');

        // Get all plans for dropdown
        $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';
        $plans = $wpdb->get_results("SELECT id, plan_name FROM $plans_table ORDER BY plan_name");
        ?>
        <div class="alignleft actions">
            <input type="text" name="filter_user" placeholder="Search User (username/email)" value="<?php echo $filter_user; ?>"
                style="width: 200px;" />

            <select name="filter_plan" style="width: 150px;">
                <option value="">All Plans</option>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?php echo esc_attr($plan->id); ?>" <?php selected($filter_plan, $plan->id); ?>>
                        <?php echo esc_html($plan->plan_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="filter_status">
                <option value="">All Status</option>
                <option value="active" <?php selected($filter_status, 'active'); ?>>Active</option>
                <option value="expired" <?php selected($filter_status, 'expired'); ?>>Expired</option>
                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
            </select>

            <select name="filter_quota_usage">
                <option value="">All Usage</option>
                <option value="full" <?php selected($filter_quota_usage, 'full'); ?>>Full (100%)</option>
                <option value="high" <?php selected($filter_quota_usage, 'high'); ?>>High (80%+)</option>
                <option value="medium" <?php selected($filter_quota_usage, 'medium'); ?>>Medium (50-80%)</option>
                <option value="low" <?php selected($filter_quota_usage, 'low'); ?>>Low (&lt;50%)</option>
            </select>

            <select name="filter_expiration">
                <option value="">All Expiration</option>
                <option value="expired" <?php selected($filter_expiration, 'expired'); ?>>Expired</option>
                <option value="expires_soon" <?php selected($filter_expiration, 'expires_soon'); ?>>Expires Soon (7 days)
                </option>
                <option value="expires_month" <?php selected($filter_expiration, 'expires_month'); ?>>Expires This Month
                </option>
            </select>

            <br><br>

            <label>Created From: </label>
            <input type="date" name="filter_date_from" value="<?php echo $filter_date_from; ?>" />
            <label>To: </label>
            <input type="date" name="filter_date_to" value="<?php echo $filter_date_to; ?>" />

            <input type="submit" class="button button-primary" value="Filter" />

            <?php if ($filter_user || $filter_plan || $filter_status || $filter_quota_usage || $filter_expiration || $filter_date_from || $filter_date_to): ?>
                <a href="<?php echo esc_url($base_url); ?>" class="button">Clear Filters</a>
            <?php endif; ?>
        </div>
        <?php
    }

    public function no_items()
    {
        echo 'No user quotas found.';
    }
}