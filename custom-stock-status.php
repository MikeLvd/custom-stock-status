<?php
/**
 * Plugin Name: Custom Stock Delivery Status For Golden Bath
 * Plugin URI: https://goldenbath.gr/
 * Description: Adds custom stock status and delivery time messages on product pages and displays custom stock status in admin product list.
 * Version: 1.4.0
 * Author: Mike Lavdanitis
 * Author URI: https://goldenbath.gr/
 * Text Domain: custom-stock-delivery-status
 * Domain Path: /languages
 */

class CustomStockStatusHandler
{
    private $custom_stock_statuses = array();

    public function __construct()
    {
        // Initialize hooks and actions
        add_action('init', array($this, 'initializeCustomStockStatuses'));
        add_filter('woocommerce_product_stock_status_options', array($this, 'filterProductStockStatusOptions'));
        add_filter('woocommerce_get_availability_text', array($this, 'filterAvailabilityText'), 10, 2);
        add_filter('woocommerce_is_purchasable', array($this, 'validatePurchasable'), 10, 2);
        add_filter('woocommerce_get_availability_class', array($this, 'getStatusAvailabilityClass'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_filter('woodmart_product_label_output', array($this, 'modify_woodmart_labels'), 10, 1);
        add_filter('woocommerce_admin_stock_html', array($this, 'customize_admin_stock_html'), 10, 2);

        // Hooks for custom admin column
        add_filter('manage_edit-product_columns', array($this, 'add_stock_status_column'), 15);
        add_action('manage_product_posts_custom_column', array($this, 'display_variation_stock_status'), 10, 2);

        // AJAX action for updating stock status
        add_action('wp_ajax_update_variation_stock_status', array($this, 'update_variation_stock_status'));
    }

    public function update_variation_stock_status()
    {
        // Check nonce for security
        if (!check_ajax_referer('update_stock_status_nonce', 'security', false)) {
            wp_send_json_error(array('message' => 'Nonce verification failed.'));
            exit; // Ensure no further code execution
        }

        // Get the variation ID and the new stock status
        $variation_id = filter_input(INPUT_POST, 'variation_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

        // Update the stock status of the variation
        if ($variation_id && in_array($new_status, array_keys($this->custom_stock_statuses))) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->exists()) {
                $variation->set_stock_status($new_status);
                $variation->save(); // Ensure the product is saved after updating

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

    public function enqueue_custom_styles()
    {
        wp_enqueue_style('front-custom-stock-status-styles', plugins_url('/css/front-custom-stock-status.css', __FILE__), array(), '1.0', 'all');
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

    // Add the custom column
    public function add_stock_status_column($columns)
    {
        // Insert the new column after the SKU column
        $reordered_columns = array();
        foreach ($columns as $key => $column) {
            $reordered_columns[$key] = $column;
            // After the SKU column, insert the Variation Stock Status column
            if ('sku' === $key) {
                $reordered_columns['variation_stock_status'] = __('Variation Stock', 'custom-stock-delivery-status');
            }
        }
        return $reordered_columns;
    }

    // Display the custom column content
    public function display_variation_stock_status($column, $post_id)
    {
        if ('variation_stock_status' === $column) {
            $product = wc_get_product($post_id);
            if ($product->is_type('variable')) {
                $stock_statuses = '<div class="stock-status-accordion">';
                $stock_statuses .= '<button class="accordion-toggle">+</button>';
                $stock_statuses .= '<div class="accordion-content" style="display:none;">';
                foreach ($product->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);
                    if ($variation && $variation->exists()) {
                        $sku = $variation->get_sku() ?: __('(No SKU)', 'custom-stock-delivery-status');
                        $status = $variation->get_stock_status();
                        $status_label = $this->custom_stock_statuses[$status]['label'];
                        // Create dropdown for status update
                        $dropdown = '<select class="variation-stock-status-dropdown" data-variation-id="' . esc_attr($child_id) . '">';
                        foreach ($this->custom_stock_statuses as $status_key => $status_data) {
                            $selected = $status_key === $status ? 'selected' : '';
                            $dropdown .= '<option value="' . esc_attr($status_key) . '" ' . esc_attr($selected) . '>' . esc_html($status_data['label']) . '</option>';
                        }
                        $dropdown .= '</select>';
                        $stock_statuses .= '<p><span class="variation-sku">' . esc_html($sku) . '</span> = ' . $dropdown . '</p>';
                    }
                }
                $stock_statuses .= '</div></div>';
                echo $stock_statuses;
            } else {
                echo '-';
            }
        }
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('admin-custom-stock-status-styles', plugins_url('/assets/css/admin-custom-stock-status.css', __FILE__), array('woocommerce_admin_styles'), '1.0', 'all');
        wp_enqueue_script('admin-custom-stock-status-script', plugins_url('/assets/js/admin-custom-stock-status.js', __FILE__), array('jquery'), '1.0', true);
        // Localize the script with nonce for security
        wp_localize_script('admin-custom-stock-status-script', 'admin_custom_stock_status_script', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('update_stock_status_nonce')
        ));
    }
}

// Instantiate the class
new CustomStockStatusHandler();