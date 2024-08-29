<?php

class CustomStockStatusHandler
{
    private static $instance = null;
    private $custom_stock_statuses = array();

    private function __construct()
    {
        // Initialize hooks and actions
        add_action('init', array($this, 'initializeCustomStockStatuses'));
        add_filter('woocommerce_product_stock_status_options', array($this, 'filterProductStockStatusOptions'));
        add_filter('woocommerce_get_availability_text', array($this, 'filterAvailabilityText'), 10, 2);
        add_filter('woocommerce_is_purchasable', array($this, 'validatePurchasable'), 10, 2);
        add_filter('woocommerce_get_availability_class', array($this, 'getStatusAvailabilityClass'), 10, 2);
        add_filter('woodmart_product_label_output', array($this, 'modify_woodmart_labels'), 10, 1);

        // Include admin and public functionalities
        if (is_admin()) {
            $this->include_admin_files();
        } else {
            $this->include_public_files();
        }

        // AJAX action for updating stock status
        add_action('wp_ajax_update_variation_stock_status', array($this, 'update_variation_stock_status'));
    }

    private function include_admin_files()
    {
        require_once CSDS_PLUGIN_DIR . 'admin/class-admin.php';
        require_once CSDS_PLUGIN_DIR . 'admin/enqueue-scripts.php';
    }

    private function include_public_files()
    {
        require_once CSDS_PLUGIN_DIR . 'public/class-public.php';
        require_once CSDS_PLUGIN_DIR . 'public/enqueue-scripts.php';
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initializeCustomStockStatuses();
        }
        return self::$instance;
    }

    public function initializeCustomStockStatuses()
    {
        $default_statuses = array(
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
        $this->custom_stock_statuses = apply_filters('custom_stock_statuses', $default_statuses);
    }

    public function update_variation_stock_status()
    {
        // Check nonce for security
        if (!check_ajax_referer('update_stock_status_nonce', 'security', false)) {
            wp_send_json_error(array('message' => 'Nonce verification failed.'));
            exit;
        }

        // Get the variation ID and the new stock status
        $variation_id = filter_input(INPUT_POST, 'variation_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_SPECIAL_CHARS);

        // Update the stock status of the variation
        if ($variation_id && in_array($new_status, array_keys($this->custom_stock_statuses))) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->exists()) {
                $variation->set_stock_status($new_status);
                $variation->save();

                // Clear product cache (optional)
                wc_delete_product_transients($variation->get_id());

                // Send a success response
                wp_send_json_success(array('message' => 'Stock status updated successfully.'));
            } else {
                wp_send_json_error(array('message' => 'Variation does not exist or failed to load.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Invalid variation ID or stock status.'));
        }
        exit;
    }

    public function get_custom_stock_statuses()
    {
        return $this->custom_stock_statuses;
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

            return sprintf(
                '<span class="availability-label">Διαθεσιμότητα:</span> <span class="availability-status">%s</span><span class="stock-icon" data-title="%s"></span>',
                esc_html($message),
                esc_attr($tooltip)
            );
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
                    break; // If any variation is instore, prioritize it
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
}
