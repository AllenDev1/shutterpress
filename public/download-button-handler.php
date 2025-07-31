<?php

add_action('woocommerce_single_product_summary', 'shutterpress_maybe_override_product_button', 25);

function shutterpress_maybe_override_product_button()
{
    global $product;

    if (!$product->is_virtual() || !$product->is_downloadable()) {
        return;
    }

    $type = get_post_meta($product->get_id(), '_shutterpress_product_type', true);
    if (!$type || $type === 'premium')
        return;

    // Remove default WooCommerce elements
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);

    add_action('wp_head', 'shutterpress_hide_unwanted_elements');
    add_action('woocommerce_single_product_summary', 'shutterpress_render_download_button', 30);
}

function shutterpress_hide_unwanted_elements()
{
    ?>
    <style>
        .size-guide,
        .quantity,
        .product-quantity,
        .woocommerce-variation-add-to-cart,
        .single_variation_wrap,
        .woocommerce-product-attributes,
        .size-guides,
        .product-size-guide,
        .compare,
        .wishlist,
        .price {
            display: none !important;
        }

        .shutterpress-download-button {
            margin: 20px 0;
        }

        .shutterpress-download-button .button {
            width: 100%;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
    </style>
    <?php
}

function shutterpress_render_download_button()
{
    global $product;

    $product_id = $product->get_id();
    $type = get_post_meta($product_id, '_shutterpress_product_type', true);

    if (!$type || $type === 'premium') {
        return;
    }

    $wasabi_key = get_post_meta($product_id, '_wasabi_object_key', true);
    if (!$wasabi_key) {
        echo '<div class="shutterpress-download-button"><p>No file available.</p></div>';
        return;
    }

    // Generate secure download URL
    $download_url = esc_url(add_query_arg([
        'shutterpress_download' => $product_id,
        '_wpnonce' => wp_create_nonce('shutterpress_download_' . $product_id)
    ], home_url('/')));

    echo '<div class="shutterpress-download-button">';

    if (!is_user_logged_in()) {
        echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button alt">Login to Download</a>';
    } elseif ($type === 'free') {
        echo '<form class="cart" method="post">';
        echo '<button type="button" onclick="window.location.href=\'' . $download_url . '\'" class="button alt">Download Now</button>';
        echo '</form>';
    } elseif ($type === 'subscription') {
        $user_id = get_current_user_id();
        $has_quota = shutterpress_user_has_quota($user_id);

        if ($has_quota) {
            echo '<form class="cart" method="post">';
            echo '<button type="button" onclick="window.location.href=\'' . $download_url . '\'" class="button alt">Download Now</button>';
            echo '</form>';
        } else {
            $plans_page = shutterpress_find_shortcode_page('shutterpress_plans');
            if ($plans_page) {
                echo '<form class="cart" method="post">';
                echo '<button type="button" onclick="window.location.href=\'' . esc_url(get_permalink($plans_page)) . '\'" class="button alt">Subscribe to Download</button>';
                echo '</form>';
            } else {
                echo '<p>No subscription plans available.</p>';
            }
        }
    }

    echo '</div>';
}

function shutterpress_user_has_quota($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_user_quotas';

    // Check for active quota that hasn't expired
    $quota = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table 
         WHERE user_id = %d 
         AND status = 'active' 
         AND (quota_renewal_date IS NULL OR quota_renewal_date >= CURDATE())
         ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));

    if (!$quota)
        return false;
    if (!empty($quota->is_unlimited))
        return true;
    return ($quota->quota_total > $quota->quota_used);
}
add_action('wp_footer', 'shutterpress_hide_elements_js');

function shutterpress_hide_elements_js()
{
    global $product;

    if (!is_product() || !$product)
        return;

    $type = get_post_meta($product->get_id(), '_shutterpress_product_type', true);
    if (!$type || $type === 'premium')
        return;

    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('.size-guide, .quantity, .product-quantity, .woocommerce-variation-add-to-cart, .single_variation_wrap, .woocommerce-product-attributes, .size-guides, .product-size-guide, .compare, .wishlist, .price').hide();
        });
    </script>
    <?php
}
