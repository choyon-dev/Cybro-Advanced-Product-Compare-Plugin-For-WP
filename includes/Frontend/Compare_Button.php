<?php
namespace Cybro\ProductCompare\Frontend;

class Compare_Button {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
        add_shortcode('cybro-compare', [$this, 'render_compare_button_shortcode']);
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Add compare button after add to cart button
        add_action('woocommerce_after_add_to_cart_button', [$this, 'render_compare_button']);
        // Add compare button in product list
        add_action('woocommerce_after_shop_loop_item', [$this, 'render_compare_button']);
    }

    /**
     * Render compare button.
     */
    public function render_compare_button() {
        global $product;
        
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $compare_list = (new \Cybro\ProductCompare\Core\ComparisonHandler())->get_compare_list();
        $is_in_compare = in_array($product_id, $compare_list);
        
        $button_text = $is_in_compare 
            ? __('Remove from Compare', 'cybro-product-compare')
            : __('Add to Compare', 'cybro-product-compare');
        
        $button_class = $is_in_compare 
            ? 'cybro-compare-button remove-from-compare'
            : 'cybro-compare-button add-to-compare';

        ?>
        <button 
            type="button" 
            class="<?php echo esc_attr($button_class); ?>"
            data-product-id="<?php echo esc_attr($product_id); ?>"
            data-comparing="<?php echo $is_in_compare ? '1' : '0'; ?>"
        >
            <span class="compare-button-text"><?php echo esc_html($button_text); ?></span>
            <?php if ($is_in_compare): ?>
                <span class="dashicons dashicons-yes"></span>
            <?php else: ?>
                <span class="dashicons dashicons-update"></span>
            <?php endif; ?>
        </button>
        <?php
    }

    /**
     * Render compare button for shortcode.
     */
    public function render_compare_button_shortcode() {
        ob_start();
        $this->render_compare_button();
        return ob_get_clean();
    }

    /**
     * Get button template path.
     *
     * @return string
     */
    private function get_template_path() {
        $template = 'compare-button.php';
        $theme_template = locate_template(['cybro-product-compare/' . $template]);
        
        if ($theme_template) {
            return $theme_template;
        }
        
        return CYBRO_COMPARE_PATH . 'templates/' . $template;
    }
}
