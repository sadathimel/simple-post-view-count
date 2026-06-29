/**
 * Admin JS for Simple Post View Count plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */
jQuery.noConflict();
jQuery(document).ready(function ($) {
  $(".simppovi-tabs .nav-tab").on("click", function (e) {
    e.preventDefault();
    $(".simppovi-tabs .nav-tab").removeClass("nav-tab-active");
    $(this).addClass("nav-tab-active");
    $(".simppovi-tab-content").removeClass("active");
    $($(this).attr("href")).addClass("active");
  });

  $("#simppovi-reset-button").on("click", function (e) {
    e.preventDefault();
    $("#simppovi-reset-modal").addClass("active");
  });

  $("#simppovi-reset-confirm").on("click", function () {
    $("#simppovi-reset-form").submit();
  });

  $("#simppovi-reset-cancel").on("click", function () {
    $("#simppovi-reset-modal").removeClass("active");
  });

  // Ensure form submission works
  $("#simppovi-reset-form").on("submit", function (e) {
    if (!confirm(simppoviAdmin.confirmReset)) {
      e.preventDefault();
      $("#simppovi-reset-modal").removeClass("active");
    }
  });

  /**
   * Admin JavaScript for Simple Post View Count plugin.
   *
   * @package Simple_Post_View
   */
  $("#preset_range").on("change", function () {
    if ($(this).val() === "custom") {
      $("#custom-date-range").show();
    } else {
      $("#custom-date-range").hide();
    }
  });
});
