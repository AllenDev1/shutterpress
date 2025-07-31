<?php
// Exit if accessed directly
defined('ABSPATH') || exit;

// ----------------------------
// Register WooCommerce Endpoints
// ----------------------------
function shutterpress_add_account_endpoints()
{
    add_rewrite_endpoint('shutterpress-subscription', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('shutterpress-downloads', EP_ROOT | EP_PAGES);
}
add_action('init', 'shutterpress_add_account_endpoints');

// ----------------------------
// My Account Page Tabs & Content
// ----------------------------
add_filter('woocommerce_account_menu_items', function ($items) {
    $items['shutterpress-subscription'] = __('My Subscription', 'shutterpress');
    $items['shutterpress-downloads'] = __('Download History', 'shutterpress');
    return $items;
});

add_action('woocommerce_account_shutterpress-subscription_endpoint', function () {
    echo do_shortcode('[shutterpress_user_subscription]');
});

add_action('woocommerce_account_shutterpress-downloads_endpoint', function () {
    echo do_shortcode('[shutterpress_download_history]');
});

// ----------------------------
// Comprehensive Dokan Dashboard Solution
// ----------------------------

// Add menu items to dashboard navigation
// add_filter('dokan_get_dashboard_nav', 'shutterpress_add_dashboard_nav');
// function shutterpress_add_dashboard_nav($urls) {
//     $urls['shutterpress-subscription'] = [
//         'title' => __('My Subscription', 'shutterpress'),
//         'url' => dokan_get_navigation_url('shutterpress-subscription'),
//         'icon' => '<i class="fas fa-box"></i>',
//         'pos' => 65,
//     ];
//     $urls['shutterpress-downloads'] = [
//         'title' => __('Download History', 'shutterpress'),
//         'url' => dokan_get_navigation_url('shutterpress-downloads'),
//         'icon' => '<i class="fas fa-download"></i>',
//         'pos' => 66,
//     ];
//     return $urls;
// }

// Fallback if Dokan doesn't render our page
add_action('template_redirect', function () {
    if (!function_exists('dokan_is_user_dashboard') || !dokan_is_user_dashboard()) return;

    $uri = $_SERVER['REQUEST_URI'];

    ob_start();

    add_action('dokan_dashboard_content_inside', function () use ($uri) {
        if (strpos($uri, 'shutterpress-subscription') !== false) {
            echo '<div class="shutterpress-dashboard-page"><div class="page-header"><h2>My Subscription</h2></div><div class="page-content">';
            echo do_shortcode('[shutterpress_user_subscription]');
            echo '</div></div>';
        }

        if (strpos($uri, 'shutterpress-downloads') !== false) {
            echo '<div class="shutterpress-dashboard-page"><div class="page-header"><h2>Download History</h2></div><div class="page-content">';
            echo do_shortcode('[shutterpress_download_history]');
            echo '</div></div>';
        }
    });

    add_action('wp_footer', function () {
        ob_end_flush();
    });
});


// Render subscription page content
function shutterpress_render_subscription_page() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    
    ?>
    <div class="shutterpress-dashboard-page">
        <div class="page-header">
            <h2>My Subscription</h2>
        </div>
        <div class="page-content">
            <?php echo do_shortcode('[shutterpress_user_subscription]'); ?>
        </div>
    </div>
    <?php
    shutterpress_add_dashboard_styles();
}

// Render downloads page content  
function shutterpress_render_downloads_page() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    
    ?>
    <div class="shutterpress-dashboard-page">
        <div class="page-header">
            <h2>Download History</h2>
        </div>
        <div class="page-content">
            <?php echo do_shortcode('[shutterpress_download_history]'); ?>
        </div>
    </div>
    <?php
    shutterpress_add_dashboard_styles();
}

// Add styling for dashboard pages
function shutterpress_add_dashboard_styles() {
    static $styles_added = false;
    if ($styles_added) return;
    $styles_added = true;
    
    ?>
    <style>
    .shutterpress-dashboard-page {
        width: 100%;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .shutterpress-dashboard-page .page-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 20px 30px;
    }
    
    .shutterpress-dashboard-page .page-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #495057;
    }
    
    .shutterpress-dashboard-page .page-content {
        padding: 30px;
    }
    
    .page-content .shutterpress-subscription-wrapper,
    .page-content .shutterpress-download-history-wrapper {
        margin: 0;
        max-width: none;
    }
    
    .page-content .subscription-header,
    .page-content .download-history-header {
        display: none;
    }
    
    @media (max-width: 768px) {
        .shutterpress-dashboard-page .page-header,
        .shutterpress-dashboard-page .page-content {
            padding: 20px;
        }
        
        .shutterpress-dashboard-page .page-header h2 {
            font-size: 20px;
        }
    }
    </style>
    <?php
}


//--------------------
// Utility Functions
// ----------------------------
function shutterpress_find_shortcode_page($shortcode)
{
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

// Hide tagged subscription plan products from public queries
add_action('pre_get_posts', function ($query) {
    // Skip admin, but allow all frontend queries including widgets/shortcodes
    if (is_admin()) return;

    // Apply only to WooCommerce product queries
    if ('product' !== $query->get('post_type')) return;

    $tax_query = $query->get('tax_query') ?: [];

    $tax_query[] = [
        'taxonomy' => 'product_tag',
        'field'    => 'slug',
        'terms'    => ['shutterpress-plan-product'],
        'operator' => 'NOT IN',
    ];

    $query->set('tax_query', $tax_query);
});


// TEMPORARY DEBUG - Remove after testing
// add_action('wp_footer', function() {
//     global $wp;
//     if (function_exists('dokan_is_user_dashboard') && dokan_is_user_dashboard() && current_user_can('manage_options')) {
//         echo '<script>console.log("Query vars:", ' . json_encode($wp->query_vars) . ');</script>';
//         echo '<script>console.log("Current URL:", "' . $_SERVER['REQUEST_URI'] . '");</script>';
//     }
// });
// ----------------------------
// Public Logic Includes
// ----------------------------
require_once plugin_dir_path(__FILE__) . 'download-history.php';
require_once plugin_dir_path(__FILE__) . 'download-button-handler.php';
require_once plugin_dir_path(__FILE__) . 'dokan-product-meta.php';
require_once plugin_dir_path(__FILE__) . 'subscription-status.php';
require_once plugin_dir_path(__FILE__) . 'plans-display.php';
require_once plugin_dir_path(__FILE__) . 'watermark-handler.php';
require_once plugin_dir_path(__FILE__) . 'dokan-hooks.php';