<?php
namespace Cybro\ProductCompare\Frontend;

class Compare_Table {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Allow authenticated users to access compare features
        if (is_user_logged_in() && current_user_can('read')) {
            // Register new shortcode for rendering the compare table
            add_shortcode('cybro-compare', [$this, 'render_compare_table_shortcode']);
            
            // Register AJAX action for authenticated users to get the compare table
            add_action('wp_ajax_get_compare_table', [$this, 'get_compare_table_ajax']);
        }
        
        // Note: The nopriv action is intentionally not added for security reasons
        // This ensures that only logged-in users can access the compare table via AJAX
    }

    /**
     * Render comparison table via shortcode.
     *
     * @return string
     */
    public function render_compare_table() {
        // Verify user permissions for authenticated features
        if (!is_user_logged_in() && !defined('CYBRO_ALLOW_GUEST_COMPARE')) {
            return '<div class="cybro-compare-error">' . 
                   __('Please log in to compare products.', 'cybro-product-compare') . 
                   '</div>';
        }

        $handler = new \Cybro\ProductCompare\Core\ComparisonHandler();
        $compare_list = $handler->get_compare_list();
        
        if (empty($compare_list)) {
            return '<div class="cybro-compare-empty">' . 
                   __('No products to compare', 'cybro-product-compare') . 
                   '</div>';
        }

        ob_start();
        $this->render_table($compare_list);
        return ob_get_clean();
    }

    /**
     * Get comparison table via AJAX.
     */
    public function get_compare_table_ajax() {
        // Set JSON response header early
        header('Content-Type: application/json');

        // Verify both nonce and user capabilities
        if (!check_ajax_referer('cybro-compare-nonce', 'nonce', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized access.', 'cybro-product-compare')], 403);
            return;
        }

        try {
            $handler = new \Cybro\ProductCompare\Core\ComparisonHandler();
            $compare_list = array_map('absint', $handler->get_compare_list());

            // Check if compare list is empty
            if (empty($compare_list)) {
                wp_send_json_success([
                    'html' => '<div class="cybro-compare-empty">' . 
                             esc_html__('No products to compare', 'cybro-product-compare') . 
                             '</div>'
                ]);
                return;
            }

            ob_start();
            $this->render_table($compare_list);
            $html = ob_get_clean();
            
            wp_send_json_success(['html' => $html]);
            
        } catch (\Exception $e) {
            // Ensure any output buffering is cleaned up
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            wp_send_json_error([
                'message' => esc_html__('Error loading comparison table.', 'cybro-product-compare'),
                'details' => WP_DEBUG ? esc_html($e->getMessage()) : null
            ], 500);
        }
    }

    /**
     * Render the comparison table.
     *
     * @param array $product_ids
     */
    private function render_table($product_ids) {
        $handler = new \Cybro\ProductCompare\Core\ComparisonHandler();
        $attributes = $handler->get_comparable_attributes();
        $products = [];

        foreach ($product_ids as $product_id) {
            $products[] = $handler->get_product_data($product_id);
        }

        if (empty($products)) {
            return;
        }
        ?>
        <div class="cybro-compare-table-wrapper">
            <table class="cybro-compare-table">
                <!-- Product Images Row -->
                <tr class="compare-row compare-images">
                    <th><?php _e('Product', 'cybro-product-compare'); ?></th>
                    <?php foreach ($products as $product): ?>
                        <td>
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo esc_url($product['image'][0]); ?>" alt="<?php echo esc_attr($product['name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="product-title">
                                <a href="<?php echo esc_url($product['url']); ?>">
                                    <?php echo esc_html($product['name']); ?>
                                </a>
                            </div>
                            <button 
                                type="button" 
                                class="remove-from-compare"
                                data-product-id="<?php echo esc_attr($product['id']); ?>"
                            >
                                <?php _e('Remove', 'cybro-product-compare'); ?>
                            </button>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- Attributes Rows -->
                <?php foreach ($attributes as $key => $label): ?>
                    <tr class="compare-row compare-<?php echo esc_attr($key); ?>">
                        <th><?php echo esc_html($label); ?></th>
                        <?php foreach ($products as $product): ?>
                            <td>
                                <?php 
                                if (isset($product[$key])) {
                                    echo wp_kses_post($product[$key]);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>

                <!-- Add to Cart Row -->
                <tr class="compare-row compare-add-to-cart">
                    <th><?php _e('Add to Cart', 'cybro-product-compare'); ?></th>
                    <?php foreach ($products as $product): ?>
                        <td>
                            <a href="<?php echo esc_url($product['url']); ?>" class="button">
                                <?php _e('View Product', 'cybro-product-compare'); ?>
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Shortcode handler for comparison table.
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_compare_table_shortcode($atts = []) {
        // Define default attributes
        $default_atts = [
            'columns' => '',              // Specific columns to display
            'limit' => -1,               // Limit number of products
            'category' => '',            // Filter by category
            'class' => '',               // Additional CSS classes
            'show_images' => 'yes',      // Show/hide product images
            'show_price' => 'yes',       // Show/hide product price
            'show_cart' => 'yes'         // Show/hide add to cart button
        ];

        // Parse and merge attributes
        $atts = shortcode_atts($default_atts, $atts, 'cybro-compare');

        // Store attributes for use in render_table
        $this->shortcode_atts = $atts;

        return $this->render_compare_table();
    }
}
