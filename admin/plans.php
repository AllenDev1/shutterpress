<?php

// Handle Add/Edit/Delete actions
add_action('admin_post_shutterpress_add_plan', 'shutterpress_handle_add_plan');
add_action('admin_post_shutterpress_delete_plan', 'shutterpress_handle_delete_plan');
add_action('admin_post_shutterpress_bulk_action', 'shutterpress_handle_bulk_action');

function shutterpress_handle_add_plan()
{
    if (!current_user_can('manage_options'))
        wp_die('Unauthorized');

    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    $name = sanitize_text_field($_POST['plan_name']);
    $quota = intval($_POST['plan_quota']);
    $price = floatval($_POST['plan_price']);
    $cycle = sanitize_text_field($_POST['billing_cycle']);
    $is_unlimited = isset($_POST['is_unlimited']) ? 1 : 0;

    if (!empty($_POST['plan_id'])) {
        // Edit mode
        $plan_id = intval($_POST['plan_id']);
        $wpdb->update($table, [
            'plan_name' => $name,
            'quota' => $quota,
            'price' => $price,
            'billing_cycle' => $cycle,
            'is_unlimited' => $is_unlimited,
        ], ['id' => $plan_id]);

        wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=updated'));
        exit;
    }

    // Add mode
    $wpdb->insert($table, [
        'plan_name' => $name,
        'quota' => $quota,
        'price' => $price,
        'billing_cycle' => $cycle,
        'is_unlimited' => $is_unlimited,
    ]);
    $plan_id = $wpdb->insert_id;

    // Create WC Product
    if ($plan_id) {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_regular_price($price);
        $product->set_virtual(true);
        $product->set_downloadable(true);
        $product->set_manage_stock(false); // New
        $product->set_stock_status('instock'); // New
        $product_id = $product->save();

        if ($product_id) {
            $wpdb->update($table, ['woocommerce_product_id' => $product_id], ['id' => $plan_id]);
        }
    }



    wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=success'));
    exit;
}

function shutterpress_handle_delete_plan()
{
    if (!current_user_can('manage_options') || !isset($_GET['id']))
        wp_die('Unauthorized');

    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';
    $plan_id = intval($_GET['id']);
    $wpdb->delete($table, ['id' => $plan_id]);

    wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=deleted'));
    exit;
}

function shutterpress_handle_bulk_action()
{
    if (!current_user_can('manage_options'))
        wp_die('Unauthorized');
    if (!isset($_POST['bulk_action'], $_POST['plan_ids']) || !is_array($_POST['plan_ids'])) {
        wp_redirect(admin_url('admin.php?page=shutterpress_all_plans'));
        exit;
    }

    global $wpdb;
    $ids = array_map('intval', $_POST['plan_ids']);
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    if ($_POST['bulk_action'] === 'delete') {
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($in)", ...$ids));
    }

    wp_redirect(admin_url('admin.php?page=shutterpress_all_plans&msg=bulk_deleted'));
    exit;
}

function shutterpress_render_create_plan_page()
{
    $edit_mode = isset($_GET['edit']) ? true : false;
    $plan = null;

    if ($edit_mode) {
        global $wpdb;
        $table = $wpdb->prefix . 'shutterpress_subscription_plans';
        $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit'])));
    }

    ?>
    <div class="wrap">
        <h1><?php echo $edit_mode ? 'Edit' : 'Create'; ?> Subscription Plan</h1>
        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="shutterpress_add_plan">
            <?php wp_nonce_field('shutterpress_add_plan'); ?>
            <?php if ($edit_mode): ?>
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan->id); ?>">
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th>Plan Name</th>
                    <td><input type="text" name="plan_name" value="<?php echo esc_attr($plan->plan_name ?? ''); ?>"
                            required /></td>
                </tr>
                <tr>
                    <th>Quota</th>
                    <td><input type="number" name="plan_quota" value="<?php echo esc_attr($plan->quota ?? ''); ?>"
                            required /></td>
                </tr>
                <tr>
                    <th>Price</th>
                    <td><input type="number" step="0.01" name="plan_price"
                            value="<?php echo esc_attr($plan->price ?? ''); ?>" required /></td>
                </tr>
                <tr>
                    <th>Billing Cycle</th>
                    <td>
                        <select name="billing_cycle">
                            <option value="monthly" <?php selected($plan->billing_cycle ?? '', 'monthly'); ?>>Monthly
                            </option>
                            <option value="quarterly" <?php selected($plan->billing_cycle ?? '', 'quarterly'); ?>>Quarterly
                            </option>
                            <option value="yearly" <?php selected($plan->billing_cycle ?? '', 'yearly'); ?>>Yearly</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Unlimited?</th>
                    <td><input type="checkbox" name="is_unlimited" value="1" <?php checked($plan->is_unlimited ?? 0, 1); ?> /></td>
                </tr>
            </table>
            <p><input type="submit" class="button button-primary"
                    value="<?php echo $edit_mode ? 'Update Plan' : 'Add Plan'; ?>" /></p>
        </form>
    </div>
    <?php
}

function shutterpress_render_all_plans_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';
    $plans = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

    $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
    if ($msg === 'success')
        echo '<div class="notice notice-success"><p>✅ Plan and product created.</p></div>';
    if ($msg === 'updated')
        echo '<div class="notice notice-success"><p>✅ Plan updated.</p></div>';
    if ($msg === 'deleted')
        echo '<div class="notice notice-success"><p>🗑️ Plan deleted.</p></div>';
    if ($msg === 'bulk_deleted')
        echo '<div class="notice notice-success"><p>🗑️ Bulk plans deleted.</p></div>';

    ?>
    <div class="wrap">
        <h1>All Subscription Plans</h1>
        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="shutterpress_bulk_action">
            <select name="bulk_action">
                <option value="">Bulk Actions</option>
                <option value="delete">Delete</option>
            </select>
            <input type="submit" class="button" value="Apply">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" onclick="jQuery('.cb-plan').prop('checked', this.checked);" /></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Quota</th>
                        <th>Price</th>
                        <th>Cycle</th>
                        <th>Unlimited</th>
                        <th>Woo Product</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><input type="checkbox" name="plan_ids[]" value="<?php echo esc_attr($plan->id); ?>"
                                    class="cb-plan" /></td>
                            <td><?php echo esc_html($plan->id); ?></td>
                            <td><?php echo esc_html($plan->plan_name); ?></td>
                            <td><?php echo esc_html($plan->quota); ?></td>
                            <td><?php echo esc_html($plan->price); ?></td>
                            <td><?php echo esc_html($plan->billing_cycle); ?></td>
                            <td><?php echo $plan->is_unlimited ? 'Yes' : 'No'; ?></td>
                            <td>
                                <?php
                                if ($plan->woocommerce_product_id) {
                                    echo '<a href="' . esc_url(admin_url('post.php?post=' . $plan->woocommerce_product_id . '&action=edit')) . '" target="_blank">' . esc_html($plan->woocommerce_product_id) . '</a>';
                                } else {
                                    echo '-';
                                }
                                ?>

                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=shutterpress&edit=' . $plan->id); ?>">✏️ Edit</a>
                                |
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=shutterpress_delete_plan&id=' . $plan->id), 'delete_plan_' . $plan->id); ?>"
                                    onclick="return confirm('Delete this plan?')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
    <?php
}
