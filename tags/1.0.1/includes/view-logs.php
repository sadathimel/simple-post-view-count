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
 * Handle CSV export separately before any HTML output
 */
function simppovi_handle_csv_export() {
    if (isset($_GET['simppovi-export']) && $_GET['simppovi-export'] === 'view-logs' && isset($_GET['simppovi_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['simppovi_nonce'])), 'simppovi_view_logs')) {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this feature.', 'simple-post-view-count'),
                esc_html__('Permission Denied', 'simple-post-view-count'),
                ['response' => 403]
            );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'post_view_logs';

        // Sanitize and validate date_range
        $date_range = isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '30_days';
        $allowed_date_ranges = ['7_days', '30_days', '90_days'];
        if (!in_array($date_range, $allowed_date_ranges, true)) {
            $date_range = '30_days';
        }
        $start_date = match ($date_range) {
            '7_days' => gmdate('Y-m-d', strtotime('-7 days')),
            '90_days' => gmdate('Y-m-d', strtotime('-90 days')),
            default => gmdate('Y-m-d', strtotime('-30 days')),
        };

        // Start output buffering
        ob_start();

        // Set headers for CSV download
        $filename = 'post_view_logs_' . $date_range . '_' . gmdate('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create CSV content
        $csv_lines = [];
        
        // CSV headers
        $csv_lines[] = esc_html__('Post View Logs Export', 'simple-post-view-count') . ',';
        
        // Total views for last 24 hours with caching
        $cache_key_24_hours = "simppovi_total_24_hours_" . gmdate('Y-m-d');
        $total_24_hours = wp_cache_get($cache_key_24_hours, 'simppovi');
        if ($total_24_hours === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $total_24_hours = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                    gmdate('Y-m-d', strtotime('-1 day'))
                )
            );
            wp_cache_set($cache_key_24_hours, $total_24_hours, 'simppovi', 60);
        }

        $csv_lines[] = esc_html__('Last 24 Hours Total Views', 'simple-post-view-count') . ',' . number_format_i18n($total_24_hours);
        $csv_lines[] = ''; // Empty row

        // Daily views for CSV with caching
        $cache_key_daily_views = "simppovi_csv_daily_views_{$start_date}";
        $daily_views = wp_cache_get($cache_key_daily_views, 'simppovi');
        if ($daily_views === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $daily_views = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(view_date) as view_date, SUM(view_count) AS daily_count 
                     FROM `" . esc_sql($table_name) . "` 
                     WHERE view_date >= %s 
                     GROUP BY DATE(view_date)
                     ORDER BY view_date DESC",
                    $start_date
                )
            );
            wp_cache_set($cache_key_daily_views, $daily_views, 'simppovi', 60);
        }

        // Output CSV headers for data
        $csv_lines[] = esc_html__('Date', 'simple-post-view-count') . ',' . esc_html__('Daily Views', 'simple-post-view-count');

        // Output data rows
        foreach ($daily_views as $row) {
            $csv_lines[] = esc_html(date_i18n(get_option('date_format'), strtotime($row->view_date))) . ',' . esc_html(number_format_i18n($row->daily_count));
        }

        // Total views for date range with caching
        $cache_key_total_views = "simppovi_total_views_{$start_date}";
        $total_views = wp_cache_get($cache_key_total_views, 'simppovi');
        if ($total_views === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $total_views = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                    $start_date
                )
            );
            wp_cache_set($cache_key_total_views, $total_views, 'simppovi', 60);
        }

        $csv_lines[] = ''; // Empty row
        $csv_lines[] = esc_html__('Total Views', 'simple-post-view-count') . ',' . number_format_i18n($total_views);

        // Output the CSV content with UTF-8 BOM
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        foreach ($csv_lines as $line) {
            echo esc_html($line) . "\n";
        }

        // Flush and end output
        ob_end_flush();
        exit;
    }
}
add_action('admin_init', 'simppovi_handle_csv_export', 1);

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
function simppovi_get_daily_views($start_date, $per_page, $offset, $orderby, $order) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'post_view_logs';

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        return [];
    }

    $cache_key = "simppovi_daily_views_{$start_date}_{$per_page}_{$offset}_{$orderby}_{$order}";
    $cached = wp_cache_get($cache_key, 'simppovi');
    if ($cached !== false) {
        return $cached;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
        return [];
    }

    // Validate orderby and order to prevent SQL injection
    $allowed_orderby = [
        'view_date' => 'DATE(view_date)',
        'daily_count' => 'daily_count'
    ];
    $allowed_order = ['ASC', 'DESC'];
    $orderby_sql = isset($allowed_orderby[$orderby]) ? $allowed_orderby[$orderby] : 'DATE(view_date)';
    $order_sql = in_array($order, $allowed_order, true) ? $order : 'DESC';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(view_date) as view_date, SUM(view_count) AS daily_count 
             FROM `" . esc_sql($table_name) . "` 
             WHERE view_date >= %s 
             GROUP BY DATE(view_date)
             ORDER BY %s %s
             LIMIT %d OFFSET %d",
            $start_date,
            $orderby_sql,
            $order_sql,
            $per_page,
            $offset
        )
    );

    wp_cache_set($cache_key, $results, 'simppovi', 60);
    return $results ?: [];
}

/**
 * Display the view logs table.
 */
function simppovi_display_view_logs_table() {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('You do not have sufficient permissions to access this page.', 'simple-post-view-count') . '</p></div>';
        return;
    }

    // Verify nonce if GET params are present
    if (!empty($_GET['orderby']) || !empty($_GET['order']) || !empty($_GET['paged']) || !empty($_GET['date_range'])) {
        if (!isset($_GET['simppovi_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['simppovi_nonce'])), 'simppovi_view_logs')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nonce verification failed. Please try again.', 'simple-post-view-count') . '</p></div>';
            return;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'post_view_logs';

    // Sanitize and validate GET parameters
    $date_range = isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '30_days';
    $allowed_ranges = ['7_days', '30_days', '90_days'];
    if (!in_array($date_range, $allowed_ranges, true)) {
        $date_range = '30_days';
    }

    $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'view_date';
    $allowed_orderby = ['view_date', 'daily_count'];
    if (!in_array($orderby, $allowed_orderby, true)) {
        $orderby = 'view_date';
    }

    $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';
    $allowed_order = ['ASC', 'DESC'];
    if (!in_array($order, $allowed_order, true)) {
        $order = 'DESC';
    }

    $current_page = isset($_GET['paged']) && is_numeric($_GET['paged']) ? absint($_GET['paged']) : 1;
    if ($current_page < 1) {
        $current_page = 1;
    }

    $per_page = 20;
    $offset = ($current_page - 1) * $per_page;

    // Calculate start_date
    $start_date = match ($date_range) {
        '7_days' => gmdate('Y-m-d', strtotime('-7 days')),
        '90_days' => gmdate('Y-m-d', strtotime('-90 days')),
        default => gmdate('Y-m-d', strtotime('-30 days')),
    };

    // Get daily views
    $daily_views = simppovi_get_daily_views($start_date, $per_page, $offset, $orderby, $order);

    // Calculate total pages with caching
    $cache_key_total_items = "simppovi_total_items_{$start_date}";
    $total_items = wp_cache_get($cache_key_total_items, 'simppovi');
    if ($total_items === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $total_items = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT DATE(view_date)) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                $start_date
            )
        );
        wp_cache_set($cache_key_total_items, $total_items, 'simppovi', 60);
    }
    $total_items = (int) $total_items;
    $total_pages = ceil($total_items / $per_page);

    // Get total views for the date range with caching
    $cache_key_total_views = "simppovi_total_views_{$start_date}";
    $total_views = wp_cache_get($cache_key_total_views, 'simppovi');
    if ($total_views === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $total_views = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                $start_date
            )
        );
        wp_cache_set($cache_key_total_views, $total_views, 'simppovi', 60);
    }

    // Get total views for last 24 hours with caching
    $cache_key_24_hours = "simppovi_total_24_hours_" . gmdate('Y-m-d');
    $total_24_hours = wp_cache_get($cache_key_24_hours, 'simppovi');
    if ($total_24_hours === false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $total_24_hours = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(view_count) FROM `" . esc_sql($table_name) . "` WHERE view_date >= %s",
                gmdate('Y-m-d', strtotime('-1 day'))
            )
        );
        wp_cache_set($cache_key_24_hours, $total_24_hours, 'simppovi', 60);
    }
    ?>
    <div class="wrap">
        <form method="get" class="simppovi-view-logs-form">
            <input type="hidden" name="page" value="simppovi-settings">
            <label for="date_range"><?php esc_html_e('Select Date Range:', 'simple-post-view-count'); ?></label>
            <select name="date_range" id="date_range">
                <option value="7_days" <?php selected($date_range, '7_days'); ?>><?php esc_html_e('Last 7 Days', 'simple-post-view-count'); ?></option>
                <option value="30_days" <?php selected($date_range, '30_days'); ?>><?php esc_html_e('Last 30 Days', 'simple-post-view-count'); ?></option>
                <option value="90_days" <?php selected($date_range, '90_days'); ?>><?php esc_html_e('Last 90 Days', 'simple-post-view-count'); ?></option>
            </select>
            <input type="hidden" name="simppovi_nonce" value="<?php echo esc_attr(wp_create_nonce('simppovi_view_logs')); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'simple-post-view-count'); ?>">
        </form>

        <form method="get" class="simppovi-export-form">
            <?php
            foreach (['page' => 'simppovi-settings', 'simppovi-export' => 'view-logs', 'date_range' => $date_range] as $key => $value) {
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }
            wp_nonce_field('simppovi_view_logs', 'simppovi_nonce');
            ?>
            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Export as CSV', 'simple-post-view-count'); ?>">
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <a href="<?php
                            $new_order = ($orderby === 'view_date' && $order === 'DESC') ? 'ASC' : 'DESC';
                            echo esc_url(add_query_arg([
                                'page' => 'simppovi-settings',
                                'orderby' => 'view_date',
                                'order' => $new_order,
                                'paged' => 1,
                                'date_range' => $date_range,
                                'simppovi_nonce' => wp_create_nonce('simppovi_view_logs'),
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
                                'page' => 'simppovi-settings',
                                'orderby' => 'daily_count',
                                'order' => $new_order,
                                'paged' => 1,
                                'date_range' => $date_range,
                                'simppovi_nonce' => wp_create_nonce('simppovi_view_logs'),
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
                                // Translators: %1$s is the start date for the view data
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
                        'base' => esc_url(add_query_arg(['paged' => '%#%', 'page' => 'simppovi-settings', 'simppovi_nonce' => wp_create_nonce('simppovi_view_logs')], admin_url('admin.php'))),
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
                // Translators: %1$s is the start date, %2$d is the total number of views
                esc_html__('Total views since %1$s: %2$d', 'simple-post-view-count'),
                esc_html($start_date),
                esc_html(number_format_i18n($total_views))
            );
            ?><br />
            <strong><?php esc_html_e('Last 24 Hours Views:', 'simple-post-view-count'); ?></strong> 
            <?php 
            printf(
                // Translators: %d is the total number of views in the last 24 hours
                esc_html__('Views in last 24 hours: %d', 'simple-post-view-count'),
                esc_html(number_format_i18n($total_24_hours))
            );
            ?>
        </p>
    </div>
    <?php
}