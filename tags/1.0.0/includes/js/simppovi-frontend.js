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
  if (typeof simppoviAjax !== "undefined" && simppoviAjax.post_id) {
    function trackView(attempt = 1, maxAttempts = 3) {
      $.ajax({
        url: simppoviAjax.ajax_url,
        type: "POST",
        data: {
          action: "simppovi_track_view",
          post_id: simppoviAjax.post_id,
          _ajax_nonce: simppoviAjax.nonce,
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
