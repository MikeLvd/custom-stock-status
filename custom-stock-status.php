<?php
/**
 * Plugin Name: Custom Stock Delivery Status by Golden Bath
 * Plugin URI: https://goldenbath.gr/
 * Description: Adds custom stock status and delivery time messages on product pages and displays custom stock status in admin product list.
 * Version: 1.5.0
 * Author: Mike Lavdanitis
 * Author URI: https://goldenbath.gr/
 * Text Domain: custom-stock-delivery-status
 */

defined('ABSPATH') || exit;

define('CSDS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once CSDS_PLUGIN_DIR . 'includes/class-custom-stock-status-handler.php';

// Initialize the main handler class
CustomStockStatusHandler::get_instance();
