<?php
/**
 * View logs table and CSV export functionality for Post View Count plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Retrieves paginated daily view counts from the database with caching.
 *
 * @param string $start_date The start date for the query (format: Y-m-d).
 * @param int    $per_page   Number of records per page.
 * @param int    $offset     Offset for pagination.
 * @param string $orderby    Column to order by (view_date or daily_count).
 * @param string $order      Order direction (ASC or DESC).
 * @return array             Array of daily view records.
 */
function spvc_get_daily_views($start_date, $per_page, $offset, $orderby = 'view_date', $order = 'ASC') {
    global $wpdb;

    // Cache key for storing results.
    $cache_key = 'spvc_daily_views_paginated_' . md5($start_date . $per_page . $offset . $orderby . $order);

    // Attempt to retrieve from cache.
    $daily_views = wp_cache_get($cache_key, 'spvc');

    if ($daily_views === false) {
        // Define the table name securely.
        $table_name = $wpdb->prefix . 'post_view_logs';

        // Whitelist allowed columns and directions to prevent SQL injection.
        $valid_columns = ['view_date', 'daily_count'];
        $valid_orders = ['ASC', 'DESC'];

        // Sanitize orderby and order.
        $orderby = in_array($orderby, $valid_columns, true) ? $orderby : 'view_date';
        $order = in_array(strtoupper($order), $valid_orders, true) ? strtoupper($order) : 'ASC';

        // Build SQL query based on orderby and order.
        if ($orderby === 'view_date' && $order === 'ASC') {
            $query = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name escaped with esc_sql().
                "SELECT view_date, SUM(view_count) AS daily_count
                FROM `" . esc_sql($table_name) . "`
                WHERE view_date >= %s
                GROUP BY view_date
                ORDER BY view_date ASC
                LIMIT %d OFFSET %d",
                $start_date,
                $per_page,
                $offset
            );
        } elseif ($orderby === 'view_date' && $order === 'DESC') {
            $query = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name escaped with esc_sql().
                "SELECT view_date, SUM(view_count) AS daily_count
                FROM `" . esc_sql($table_name) . "`
                WHERE view_date >= %s
                GROUP BY view_date
                ORDER BY view_date DESC
                LIMIT %d OFFSET %d",
                $start_date,
                $per_page,
                $offset
            );
        } elseif ($orderby === 'daily_count' && $order === 'ASC') {
            $query = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name escaped with esc_sql().
                "SELECT view_date, SUM(view_count) AS daily_count
                FROM `" . esc_sql($table_name) . "`
                WHERE view_date >= %s
                GROUP BY view_date
                ORDER BY daily_count ASC
                LIMIT %d OFFSET %d",
                $start_date,
                $per_page,
                $offset
            );
        } else {
            $query = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name escaped with esc_sql().
                "SELECT view_date, SUM(view_count) AS daily_count
                FROM `" . esc_sql($table_name) . "`
                WHERE view_date >= %s
                GROUP BY view_date
                ORDER BY daily_count DESC
                LIMIT %d OFFSET %d",
                $start_date,
                $per_page,
                $offset
            );
        }

        // Run the query.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Custom table wp_post_view_logs requires direct query, no core API available; query is prepared with $wpdb->prepare().
        $daily_views = $wpdb->get_results($query);

        // Cache results for 5 minutes.
        wp_cache_set($cache_key, $daily_views, 'spvc', 300);
    }

    return $daily_views;
}

/**
 * Display the view logs table and handle CSV export.
 */
function spvc_display_view_logs_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'post_view_logs';

    // Validate table name format to prevent SQL injection.
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php 
                printf(
                    // Translators: %s is the email address for support.
                    esc_html__('Error: Invalid view logs table name. Please deactivate and reactivate the plugin to recreate the table, or contact support at %s.', 'simple-post-view-count'), 
                    '<a href="mailto:sadathossen.cse@gmail.com">sadathossen.cse@gmail.com</a>'
                ); 
                ?>
            </p>
        </div>
        <?php
        return;
    }

    // Check if table exists with caching.
    $cache_key_table_exists = 'spvc_table_exists_' . $table_name;
    $table_exists = wp_cache_get($cache_key_table_exists, 'spvc');

    if ($table_exists === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Necessary to check custom table existence, no core API available.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        ) === $table_name;
        wp_cache_set($cache_key_table_exists, $table_exists, 'spvc', 300);
    }

    if (!$table_exists) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    // Translators: %s is the email address for support.
                    esc_html__('Error: View logs table is missing. Please deactivate and reactivate the plugin to recreate the table, or contact support at %s.', 'simple-post-view-count'),
                    '<a href="mailto:sadathossen.cse@gmail.com">sadathossen.cse@gmail.com</a>'
                );
                ?>
            </p>
        </div>
        <?php
        return;
    }

    // Initialize date variables.
    $date_range = isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '30_days';
    $start_date = gmdate('Y-m-d', strtotime('-30 days')); // Default to 30 days.
    if ($date_range === '7_days') {
        $start_date = gmdate('Y-m-d', strtotime('-7 days'));
    } elseif ($date_range === '90_days') {
        $start_date = gmdate('Y-m-d', strtotime('-90 days'));
    }

    // Handle CSV export.
    if (isset($_GET['wp-spv-export']) && $_GET['wp-spv-export'] === 'view-logs' && check_admin_referer('wp_spv_export_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this feature.', 'simple-post-view-count'),
                esc_html__('Permission Denied', 'simple-post-view-count'),
                ['response' => 403]
            );
        }

        // Start output buffering to prevent stray output.
        ob_start();

        // Set headers for CSV download.
        $filename = 'post_view_logs_' . $date_range . '_' . gmdate('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream.
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel compatibility.

        // CSV headers.
        fputcsv($output, [
            __('Post View Logs Export', 'simple-post-view-count'),
            ''
        ]);

        // Total views for last 24 hours.
        $cache_key_total_24h = 'spvc_total_24h_' . $table_name;
        $total_24_hours = wp_cache_get($cache_key_total_24h, 'spvc');
        if ($total_24_hours === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table requires direct query, no core API available.
            $total_24_hours = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                    gmdate('Y-m-d', strtotime('-1 day'))
                )
            );
            wp_cache_set($cache_key_total_24h, $total_24_hours, 'spvc', 300);
        }
        fputcsv($output, [
            __('Last 24 Hours Total Views', 'simple-post-view-count'),
            number_format_i18n($total_24_hours)
        ]);
        fputcsv($output, ['']); // Empty row.

        // Daily views for CSV.
        $cache_key_daily_views = 'spvc_daily_views_' . $table_name . '_' . $start_date;
        $daily_views = wp_cache_get($cache_key_daily_views, 'spvc');
        if ($daily_views === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table requires direct query, no core API available.
            $daily_views = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT view_date, SUM(view_count) AS daily_count 
                     FROM `" . esc_sql($table_name) . "` 
                     WHERE view_date >= %s 
                     GROUP BY view_date 
                     ORDER BY view_date DESC",
                    $start_date
                )
            );
            wp_cache_set($cache_key_daily_views, $daily_views, 'spvc', 300);
        }

        // Output CSV headers for data.
        fputcsv($output, [
            __('Date', 'simple-post-view-count'),
            __('Daily Views', 'simple-post-view-count')
        ]);

        // Output data rows.
        foreach ($daily_views as $row) {
            fputcsv($output, [
                date_i18n(get_option('date_format'), strtotime($row->view_date)),
                number_format_i18n($row->daily_count)
            ]);
        }

        // Total views for date range.
        $cache_key_total_views = 'spvc_total_views_' . $table_name . '_' . $start_date;
        $total_views = wp_cache_get($cache_key_total_views, 'spvc');
        if ($total_views === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table requires direct query, no core API available.
            $total_views = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                    $start_date
                )
            );
            wp_cache_set($cache_key_total_views, $total_views, 'spvc', 300);
        }
        fputcsv($output, ['']); // Empty row.
        fputcsv($output, [
            __('Total Views', 'simple-post-view-count'),
            number_format_i18n($total_views)
        ]);

        // Close output stream.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream requires direct fclose() as WP_Filesystem does not apply.
        fclose($output);
        ob_end_flush();
        exit;
    }

    // Pagination and sorting.
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'view_date';
    $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';

    $valid_orderby = ['view_date', 'daily_count'];
    $valid_order = ['ASC', 'DESC'];

    $orderby = in_array($orderby, $valid_orderby, true) ? $orderby : 'view_date';
    $order = in_array($order, $valid_order, true) ? $order : 'DESC';

    // Total records for pagination.
    $cache_key_total_records = 'spvc_total_records_' . $table_name . '_' . $start_date;
    $total_records = wp_cache_get($cache_key_total_records, 'spvc');

    if ($total_records === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table requires direct query, no core API available.
        $total_records = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT view_date) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                $start_date
            )
        );
        wp_cache_set($cache_key_total_records, $total_records, 'spvc', 300);
    }

    $total_pages = ceil($total_records / $per_page);

    // Call spvc_get_daily_views for paginated data.
    $daily_views = spvc_get_daily_views($start_date, $per_page, $offset, $orderby, $order);

    // Total views for the selected date range.
    $cache_key_total_views = 'spvc_total_views_' . $table_name . '_' . $start_date;
    $total_views = wp_cache_get($cache_key_total_views, 'spvc');

    if ($total_views === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table requires direct query, no core API available.
        $total_views = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                $start_date
            )
        );
        wp_cache_set($cache_key_total_views, $total_views, 'spvc', 300);
    }

    // Total views for last 24 hours.
    $cache_key_total_24h = 'spvc_total_24h_' . $table_name;
    $total_24_hours = wp_cache_get($cache_key_total_24h, 'spvc');

    if ($total_24_hours === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table requires direct query, no core API available.
        $total_24_hours = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                gmdate('Y-m-d', strtotime('-1 day'))
            )
        );
        wp_cache_set($cache_key_total_24h, $total_24_hours, 'spvc', 300);
    }

    // Output the HTML for table display, date range filter, and export button.
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Post View Logs', 'simple-post-view-count'); ?></h1>
        
        <!-- Date Range Form -->
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <?php
            // Preserve existing query parameters
            $query_params = array_intersect_key($_GET, array_flip(['page', 'orderby', 'order', 'paged']));
            $query_params['page'] = 'wp-spv';
            foreach ($query_params as $key => $value) {
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }
            ?>
            <label for="date_range"><?php esc_html_e('Date Range:', 'simple-post-view-count'); ?></label>
            <select id="date_range" name="date_range" onchange="this.form.submit()">
                <option value="7_days" <?php selected($date_range, '7_days'); ?>><?php esc_html_e('Last 7 Days', 'simple-post-view-count'); ?></option>
                <option value="30_days" <?php selected($date_range, '30_days'); ?>><?php esc_html_e('Last 30 Days', 'simple-post-view-count'); ?></option>
                <option value="90_days" <?php selected($date_range, '90_days'); ?>><?php esc_html_e('Last 90 Days', 'simple-post-view-count'); ?></option>
            </select>
        </form>

        <!-- Export as CSV Form -->
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-top: 10px;">
            <?php
            // Preserve necessary query parameters for export
            $export_params = array_intersect_key($_GET, array_flip(['page', 'date_range']));
            $export_params['page'] = 'wp-spv';
            $export_params['wp-spv-export'] = 'view-logs';
            foreach ($export_params as $key => $value) {
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }
            ?>
            <?php wp_nonce_field('wp_spv_export_nonce'); ?>
            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Export as CSV', 'simple-post-view-count'); ?>">
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <a href="<?php
                            $new_order = ($orderby === 'view_date' && $order === 'DESC') ? 'ASC' : 'DESC';
                            echo esc_url(add_query_arg([
                                'page' => 'wp-spv',
                                'orderby' => 'view_date',
                                'order' => $new_order,
                                'paged' => 1,
                                'date_range' => $date_range,
                            ], admin_url('admin.php')));
                        ?>">
                            <?php esc_html_e('Date', 'simple-post-view-count'); ?>
                            <?php if ($orderby === 'view_date') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php
                            $new_order = ($orderby === 'daily_count' && $order === 'DESC') ? 'ASC' : 'DESC';
                            echo esc_url(add_query_arg([
                                'page' => 'wp-spv',
                                'orderby' => 'daily_count',
                                'order' => $new_order,
                                'paged' => 1,
                                'date_range' => $date_range,
                            ], admin_url('admin.php')));
                        ?>">
                            <?php esc_html_e('Views', 'simple-post-view-count'); ?>
                            <?php if ($orderby === 'daily_count') echo $order === 'ASC' ? ' ▲' : ' ▼'; ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daily_views)) : ?>
                    <tr>
                        <td colspan="2">
                            <?php 
                            printf(
                                // Translators: %1$s is the start date for the view data.
                                esc_html__('No view logs found for the selected date range starting from %1$s.', 'simple-post-view-count'),
                                esc_html($start_date)
                            );
                            ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($daily_views as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row->view_date))); ?></td>
                            <td><?php echo esc_html(number_format_i18n($row->daily_count)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(paginate_links([
                        'base' => add_query_arg(['paged' => '%#%', 'page' => 'wp-spv'], admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'simple-post-view-count'),
                        'next_text' => __('&raquo;', 'simple-post-view-count'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]));
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <p>
            <strong><?php esc_html_e('Total Views:', 'simple-post-view-count'); ?></strong> 
            <?php 
            printf(
                // Translators: %1$s is the start date, %2$d is the total number of views.
                esc_html__('Total views since %1$s: %2$d', 'simple-post-view-count'),
                esc_html($start_date),
                esc_html(number_format_i18n($total_views))
            );
            ?><br />
            <strong><?php esc_html_e('Last 24 Hours Views:', 'simple-post-view-count'); ?></strong> 
            <?php 
            printf(
                // Translators: %d is the total number of views in the last 24 hours.
                esc_html__('Views in last 24 hours: %d', 'simple-post-view-count'),
                esc_html(number_format_i18n($total_24_hours))
            );
            ?>
        </p>
    </div>
    <?php
}
?>