<?php
/**
 * Adds a post view column to the admin post list.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('SPVC_Admin_Columns')) {
    /**
     * Admin class for managing post view columns.
     */
    class SPVC_Admin_Columns {
        /**
         * Constructor.
         */
        public function __construct() {
            // Use default priority (10) to avoid conflicts
            add_filter('manage_post_posts_columns', [$this, 'add_post_view_column'], 10);
            add_action('manage_post_posts_custom_column', [$this, 'populate_post_view_column'], 10, 2);
            add_filter('manage_edit-post_sortable_columns', [$this, 'register_sortable_columns'], 10);
            add_filter('request', [$this, 'sort_by_post_view'], 10);
        }

        /**
         * Add post view column to posts list.
         *
         * @param array $columns Existing columns.
         * @return array Modified columns.
         */
        public function add_post_view_column($columns) {

            $columns['post_view'] = esc_html(spvc_get_post_view_text());
            return $columns;
        }

        /**
         * Populate post view column with view count.
         *
         * @param string $column  Column name.
         * @param int    $post_id Post ID.
         */
        public function populate_post_view_column($column, $post_id) {
            if ($column === 'post_view') {
                $view_count = absint(get_post_meta($post_id, 'post_view', true));
                echo esc_html($view_count ?: 0);

            }
        }

        /**
         * Register the post view column as sortable.
         *
         * @param array $columns Sortable columns.
         * @return array Modified sortable columns.
         */
        public function register_sortable_columns($columns) {
            $columns['post_view'] = 'post_view';
            return $columns;
        }

        /**
         * Handle sorting for post view column.
         *
         * @param array $vars Query variables.
         * @return array Modified query variables.
         */
        public function sort_by_post_view($vars) {
            if (isset($vars['orderby']) && 'post_view' === sanitize_text_field($vars['orderby'])) {
                $vars = array_merge($vars, [
                    'meta_key' => 'post_view',
                    'orderby' => 'meta_value_num'
                ]);

            
            }
            return $vars;
        }
    }
}

/**
 * Initialize the admin columns class.
 */
if (!function_exists('spvc_admin_columns_init')) {
    function spvc_admin_columns_init() {
        new SPVC_Admin_Columns();
    }
    add_action('plugins_loaded', 'spvc_admin_columns_init', 10); // Adjusted priority to 10
}
?>