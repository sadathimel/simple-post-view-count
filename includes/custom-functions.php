<?php
/**
 * Admin meta box functionality for post view count.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('SPVC_Admin_Meta_Box')) {
    /**
     * Class for adding post view meta box.
     */
    class SPVC_Admin_Meta_Box {
        /**
         * Constructor.
         */
        public function __construct() {
            add_action('add_meta_boxes', [$this, 'add_meta_box']);
            add_action('save_post', [$this, 'save_post_data']);
        }

        /**
         * Add meta box for post view count.
         */
        public function add_meta_box($post) {
            if (!is_object($post) || !isset($post->ID) || get_post_type($post->ID) !== 'post') {
                return;
            }
            add_meta_box(
                'add_post_view',
                esc_html(spvc_get_post_view_text()),
                [$this, 'render_meta_box'],
                'post',
                'side'
            );
        }

        /**
         * Render meta box HTML.
         */
        public function render_meta_box($post) {
            $value = get_post_meta($post->ID, 'post_view', true);
            wp_nonce_field('wpspv_action', 'wpspv_field');
            ?>
            <label for="post_view"><strong><?php echo esc_html(spvc_get_post_view_text()); ?></strong></label>
            <input type="number" name="post_view" style="width: 70%;" min="0" placeholder="0" value="<?php echo esc_attr($value); ?>">
            <?php
        }

        /**
         * Save post view meta data.
         */
        public function save_post_data($post_id) {
            if (get_post_type($post_id) !== 'post' || !isset($_POST['wpspv_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpspv_field'])), 'wpspv_action')) {
                return;
            }

            if (isset($_POST['post_view'])) {
			$post_view_value = absint($_POST['post_view']);
			if ($post_view_value >= 0 && $post_view_value <= PHP_INT_MAX) {
				update_post_meta($post_id, 'post_view', $post_view_value);
			} else {
				// Simply handle the invalid case without logging
				add_action('admin_notices', function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . esc_html__('Error: Please enter a valid view count (0 or positive number).', 'simple-post-view-count') . '</p>';
					echo '</div>';
				});
			}
		}
        }
    }
}

/**
 * Initialize the meta box class.
 */
if (!function_exists('spvc_admin_meta_box_init')) {
    function spvc_admin_meta_box_init() {
        new SPVC_Admin_Meta_Box();
    }
    add_action('plugins_loaded', 'spvc_admin_meta_box_init');
}