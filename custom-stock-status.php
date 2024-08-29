<?php
/**
 * Plugin Name: Custom Stock Delivery Status For Golden Bath
 * Plugin URI: https://goldenbath.gr/
 * Description: Adds custom stock status and delivery time messages on product pages and displays custom stock status in admin product list.
 * Version: 1.5.0
 * Author: Mike Lavdanitis
 * Author URI: https://goldenbath.gr/
 * Text Domain: custom-stock-delivery-status
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
    add_action( 'admin_notices', 'csds_woocommerce_inactive_notice' );
    return;
}

// Function to display admin notice if WooCommerce is not active
function csds_woocommerce_inactive_notice() {
    ?>
    <div class="error notice">
        <p><?php _e( 'Το πρόσθετο Custom Stock Delivery Status απαιτεί το WooCommerce να είναι εγκατεστημένο και ενεργό.', 'custom-stock-delivery-status' ); ?></p>
    </div>
    <?php
}

define('CSDS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once CSDS_PLUGIN_DIR . 'includes/class-custom-stock-status-handler.php';

// Initialize the main handler class
CustomStockStatusHandler::get_instance();
