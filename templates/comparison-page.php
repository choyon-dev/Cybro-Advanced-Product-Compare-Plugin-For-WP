<?php
/**
 * Template for the product comparison page
 *
 * @package Cybro_Product_Compare
 */

if (!defined('ABSPATH')) {
    status_header(403);
    wp_die(__('Direct access not allowed.', 'cybro-product-compare'), 'Access Denied', ['response' => 403]);
}

// Verify user permissions
if (!current_user_can('read')) {
    status_header(403);
    wp_die(
        __('Sorry, you are not allowed to access this page.', 'cybro-product-compare'),
        'Access Denied',
        ['response' => 403]
    );
}

get_header('shop');

// Add error handling for ComparisonHandler
try {
    $handler = new \Cybro\ProductCompare\Core\ComparisonHandler();
    $compare_list = $handler->get_compare_list();
} catch (Exception $e) {
    error_log('Comparison page error: ' . $e->getMessage());
    wp_die(
        __('Error loading comparison list. Please try again later.', 'cybro-product-compare'),
        'Error',
        ['response' => 500, 'back_link' => true]
    );
}

?>

<div class="cybro-compare-page-wrapper">
    <div class="container">
        <?php
        if (empty($compare_list)) {
            ?>
            <div class="cybro-compare-empty">
                <h2><?php esc_html_e('Your comparison list is empty', 'cybro-product-compare'); ?></h2>
                <p><?php esc_html_e('Add some products to compare', 'cybro-product-compare'); ?></p>
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button">
                    <?php esc_html_e('Browse Products', 'cybro-product-compare'); ?>
                </a>
            </div>
            <?php
        } else {
            // Get settings
            $settings = \Cybro\ProductCompare\Admin\Settings::get_settings();
            $table_style = isset($settings['table_style']) ? $settings['table_style'] : 'default';
            ?>
            
            <div class="cybro-compare-header">
                <h1><?php esc_html_e('Product Comparison', 'cybro-product-compare'); ?></h1>
                <div class="compare-actions">
                    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button">
                        <?php esc_html_e('Add More Products', 'cybro-product-compare'); ?>
                    </a>
                    <button type="button" class="clear-all-compare button">
                        <?php esc_html_e('Clear All', 'cybro-product-compare'); ?>
                    </button>
                </div>
            </div>

            <div class="cybro-compare-content">
                <?php 
                // Render comparison table
                do_action('cybro_before_compare_table');
                echo do_shortcode('[cybro_compare_table]');
                do_action('cybro_after_compare_table');
                ?>
            </div>

            <?php if (count($compare_list) >= 2): ?>
                <div class="cybro-compare-footer">
                    <div class="print-compare">
                        <button type="button" class="print-compare-table button">
                            <?php esc_html_e('Print Comparison', 'cybro-product-compare'); ?>
                        </button>
                    </div>
                    <div class="share-compare">
                        <button type="button" class="share-compare-list button">
                            <?php esc_html_e('Share Comparison', 'cybro-product-compare'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php
        }
        ?>
    </div>
</div>

<?php
// Add print styles
add_action('wp_footer', function() {
    ?>
    <style type="text/css" media="print">
        .site-header,
        .site-footer,
        .cybro-compare-header,
        .cybro-compare-footer,
        .remove-from-compare {
            display: none !important;
        }
        .cybro-compare-content {
            padding: 0 !important;
        }
    </style>
    <?php
});

get_footer('shop');
