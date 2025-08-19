<?php
/**
 * Post view count functionality for Simple Post View plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include shortcode functionality
require_once plugin_dir_path(__FILE__) . 'shortcodes.php';

/**
 * Track post views (non-AJAX fallback).
 */
function spvc_track_post_view() {
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }

    if ( ! is_single() || ! is_singular( 'post' ) ) {
        return;
    }

    global $post, $wpdb;
    $post_id = $post->ID;
    $table_name = $wpdb->prefix . 'post_view_logs';

    // Cache key for table existence
    $cache_key = 'spvc_table_exists_' . $table_name;

    // Try to get cached value
    $table_exists = wp_cache_get( $cache_key, 'spvc' );

    if ( false === $table_exists ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- checking table existence
        $table_exists = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name );
        wp_cache_set( $cache_key, $table_exists, 'spvc', HOUR_IN_SECONDS );
    }

    if ( ! $table_exists ) {
        return;
    }

    // Get visitor IP address, sanitize for safety
    $ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );

    // Use transient to limit counting views per IP per hour
    $transient_key = 'is_post_view_' . $post_id . '_' . substr( md5( $ip_address ), 0, 16 ) . '_' . gmdate( 'YmdH' );

    if ( get_transient( $transient_key ) === false ) {
        // Increment post meta view count
        $view_count = (int) get_post_meta( $post_id, 'post_view', true );
        $view_count++;
        update_post_meta( $post_id, 'post_view', $view_count );

        // Update other meta flags
        update_post_meta( $post_id, 'is_post_view', true );

        // Update 24 hour view count meta
        $view_24_hour_count = (int) spvc_get_views_in_range( $post_id, '-1 day' );
        update_post_meta( $post_id, 'view_24_hour_count', $view_24_hour_count );

        // Insert view log into custom table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe insert via $wpdb->insert()
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id'     => $post_id,
                'view_date'   => current_time( 'mysql', 1 ), // GMT time
                'view_count'  => 1,
                'ip_address'  => $ip_address,
            ],
            [ '%d', '%s', '%d', '%s' ]
        );

        if ( $result !== false ) {
            // Verify consistency of post meta on success
            spvc_verify_post_view_meta( $post_id );
        }

        // Set transient to avoid recounting for this IP and post for one hour
        set_transient( $transient_key, true, HOUR_IN_SECONDS );

        // Clear cache for today's views
        wp_cache_delete("spvc_today_views_{$post_id}", 'spvc');
        wp_cache_delete('spvc_last_24_hours_views', 'spvc');
        wp_cache_delete('spvc_all_posts_total_views', 'spvc');
    }
}
add_action('wp', 'spvc_track_post_view');

/**
 * AJAX handler to track post views.
 */
function spvc_ajax_track_post_view() {
    check_ajax_referer('spvc_track_view_nonce', '_ajax_nonce');

    global $wpdb;

    // Validate $_POST['post_id'] exists
    if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
        wp_send_json_error();
    }

    $post_id = absint($_POST['post_id']);

    if (!$post_id) {
        wp_send_json_error();
    }

    $table_name = $wpdb->prefix . 'post_view_logs';

    // Cache key for table existence
    $cache_key = 'spvc_table_exists_' . $table_name;

    // Try to get cached value
    $table_exists = wp_cache_get($cache_key, 'spvc');

    if (false === $table_exists) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- checking table existence
        $table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
        wp_cache_set($cache_key, $table_exists, 'spvc', HOUR_IN_SECONDS);
    }

    if (!$table_exists) {
        wp_send_json_error();
    }

    // Get visitor IP address, sanitize for safety
    $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

    // Use transient to limit counting views per IP per hour
    $transient_key = 'is_post_view_' . $post_id . '_' . substr(md5($ip_address), 0, 16) . '_' . gmdate('YmdH');

    if (get_transient($transient_key) === false) {
        // Increment post meta view count
        $view_count = (int) get_post_meta($post_id, 'post_view', true);
        $view_count++;
        update_post_meta($post_id, 'post_view', $view_count);

        // Update other meta flags
        update_post_meta($post_id, 'is_post_view', true);

        // Update 24 hour view count meta
        $view_24_hour_count = (int) spvc_get_views_in_range($post_id, '-1 day');
        update_post_meta($post_id, 'view_24_hour_count', $view_24_hour_count);

        // Insert view log into custom table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe insert via $wpdb->insert()
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id'     => $post_id,
                'view_date'   => current_time('mysql', 1), // GMT time
                'view_count'  => 1,
                'ip_address'  => $ip_address,
            ],
            ['%d', '%s', '%d', '%s']
        );

        if ($result !== false) {
            // Verify consistency of post meta on success
            spvc_verify_post_view_meta($post_id);
        }

        // Set transient to avoid recounting for this IP and post for one hour
        set_transient($transient_key, true, HOUR_IN_SECONDS);

        // Clear cache for today's views
        wp_cache_delete("spvc_today_views_{$post_id}", 'spvc');
        wp_cache_delete('spvc_last_24_hours_views', 'spvc');
        wp_cache_delete('spvc_all_posts_total_views', 'spvc');

        wp_send_json_success();
    }

    wp_send_json_error();
}
add_action('wp_ajax_spvc_track_view', 'spvc_ajax_track_post_view');
add_action('wp_ajax_nopriv_spvc_track_view', 'spvc_ajax_track_post_view');

/**
 * Get views for a post within a date range.
 */
function spvc_get_views_in_range($post_id, $end_time = 'now') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'post_view_logs';

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        return 0;
    }

    $cache_key = "spvc_views_{$post_id}_" . md5($end_time);
    $cached = wp_cache_get( $cache_key, 'spvc' );
    if ( $cached !== false ) {
        return (int) $cached;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
        return 0;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Querying sum of view_count from custom table with dynamic table name properly escaped.
    $views = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(view_count) FROM `" . esc_sql( $table_name ) . "` 
             WHERE post_id = %d 
             AND DATE(view_date) >= %s",
            $post_id,
            gmdate( 'Y-m-d', strtotime( $end_time ) )
        )
    );

    wp_cache_set( $cache_key, $views, 'spvc', 60 ); // Reduced cache time

    return (int) $views ?: 0;
}

/**
 * Get today's views for a post.
 */
function spvc_get_today_views($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'post_view_logs';

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        return 0;
    }

    // Cache key for today's views
    $cache_key = "spvc_today_views_{$post_id}";
    $cached = wp_cache_get( $cache_key, 'spvc' );
    if ( $cached !== false ) {
        return (int) $cached;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
        return 0;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- safe, prepared query on custom table to sum view_count.
    $views = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(view_count) FROM `" . esc_sql( $table_name ) . "` 
             WHERE post_id = %d 
             AND DATE(view_date) = %s",
            $post_id,
            gmdate( 'Y-m-d' )
        )
    );

    wp_cache_set( $cache_key, $views, 'spvc', 60 ); // Reduced cache time

    return (int) $views ?: 0;
}

/**
 * Verify and fix post view meta to avoid data inconsistency.
 */
function spvc_verify_post_view_meta($post_id) {
    $stored_count = (int) get_post_meta($post_id, 'post_view', true);
    $actual_count = spvc_get_views_in_range($post_id, '-100 years'); // all-time

    if ($stored_count !== $actual_count) {
        update_post_meta($post_id, 'post_view', $actual_count);
    }
}

/**
 * AJAX nonce enqueue and script localize
 */
function spvc_enqueue_scripts() {
    if (is_single() && is_singular('post')) {
        wp_enqueue_script('spvc-frontend', plugin_dir_url(__FILE__) . 'js/spvc-frontend.js', ['jquery'], '1.0', true);
        wp_localize_script('spvc-frontend', 'spvc_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('spvc_track_view_nonce'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'spvc_enqueue_scripts');
?>