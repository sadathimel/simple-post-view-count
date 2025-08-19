/**
 * Frontend AJAX for Simple Post View Count plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */
jQuery.noConflict();
jQuery(document).ready(function ($) {
  // Only track views for published posts
  if (typeof spvcAjax !== "undefined" && spvcAjax.post_id) {
    function trackView(attempt = 1, maxAttempts = 3) {
      $.ajax({
        url: spvcAjax.ajax_url,
        type: "POST",
        data: {
          action: "spvc_track_view",
          post_id: spvcAjax.post_id,
          _ajax_nonce: spvcAjax.nonce,
        },
        success: function (response) {
          console.log("View tracking response:", response);
        },
        error: function (error) {
          if (attempt < maxAttempts) {
            console.log("View tracking error, retrying:", error);
            setTimeout(function () {
              trackView(attempt + 1, maxAttempts);
            }, 1000 * attempt);
          } else {
            console.log(
              "View tracking failed after",
              maxAttempts,
              "attempts:",
              error
            );
          }
        },
      });
    }
    trackView();
  }
});
