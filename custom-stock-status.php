<?php
/**
 * Plugin Name: Custom Stock Delivery Status by Golden Bath
 * Plugin URI: https://goldenbath.gr/
 * Description: Adds custom stock status and delivery time messages on product pages.
 * Version: 1.0.3
 * Author: Mike Lavdanitis
 * Author URI: https://goldenbath.gr/
 */

// Function to initialize custom stock statuses and corresponding messages globally.
function initialize_custom_stock_statuses() {
    global $custom_stock_statuses;
    // Define an array of custom stock statuses with their labels and messages.
    $custom_stock_statuses = array(
        'instock' => array(
            'label' => esc_html__( 'Σε απόθεμα', 'woocommerce' ),
            'message' => esc_html__( 'Παράδoση 1 έως 3 ημέρες', 'woocommerce' )
        ),
        'outofstock' => array(
            'label' => esc_html__( 'Εξαντλημένο', 'woocommerce' ),
            'message' => esc_html__( 'Το προϊόν έχει εξαντληθεί', 'woocommerce' )
        ),
        'onbackorder' => array(
            'label' => esc_html__( 'Προπαραγγελία', 'woocommerce' ),
            'message' => esc_html__( 'Κατόπιν παραγγελίας - έως 30 ημέρες', 'woocommerce' )
        ),
        'instock_2' => array(
            'label' => esc_html__( 'Ετοιμοπαράδοτο', 'woocommerce' ),
            'message' => esc_html__( 'Ετοιμοπαράδοτο', 'woocommerce' )
        ),
        'discontinued' => array(
            'label' => esc_html__( 'Καταργήθηκε', 'woocommerce' ),
            'message' => esc_html__( 'Το προϊόν έχει καταργηθεί', 'woocommerce' )
        ),
    );
}
// Hook the function to the 'init' action.
add_action( 'init', 'initialize_custom_stock_statuses' );

// Function to add new stock status options or override defaults.
add_filter( 'woocommerce_product_stock_status_options', 'filter_woocommerce_product_stock_status_options', 10, 1 );
function filter_woocommerce_product_stock_status_options( $status ) {
    global $custom_stock_statuses;
    // Loop through each custom stock status and add it to the status array.
    foreach ($custom_stock_statuses as $key => $value) {
        $status[$key] = $value['label'];
    }
    return $status; // Return the modified status array.
}

// Function to display custom availability text on product page.
add_filter( 'woocommerce_get_availability_text', 'filter_woocommerce_get_availability_text', 10, 2 );
function filter_woocommerce_get_availability_text( $availability, $product ) {
    global $custom_stock_statuses;
    $stock_status = $product->get_stock_status(); // Get the stock status of the product.
    // Check if the stock status exists in the custom statuses array.
    if (array_key_exists($stock_status, $custom_stock_statuses)) {
        return $custom_stock_statuses[$stock_status]['message']; // Return the custom message.
    }
    return $availability; // Return default availability text if custom status not found.
}

// Function to ensure products with 'discontinued' status are not purchasable.
add_filter( 'woocommerce_is_purchasable', 'custom_is_purchasable', 10, 2 );
function custom_is_purchasable( $purchasable, $product ) {
    // Check if the product has 'discontinued' stock status.
    if ( 'discontinued' === $product->get_stock_status() ) {
        $purchasable = false; // Set purchasable to false.
    }
    return $purchasable; // Return the modified purchasable status.
}

// Function to add CSS class for discontinued status to match out-of-stock styling.
add_filter( 'woocommerce_get_availability_class', 'custom_get_availability_class', 10, 2 );
function custom_get_availability_class( $availability_class, $product ) {
    // Check if the product has 'discontinued' stock status.
    if ( 'discontinued' === $product->get_stock_status() ) {
        $availability_class = 'out-of-stock'; // Set the availability class to 'out-of-stock'.
    }
    return $availability_class; // Return the modified availability class.
}

// Function to add meta key for 'instock_2' and 'discontinued' statuses.
add_action( 'woocommerce_process_product_meta', 'add_custom_stock_status_meta', 10, 1 );
function add_custom_stock_status_meta( $post_id ) {
    $product = wc_get_product( $post_id ); // Get the product object.
    $stock_status = $product->get_stock_status(); // Get the stock status of the product.
    // Check if the stock status is 'instock_2' or 'discontinued'.
    if ( 'instock_2' === $stock_status || 'discontinued' === $stock_status ) {
        update_post_meta( $post_id, '_custom_stock_status', $stock_status ); // Update the meta key with the stock status.
    } else {
        delete_post_meta( $post_id, '_custom_stock_status' ); // Delete the meta key if the stock status is not 'instock_2' or 'discontinued'.
    }
}