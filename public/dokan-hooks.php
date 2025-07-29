<?php
// File: public/dokan-hooks.php

add_action('dokan_new_product_added', 'shutterpress_handle_dokan_product_save', 20, 2);
add_action('dokan_product_updated', 'shutterpress_handle_dokan_product_save', 20, 2);

function shutterpress_handle_dokan_product_save($product_id, $postdata) {
    if (get_post_type($product_id) !== 'product') return;

    // Handle downloadable files (Wasabi upload + local cleanup)
    shutterpress_handle_downloadable_files($product_id);

    // Watermarking
    if (!class_exists('ShutterPress_Watermark_Handler')) return;

    global $shutterpress_watermark_handler;

    if (!$shutterpress_watermark_handler instanceof ShutterPress_Watermark_Handler) return;

    // Featured image
    $thumbnail_id = get_post_thumbnail_id($product_id);
    if ($thumbnail_id) {
        $shutterpress_watermark_handler->get_watermarked_image_url($thumbnail_id);
    }

    // Gallery images
    $product = wc_get_product($product_id);
    if ($product) {
        foreach ($product->get_gallery_image_ids() as $img_id) {
            $shutterpress_watermark_handler->get_watermarked_image_url($img_id);
        }
    }
}
