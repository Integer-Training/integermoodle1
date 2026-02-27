// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * News popup module for displaying important news on login.
 *
 * @module     local_news/popup
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/notification", "core/str"], function (
  $,
  Ajax,
  Notification,
  Str,
) {
  var newsQueue = [];
  var currentIndex = 0;

  /**
   * Initialize the popup module.
   *
   * @param {Array} newsIds Array of news IDs to display.
   */
  var init = function (newsIds) {
    newsQueue = newsIds;
    currentIndex = 0;

    if (newsQueue.length > 0) {
      showNextPopup();
    }
  };

  /**
   * Show the next news popup in the queue.
   */
  var showNextPopup = function () {
    if (currentIndex >= newsQueue.length) {
      return;
    }

    var newsId = newsQueue[currentIndex];
    fetchNewsAndShow(newsId);
  };

  /**
   * Fetch news data via AJAX and show popup.
   *
   * @param {number} newsId The news ID to fetch.
   */
  var fetchNewsAndShow = function (newsId) {
    $.ajax({
      url: M.cfg.wwwroot + "/local/news/ajax.php",
      method: "POST",
      dataType: "json",
      data: {
        sesskey: M.cfg.sesskey,
        action: "getnews",
        newsid: newsId,
      },
      success: function (response) {
        if (response.success && response.news) {
          showPopupModal(response.news);
        } else {
          // Skip to next if error.
          currentIndex++;
          showNextPopup();
        }
      },
      error: function () {
        // Skip to next on error.
        currentIndex++;
        showNextPopup();
      },
    });
  };

  /**
   * Show the popup modal for a news item.
   *
   * @param {Object} news The news object with title, content, etc.
   */
  var showPopupModal = function (news) {
    // Remove any existing popup.
    $("#news-popup-overlay").remove();

    var categoryClass = "nw-popup-category-" + news.category.replace("_", "-");

    var importantBadge = news.important
      ? '<span class="nw-popup-important"><i class="bi bi-exclamation-triangle-fill"></i> Important Notice</span>'
      : "";

    var html = `
            <div id="news-popup-overlay" class="nw-popup-overlay">
                <div class="nw-popup-modal">
                    <button type="button" class="nw-popup-close" aria-label="Close">&times;</button>

                    <div class="nw-popup-header">
                        ${importantBadge}
                        <span class="badge ${categoryClass}">${news.category_label}</span>
                    </div>

                    <h2 class="nw-popup-title">${news.title}</h2>

                    <div class="nw-popup-meta">
                        Published: ${news.publishdate}
                    </div>

                    <div class="nw-popup-content">
                        ${news.content}
                    </div>

                    <div class="nw-popup-footer">
                        ${
                          news.requires_acknowledgement
                            ? '<button type="button" class="btn btn-primary btn-lg nw-popup-acknowledge" data-newsid="' +
                              news.id +
                              '"><i class="bi bi-check-lg me-2"></i>I Acknowledge - I Have Read This</button>'
                            : '<button type="button" class="btn btn-secondary nw-popup-dismiss">Continue</button>'
                        }
                    </div>
                </div>
            </div>
        `;

    $("body").append(html);

    // Fade in.
    setTimeout(function () {
      $("#news-popup-overlay").addClass("nw-popup-visible");
    }, 10);

    // Mark as read.
    markAsRead(news.id);

    // Bind events.
    $("#news-popup-overlay .nw-popup-close").on("click", function () {
      closePopup();
    });

    $("#news-popup-overlay .nw-popup-dismiss").on("click", function () {
      closePopup();
    });

    $("#news-popup-overlay .nw-popup-acknowledge").on("click", function () {
      var newsid = $(this).data("newsid");
      acknowledgeNews(newsid);
    });

    // Close on overlay click (outside modal).
    $("#news-popup-overlay").on("click", function (e) {
      if (e.target === this) {
        closePopup();
      }
    });
  };

  /**
   * Mark news as read via AJAX.
   *
   * @param {number} newsId The news ID.
   */
  var markAsRead = function (newsId) {
    $.ajax({
      url: M.cfg.wwwroot + "/local/news/ajax.php",
      method: "POST",
      dataType: "json",
      data: {
        sesskey: M.cfg.sesskey,
        action: "markread",
        newsid: newsId,
      },
    });
  };

  /**
   * Acknowledge news via AJAX.
   *
   * @param {number} newsId The news ID.
   */
  var acknowledgeNews = function (newsId) {
    var $btn = $("#news-popup-overlay .nw-popup-acknowledge");
    $btn
      .prop("disabled", true)
      .html('<i class="bi bi-hourglass-split me-2"></i>Processing...');

    $.ajax({
      url: M.cfg.wwwroot + "/local/news/ajax.php",
      method: "POST",
      dataType: "json",
      data: {
        sesskey: M.cfg.sesskey,
        action: "acknowledge",
        newsid: newsId,
      },
      success: function (response) {
        if (response.success) {
          $btn
            .removeClass("btn-primary")
            .addClass("btn-success")
            .html('<i class="bi bi-check-circle me-2"></i>Acknowledged');
          setTimeout(function () {
            closePopup();
          }, 1000);
        } else {
          $btn
            .prop("disabled", false)
            .html(
              '<i class="bi bi-check-lg me-2"></i>I Acknowledge - I Have Read This',
            );
          Notification.alert(
            "Error",
            response.error || "Failed to acknowledge.",
          );
        }
      },
      error: function () {
        $btn
          .prop("disabled", false)
          .html(
            '<i class="bi bi-check-lg me-2"></i>I Acknowledge - I Have Read This',
          );
        Notification.alert("Error", "Network error. Please try again.");
      },
    });
  };

  /**
   * Close the current popup and show next if available.
   */
  var closePopup = function () {
    $("#news-popup-overlay").removeClass("nw-popup-visible");
    setTimeout(function () {
      $("#news-popup-overlay").remove();
      currentIndex++;
      showNextPopup();
    }, 300);
  };

  return {
    init: init,
  };
});
