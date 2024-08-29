<?php

class CustomStockStatusAdmin
{
    private $custom_stock_statuses;

    public function __construct()
    {
        $handler = CustomStockStatusHandler::get_instance();
        $this->custom_stock_statuses = $handler->get_custom_stock_statuses();

        add_filter('manage_edit-product_columns', array($this, 'add_stock_status_column'), 15);
        add_action('manage_product_posts_custom_column', array($this, 'display_variation_stock_status'), 10, 2);
    }

    public function add_stock_status_column($columns)
    {
        $reordered_columns = array();
        foreach ($columns as $key => $column) {
            $reordered_columns[$key] = $column;
            if ('sku' === $key) {
                $reordered_columns['variation_stock_status'] = __('Απόθεμα παραλλαγών', 'custom-stock-delivery-status');
            }
        }
        return $reordered_columns;
    }

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
                        $sku = $variation->get_sku() ?: __('(Κανένας κωδικός)', 'custom-stock-delivery-status');
                        $status = $variation->get_stock_status();
                        $status_label = $this->custom_stock_statuses[$status]['label'];

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
}

// Initialize the class
new CustomStockStatusAdmin();
