<?php
add_shortcode('shutterpress_user_subscription', function () {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your subscription.</p>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'shutterpress_user_quotas';
    $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';

    $quota = $wpdb->get_row($wpdb->prepare("
        SELECT q.*, p.plan_name, p.billing_cycle, p.is_unlimited
        FROM $table q
        LEFT JOIN $plans_table p ON q.plan_id = p.id
        WHERE q.user_id = %d AND q.status = 'active'
        ORDER BY q.created_at DESC LIMIT 1
    ", $user_id));

    if (!$quota) {
        return '<p>You do not have an active subscription.</p>';
    }

    ob_start();
    ?>
    <div class="shutterpress-subscription-summary">
        <h3>Your Current Subscription</h3>
        <table class="woocommerce-table shop_table">
            <tr><th>Plan</th><td><?php echo esc_html($quota->plan_name); ?></td></tr>
            <tr><th>Type</th><td><?php echo $quota->is_unlimited ? 'Unlimited' : 'Quota-based'; ?></td></tr>
            <tr><th>Total Downloads</th><td><?php echo $quota->is_unlimited ? '&infin;' : esc_html($quota->quota_total); ?></td></tr>
            <tr><th>Used</th><td><?php echo esc_html($quota->quota_used); ?></td></tr>
            <tr><th>Remaining</th><td><?php echo $quota->is_unlimited ? '&infin;' : ($quota->quota_total - $quota->quota_used); ?></td></tr>
            <tr><th>Renewal Date</th><td><?php echo esc_html(date('F j, Y', strtotime($quota->quota_renewal_date))); ?></td></tr>
        </table>
    </div>
    <?php
    return ob_get_clean();
});
