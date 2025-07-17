<?php

function shutterpress_settings_page_callback()
{
    ?>
    <div class="wrap">
        <h1>ShutterPress Settings</h1>

        <h2>Plugin Information</h2>
        <p><strong>Version:</strong> 1.0.0</p>
        <p><strong>Description:</strong> Shutterstock-style image download system using WooCommerce, Dokan, and custom
            quotas.</p>

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
