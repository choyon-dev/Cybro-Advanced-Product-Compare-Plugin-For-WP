<?php
namespace Cybro\ProductCompare\Admin;

class Settings {
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
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add menu page.
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __('Product Compare Settings', 'cybro-product-compare'),
            __('Product Compare', 'cybro-product-compare'),
            'manage_options',
            'cybro-compare-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting('cybro_compare_options', 'cybro_compare_settings');

        add_settings_section(
            'cybro_compare_general',
            __('General Settings', 'cybro-product-compare'),
            [$this, 'render_section_description'],
            'cybro-compare-settings'
        );

        // Button Position
        add_settings_field(
            'button_position',
            __('Compare Button Position', 'cybro-product-compare'),
            [$this, 'render_button_position_field'],
            'cybro-compare-settings',
            'cybro_compare_general'
        );

        // Attributes to Compare
        add_settings_field(
            'compare_attributes',
            __('Attributes to Compare', 'cybro-product-compare'),
            [$this, 'render_attributes_field'],
            'cybro-compare-settings',
            'cybro_compare_general'
        );

        // Table Style
        add_settings_field(
            'table_style',
            __('Table Style', 'cybro-product-compare'),
            [$this, 'render_table_style_field'],
            'cybro-compare-settings',
            'cybro_compare_general'
        );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('cybro_compare_options');
                do_settings_sections('cybro-compare-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render section description.
     */
    public function render_section_description() {
        echo '<p>' . esc_html__('Configure the product comparison settings below.', 'cybro-product-compare') . '</p>';
    }

    /**
     * Render button position field.
     */
    public function render_button_position_field() {
        $options = get_option('cybro_compare_settings');
        $position = isset($options['button_position']) ? $options['button_position'] : 'after_add_to_cart';
        ?>
        <select name="cybro_compare_settings[button_position]">
            <option value="after_add_to_cart" <?php selected($position, 'after_add_to_cart'); ?>>
                <?php esc_html_e('After Add to Cart', 'cybro-product-compare'); ?>
            </option>
            <option value="before_add_to_cart" <?php selected($position, 'before_add_to_cart'); ?>>
                <?php esc_html_e('Before Add to Cart', 'cybro-product-compare'); ?>
            </option>
            <option value="after_product_title" <?php selected($position, 'after_product_title'); ?>>
                <?php esc_html_e('After Product Title', 'cybro-product-compare'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render attributes field.
     */
    public function render_attributes_field() {
        $options = get_option('cybro_compare_settings');
        $attributes = isset($options['compare_attributes']) ? $options['compare_attributes'] : [];
        $available_attributes = wc_get_attribute_taxonomies();
        ?>
        <div class="compare-attributes-wrapper">
            <?php foreach ($available_attributes as $attribute): ?>
                <label>
                    <input type="checkbox" 
                           name="cybro_compare_settings[compare_attributes][]" 
                           value="<?php echo esc_attr($attribute->attribute_name); ?>"
                           <?php checked(in_array($attribute->attribute_name, $attributes)); ?>>
                    <?php echo esc_html($attribute->attribute_label); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render table style field.
     */
    public function render_table_style_field() {
        $options = get_option('cybro_compare_settings');
        $style = isset($options['table_style']) ? $options['table_style'] : 'default';
        ?>
        <select name="cybro_compare_settings[table_style]">
            <option value="default" <?php selected($style, 'default'); ?>>
                <?php esc_html_e('Default', 'cybro-product-compare'); ?>
            </option>
            <option value="minimal" <?php selected($style, 'minimal'); ?>>
                <?php esc_html_e('Minimal', 'cybro-product-compare'); ?>
            </option>
            <option value="modern" <?php selected($style, 'modern'); ?>>
                <?php esc_html_e('Modern', 'cybro-product-compare'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Get settings.
     *
     * @return array
     */
    public static function get_settings() {
        return get_option('cybro_compare_settings', [
            'button_position' => 'after_add_to_cart',
            'compare_attributes' => [],
            'table_style' => 'default'
        ]);
    }
}
