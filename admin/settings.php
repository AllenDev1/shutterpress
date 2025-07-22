<?php

function shutterpress_settings_page_callback()
{
    // Handle saving plans page
    if (isset($_POST['shutterpress_settings_submit'])) {
        check_admin_referer('shutterpress_settings_form');

        if (isset($_POST['shutterpress_plans_page_id'])) {
            update_option('shutterpress_plans_page_id', (int) $_POST['shutterpress_plans_page_id']);
            echo '<div class="notice notice-success"><p>✅ Settings updated.</p></div>';
        }
    }

    $plans_page_id = get_option('shutterpress_plans_page_id');
    ?>

    <div class="wrap">
        <h1>ShutterPress Settings</h1>

        <h2>Plugin Information</h2>
        <p><strong>Version:</strong> 1.0.0</p>
        <p><strong>Description:</strong> Shutterstock-style image download system using WooCommerce, Dokan, and custom
            quotas.</p>

        <hr>

        <h2>Plans Page</h2>
        <form method="post">
            <?php wp_nonce_field('shutterpress_settings_form'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Subscription Plans Page</th>
                    <td>
                        <?php wp_dropdown_pages([
                            'name' => 'shutterpress_plans_page_id',
                            'selected' => $plans_page_id,
                            'show_option_none' => '-- Select a page --',
                        ]); ?>
                        <p class="description">This page should contain the <code>[shutterpress_plans]</code> shortcode. It
                            is shown to users when they want to subscribe to a plan.</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="shutterpress_settings_submit" class="button button-primary" value="Save Settings">
            </p>
        </form>

        <hr>

        <h2>Database Setup</h2>
        <p>If you're seeing issues with missing tables, click below to manually create them.</p>

        <form method="post">
            <?php wp_nonce_field('shutterpress_create_tables_action', 'shutterpress_create_tables_nonce'); ?>
            <input type="submit" name="shutterpress_create_tables" class="button button-primary"
                value="Create Database Tables">
        </form>

        <?php
        if (isset($_POST['shutterpress_create_tables']) && check_admin_referer('shutterpress_create_tables_action', 'shutterpress_create_tables_nonce')) {
            if (function_exists('shutterpress_activate_plugin')) {
                shutterpress_activate_plugin();
                echo '<div class="notice notice-success"><p>✅ ShutterPress tables created successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Error: Activation function not found.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}
