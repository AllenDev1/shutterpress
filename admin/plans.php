<?php

// Handle form submission for adding/editing plans
add_action('admin_post_shutterpress_add_plan', 'shutterpress_handle_add_plan');

function shutterpress_handle_add_plan()
{
    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'shutterpress_add_plan')) {
        wp_die('Security check failed');
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    // Sanitize input
    $plan_name = sanitize_text_field($_POST['plan_name']);
    $quota = intval($_POST['plan_quota']);
    $price = floatval($_POST['plan_price']);
    $billing_cycle = sanitize_text_field($_POST['billing_cycle']);
    $is_unlimited = isset($_POST['is_unlimited']) ? 1 : 0;

    // Validate required fields
    if (empty($plan_name) || empty($quota) || empty($price)) {
        wp_redirect(admin_url('admin.php?page=shutterpress&msg=error'));
        exit;
    }

    // Check if we're editing or adding
    $edit_mode = isset($_POST['plan_id']) && !empty($_POST['plan_id']);

    if ($edit_mode) {
        // Update existing plan
        $plan_id = intval($_POST['plan_id']);
        $result = $wpdb->update(
            $table,
            [
                'plan_name' => $plan_name,
                'quota' => $quota,
                'price' => $price,
                'billing_cycle' => $billing_cycle,
                'is_unlimited' => $is_unlimited,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $plan_id],
            ['%s', '%d', '%f', '%s', '%d', '%s'],
            ['%d']
        );

        if ($result !== false) {
            // Update associated WooCommerce product if it exists
            $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $plan_id));
            if ($plan && $plan->woocommerce_product_id) {
                wp_update_post([
                    'ID' => $plan->woocommerce_product_id,
                    'post_title' => $plan_name,
                ]);
                update_post_meta($plan->woocommerce_product_id, '_regular_price', $price);
                update_post_meta($plan->woocommerce_product_id, '_price', $price);
            }
            wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=shutterpress&edit=' . $plan_id . '&msg=error'));
        }
    } else {
        // Add new plan
        $result = $wpdb->insert(
            $table,
            [
                'plan_name' => $plan_name,
                'quota' => $quota,
                'price' => $price,
                'billing_cycle' => $billing_cycle,
                'is_unlimited' => $is_unlimited,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%d', '%f', '%s', '%d', '%s', '%s']
        );

        if ($result !== false) {
            $plan_id = $wpdb->insert_id;

            // Create associated WooCommerce product
            $product_id = wp_insert_post([
                'post_title' => $plan_name,
                'post_content' => 'Subscription plan: ' . $plan_name,
                'post_status' => 'publish',
                'post_type' => 'product',
            ]);

            if ($product_id) {
                // Set product meta
                update_post_meta($product_id, '_regular_price', $price);
                update_post_meta($product_id, '_price', $price);
                update_post_meta($product_id, '_virtual', 'yes');
                update_post_meta($product_id, '_downloadable', 'yes');
                update_post_meta($product_id, '_shutterpress_product_type', 'subscription');

                // Set product type
                wp_set_object_terms($product_id, 'simple', 'product_type');

                // Link product to plan
                $wpdb->update(
                    $table,
                    ['woocommerce_product_id' => $product_id],
                    ['id' => $plan_id],
                    ['%d'],
                    ['%d']
                );
            }

            wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=added'));
        } else {
            wp_redirect(admin_url('admin.php?page=shutterpress&msg=error'));
        }
    }
    exit;
}

// Handle plan deletion
add_action('admin_post_shutterpress_delete_plan', 'shutterpress_handle_delete_plan');

function shutterpress_handle_delete_plan()
{
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $plan_id = intval($_GET['plan_id']);

    // Verify nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_plan_' . $plan_id)) {
        wp_die('Security check failed');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    // Get plan info before deletion
    $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $plan_id));

    if ($plan) {
        // Delete associated WooCommerce product
        if ($plan->woocommerce_product_id) {
            wp_delete_post($plan->woocommerce_product_id, true);
        }

        // Delete plan
        $result = $wpdb->delete($table, ['id' => $plan_id], ['%d']);

        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=error'));
        }
    } else {
        wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=error'));
    }
    exit;
}

// Handle bulk actions
add_action('admin_post_shutterpress_bulk_action', 'shutterpress_handle_bulk_action');

function shutterpress_handle_bulk_action()
{
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'shutterpress_bulk_action')) {
        wp_die('Security check failed');
    }

    $action = sanitize_text_field($_POST['bulk_action']);
    $plan_ids = array_map('intval', $_POST['plan_ids'] ?? []);

    if (empty($plan_ids) || $action === '-1') {
        wp_redirect(admin_url('admin.php?page=shutterpress_all_plans'));
        exit;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    switch ($action) {
        case 'delete':
            foreach ($plan_ids as $plan_id) {
                // Get plan info
                $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $plan_id));

                if ($plan) {
                    // Delete associated WooCommerce product
                    if ($plan->woocommerce_product_id) {
                        wp_delete_post($plan->woocommerce_product_id, true);
                    }

                    // Delete plan
                    $wpdb->delete($table, ['id' => $plan_id], ['%d']);
                }
            }
            wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=deleted'));
            break;

        default:
            wp_redirect(admin_url('admin.php?page=shutterpress_all_plans'));
            break;
    }
    exit;
}

// Render create/edit plan page
function shutterpress_render_create_plan_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    // Check if we're editing
    $edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);
    $plan = null;

    if ($edit_mode) {
        $plan_id = intval($_GET['edit']);
        $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $plan_id));

        if (!$plan) {
            wp_die('Plan not found.');
        }
    }

    // Handle messages
    $message = '';
    if (isset($_GET['msg'])) {
        switch ($_GET['msg']) {
            case 'error':
                $message = '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>';
                break;
        }
    }

    ?>
        <div class="wrap">
            <h1><?php echo $edit_mode ? 'Edit' : 'Add New'; ?> Subscription Plan</h1>
        
            <?php if ($edit_mode): ?>
                    <p><a href="<?php echo admin_url('admin.php?page=shutterpress_all_plans'); ?>" class="button">← Back to All Plans</a></p>
            <?php endif; ?>
        
            <?php echo $message; ?>
        
            <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="shutterpress_add_plan">
                <?php wp_nonce_field('shutterpress_add_plan'); ?>
                <?php if ($edit_mode): ?>
                        <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan->id); ?>">
                <?php endif; ?>
            
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="plan_name">Plan Name</label></th>
                        <td><input type="text" id="plan_name" name="plan_name" value="<?php echo esc_attr($plan->plan_name ?? ''); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="plan_quota">Quota</label></th>
                        <td>
                            <input type="number" id="plan_quota" name="plan_quota" value="<?php echo esc_attr($plan->quota ?? ''); ?>" class="regular-text" required />
                            <p class="description">Number of downloads allowed per billing cycle</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="plan_price">Price</label></th>
                        <td>
                            <input type="number" step="0.01" id="plan_price" name="plan_price" value="<?php echo esc_attr($plan->price ?? ''); ?>" class="regular-text" required />
                            <p class="description">Price per billing cycle</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="billing_cycle">Billing Cycle</label></th>
                        <td>
                            <select id="billing_cycle" name="billing_cycle">
                                <option value="monthly" <?php selected($plan->billing_cycle ?? '', 'monthly'); ?>>Monthly</option>
                                <option value="quarterly" <?php selected($plan->billing_cycle ?? '', 'quarterly'); ?>>Quarterly</option>
                                <option value="yearly" <?php selected($plan->billing_cycle ?? '', 'yearly'); ?>>Yearly</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_unlimited">Unlimited</label></th>
                        <td>
                            <input type="checkbox" id="is_unlimited" name="is_unlimited" value="1" <?php checked($plan->is_unlimited ?? 0, 1); ?> />
                            <label for="is_unlimited">Enable unlimited downloads</label>
                            <p class="description">If checked, quota limit will be ignored</p>
                        </td>
                    </tr>
                </table>
            
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo $edit_mode ? 'Update Plan' : 'Add Plan'; ?>" />
                    <a href="<?php echo admin_url('admin.php?page=shutterpress_all_plans'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
}

// Render all plans page
function shutterpress_render_all_plans_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';
    $plans = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

    // Handle success/error messages
    $message = '';
    if (isset($_GET['msg'])) {
        switch ($_GET['msg']) {
            case 'added':
                $message = '<div class="notice notice-success"><p>Plan added successfully!</p></div>';
                break;
            case 'updated':
                $message = '<div class="notice notice-success"><p>Plan updated successfully!</p></div>';
                break;
            case 'deleted':
                $message = '<div class="notice notice-success"><p>Plan(s) deleted successfully!</p></div>';
                break;
            case 'error':
                $message = '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>';
                break;
        }
    }

    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">All Subscription Plans</h1>
        
            <!-- Add New Button -->
            <a href="<?php echo admin_url('admin.php?page=shutterpress'); ?>" class="page-title-action">Add New</a>
        
            <hr class="wp-header-end">
        
            <?php echo $message; ?>
        
            <?php if (empty($plans)): ?>
                    <div class="notice notice-info">
                        <p>No subscription plans found. <a href="<?php echo admin_url('admin.php?page=shutterpress'); ?>">Create your first plan</a></p>
                    </div>
            <?php else: ?>
                    <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="shutterpress_bulk_action">
                        <?php wp_nonce_field('shutterpress_bulk_action'); ?>
                
                        <div class="tablenav top">
                            <div class="alignleft actions bulkactions">
                                <select name="bulk_action" id="bulk-action-selector-top">
                                    <option value="-1">Bulk Actions</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <input type="submit" id="doaction" class="button action" value="Apply" 
                                       onclick="return confirm('Are you sure you want to perform this action?');">
                            </div>
                        </div>
                
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <td id="cb" class="manage-column column-cb check-column">
                                        <input id="cb-select-all-1" type="checkbox" onclick="jQuery('.cb-plan').prop('checked', this.checked);" />
                                    </td>
                                    <th scope="col" class="manage-column column-id">ID</th>
                                    <th scope="col" class="manage-column column-name">Name</th>
                                    <th scope="col" class="manage-column column-quota">Quota</th>
                                    <th scope="col" class="manage-column column-price">Price</th>
                                    <th scope="col" class="manage-column column-cycle">Cycle</th>
                                    <th scope="col" class="manage-column column-unlimited">Unlimited</th>
                                    <th scope="col" class="manage-column column-product">Woo Product</th>
                                    <th scope="col" class="manage-column column-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <th scope="row" class="check-column">
                                                <input type="checkbox" name="plan_ids[]" value="<?php echo esc_attr($plan->id); ?>" class="cb-plan" />
                                            </th>
                                            <td class="column-id"><?php echo esc_html($plan->id); ?></td>
                                            <td class="column-name">
                                                <strong>
                                                    <a href="<?php echo admin_url('admin.php?page=shutterpress&edit=' . $plan->id); ?>">
                                                        <?php echo esc_html($plan->plan_name); ?>
                                                    </a>
                                                </strong>
                                                <div class="row-actions">
                                                    <span class="edit">
                                                        <a href="<?php echo admin_url('admin.php?page=shutterpress&edit=' . $plan->id); ?>">Edit</a> |
                                                    </span>
                                                    <span class="delete">
                                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=shutterpress_delete_plan&plan_id=' . $plan->id), 'delete_plan_' . $plan->id); ?>" 
                                                           onclick="return confirm('Are you sure you want to delete this plan?')">Delete</a>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="column-quota"><?php echo esc_html($plan->quota); ?></td>
                                            <td class="column-price"><?php echo wc_price($plan->price); ?></td>
                                            <td class="column-cycle"><?php echo esc_html(ucfirst($plan->billing_cycle)); ?></td>
                                            <td class="column-unlimited">
                                                <?php if ($plan->is_unlimited): ?>
                                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                                <?php else: ?>
                                                        <span class="dashicons dashicons-minus" style="color: #ddd;"></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="column-product">
                                                <?php if ($plan->woocommerce_product_id): ?>
                                                        <a href="<?php echo admin_url('post.php?post=' . $plan->woocommerce_product_id . '&action=edit'); ?>" target="_blank">
                                                            #<?php echo esc_html($plan->woocommerce_product_id); ?>
                                                        </a>
                                                <?php else: ?>
                                                        <span class="description">Not linked</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="column-actions">
                                                <a href="<?php echo admin_url('admin.php?page=shutterpress&edit=' . $plan->id); ?>" class="button button-small">Edit</a>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=shutterpress_delete_plan&plan_id=' . $plan->id), 'delete_plan_' . $plan->id); ?>" 
                                                   class="button button-small" 
                                                   onclick="return confirm('Are you sure you want to delete this plan?')">Delete</a>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                
                        <div class="tablenav bottom">
                            <div class="alignleft actions bulkactions">
                                <select name="bulk_action" id="bulk-action-selector-bottom">
                                    <option value="-1">Bulk Actions</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <input type="submit" id="doaction2" class="button action" value="Apply" 
                                       onclick="return confirm('Are you sure you want to perform this action?');">
                            </div>
                        </div>
                    </form>
            <?php endif; ?>
        </div>
    
        <style>
            .wp-list-table .column-id { width: 60px; }
            .wp-list-table .column-quota { width: 80px; }
            .wp-list-table .column-price { width: 100px; }
            .wp-list-table .column-cycle { width: 100px; }
            .wp-list-table .column-unlimited { width: 80px; text-align: center; }
            .wp-list-table .column-product { width: 100px; }
            .wp-list-table .column-actions { width: 120px; }
        </style>
        <?php
}
?>