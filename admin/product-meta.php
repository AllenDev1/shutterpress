<?php
add_action('woocommerce_product_options_general_product_data', 'shutterpress_render_product_type_admin_field');

function shutterpress_render_product_type_admin_field()
{
    global $post;

    $is_virtual = isset($_GET['post']) ? get_post_meta($post->ID, '_virtual', true) === 'yes' : true;
    $is_downloadable = isset($_GET['post']) ? get_post_meta($post->ID, '_downloadable', true) === 'yes' : true;

    // Always show the field on Add New screen
    if (isset($_GET['post']) && (!$is_virtual || !$is_downloadable)) {
        return;
    }

    $value = get_post_meta($post->ID, '_shutterpress_product_type', true);

    echo '<div class="options_group">';
    woocommerce_wp_select([
        'id' => '_shutterpress_product_type',
        'label' => __('ShutterPress Product Type', 'shutterpress'),
        'options' => [
            '' => __('Select a type', 'shutterpress'),
            'free' => __('Free (Login Required)', 'shutterpress'),
            'subscription' => __('Subscription-Based', 'shutterpress'),
            'premium' => __('Premium (Paid)', 'shutterpress'),
        ],
        'desc_tip' => true,
        'description' => __('Choose how users can access this downloadable product.', 'shutterpress'),
        'value' => $value
    ]);
    echo '</div>';
}

// Save admin field value
add_action('woocommerce_process_product_meta', function ($post_id) {
    if (isset($_POST['_shutterpress_product_type'])) {
        update_post_meta($post_id, '_shutterpress_product_type', sanitize_text_field($_POST['_shutterpress_product_type']));
    }
});


add_action('save_post_product', function ($post_id) {
    // Avoid autosaves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $product = wc_get_product($post_id);
    if (!$product || !$product->is_downloadable()) {
        return;
    }

    $downloads = $product->get_downloads();
    if (empty($downloads)) return;

    // Use the first downloadable file
    $first = reset($downloads);
    $url = $first['file'];

    // Parse the URL to get the object key
    $parsed = wp_parse_url($url);
    if (empty($parsed['path'])) return;

    $object_key = ltrim($parsed['path'], '/');

    // ❗ Strip bucket name if accidentally included
    $object_key = preg_replace('#^designfabricmedia/#', '', $object_key);

    // Save cleaned key to meta
    update_post_meta($post_id, '_wasabi_object_key', $object_key);
}, 20);
