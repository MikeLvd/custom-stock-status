<?php

/**
 * Plugin Name: Custom Stock Delivery Status by Golden Bath
 * Plugin URI: https://goldenbath.gr/
 * Description: Adds custom stock status and delivery time messages on product pages.
 * Version: 1.0.3
 * Author: Mike Lavdanitis
 * Author URI: https://goldenbath.gr/
 * Text Domain: custom-stock-delivery-status
 */
class CustomStockStatusHandler
{
    private $custom_stock_statuses = array();

    public function __construct()
    {
        add_action('init', array($this, 'initializeCustomStockStatuses'));
        add_filter('woocommerce_product_stock_status_options', array($this, 'filterProductStockStatusOptions'));
        add_filter('woocommerce_get_availability_text', array($this, 'filterAvailabilityText'), 10, 2);
        add_filter('woocommerce_is_purchasable', array($this, 'validatePurchasable'), 10, 2);
        add_filter('woocommerce_get_availability_class', array($this, 'getStatusAvailabilityClass'), 10, 2);
        add_action('woocommerce_process_product_meta', array($this, 'addCustomStockStatusMeta'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_styles')); // Enqueue custom styles
    }

    public function initializeCustomStockStatuses()
    {
        $this->custom_stock_statuses = array(
            'instock' => array(
                'label' => esc_html__('Σε απόθεμα', 'custom-stock-delivery-status'),
                'message' => esc_html__('Παράδoση 1 έως 3 ημέρες', 'custom-stock-delivery-status')
            ),
            'outofstock' => array(
                'label' => esc_html__('Εξαντλημένο', 'custom-stock-delivery-status'),
                'message' => esc_html__('Το προϊόν έχει εξαντληθεί', 'custom-stock-delivery-status')
            ),
            'onbackorder' => array(
                'label' => esc_html__('Προπαραγγελία', 'custom-stock-delivery-status'),
                'message' => esc_html__('Κατόπιν παραγγελίας - έως 30 ημέρες', 'custom-stock-delivery-status')
            ),
            'instore' => array(
                'label' => esc_html__('Ετοιμοπαράδοτο', 'custom-stock-delivery-status'),
                'message' => esc_html__('Ετοιμοπαράδοτο', 'custom-stock-delivery-status')
            ),
            'discontinued' => array(
                'label' => esc_html__('Καταργήθηκε', 'custom-stock-delivery-status'),
                'message' => esc_html__('Αυτό το προϊόν έχει καταργηθεί απο τον κατασκευαστή', 'custom-stock-delivery-status')
            )
        );
    }

    public function filterProductStockStatusOptions($status)
    {
        foreach ($this->custom_stock_statuses as $key => $value) {
            $status[$key] = $value['label'];
        }
        return $status;
    }

    public function filterAvailabilityText($availability, $product)
    {
        $stock_status = $product->get_stock_status();
        if (array_key_exists($stock_status, $this->custom_stock_statuses)) {
            return $this->custom_stock_statuses[$stock_status]['message'];
        }
        return $availability;
    }

    public function validatePurchasable($purchasable, $product)
    {
        if ('discontinued' === $product->get_stock_status()) {
            $purchasable = false;
        }
        return $purchasable;
    }

    public function getStatusAvailabilityClass($availability_class, $product)
    {
        if (array_key_exists($product->get_stock_status(), $this->custom_stock_statuses)) {
            $availability_class = $product->get_stock_status();
        }
        return $availability_class;
    }

    public function addCustomStockStatusMeta($post_id)
    {
        $product = wc_get_product($post_id);
        $stock_status = $product->get_stock_status();
        if ('instore' === $stock_status || 'discontinued' === $stock_status) {
            update_post_meta($post_id, '_custom_stock_status', $stock_status);
        } else {
            delete_post_meta($post_id, '_custom_stock_status');
        }
    }

    public function enqueue_custom_styles()
    {
        wp_enqueue_style('custom-stock-status-styles', plugins_url('/css/custom-stock-status.css', __FILE__));
    }
}

new CustomStockStatusHandler();
