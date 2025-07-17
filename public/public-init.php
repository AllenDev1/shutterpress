<?php
// Exit if accessed directly
defined('ABSPATH') || exit;

// ----------------------------
// Register WooCommerce Endpoints
// ----------------------------
function shutterpress_add_account_endpoints() {
    add_rewrite_endpoint('shutterpress-subscription', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('shutterpress-downloads', EP_ROOT | EP_PAGES);
}
add_action('init', 'shutterpress_add_account_endpoints');

// ----------------------------
// My Account Page Tabs & Content
// ----------------------------
add_filter('woocommerce_account_menu_items', function($items) {
    $items['shutterpress-subscription'] = __('My Subscription', 'shutterpress');
    $items['shutterpress-downloads']    = __('Download History', 'shutterpress');
    return $items;
});

add_action('woocommerce_account_shutterpress-subscription_endpoint', function () {
    echo do_shortcode('[shutterpress_user_subscription]');
});

add_action('woocommerce_account_shutterpress-downloads_endpoint', function () {
    echo do_shortcode('[shutterpress_download_history]');
});

// ----------------------------
// Dokan Dashboard Tabs
// ----------------------------
add_filter('dokan_get_dashboard_nav', function ($urls) {
    $urls['shutterpress-subscription'] = [
        'title' => __('My Subscription', 'shutterpress'),
        'url'   => dokan_get_navigation_url('shutterpress-subscription'),
        'icon'  => '<i class="fas fa-box"></i>',
        'pos'   => 65,
    ];
    $urls['shutterpress-downloads'] = [
        'title' => __('Download History', 'shutterpress'),
        'url'   => dokan_get_navigation_url('shutterpress-downloads'),
        'icon'  => '<i class="fas fa-download"></i>',
        'pos'   => 66,
    ];
    return $urls;
});

add_action('dokan_load_custom_template', function ($query_var) {
    if ($query_var === 'shutterpress-subscription') {
        echo do_shortcode('[shutterpress_user_subscription]');
    }
    if ($query_var === 'shutterpress-downloads') {
        echo do_shortcode('[shutterpress_download_history]');
    }
});


function shutterpress_find_shortcode_page($shortcode) {
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1
    ]);

    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, $shortcode)) {
            return $page->ID;
        }
    }

    return false;
}


// ----------------------------
// Public Logic Includes
// ----------------------------

require_once plugin_dir_path(__FILE__) . 'download-history.php';
require_once plugin_dir_path(__FILE__) . 'download-button-handler.php';
require_once plugin_dir_path(__FILE__) . 'dokan-product-meta.php';
require_once plugin_dir_path(__FILE__) . 'subscription-status.php';
require_once plugin_dir_path(__FILE__) . 'plans-display.php';
