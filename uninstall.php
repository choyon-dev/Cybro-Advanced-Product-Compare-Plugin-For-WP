<?php
/**
 * Uninstall Cybro Product Compare
 *
 * @package Cybro_Product_Compare
 */

// If uninstall not called from WordPress, exit gracefully
if (!defined('WP_UNINSTALL_PLUGIN')) {
    status_header(403);
    wp_die('Direct access not allowed.', 'Access Denied', ['response' => 403]);
}

// Add additional security and verification checks
if (!current_user_can('activate_plugins')) {
    status_header(403);
    wp_die('Insufficient permissions.', 'Access Denied', ['response' => 403]);
}

// Ensure ABSPATH is defined
if (defined('ABSPATH')) {
    // Check if the file exists before requiring it
    $pluggable_path = ABSPATH . 'wp-includes/pluggable.php';
    if (file_exists($pluggable_path)) {
        require_once($pluggable_path);
    } else {
        error_log('Pluggable file not found: ' . $pluggable_path);
        return false;
    }
} else {
    error_log('ABSPATH is not defined.');
    return false;
}

try {
    // Add error reporting and logging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Create logs directory if it doesn't exist
    $log_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/logs' : dirname(__FILE__) . '/logs';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    
    // Set error log path with fallback
    $log_file = $log_dir . '/cybro-compare-uninstall.log';
    ini_set('error_log', $log_file);
    
    // Start transaction
    global $wpdb;
    $wpdb->query('START TRANSACTION');

    // Verify database connection before proceeding
    if (!$wpdb->check_connection(false)) {
        throw new Exception('Database connection failed');
    }

    // Delete plugin options
    delete_option('cybro_compare_settings');
    delete_option('cybro_compare_version');

    // Clear any transients we've created
    delete_transient('cybro_compare_cache');

    // Remove any scheduled events
    wp_clear_scheduled_hook('cybro_compare_cleanup_event');

    // Delete user meta if any
    $users = get_users(['fields' => 'ID']);
    foreach ($users as $user_id) {
        delete_user_meta($user_id, 'cybro_compare_list');
    }

    // Clean up database tables if created
    $table_name = $wpdb->prefix . 'cybro_compare_lists';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
    }

    // Clear any session data
    if (isset($_SESSION['cybro_compare_list'])) {
        unset($_SESSION['cybro_compare_list']);
    }

    // Add timeout protection for long operations
    set_time_limit(300); // 5 minutes

    // More robust directory removal
    $upload_dir = wp_upload_dir();
    $compare_dir = $upload_dir['basedir'] . '/cybro-compare';
    if (is_dir($compare_dir)) {
        if (!class_exists('WP_Filesystem_Direct')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
        }
        
        $filesystem = new WP_Filesystem_Direct(null);
        if (is_dir($compare_dir)) {
            // Attempt cleanup multiple times
            for ($i = 0; $i < 3; $i++) {
                if ($filesystem->rmdir($compare_dir, true)) {
                    break;
                }
                sleep(1);
            }
            if (is_dir($compare_dir)) {
                throw new Exception('Failed to remove compare directory after multiple attempts');
            }
        }
    }

    // Remove any custom post types or taxonomies
    $posts = get_posts([
        'post_type' => 'cybro_compare_list',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);

    foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
    }

    // Clear any cached data
    wp_cache_flush();

    /**
     * Fires after the plugin is uninstalled
     */
    do_action('cybro_compare_uninstalled');

    // Verify all critical operations completed
    if ($wpdb->last_error) {
        throw new Exception('Database operation failed: ' . $wpdb->last_error);
    }

    // Commit database changes if everything succeeded
    $wpdb->query('COMMIT');
    
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    error_log('Cybro Product Compare uninstall error: ' . $e->getMessage());
    // Ensure we exit cleanly
    return false;
} catch (Error $e) {
    $wpdb->query('ROLLBACK');
    error_log('Cybro Product Compare fatal error: ' . $e->getMessage());
    return false;
}
