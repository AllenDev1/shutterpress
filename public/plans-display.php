<?php
add_shortcode('shutterpress_plans', 'shutterpress_render_frontend_plans');

function shutterpress_render_frontend_plans()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    $plans = $wpdb->get_results("SELECT * FROM $table ORDER BY price ASC");
    if (!$plans) {
        return '<p>No plans available at the moment.</p>';
    }

    ob_start();
    echo '<div class="shutterpress-plan-list">';
    foreach ($plans as $plan) {
        if (!$plan->woocommerce_product_id)
            continue;

        $checkout_url = wc_get_checkout_url() . '?add-to-cart=' . $plan->woocommerce_product_id;

        echo '<div class="shutterpress-plan">';
        echo '<h3>' . esc_html($plan->plan_name) . '</h3>';
        echo '<p><strong>Price:</strong> ' . wc_price($plan->price) . '</p>';
        echo '<p><strong>Quota:</strong> ' . ($plan->is_unlimited ? 'Unlimited' : esc_html($plan->quota)) . '</p>';
        echo '<p><strong>Billing Cycle:</strong> ' . esc_html(ucfirst($plan->billing_cycle)) . '</p>';
        echo '<a class="button" href="' . esc_url($checkout_url) . '">Subscribe</a>';
        echo '</div>';
    }
    echo '</div>';

    return ob_get_clean();
}

