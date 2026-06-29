<?php
/**
 * Shortcode functionality for Simple Post View plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Get total views for all posts (all-time).
 *
 * @return int
 */
function simppovi_get_all_posts_total_views() {
    global $wpdb;
    static $table_verified = false;

    $cache_key = 'simppovi_all_posts_total_views';
    $cached_total = wp_cache_get( $cache_key, 'simppovi' );
    if ( false !== $cached_total ) {
        return (int) $cached_total;
    }

    $table_name = $wpdb->prefix . 'post_view_logs';

    if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
        return 0;
    }

    if ( ! $table_verified ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $table_verified = (bool) $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name )
        );

        if ( ! $table_verified ) {
            return 0;
        }
    }

 $sql = "SELECT SUM(view_count) FROM `" . $table_name . "`";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- safe: table name validated, no placeholders needed
$total_views = (int) $wpdb->get_var( $sql );


    // Cache the result for 10 minutes
    wp_cache_set( $cache_key, $total_views, 'simppovi', 600 );

    return $total_views;
}

add_shortcode( 'total_views', 'simppovi_total_views_shortcode' );

/**
 * Shortcode to display today's post view count.
 */
function simppovi_today_post_view_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts, 'simppovi-today-post-view');
    $post_id = (int) $atts['id'];

    if (!$post_id && !is_singular('post')) {
        return '';
    }

    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }

    if (!get_post($post_id)) {
        return '';
    }

    $today_views = simppovi_get_today_views($post_id);
    $text = esc_html(get_option('simppovi_post_view_text', __('Views', 'simple-post-view-count')));
    $output = '<div class="simppovi-formated-post-view formated_post_view">';
    $output .= '<span>' . esc_html__('Today\'s Views', 'simple-post-view-count') . '</span>: ' . esc_html($today_views);
    $output .= '</div>';

    return $output;
}
add_shortcode('simppovi-today-post-view', 'simppovi_today_post_view_shortcode');

/**
 * Shortcode to display total post view count for a specific post.
 */
function simppovi_total_post_view_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts, 'simppovi-total-post-view');
    $post_id = (int) $atts['id'];

    if (!$post_id && !is_singular('post')) {
        return '';
    }

    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }

    if (!get_post($post_id)) {
        return '';
    }

    $total_views = (int) get_post_meta($post_id, 'post_view', true);
    $text = esc_html(get_option('simppovi_post_view_text', __('Views', 'simple-post-view-count')));
    $output = '<div class="simppovi-formated-post-view formated_post_view">';
    $output .= '<span>' . esc_html__('Total Views', 'simple-post-view-count') . '</span>: ' . esc_html($total_views);
    $output .= '</div>';

    return $output;
}
add_shortcode('simppovi-total-post-view', 'simppovi_total_post_view_shortcode');

/**
 * Shortcode to display total view count for all posts (all-time).
 */
function simppovi_single_post_view_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0, // Ignored, as this shortcode shows total views for all posts
    ], $atts, 'simppovi-single-post-view');

    $total_views = simppovi_get_all_posts_total_views();
    $text = esc_html(get_option('simppovi_post_view_text', __('Views', 'simple-post-view-count')));
    $output = '<div class="simppovi-formated-post-view formated_post_view">';
    $output .= '<span>' . esc_html__('Total Views for All Posts', 'simple-post-view-count') . '</span>: ' . esc_html($total_views);
    $output .= '</div>';

    return $output;
}
add_shortcode('simppovi-single-post-view', 'simppovi_single_post_view_shortcode');