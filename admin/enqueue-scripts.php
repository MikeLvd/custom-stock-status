<?php

function enqueue_custom_admin_styles_and_scripts()
{
    // Adjust the path to correctly reference the CSS and JS files
    wp_enqueue_style('admin-custom-stock-status-styles', plugins_url('/assets/css/admin-custom-stock-status.css', __DIR__), array('woocommerce_admin_styles'), '1.0', 'all');
    wp_enqueue_script('admin-custom-stock-status-script', plugins_url('/assets/js/admin-custom-stock-status.js', __DIR__), array('jquery'), '1.0', true);

    // Localize the script with nonce for security
    wp_localize_script('admin-custom-stock-status-script', 'admin_custom_stock_status_script', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('update_stock_status_nonce')
    ));
}

add_action('admin_enqueue_scripts', 'enqueue_custom_admin_styles_and_scripts');
