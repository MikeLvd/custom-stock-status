<?php
/**
 * Plugin Name: Custom Stock Delivery Status by Golden Bath
 * Plugin URI: https://goldenbath.gr/
 * Description: Adds custom stock status and delivery time messages on product pages and displays custom stock status in admin product list.
 * Version: 1.2.0
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_styles')); // Enqueue custom styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles')); // Enqueue admin styles
        add_filter('woodmart_product_label_output', array($this, 'modify_woodmart_labels'), 10, 1);
        add_filter('woocommerce_admin_stock_html', array($this, 'customize_admin_stock_html'), 10, 2); // Hook for admin stock status display
    }

    public function initializeCustomStockStatuses()
    {
        $this->custom_stock_statuses = array(
            'instock' => array(
                'label' => esc_html__('Σε απόθεμα', 'custom-stock-delivery-status'),
                'message' => esc_html__('1 έως 3 ημέρες', 'custom-stock-delivery-status'),
                'tooltip' => esc_html__('Αυτό το προϊόν είναι σε απόθεμα στον προμηθευτή και διαθέσιμο για άμεση παραγγελία', 'custom-stock-delivery-status')
            ),
            'outofstock' => array(
                'label' => esc_html__('Εξαντλημένο', 'custom-stock-delivery-status'),
                'message' => esc_html__('Εξαντλημένο', 'custom-stock-delivery-status'),
                'tooltip' => esc_html__('Αυτό το προϊόν έχει εξαντληθεί προς το παρόν', 'custom-stock-delivery-status')
            ),
            'onbackorder' => array(
                'label' => esc_html__('Προπαραγγελία', 'custom-stock-delivery-status'),
                'message' => esc_html__('Κατόπιν παραγγελίας', 'custom-stock-delivery-status'),
                'tooltip' => esc_html__('Αυτό το προϊόν είναι κατόπιν παραγγελίας και θα αποσταλεί μόλις είναι διαθέσιμο', 'custom-stock-delivery-status')
            ),
            'instore' => array(
                'label' => esc_html__('Ετοιμοπαράδοτο', 'custom-stock-delivery-status'),
                'message' => esc_html__('Ετοιμοπαράδοτο', 'custom-stock-delivery-status'),
                'tooltip' => esc_html__('Αυτό το προϊόν είναι ετοιμοπαράδοτο και άμεσα διαθέσιμο στο κατάστημα μας', 'custom-stock-delivery-status')
            ),
            'discontinued' => array(
                'label' => esc_html__('Καταργήθηκε', 'custom-stock-delivery-status'),
                'message' => esc_html__('Καταργήθηκε', 'custom-stock-delivery-status'),
                'tooltip' => esc_html__('Αυτό το προϊόν έχει καταργηθεί απο τον κατασκευαστή και δεν είναι πλέον διαθέσιμο', 'custom-stock-delivery-status')
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
            $message = $this->custom_stock_statuses[$stock_status]['message'];
            $tooltip = $this->custom_stock_statuses[$stock_status]['tooltip'];
            return '<span class="availability-label">Διαθεσιμότητα:</span> <span class="availability-status">' . esc_html($message) . '</span><span class="stock-icon" data-title="' . esc_attr($tooltip) . '"></span>';
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

    public function enqueue_custom_styles()
    {
        wp_enqueue_style('front-custom-stock-status-styles', plugins_url('/css/front-custom-stock-status.css', __FILE__));
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('admin-custom-stock-status-styles', plugins_url('/css/admin-custom-stock-status.css', __FILE__), array('woocommerce_admin_styles'));
    }

    public function modify_woodmart_labels($output)
    {
        global $product;

        $stock_status = $product->get_stock_status();
        $has_instore = false;
        $all_outofstock = true;
        $all_discontinued = true;

        // Handle simple products
        if ($stock_status === 'instore') {
            $this->remove_out_of_stock_label($output);
            $output[] = '<span class="instore product-label">' . esc_html__('Ετοιμοπαράδοτο', 'custom-stock-delivery-status') . '</span>';
            return $output;
        }

        if ($stock_status === 'discontinued') {
            $this->remove_out_of_stock_label($output);
            $output[] = '<span class="discontinued product-label">' . esc_html__('Καταργήθηκε', 'custom-stock-delivery-status') . '</span>';
            return $output;
        }

        // Handle variable products
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                $variation_status = $variation_obj->get_stock_status();

                if ($variation_status === 'instore') {
                    $has_instore = true;
                    $all_outofstock = false;
                    $all_discontinued = false;
                    break; // If any variation is instore, we prioritize it
                }

                if ($variation_status !== 'outofstock') {
                    $all_outofstock = false;
                }

                if ($variation_status !== 'discontinued') {
                    $all_discontinued = false;
                }
            }

            if ($has_instore) {
                $this->remove_out_of_stock_label($output);
                $output[] = '<span class="instore product-label">' . esc_html__('Ετοιμοπαράδοτο', 'custom-stock-delivery-status') . '</span>';
            } elseif ($all_discontinued) {
                $this->remove_out_of_stock_label($output);
                $output[] = '<span class="discontinued product-label">' . esc_html__('Καταργήθηκε', 'custom-stock-delivery-status') . '</span>';
            } elseif ($all_outofstock) {
                $this->remove_out_of_stock_label($output);
                $output[] = '<span class="out-of-stock product-label">' . esc_html__('Sold out', 'woodmart') . '</span>';
            }
        } elseif ($stock_status === 'outofstock') {
            // Handle simple products that are out of stock
            $this->remove_out_of_stock_label($output);
            $output[] = '<span class="out-of-stock product-label">' . esc_html__('Sold out', 'woodmart') . '</span>';
        }

        return $output;
    }

    private function remove_out_of_stock_label(&$output)
    {
        foreach ($output as $key => $label) {
            if (strpos($label, 'out-of-stock') !== false) {
                unset($output[$key]);
            }
        }
    }

    public function customize_admin_stock_html($stock_html, $product)
    {
        // Handle variable products
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            $priority = [
                'discontinued' => 1,
                'outofstock'   => 2,
                'onbackorder'  => 3,
                'instock'      => 4,
                'instore'      => 5
            ];

            $highest_priority_status = null;
            $highest_priority = 0;

            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variation_status = $variation->get_stock_status();
                    if (isset($priority[$variation_status]) && $priority[$variation_status] > $highest_priority) {
                        $highest_priority_status = $variation_status;
                        $highest_priority = $priority[$variation_status];
                    }
                }
            }

            if ($highest_priority_status && isset($this->custom_stock_statuses[$highest_priority_status])) {
                $stock_html = '<mark class="' . esc_attr($highest_priority_status) . '">' . esc_html($this->custom_stock_statuses[$highest_priority_status]['label']) . '</mark>';
                return $stock_html;
            }
        }

        // Handle simple products or fallback for variable products
        $stock_status = $product->get_stock_status();

        if (array_key_exists($stock_status, $this->custom_stock_statuses)) {
            $stock_html = '<mark class="' . esc_attr($stock_status) . '">' . esc_html($this->custom_stock_statuses[$stock_status]['label']) . '</mark>';
        }

        if ($product->managing_stock()) {
            $stock_html .= ' (' . wc_stock_amount($product->get_stock_quantity()) . ')';
        }

        return $stock_html;
    }
}

new CustomStockStatusHandler();