<?php

function enqueue_custom_admin_styles_and_scripts()
{
    // Adjust the path to correctly reference the CSS and JS files
    wp_enqueue_style('admin-custom-stock-status-styles', plugins_url('/assets/css/admin-custom-stock-status.css', __DIR__), array('woocommerce_admin_styles'), '1.0', 'all');
    wp_enqueue_script('admin-custom-stock-status-script', plugins_url('/assets/js/admin-custom-stock-status.js', __DIR__), array('jquery'), '1.0', true);

    // Localize the script with translations and nonce for security
    $translations = array(
        'success'       => __('Επιτυχία', 'custom-stock-delivery-status'),
        'error'         => __('Σφάλμα', 'custom-stock-delivery-status'),
        'update_error'  => __('Υπήρξε σφάλμα στην ενημέρωση της κατάστασης των αποθεμάτων.', 'custom-stock-delivery-status'),
    );

    wp_localize_script('admin-custom-stock-status-script', 'admin_custom_stock_status_script', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('update_stock_status_nonce'),
        'translations' => $translations,
    ));
}

add_action('admin_enqueue_scripts', 'enqueue_custom_admin_styles_and_scripts');
