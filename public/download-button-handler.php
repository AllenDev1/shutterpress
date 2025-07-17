<?php

add_action('woocommerce_single_product_summary', 'shutterpress_maybe_override_product_button', 1);

function shutterpress_maybe_override_product_button()
{
    global $product;

    if (!$product->is_virtual() || !$product->is_downloadable()) {
        return;
    }

    $type = get_post_meta($product->get_id(), '_shutterpress_product_type', true);
    if (!$type || $type === 'premium')
        return;

    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);

    echo '<div class="shutterpress-download-button">';

    $downloads = $product->get_downloads();
    $download_url = '';
    if (!empty($downloads)) {
        $download_url = add_query_arg([
            'shutterpress_download' => $product->get_id()
        ], home_url('/'));
    }

    if ($type === 'free') {
        if (is_user_logged_in()) {
            if (!empty($download_url)) {
                echo '<form class="cart">';
                echo '<a href="' . esc_url($download_url) . '" class="single_add_to_cart_button button alt">Download Now</a>';
                echo '</form>';
            } else {
                echo '<p>No file available.</p>';
            }
        } else {
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button alt">Login to Download</a>';
        }
    }

    if ($type === 'subscription') {
        if (!is_user_logged_in()) {
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button alt">Login to Download</a>';
        } else {
            $user_id = get_current_user_id();
            $has_quota = shutterpress_user_has_quota($user_id);
            if ($has_quota && !empty($download_url)) {
                echo '<form class="cart">';
                echo '<a href="' . esc_url($download_url) . '" class="single_add_to_cart_button button alt">Download Now</a>';
                echo '</form>';
            } else {
                $plans_page = shutterpress_find_shortcode_page('shutterpress_plans');
                if ($plans_page) {
                    echo '<form class="cart">';
                    echo '<a href="' . esc_url(get_permalink($plans_page)) . '" class="single_add_to_cart_button button alt">Subscribe to Download</a>';
                    echo '</form>';
                } else {
                    echo '<p>No subscription plans available.</p>';
                }
            }
        }
    }

    echo '</div>';
}


// ✅ User quota checker
function shutterpress_user_has_quota($user_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'shutterpress_user_quotas';

    $quota = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));

    if (!$quota)
        return false;

    $is_unlimited = isset($quota->is_unlimited) ? (bool) $quota->is_unlimited : false;

    if ($is_unlimited)
        return true;

    return ($quota->quota_total > $quota->quota_used);
}
