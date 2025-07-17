<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ShutterPress_Download_Logs_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'download_log',
            'plural' => 'download_logs',
            'ajax' => false,
        ]);
    }

    public function prepare_items()
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'shutterpress_download_logs';
        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;

        $per_page = $this->get_items_per_page('download_logs_per_page', 20);
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
                $where_clauses[] = 'l.user_id = %d';
                $params[] = $user->ID;
            } else {
                // Search by display name or login
                $where_clauses[] = '(u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
                $params[] = '%' . $user_search . '%';
                $params[] = '%' . $user_search . '%';
                $params[] = '%' . $user_search . '%';
            }
        }

        // Filter by product
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

        // Filter by date range
        if (!empty($_GET['filter_date_from']) || !empty($_GET['filter_date_to'])) {
            if (!empty($_GET['filter_date_from'])) {
                $date_from = sanitize_text_field($_GET['filter_date_from']);
                $date_from_obj = DateTime::createFromFormat('Y-m-d', $date_from);
                if ($date_from_obj) {
                    $where_clauses[] = 'l.download_time >= %s';
                    $params[] = $date_from_obj->format('Y-m-d') . ' 00:00:00';
                }
            }

            if (!empty($_GET['filter_date_to'])) {
                $date_to = sanitize_text_field($_GET['filter_date_to']);
                $date_to_obj = DateTime::createFromFormat('Y-m-d', $date_to);
                if ($date_to_obj) {
                    $where_clauses[] = 'l.download_time <= %s';
                    $params[] = $date_to_obj->format('Y-m-d') . ' 23:59:59';
                }
            }
        }

        // Filter by status if you have a status column
        if (!empty($_GET['filter_status'])) {
            $status = sanitize_text_field($_GET['filter_status']);
            if (in_array($status, ['success', 'failed', 'pending'])) {
                $where_clauses[] = 'l.status = %s';
                $params[] = $status;
            }
        }

        $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        // Order by
        $orderby = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'download_time';
        $order = !empty($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        $allowed_orderby = ['id', 'download_time', 'user_id', 'product_id'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'download_time';
        }

        $sql_logs = "
            SELECT l.*, u.display_name, u.user_login, u.user_email, p.post_title
            FROM $logs_table l
            LEFT JOIN $users_table u ON l.user_id = u.ID
            LEFT JOIN $posts_table p ON l.product_id = p.ID
            $where_sql
            ORDER BY l.$orderby $order
            LIMIT %d OFFSET %d
        ";

        $sql_count = "
            SELECT COUNT(*)
            FROM $logs_table l
            LEFT JOIN $users_table u ON l.user_id = u.ID
            LEFT JOIN $posts_table p ON l.product_id = p.ID
            $where_sql
        ";

        $params_logs = array_merge($params, [$per_page, $offset]);

        if (!empty($params)) {
            $this->items = $wpdb->get_results($wpdb->prepare($sql_logs, ...$params_logs));
            $total_items = $wpdb->get_var($wpdb->prepare($sql_count, ...$params));
        } else {
            $this->items = $wpdb->get_results($wpdb->prepare("
                SELECT l.*, u.display_name, u.user_login, u.user_email, p.post_title
                FROM $logs_table l
                LEFT JOIN $users_table u ON l.user_id = u.ID
                LEFT JOIN $posts_table p ON l.product_id = p.ID
                ORDER BY l.$orderby $order
                LIMIT %d OFFSET %d
            ", $per_page, $offset));
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table l");
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
            'product' => 'Product',
            'download_time' => 'Download Time',
            'ip_address' => 'IP Address',
            'user_agent' => 'User Agent',
            'actions' => 'Actions',
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'id' => ['id', true],
            'download_time' => ['download_time', true],
            'user' => ['user_id', false],
            'product' => ['product_id', false],
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="log_ids[]" value="%s" />',
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
            $user_info = esc_html($item->display_name);
        } elseif ($item->user_login) {
            $user_info = esc_html($item->user_login);
        } else {
            $user_info = 'User #' . $item->user_id;
        }

        if ($item->user_email) {
            $user_info .= '<br><small>' . esc_html($item->user_email) . '</small>';
        }

        return $user_info;
    }

    public function column_product($item)
    {
        $product_info = '';
        if ($item->post_title) {
            $product_info = esc_html($item->post_title);
        } else {
            $product_info = 'Product #' . $item->product_id;
        }

        $product_info .= '<br><small>ID: ' . $item->product_id . '</small>';

        return $product_info;
    }

    public function column_download_time($item)
    {
        $date = new DateTime($item->download_time);
        return $date->format('Y-m-d H:i:s');
    }

    public function column_ip_address($item)
    {
        return $item->ip_address ?? 'N/A';
    }

    public function column_user_agent($item)
    {
        $user_agent = $item->user_agent ?? 'N/A';
        return '<span title="' . esc_attr($user_agent) . '">' .
            esc_html(substr($user_agent, 0, 50)) .
            (strlen($user_agent) > 50 ? '...' : '') .
            '</span>';
    }

    public function column_actions($item)
    {
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=shutterpress_download_logs&action=delete&log_id=' . $item->id),
            'delete_log_' . $item->id
        );

        return '<a href="' . esc_url($delete_url) . '" class="button button-small" onclick="return confirm(\'Are you sure you want to delete this log?\')">Delete</a>';
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete',
        ];
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top')
            return;

        $filter_user = esc_attr($_GET['filter_user'] ?? '');
        $filter_product = esc_attr($_GET['filter_product'] ?? '');
        $filter_date_from = esc_attr($_GET['filter_date_from'] ?? '');
        $filter_date_to = esc_attr($_GET['filter_date_to'] ?? '');
        $filter_status = esc_attr($_GET['filter_status'] ?? '');
        $base_url = admin_url('admin.php?page=shutterpress_download_logs');
        ?>
        <div class="alignleft actions">
            <input type="text" name="filter_user" placeholder="Search User (username/email)" value="<?php echo $filter_user; ?>"
                style="width: 200px;" />
            <input type="text" name="filter_product" placeholder="Product ID or Name" value="<?php echo $filter_product; ?>"
                style="width: 150px;" />
            <br><br>
            <label>From: </label>
            <input type="date" name="filter_date_from" value="<?php echo $filter_date_from; ?>" />
            <label>To: </label>
            <input type="date" name="filter_date_to" value="<?php echo $filter_date_to; ?>" />
            <select name="filter_status">
                <option value="">All Status</option>
                <option value="success" <?php selected($filter_status, 'success'); ?>>Success</option>
                <option value="failed" <?php selected($filter_status, 'failed'); ?>>Failed</option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
            </select>
            <input type="submit" class="button button-primary" value="Filter" />
            <?php if ($filter_user || $filter_product || $filter_date_from || $filter_date_to || $filter_status): ?>
                <a href="<?php echo esc_url($base_url); ?>" class="button">Clear Filters</a>
            <?php endif; ?>
        </div>
        <?php
    }

    public function no_items()
    {
        echo 'No download logs found.';
    }
}