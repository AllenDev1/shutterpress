<?php 
add_shortcode('shutterpress_download_history', 'shutterpress_render_download_history');

function shutterpress_render_download_history() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your download history.</p>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'shutterpress_download_logs';

    $logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table WHERE user_id = %d ORDER BY download_time DESC LIMIT 100
    ", $user_id));

    if (!$logs) {
        return '<p>You haven’t downloaded anything yet.</p>';
    }

    ob_start();
    ?>
    <div class="shutterpress-download-history">
        <h3>Your Download History</h3>
        <table class="woocommerce-table shop_table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    $product = wc_get_product($log->product_id);
                    if (!$product) continue;
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                                <?php echo esc_html($product->get_name()); ?>
                            </a>
                        </td>
                        <td><?php echo ucfirst(esc_html($log->download_type)); ?></td>
                        <td><?php echo esc_html(date('F j, Y g:i a', strtotime($log->download_time))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
