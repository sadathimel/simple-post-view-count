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

if (!class_exists('SIMPPOVI_Admin_Meta_Box')) {
    /**
     * Class for adding post view meta box.
     */
    class SIMPPOVI_Admin_Meta_Box {
        /**
         * The nonce action for verification.
         *
         * @var string
         */
        private $nonce_action = 'simppovi_save_post_view';
        
        /**
         * The nonce name for the form field.
         *
         * @var string
         */
        private $nonce_name = 'simppovi_post_view_nonce';
        
        /**
         * The meta key for storing post views.
         *
         * @var string
         */
        private $meta_key = 'post_view';

        /**
         * Constructor.
         */
        public function __construct() {
            add_action('add_meta_boxes', [$this, 'add_meta_box']);
            add_action('save_post', [$this, 'save_post_data'], 10, 2);
        }

        /**
         * Add meta box for post view count.
         *
         * @param string $post_type The current post type.
         * @param WP_Post $post The current post object.
         */
        public function add_meta_box($post_type, $post = null) {
            // Only add to posts by default, but allow filtering for other post types
            $allowed_post_types = apply_filters('simppovi_allowed_post_types', ['post']);
            
            if (!in_array($post_type, $allowed_post_types, true)) {
                return;
            }
            
            add_meta_box(
                'simppovi_post_view_meta_box',
                esc_html($this->get_meta_box_title()),
                [$this, 'render_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }

        /**
         * Get the meta box title.
         *
         * @return string
         */
        private function get_meta_box_title() {
            $title = simppovi_get_post_view_text();
            return apply_filters('simppovi_meta_box_title', $title);
        }

        /**
         * Render meta box HTML.
         *
         * @param WP_Post $post The post object.
         */
        public function render_meta_box($post) {
            if (!is_object($post) || !isset($post->ID)) {
                return;
            }
            
            $value = get_post_meta($post->ID, $this->meta_key, true);
            $value = is_numeric($value) ? (int) $value : 0;
            
            wp_nonce_field($this->nonce_action, $this->nonce_name);
            ?>
            <div class="simppovi-meta-box">
                <label for="post_view">
                    <strong><?php echo esc_html($this->get_meta_box_title()); ?></strong>
                </label>
                <p>
                    <input type="number" 
                           id="post_view" 
                           name="post_view" 
                           class="widefat" 
                           min="0" 
                           step="1" 
                           placeholder="0" 
                           value="<?php echo esc_attr($value); ?>"
                           style="max-width: 100px;">
                </p>
                <p class="description">
                    <?php esc_html_e('Enter the number of views for this post. This will override the automatic view counting.', 'simple-post-view-count'); ?>
                </p>
                <?php do_action('simppovi_after_meta_box_fields', $post); ?>
            </div>
            <?php
        }

        /**
         * Save post view meta data.
         *
         * @param int $post_id The post ID.
         * @param WP_Post $post The post object.
         */
        public function save_post_data($post_id, $post) {
            // Check if this is an autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            
            // Verify nonce
            if (!isset($_POST[$this->nonce_name]) || 
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->nonce_name])), $this->nonce_action)) {
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
            
            // Check post type
            $allowed_post_types = apply_filters('simppovi_allowed_post_types', ['post']);
            if (!in_array($post->post_type, $allowed_post_types, true)) {
                return;
            }
            
            // Save the post view count
            if (isset($_POST['post_view'])) {
                $post_view_value = sanitize_text_field(wp_unslash($_POST['post_view']));
                
                // Validate as positive integer
                if ($post_view_value === '' || (is_numeric($post_view_value) && $post_view_value >= 0)) {
                    $post_view_value = absint($post_view_value);
                    update_post_meta($post_id, $this->meta_key, $post_view_value);
                    
                    // Hook for additional actions after saving
                    do_action('simppovi_after_save_post_view', $post_id, $post_view_value);
                } else {
                    // Set admin notice for invalid input
                    set_transient('simppovi_invalid_view_count_' . get_current_user_id(), true, 30);
                }
            }
        }
        
        /**
         * Display admin notices if needed.
         */
        public function display_admin_notices() {
            $user_id = get_current_user_id();
            if (get_transient('simppovi_invalid_view_count_' . $user_id)) {
                delete_transient('simppovi_invalid_view_count_' . $user_id);
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Error: Please enter a valid view count (0 or positive number).', 'simple-post-view-count'); ?></p>
                </div>
                <?php
            }
        }
    }
}

/**
 * Initialize the meta box class.
 */
if (!function_exists('simppovi_admin_meta_box_init')) {
    function simppovi_admin_meta_box_init() {
        $meta_box = new SIMPPOVI_Admin_Meta_Box();
        
        // Add admin notices hook
        add_action('admin_notices', [$meta_box, 'display_admin_notices']);
    }
    add_action('plugins_loaded', 'simppovi_admin_meta_box_init');
}