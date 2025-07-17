<?php
// admin/class-shutterpress-download-logs-table.php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ShutterPress_Download_Logs_Table extends WP_List_Table
{

    private $logs;
    private $total_items;

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

        $per_page = 20;
        $paged = $this->get_pagenum();
        $offset = ($paged - 1) * $per_page;

        // Filters
        $where = '1=1';
        $params = [];

        if (!empty($_REQUEST['user_id'])) {
            $where .= ' AND l.user_id = %d';
            $params[] = absint($_REQUEST['user_id']);
        }
        if (!empty($_REQUEST['product_id'])) {
            $where .= ' AND l.product_id = %d';
            $params[] = absint($_REQUEST['product_id']);
        }
        if (!empty($_REQUEST['date_from']) && !empty($_REQUEST['date_to'])) {
            $where .= ' AND l.download_time BETWEEN %s AND %s';
            $params[] = sanitize_text_field($_REQUEST['date_from']);
            $params[] = sanitize_text_field($_REQUEST['date_to']);
        }

        $where_sql = $wpdb->prepare($where, ...$params);

        // Fetch rows
        $sql = "
            SELECT l.*, u.display_name, p.post_title
            FROM $logs_table l
            LEFT JOIN $users_table u ON l.user_id = u.ID
            LEFT JOIN $posts_table p ON l.product_id = p.ID
            WHERE $where_sql
            ORDER BY l.download_time DESC
            LIMIT %d OFFSET %d
        ";

        $this->logs = $wpdb->get_results(
            $wpdb->prepare($sql, $per_page, $offset)
        );

        // Count total
        $this->total_items = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table l WHERE $where_sql");

        $this->set_pagination_args([
            'total_items' => $this->total_items,
            'per_page' => $per_page,
        ]);
    }

    public function get_columns()
    {
        return [
            'id' => 'ID',
            'user' => 'User',
            'product' => 'Product',
            'download_time' => 'Download Time',
            'actions' => 'Action',
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'id':
                return $item->id;
            case 'user':
                return esc_html($item->display_name ?: 'User #' . $item->user_id);
            case 'product':
                return esc_html($item->post_title ?: 'Product #' . $item->product_id);
            case 'download_time':
                return esc_html(date('Y-m-d H:i:s', strtotime($item->download_time)));
            case 'actions':
                $url = wp_nonce_url(admin_url('admin.php?page=shutterpress_download_logs&action=delete&log_id=' . $item->id), 'delete_log_' . $item->id);
                return '<a href="' . esc_url($url) . '" class="button button-small delete">Delete</a>';
            default:
                return '';
        }
    }
}
