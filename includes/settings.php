<?php
/**
 * Settings page functionality for Post View Count plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue admin scripts and styles.
 */
function spvc_enqueue_admin_scripts($hook) {
    $should_enqueue = in_array($hook, ['toplevel_page_wp-spv', 'edit.php']) && ($hook !== 'edit.php' || get_current_screen()->post_type === 'post');
    if ($should_enqueue) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script(
            'wp-color-picker',
            'jQuery(document).ready(function($){
                $(".wp-spv-color-picker").wpColorPicker();
            });'
        );
        wp_enqueue_style('spvc-styles', plugin_dir_url(__FILE__) . 'css/spvc-styles.css', [], '1.0.6');
        $text_color = esc_attr(get_option('simple_post_view_color', '#000000'));
        $title_color = esc_attr(get_option('simple_post_view_title_color', '#000000'));
        $custom_css = "
            .spvc-formated-post-view.formated_post_view { color: {$text_color}; }
            .spvc-formated-post-view.formated_post_view span { color: {$title_color}; }
            .wp-list-table td.column-post_view { color: {$text_color}; }
            .wp-list-table th.column-post_view a span { color: {$title_color}; }
        ";
        wp_add_inline_style('spvc-styles', $custom_css);

        if ($hook === 'toplevel_page_wp-spv') {
            wp_enqueue_script('spvc-admin', plugin_dir_url(__FILE__) . 'js/spvc-admin.js', ['jquery'], '1.0.6', true);
            wp_localize_script('spvc-admin', 'spvcAdmin', [
                'confirmReset' => esc_js(__('Are you sure you want to reset all post view data? This action is irreversible.', 'simple-post-view-count'))
            ]);
        }
    }
}
add_action('admin_enqueue_scripts', 'spvc_enqueue_admin_scripts');

/**
 * Enqueue frontend scripts.
 */
function spvc_enqueue_frontend_scripts() {
    if (is_single() && get_post_type() === 'post' && 'publish' === get_post_status()) {
        wp_enqueue_script('spvc-frontend', plugin_dir_url(__FILE__) . 'js/spvc-frontend.js', ['jquery'], '1.0.6', true);
        wp_localize_script('spvc-frontend', 'spvcAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spvc_track_view_nonce'),
            'post_id' => get_the_ID(),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'spvc_enqueue_frontend_scripts');

/**
 * Register "Post View Settings" menu page.
 */
function spvc_register_menu_page() {
    add_menu_page(
        __('Simple Post View Settings', 'simple-post-view-count'),
        __('Simple Post View', 'simple-post-view-count'),
        'manage_options',
        'wp-spv',
        'spvc_settings_page',
        'dashicons-welcome-view-site',
        80
    );
    add_action('admin_init', 'spvc_register_settings');
}
add_action('admin_menu', 'spvc_register_menu_page');

/**
 * Register plugin settings.
 */
function spvc_register_settings() {
    register_setting('wp-simple-post-view-settings-group', 'simple_post_view_text', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('wp-simple-post-view-settings-group', 'simple_post_view_color', ['sanitize_callback' => 'sanitize_hex_color']);
    register_setting('wp-simple-post-view-settings-group', 'simple_post_view_title_color', ['sanitize_callback' => 'sanitize_hex_color']);
}

/**
 * Delete transients using core WordPress functions.
 */
function delete_post_view_transients() {
    // Get all transients using WordPress API
    $transients = get_option('_transient_is_post_view_keys', []);

    // Delete each transient properly
    foreach (array_keys($transients) as $transient_name) {
        delete_transient($transient_name);
    }

    // Clear our tracking option
    update_option('_transient_is_post_view_keys', []);
}

/**
 * Set transient for post view data.
 *
 * @param int   $post_id    Post ID.
 * @param mixed $value      Transient value.
 * @param int   $expiration Transient expiration time in seconds.
 */
function set_post_view_transient($post_id, $value, $expiration = 0) {
    $transient_name = "is_post_view_{$post_id}";
    set_transient($transient_name, $value, $expiration);
    
    // Track our transients in an option
    $tracked = get_option('_transient_is_post_view_keys', []);
    $tracked[$transient_name] = 1;
    update_option('_transient_is_post_view_keys', $tracked);
}

/**
 * Delete transient timeouts using WordPress API.
 */
function delete_post_view_transient_timeouts() {
    // Get all transient timeout keys
    $transient_keys = get_option('_transient_timeout_is_post_view_keys', []);

    // Delete each transient timeout by letting WordPress handle it
    foreach (array_keys($transient_keys) as $transient_name) {
        // Deleting the transient will automatically handle its timeout
        delete_transient($transient_name);
    }

    // Clear our tracking option
    update_option('_transient_timeout_is_post_view_keys', []);
}

/**
 * Set transient with timeout tracking.
 *
 * @param int   $post_id    Post ID.
 * @param mixed $value      Transient value.
 * @param int   $expiration Transient expiration time in seconds.
 */
function set_post_view_transient_with_timeout($post_id, $value, $expiration = 0) {
    $transient_name = "is_post_view_{$post_id}";
    set_transient($transient_name, $value, $expiration);

    // Track our transient timeouts in a separate option
    if ($expiration > 0) {
        $timeout_keys = get_option('_transient_timeout_is_post_view_keys', []);
        $timeout_keys[$transient_name] = 1;
        update_option('_transient_timeout_is_post_view_keys', $timeout_keys);
    }
}

/**
 * Handle reset post view data.
 */
function spvc_handle_reset_post_view_data() {
    if (isset($_POST['wp-spv-reset-settings']) && $_POST['wp-spv-reset-settings'] == 1 && check_admin_referer('wpspv_action', 'wpspv_field')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'simple-post-view-count'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'post_view_logs';

        // Validate table name
        if (!preg_match('/^' . $wpdb->prefix . '[a-zA-Z0-9_]+$/', $table_name)) {
            wp_die(esc_html__('Invalid table name.', 'simple-post-view-count'));
        }

        // Drop view logs table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table_name) . "`");

        // Use WordPress transaction API when available
        if (method_exists($wpdb, 'start_transaction')) {
            $wpdb->start_transaction();
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->query('START TRANSACTION');
        }

        try {
            // Delete post meta using WordPress APIs
            foreach (['post_view', 'is_post_view', 'view_24_hour_count'] as $key) {
                wp_cache_delete($key, 'post_meta');
                delete_post_meta_by_key($key);
            }

            // Commit using WordPress API
            if (method_exists($wpdb, 'commit')) {
                $wpdb->commit();
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->query('COMMIT');
            }
        } catch (Exception $e) {
            // Rollback using WordPress API
            if (method_exists($wpdb, 'rollback')) {
                $wpdb->rollback();
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->query('ROLLBACK');
            }
           wp_die(
			sprintf(
				/* translators: %s is the error message from the exception */
				esc_html__('Error resetting post view data: %s', 'simple-post-view-count'),
				esc_html($e->getMessage())
			),
			esc_html__('Error', 'simple-post-view-count'),
			['response' => 500]
		);
				}

        // Delete transients and timeouts
        delete_post_view_transients();
        delete_post_view_transient_timeouts();

        // Delete options
        delete_option('simple_post_view_text');
        delete_option('simple_post_view_color');
        delete_option('simple_post_view_title_color');
        delete_option('spvc_db_version');

        // Recreate the table to ensure it’s ready for new data
      wp_die(
		sprintf(
			/* translators: %s is the error message from the exception */
			esc_html__('Error resetting post view data: %s', 'simple-post-view-count'),
			esc_html($e->getMessage())
		),
		esc_html__('Error', 'simple-post-view-count'),
		['response' => 500]
	);

        // Clear scheduled events
        wp_clear_scheduled_hook('wp_update_24_hour_counts');
        wp_clear_scheduled_hook('spvc_daily_reset');

        // Redirect with success message
        add_settings_error(
            'wp-spv-messages',
            'reset_success',
            __('All post view data has been reset successfully.', 'simple-post-view-count'),
            'updated'
        );
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=wp-spv'));
        exit;
    }
}
add_action('admin_init', 'spvc_handle_reset_post_view_data');

/**
 * Output custom CSS for admin.
 */
function spvc_output_admin_css() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_wp-spv') {
        ?>
        <style type="text/css">
            .wp-spv-tabs { margin-bottom: 20px; }
            .wp-spv-tabs .nav-tab { margin-right: 5px; }
            .wp-spv-tab-content { display: none; }
            .wp-spv-tab-content.active { display: block; }
        </style>
        <?php
    }
}
add_action('admin_head', 'spvc_output_admin_css');

/**
 * Render the settings page.
 */
function spvc_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'simple-post-view-count'));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Simple Post View Settings', 'simple-post-view-count'); ?></h1>
        <?php settings_errors('wp-spv-messages'); ?>
        <h2 class="nav-tab-wrapper wp-spv-tabs">
            <a href="#settings" class="nav-tab nav-tab-active"><?php esc_html_e('Settings', 'simple-post-view-count'); ?></a>
            <a href="#view-logs" class="nav-tab"><?php esc_html_e('View Logs', 'simple-post-view-count'); ?></a>
            <a href="#help" class="nav-tab"><?php esc_html_e('Help', 'simple-post-view-count'); ?></a>
        </h2>

        <div id="settings" class="wp-spv-tab-content active">
            <form method="post" action="options.php">
                <?php settings_fields('wp-simple-post-view-settings-group'); ?>
                <?php do_settings_sections('wp-simple-post-view-settings-group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Post View Text', 'simple-post-view-count'); ?></th>
                        <td><input type="text" style="width: 60%;" name="simple_post_view_text" value="<?php echo esc_attr(get_option('simple_post_view_text')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post View Text Color', 'simple-post-view-count'); ?></th>
                        <td><input type="text" class="wp-spv-color-picker" name="simple_post_view_color" value="<?php echo esc_attr(get_option('simple_post_view_color', '#000000')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post View Title Color', 'simple-post-view-count'); ?></th>
                        <td><input type="text" class="wp-spv-color-picker" name="simple_post_view_title_color" value="<?php echo esc_attr(get_option('simple_post_view_title_color', '#000000')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" id="wp-spv-reset-form">
                <?php wp_nonce_field('wpspv_action', 'wpspv_field'); ?>
                <input type="hidden" name="wp-spv-reset-settings" value="1">
                <button type="submit" class="button button-primary" id="wp-spv-reset-button" aria-label="<?php esc_attr_e('Reset all post view data', 'simple-post-view-count'); ?>"><?php esc_html_e('Reset Post View Data', 'simple-post-view-count'); ?></button>
            </form>
            <div id="wp-spv-reset-modal" class="wp-spv-modal">
                <div class="wp-spv-modal-content">
                    <h2><?php esc_html_e('Confirm Reset', 'simple-post-view-count'); ?></h2>
                    <p><?php esc_html_e('Are you sure you want to reset all post view data? This action is irreversible.', 'simple-post-view-count'); ?></p>
                    <div class="wp-spv-modal-buttons">
                        <button id="wp-spv-reset-confirm" class="button button-primary"><?php esc_html_e('Confirm', 'simple-post-view-count'); ?></button>
                        <button id="wp-spv-reset-cancel" class="button"><?php esc_html_e('Cancel', 'simple-post-view-count'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-logs" class="wp-spv-tab-content">
            <h2><?php esc_html_e('View Logs', 'simple-post-view-count'); ?></h2>
            <p><?php esc_html_e('Note: View counts shown here are for the selected date range. Total post views in the Posts table reflect all-time views.', 'simple-post-view-count'); ?></p>
            <?php spvc_display_view_logs_table(); ?>
        </div>

        <div id="help" class="wp-spv-tab-content">
            <div class="wp-spv-help-section">
                <h2><?php esc_html_e('How to Use the Shortcodes', 'simple-post-view-count'); ?></h2>
                <p><?php esc_html_e('The plugin provides three shortcodes to display view counts for posts in your content.', 'simple-post-view-count'); ?></p>
                <ul>
                    <li><strong><?php esc_html_e('[spvc-today-post-view]', 'simple-post-view-count'); ?></strong>: <?php esc_html_e('Displays the view count for the current post for today only.', 'simple-post-view-count'); ?>
                        <ul>
                            <li><?php esc_html_e('Example: <code>[spvc-today-post-view]</code> shows today\'s views for the current post.', 'simple-post-view-count'); ?></li>
                            <li><?php esc_html_e('Use <code>[spvc-today-post-view id="123"]</code> to show today\'s views for a specific post (replace 123 with the post ID).', 'simple-post-view-count'); ?></li>
                        </ul>
                    </li>
                    <li><strong><?php esc_html_e('[spvc-total-post-view]', 'simple-post-view-count'); ?></strong>: <?php esc_html_e('Displays the total (all-time) view count for the current post.', 'simple-post-view-count'); ?>
                        <ul>
                            <li><?php esc_html_e('Example: <code>[spvc-total-post-view]</code> shows the total views for the current post.', 'simple-post-view-count'); ?></li>
                            <li><?php esc_html_e('Use <code>[spvc-total-post-view id="123"]</code> to show total views for a specific post (replace 123 with the post ID).', 'simple-post-view-count'); ?></li>
                        </ul>
                    </li>
                    <li><strong><?php esc_html_e('[spvc-single-post-view]', 'simple-post-view-count'); ?></strong>: <?php esc_html_e('Displays the total (all-time) view count across all posts.', 'simple-post-view-count'); ?>
                        <ul>
                            <li><?php esc_html_e('Example: <code>[spvc-single-post-view]</code> shows the total views for all posts.', 'simple-post-view-count'); ?></li>
                            <li><?php esc_html_e('Note: This shortcode ignores the <code>id</code> attribute, as it shows the total for all posts.', 'simple-post-view-count'); ?></li>
                        </ul>
                    </li>
                </ul>
                <p><?php esc_html_e('Add these shortcodes via the WordPress editor (Classic or Block) or in a shortcode-compatible widget.', 'simple-post-view-count'); ?></p>
            </div>
            <div class="wp-spv-help-section">
                <h2><?php esc_html_e('Troubleshooting Caching Issues', 'simple-post-view-count'); ?></h2>
                <p><?php esc_html_e('If view counts are not updating on cached sites, ensure your caching plugin excludes the AJAX endpoint <code>/wp-admin/admin-ajax.php</code> from caching.', 'simple-post-view-count'); ?></p>
            </div>
            <div class="wp-spv-help-section">
                <h2><?php esc_html_e('View Count Mismatch', 'simple-post-view-count'); ?></h2>
                <p>
                    <?php
                   
                    printf(
						 // Translators: %s is the email address for support.
                        esc_html__('If view counts in Posts > All Posts do not match View Logs, try resetting the data or contact support at %s.', 'simple-post-view-count'),
                        '<a href="mailto:sadathossen.cse@gmail.com">sadathossen.cse@gmail.com</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get the post view column text from settings.
 *
 * @return string The text for the post view column.
 */
function spvc_get_post_view_text() {
    return get_option('simple_post_view_text', __('Views', 'simple-post-view-count'));
}
?>