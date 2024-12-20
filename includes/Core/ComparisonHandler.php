<?php
namespace Cybro\ProductCompare\Core;

class ComparisonHandler {
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
        // Start session securely if not started
        if (!session_id() && !headers_sent()) {
            session_start(['cookie_httponly' => true, 'cookie_secure' => is_ssl()]);
        }

        // Register actions for both authenticated and non-authenticated users
        add_action('wp_ajax_add_to_compare', [$this, 'add_to_compare']);
        add_action('wp_ajax_nopriv_add_to_compare', [$this, 'add_to_compare']);
        add_action('wp_ajax_remove_from_compare', [$this, 'remove_from_compare']);
        add_action('wp_ajax_nopriv_remove_from_compare', [$this, 'remove_from_compare']);

        // Add cleanup hooks
        add_action('wp_logout', [$this, 'clear_session_data']);
        add_action('wp_login', [$this, 'merge_session_data'], 10, 2);
    }

    private function verify_user_access() {
        // Check user authentication and role
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = apply_filters('cybro_compare_allowed_roles', ['customer', 'subscriber', 'editor', 'administrator']);
        
        // Verify user has an allowed role and required capability
        return array_intersect($allowed_roles, $user->roles) && current_user_can('read');
    }

    private function verify_request() {
        if (!$this->verify_user_access() || !check_ajax_referer('cybro_compare_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'cybro-product-compare')], 403);
        }
        return true;
    }

    /**
     * Add product to comparison list.
     */
    public function add_to_compare() {
        $this->verify_request();

        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product', 'cybro-product-compare')], 400);
        }

        $compare_list = $this->get_compare_list();
        
        if (count($compare_list) >= CYBRO_COMPARE_MAX_PRODUCTS) {
            wp_send_json_error([
                'message' => __('Maximum products reached', 'cybro-product-compare')
            ]);
        }

        if (!in_array($product_id, $compare_list)) {
            $compare_list[] = $product_id;
            $this->save_compare_list($compare_list);
        }

        wp_send_json_success([
            'message' => __('Product added to compare', 'cybro-product-compare'),
            'count' => count($compare_list)
        ]);
    }

    /**
     * Remove product from comparison list.
     */
    public function remove_from_compare() {
        $this->verify_request();

        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product', 'cybro-product-compare')], 400);
        }

        $compare_list = $this->get_compare_list();
        $compare_list = array_diff($compare_list, [$product_id]);
        $this->save_compare_list($compare_list);

        wp_send_json_success([
            'message' => __('Product removed from compare', 'cybro-product-compare'),
            'count' => count($compare_list)
        ]);
    }

    /**
     * Get comparison list from session.
     *
     * @return array
     */
    public function get_compare_list() {
        if (!is_user_logged_in()) {
            return [];
        }
        
        $user_id = get_current_user_id();
        $list = get_user_meta($user_id, 'cybro_compare_list', true);
        return !empty($list) ? (array)$list : [];
    }

    /**
     * Save comparison list to session.
     *
     * @param array $list
     */
    private function save_compare_list($list) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        return update_user_meta($user_id, 'cybro_compare_list', array_values(array_unique($list)));
    }

    /**
     * Get comparable attributes for products.
     *
     * @return array
     */
    public function get_comparable_attributes() {
        return [
            'price' => __('Price', 'cybro-product-compare'),
            'description' => __('Description', 'cybro-product-compare'),
            'sku' => __('SKU', 'cybro-product-compare'),
            'stock_status' => __('Stock Status', 'cybro-product-compare'),
            'weight' => __('Weight', 'cybro-product-compare'),
            'dimensions' => __('Dimensions', 'cybro-product-compare')
        ];
    }

    /**
     * Get product data for comparison.
     *
     * @param int $product_id
     * @return array
     */
    public function get_product_data($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return [];
        }

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price_html(),
            'description' => $product->get_short_description(),
            'sku' => $product->get_sku(),
            'stock_status' => $product->get_stock_status(),
            'weight' => $product->get_weight(),
            'dimensions' => wc_format_dimensions($product->get_dimensions(false)),
            'image' => wp_get_attachment_image_src($product->get_image_id(), 'woocommerce_thumbnail'),
            'url' => get_permalink($product_id)
        ];
    }

    /**
     * Clear session data on logout
     */
    public function clear_session_data() {
        if (isset($_SESSION['cybro_compare_list'])) {
            unset($_SESSION['cybro_compare_list']);
        }
    }

    /**
     * Merge session data with user meta on login
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function merge_session_data($user_login, $user) {
        if (isset($_SESSION['cybro_compare_list'])) {
            $session_list = $_SESSION['cybro_compare_list'];
            $user_list = $this->get_compare_list();
            
            // Merge and remove duplicates
            $merged_list = array_values(array_unique(array_merge($session_list, $user_list)));
            
            // Save merged list to user meta
            $this->save_compare_list($merged_list);
            
            // Clear session data
            unset($_SESSION['cybro_compare_list']);
        }
    }
}
