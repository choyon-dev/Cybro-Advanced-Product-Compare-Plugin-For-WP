<?php
namespace Cybro\ProductCompare\Core;

class Plugin {
    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private static $instance = null;
    private $user_capability = 'read';

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_handlers();
        $this->init_auth();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('init', [$this, 'load_textdomain']);
    }

    /**
     * Initialize handlers.
     */
    private function init_handlers() {
        // Initialize Compare Button
        new \Cybro\ProductCompare\Frontend\Compare_Button();
        
        // Initialize Compare Table
        new \Cybro\ProductCompare\Frontend\Compare_Table();
        
        // Initialize Admin Settings
        if (is_admin()) {
            new \Cybro\ProductCompare\Admin\Settings();
        }
        
        // Initialize Comparison Handler
        new \Cybro\ProductCompare\Core\ComparisonHandler();
    }

    /**
     * Initialize authentication.
     */
    private function init_auth() {
        // Verify basic WordPress authentication
        if (!function_exists('wp_get_current_user')) {
            require_once(ABSPATH . 'wp-includes/pluggable.php');
        }

        // Block unauthorized AJAX requests for all plugin actions
        add_action('wp_ajax_nopriv_cybro_compare_add', [$this, 'block_unauthorized_access']);
        add_action('wp_ajax_nopriv_cybro_compare_remove', [$this, 'block_unauthorized_access']);
        add_action('wp_ajax_nopriv_cybro_compare_clear', [$this, 'block_unauthorized_access']);
        
        // Initialize session if needed
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'cybro-compare-styles',
            CYBRO_COMPARE_URL . 'assets/css/style.css',
            [],
            CYBRO_COMPARE_VERSION
        );

        wp_enqueue_script(
            'cybro-compare-scripts',
            CYBRO_COMPARE_URL . 'assets/js/compare.js',
            ['jquery'],
            CYBRO_COMPARE_VERSION,
            true
        );

        wp_localize_script('cybro-compare-scripts', 'cybroCompare', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'max_products' => CYBRO_COMPARE_MAX_PRODUCTS,
            'nonce' => wp_create_nonce('cybro-compare-nonce'),
            'i18n' => [
                'max_products_reached' => __('Maximum products reached!', 'cybro-product-compare'),
                'product_added' => __('Product added to compare', 'cybro-product-compare'),
                'product_removed' => __('Product removed from compare', 'cybro-product-compare'),
            ],
        ]);
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function admin_enqueue_scripts($hook) {
        if ('woocommerce_page_cybro-compare-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'cybro-compare-admin',
            CYBRO_COMPARE_URL . 'assets/css/admin.css',
            [],
            CYBRO_COMPARE_VERSION
        );
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'cybro-product-compare',
            false,
            dirname(plugin_basename(CYBRO_COMPARE_FILE)) . '/languages/'
        );
    }

    /**
     * Verify authentication.
     *
     * @return void
     */
    public function verify_auth() {
        // Check nonce first
        if (!check_ajax_referer('cybro-compare-nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'cybro-product-compare')], 403);
        }
        
        // Then check user authentication
        if (!is_user_logged_in() || !current_user_can($this->user_capability)) {
            wp_send_json_error(['message' => __('Authentication required.', 'cybro-product-compare')], 401);
        }
    }

    /**
     * Block unauthorized access.
     */
    public function block_unauthorized_access() {
        wp_send_json_error(['message' => __('Login required.', 'cybro-product-compare')], 401);
    }
}
