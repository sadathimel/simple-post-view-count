/**
 * Admin JS for Simple Post View Count plugin.
 *
 * @package Simple_Post_View
 * @license GPLv2 or later
 * @license URI http://www.gnu.org/licenses/gpl-2.0.html
 */
jQuery.noConflict();
jQuery(document).ready(function ($) {
  $(".wp-spv-tabs .nav-tab").on("click", function (e) {
    e.preventDefault();
    $(".wp-spv-tabs .nav-tab").removeClass("nav-tab-active");
    $(this).addClass("nav-tab-active");
    $(".wp-spv-tab-content").removeClass("active");
    $($(this).attr("href")).addClass("active");
  });

  $("#wp-spv-reset-button").on("click", function (e) {
    e.preventDefault();
    $("#wp-spv-reset-modal").addClass("active");
  });

  $("#wp-spv-reset-confirm").on("click", function () {
    $("#wp-spv-reset-form").submit();
  });

  $("#wp-spv-reset-cancel").on("click", function () {
    $("#wp-spv-reset-modal").removeClass("active");
  });

  // Ensure form submission works
  $("#wp-spv-reset-form").on("submit", function (e) {
    if (!confirm(spvcAdmin.confirmReset)) {
      e.preventDefault();
      $("#wp-spv-reset-modal").removeClass("active");
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
