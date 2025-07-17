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

    // Remove standard WooCommerce elements
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);

    // Remove quantity and other form elements
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);

    // Add custom CSS to hide unwanted elements
    add_action('wp_head', 'shutterpress_hide_unwanted_elements');

    // Position the download button at priority 30 (same as add to cart)
    add_action('woocommerce_single_product_summary', 'shutterpress_render_download_button', 30);
}

function shutterpress_hide_unwanted_elements()
{
    ?>
    <style>
        /* Hide size guide, quantity inputs, and other unwanted elements */
        .size-guide,
        .quantity,
        .product-quantity,
        .woocommerce-variation-add-to-cart,
        .single_variation_wrap,
        .woocommerce-product-attributes,
        .size-guides,
        .product-size-guide {
            display: none !important;
        }

        /* Style the download button container to match WooCommerce cart */
        .shutterpress-download-button {
            margin: 20px 0;
        }

        .shutterpress-download-button .button,
        .shutterpress-download-button .single_add_to_cart_button {
            width: 100%;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .shutterpress-download-button form.cart {
            margin: 0;
        }
    </style>
    <?php
}

function shutterpress_render_download_button()
{
    global $product;

    $type = get_post_meta($product->get_id(), '_shutterpress_product_type', true);
    if (!$type || $type === 'premium') {
        return;
    }

    $downloads = $product->get_downloads();
    $download_url = '';
    if (!empty($downloads)) {
        $download_url = add_query_arg([
            'shutterpress_download' => $product->get_id()
        ], home_url('/'));
    }

    echo '<div class="shutterpress-download-button">';

    if ($type === 'free') {
        if (is_user_logged_in()) {
            if (!empty($download_url)) {
                echo '<form class="cart" method="post" enctype="multipart/form-data">';
                echo '<button type="button" onclick="window.location.href=\'' . esc_url($download_url) . '\'" class="single_add_to_cart_button button alt">Download Now</button>';
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
                echo '<form class="cart" method="post" enctype="multipart/form-data">';
                echo '<button type="button" onclick="window.location.href=\'' . esc_url($download_url) . '\'" class="single_add_to_cart_button button alt">Download Now</button>';
                echo '</form>';
            } else {
                $plans_page = shutterpress_find_shortcode_page('shutterpress_plans');
                if ($plans_page) {
                    echo '<form class="cart" method="post" enctype="multipart/form-data">';
                    echo '<button type="button" onclick="window.location.href=\'' . esc_url(get_permalink($plans_page)) . '\'" class="single_add_to_cart_button button alt">Subscribe to Download</button>';
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

// Additional function to hide more elements via JavaScript (for dynamic content)
add_action('wp_footer', 'shutterpress_hide_elements_js');

function shutterpress_hide_elements_js()
{
    global $product;

    if (!is_product() || !$product) {
        return;
    }

    $type = get_post_meta($product->get_id(), '_shutterpress_product_type', true);
    if (!$type || $type === 'premium') {
        return;
    }

    ?>
    <script>
        jQuery(document).ready(function ($) {
            // Hide any remaining unwanted elements
            $('.size-guide, .quantity, .product-quantity, .woocommerce-variation-add-to-cart, .single_variation_wrap, .woocommerce-product-attributes, .size-guides, .product-size-guide').hide();

            // Hide compare and wishlist if not needed
            $('.compare, .wishlist').hide();

            // Remove any price display for free/subscription items
            $('.price').hide();
        });
    </script>
    <?php
}