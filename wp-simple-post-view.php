<?php
/**
 * Plugin Name: Simple Post View Count
 * Plugin URI: https://wordpress.org/plugins/simple-post-view-count/
 * Description: Track and display post view counts. Includes shortcode support, customizable settings and CSV export.
 * Version: 1.0.0
 * Author: Sadathimel
 * Author URI: https://profiles.wordpress.org/sadathimel/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-post-view-count
 * Domain Path: /languages
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Include necessary files with error handling.
 */
$include_files = [
    'settings.php',
    'simple-post-view-count.php',
    'shortcodes.php',
    'view-logs.php',
    'add-post-column.php',
    'custom-functions.php'
];

foreach ($include_files as $file) {
    $file_path = plugin_dir_path(__FILE__) . 'includes/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } 
}

/**
 * Create the view logs table on plugin activation.
 */
function spvc_create_view_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'post_view_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        view_date datetime NOT NULL,
        view_count int(11) NOT NULL DEFAULT 1,
        ip_address varchar(100) NOT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY view_date (view_date)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $result = dbDelta($sql);

    // Check for errors
    if (!empty($wpdb->last_error)) {
        printf(
            // translators: %1$s is the database error message, %2$s is the support email link.
            '<div class="notice notice-error"><p>' . esc_html__('Error creating/updating Post View Count table: %1$s. Please deactivate and reactivate the plugin or contact support at %2$s.', 'simple-post-view-count') . '</p></div>',
            esc_html($wpdb->last_error),
            '<a href="mailto:sadathossen.cse@gmail.com">sadathossen.cse@gmail.com</a>'
        );
    } else {
        update_option('spvc_db_version', '1.0');
    }
}
register_activation_hook(__FILE__, 'spvc_create_view_logs_table');
?>