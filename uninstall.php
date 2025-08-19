<?php
/**
 * Uninstall functionality for Post View Count plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('WP_UNINSTALL_PLUGIN') || !current_user_can('activate_plugins')) {
    exit;
}

global $wpdb;

if (is_multisite()) {
    $sites = get_sites();
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        $table_name = $wpdb->prefix . 'post_view_logs';
        if (preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom table cleanup required during uninstall, caching unnecessary, schema change intentional.
            $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table_name) . "`");
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk meta deletion during uninstall, caching unnecessary.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->postmeta WHERE meta_key IN (%s, %s, %s)",
                'post_view',
                'is_post_view',
                'view_24_hour_count'
            )
        );
        delete_option('simple_post_view_text');
        delete_option('simple_post_view_color');
        delete_option('simple_post_view_title_color');
        delete_option('spvc_db_version');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk transient deletion during uninstall, caching unnecessary.
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_is_post_view_%'");
        restore_current_blog();
    }
} else {
    $table_name = $wpdb->prefix . 'post_view_logs';
    if (preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom table cleanup required during uninstall, caching unnecessary, schema change intentional.
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table_name) . "`");
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk meta deletion during uninstall, caching unnecessary.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE meta_key IN (%s, %s, %s)",
            'post_view',
            'is_post_view',
            'view_24_hour_count'
        )
    );
    delete_option('simple_post_view_text');
    delete_option('simple_post_view_color');
    delete_option('simple_post_view_title_color');
    delete_option('spvc_db_version');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for bulk transient deletion during uninstall, caching unnecessary.
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_is_post_view_%'");
}

wp_clear_scheduled_hook('wp_update_24_hour_counts');
wp_clear_scheduled_hook('spvc_daily_reset');
?>