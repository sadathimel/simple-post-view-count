=== Simple Post View Count ===
Contributors: sadathimel
Tags: post views, view counter, analytics, shortcode
Requires at least: 5.0  
Tested up to: 6.8  
Stable tag: 1.0.0
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  


Track and display post view counts. Includes shortcode support, customizable settings, and view logs with CSV export.

== Description ==
Simple Post View Count is a lightweight plugin that allows you to track and display the number of views for your WordPress posts. Key features include:

- **View Tracking**: Automatically tracks post views using AJAX or a fallback method, with IP-based deduplication.
- **Shortcode Support**: Use `[spvc-single-post-view]` to display view counts anywhere on your site.
- **Customizable Display**: Customize text and colors for view counts via the settings page.
- **Admin Column**: Adds a sortable "Views" column to the post list in the admin area.
- **View Logs**: View detailed logs of post views and export them to CSV.
- **Caching Compatibility**: Works with caching plugins when configured correctly.

Perfect for bloggers, content creators, and site owners who want to monitor post popularity without heavy analytics plugins.

== Installation ==
1. Upload the `simple-post-view-count` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure settings under **Settings > Simple Post View** in the WordPress admin.
4. Use the `[spvc-single-post-view]` shortcode to display view counts in your posts or pages.
5. For caching plugins, ensure the `/wp-admin/admin-ajax.php` endpoint is excluded from caching.

== Frequently Asked Questions ==
= How do I display the view count? =
Use the `[spvc-single-post-view]` shortcode in your post or page content. For a specific post, use `[spvc-single-post-view id="123"]`. Customize with attributes like `show_total="true"` or `show_24_hour="false"`.

= Why are view counts not updating? =
Ensure your caching plugin excludes the `/wp-admin/admin-ajax.php` endpoint. Check the view logs under **Settings > Simple Post View** for details.

= Can I reset view counts? =
Yes, use the "Reset Post View Data" button in the settings page. This action is irreversible.

= Does it work with multisite? =
Yes, the plugin supports WordPress multisite installations.

== Screenshots ==
1. Settings page for customizing view count text and colors.
2. Post view column in the admin post list.
3. View logs table with CSV export option.
4. Shortcode output on a post page.

== Changelog ==
= 1.0.1 =
* Fixed database error for missing `ip_address` column.
* Added database schema upgrade routine.
* Improved performance with view_date index.
* Added date range filter for view logs.
* Enhanced security with CSV sanitization and input validation.
* Updated frontend AJAX with retry mechanism.
* Initial release.


== License ==
This plugin is licensed under the GPLv2 or later. See the included `license.txt` file for details.