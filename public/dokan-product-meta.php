<?php
// Add field to Dokan vendor product form (both new and edit)
add_action('dokan_new_product_after_product_type', 'shutterpress_add_dokan_product_type_field');
// Add field to Dokan vendor product form (both new and edit)
add_action('dokan_product_edit_after_inventory', 'shutterpress_add_dokan_product_type_field');

function shutterpress_add_dokan_product_type_field($post_id = 0) {
    $value = $post_id ? get_post_meta($post_id, '_shutterpress_product_type', true) : '';

    ?>
    <div class="dokan-form-group">
        <label for="shutterpress_product_type"><?php _e('ShutterPress Product Type', 'shutterpress'); ?></label>
        <select name="shutterpress_product_type" class="dokan-form-control">
            <option value=""><?php _e('Select a type', 'shutterpress'); ?></option>
            <option value="free" <?php selected($value, 'free'); ?>><?php _e('Free (Login Required)', 'shutterpress'); ?></option>
            <option value="subscription" <?php selected($value, 'subscription'); ?>><?php _e('Subscription-Based', 'shutterpress'); ?></option>
            <option value="premium" <?php selected($value, 'premium'); ?>><?php _e('Premium (Paid)', 'shutterpress'); ?></option>
        </select>
    </div>
    <?php
}




// Save field on submit
add_action('dokan_process_product_meta', function ($post_id, $post = []) {
    if (isset($_POST['shutterpress_product_type'])) {
        update_post_meta($post_id, '_shutterpress_product_type', sanitize_text_field($_POST['shutterpress_product_type']));
    }
}, 10, 2);

