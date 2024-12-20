<?php
/**
 * Plugin Name: Cybro Product Compare for WooCommerce
 * Plugin URI: #
 * Description: A powerful product comparison tool for WooCommerce stores
 * Version: 1.1.0
 * Author: Nirmaan Technologies
 * Author URI: https://nirmaan.com.bd
 * Text Domain: cybro-product-compare
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package Cybro_Product_Compare
 */

if (!defined('ABSPATH')) {
    status_header(403);
    wp_die('Direct access not allowed.', 'Access Denied', ['response' => 403]);
}

// Define plugin constants
define('CYBRO_COMPARE_VERSION', '1.0.0');
define('CYBRO_COMPARE_FILE', __FILE__);
define('CYBRO_COMPARE_PATH', plugin_dir_path(__FILE__));
define('CYBRO_COMPARE_URL', plugin_dir_url(__FILE__));
define('CYBRO_COMPARE_MAX_PRODUCTS', 4);

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Cybro Product Compare requires WooCommerce to be installed and activated!', 'cybro-product-compare'),
            'Plugin Dependency Error',
            ['back_link' => true]
        );
    });
    return;
}

// Add version checking for WooCommerce
if (!function_exists('cybro_compare_check_wc_version')) {
    function cybro_compare_check_wc_version() {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '<')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('Cybro Product Compare requires WooCommerce version 5.0 or higher!', 'cybro-product-compare'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        return true;
    }
}

// Autoloader with security checks
spl_autoload_register(function ($class) {
    try {
        $prefix = 'Cybro\\ProductCompare\\';
        $base_dir = realpath(CYBRO_COMPARE_PATH . 'includes/');

        // Early return if base directory doesn't exist
        if ($base_dir === false) {
            throw new Exception('Invalid base directory');
        }

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // Sanitize and validate the path
        $relative_class = substr($class, $len);
        $file = $base_dir . DIRECTORY_SEPARATOR . 
                str_replace(
                    ['\\', '/', '..', '%2e', '%2E'],
                    [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '', '', ''],
                    $relative_class
                ) . '.php';
        
        // Normalize paths for comparison
        $real_file = realpath($file);
        if ($real_file === false || strpos($real_file, $base_dir) !== 0) {
            throw new Exception('Invalid file path detected');
        }

        if (file_exists($real_file)) {
            require_once $real_file;
        }
    } catch (Exception $e) {
        error_log('Cybro Product Compare autoloader error: ' . $e->getMessage());
        return false;
    }
});

// Initialize plugin
function cybro_product_compare_init() {
    if (class_exists('Cybro\\ProductCompare\\Core\\Plugin')) {
        return \Cybro\ProductCompare\Core\Plugin::get_instance();
    }
}

// Include necessary files and define functions before activation
if (!function_exists('cybro_compare_create_tables')) {
    function cybro_compare_create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cybro_compare';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            added_on datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Activation hook
register_activation_hook(__FILE__, function() {
    // Ensure necessary database tables or options are created
    add_option('cybro_compare_version', CYBRO_COMPARE_VERSION);
    
    // Add activation timestamp
    add_option('cybro_compare_activated', time());
    
    // Create any required database tables
    cybro_compare_create_tables();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});

// Add uninstall hook (in separate file)
register_uninstall_hook(__FILE__, 'cybro_compare_uninstall');

// Initialize the plugin
add_action('plugins_loaded', 'cybro_product_compare_init');

// Enqueue styles for the compare button
function cybro_enqueue_compare_assets() {
    wp_enqueue_style('cybro-compare-style', CYBRO_COMPARE_URL . 'assets/css/style.css', array(), CYBRO_COMPARE_VERSION);
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
}
add_action('wp_enqueue_scripts', 'cybro_enqueue_compare_assets');

/**
 * Compare Button Shortcode
 * Usage: [cybro_compare]
 * Optional attributes:
 * - product_id: Specific product ID (defaults to current product)
 * - icon: Custom Font Awesome icon class (defaults to 'fas fa-exchange-alt')
 * - text: Custom button text (defaults to 'Compare')
 */
function cybro_compare_button_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'product_id' => get_the_ID(),
        'icon' => 'fas fa-exchange-alt',
        'text' => __('Compare', 'cybro-product-compare')
    ), $atts);

    // Ensure product ID is valid
    if (!$atts['product_id'] || !wc_get_product($atts['product_id'])) {
        return '';
    }

    // Generate unique ID for the button
    $button_id = 'cybro-compare-' . esc_attr($atts['product_id']);

    // Generate button HTML
    $output = sprintf(
        '<button id="%s" class="cybro-compare-button" data-product-id="%d">
            <i class="%s"></i> %s
        </button>',
        $button_id,
        esc_attr($atts['product_id']),
        esc_attr($atts['icon']),
        esc_html($atts['text'])
    );

    return $output;
}
add_shortcode('cybro_compare', 'cybro_compare_button_shortcode');

// Optional: Add the compare button automatically after product title on single product pages
function cybro_add_compare_button_after_title() {
    if (is_product()) {
        echo do_shortcode('[cybro_compare]');
    }
}
add_action('woocommerce_single_product_summary', 'cybro_add_compare_button_after_title', 6);
