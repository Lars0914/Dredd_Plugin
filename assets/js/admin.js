/**
 * DREDD AI Admin JavaScript
 */

(function ($) {
  "use strict";

  class DreddAdmin {
    constructor() {
      this.init();
    }

    init() {
      this.bindEvents();
      this.initCharts();
      this.loadDashboardData();
    }

    bindEvents() {
      // Connection testing
      $(".test-connection").on("click", (e) => this.testConnection(e));

      // Toggle paid mode
      $(".toggle-paid-mode").on("click", (e) => this.togglePaidMode(e));

      // Clear cache
      $(".clear-cache").on("click", (e) => this.clearCache(e));

      // Export data
      $(".export-data").on("click", (e) => this.exportData(e));

      // Token package management
      $(".add-package").on("click", (e) => this.addTokenPackage(e));
      $(document).on("click", ".remove-package", (e) =>
        this.removeTokenPackage(e)
      );

      // Promotion management - using event delegation for dynamic content
      $(document).on("click", ".edit-promotion", (e) => this.editPromotion(e));
      $(document).on("click", ".approve-promotion", (e) =>
        this.approvePromotion(e)
      );
      $(document).on("click", ".cancel-promotion", (e) =>
        this.cancelPromotion(e)
      );
      $(document).on("click", ".delete-promotion", (e) =>
        this.deletePromotion(e)
      );

      // Form submissions
      $("#dredd-settings-form").on("submit", (e) => this.saveSettings(e));
      $("#add-promotion-form").on("submit", (e) => this.addPromotion(e));

      // Analytics date range
      $("#analytics-date-range").on("change", () => this.updateAnalytics());
      $("#user-search").on("input", () => this.filterUsers());
      $("#user-filter").on("change", () => this.filterUsers());
    }

    testConnection(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const service = $btn.data("service");
      const originalText = $btn.text();

      $btn.text("Testing...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_test_connection",
          service: service,
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice(response.message, "success");
            $btn
              .closest(".status-item")
              .find(".status-value")
              .removeClass("offline")
              .addClass("online")
              .text("Online");
          } else {
            this.showNotice("Connection failed: " + response.message, "error");
            $btn
              .closest(".status-item")
              .find(".status-value")
              .removeClass("online")
              .addClass("offline")
              .text("Offline");
          }
        },
        error: () => {
          this.showNotice("Connection test failed", "error");
        },
        complete: () => {
          $btn.text(originalText).prop("disabled", false);
        },
      });
    }

    togglePaidMode(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const currentMode = $btn.text().toLowerCase().includes("enable");

      if (
        !confirm(
          `Are you sure you want to ${
            currentMode ? "enable" : "disable"
          } paid mode?`
        )
      ) {
        return;
      }

      $btn.text("Updating...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_toggle_paid_mode",
          enable: currentMode,
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            $btn.text(currentMode ? "Disable" : "Enable");
            this.showNotice(
              `Paid mode ${currentMode ? "enabled" : "disabled"}`,
              "success"
            );

            // Update status display
            const statusText = currentMode ? "Enabled" : "Disabled";
            $btn.closest(".status-item").find(".status-value").text(statusText);
          } else {
            this.showNotice("Failed to update paid mode", "error");
          }
        },
        error: () => {
          this.showNotice("Update failed", "error");
        },
        complete: () => {
          $btn.prop("disabled", false);
        },
      });
    }

    clearCache(e) {
      e.preventDefault();

      if (
        !confirm("Are you sure you want to clear all cached analysis data?")
      ) {
        return;
      }

      const $btn = $(e.currentTarget);
      $btn.text("Clearing...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_clear_cache",
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice("Cache cleared successfully", "success");
          } else {
            this.showNotice("Failed to clear cache", "error");
          }
        },
        error: () => {
          this.showNotice("Cache clear failed", "error");
        },
        complete: () => {
          $btn.text("Clear Cache").prop("disabled", false);
        },
      });
    }

    exportData(e) {
      e.preventDefault();

      const format = prompt("Export format (csv or json):", "csv");
      if (!format || !["csv", "json"].includes(format.toLowerCase())) {
        return;
      }

      const dateFrom = prompt(
        "Start date (YYYY-MM-DD):",
        this.getDateWeeksAgo(4)
      );
      const dateTo = prompt("End date (YYYY-MM-DD):", this.getTodayDate());

      if (!dateFrom || !dateTo) {
        return;
      }

      // Create download link
      const downloadUrl =
        dredd_admin_ajax.ajax_url +
        "?action=dredd_export_analytics" +
        "&format=" +
        encodeURIComponent(format) +
        "&date_from=" +
        encodeURIComponent(dateFrom) +
        "&date_to=" +
        encodeURIComponent(dateTo) +
        "&nonce=" +
        dredd_admin_ajax.nonce;

      window.location.href = downloadUrl;
    }

    addTokenPackage(e) {
      e.preventDefault();

      const packageIndex = $(".token-package").length;
      const packageHtml = `
                <div class="token-package">
                    <input type="text" name="token_packages[${packageIndex}][name]" placeholder="Package Name" required />
                    <input type="number" name="token_packages[${packageIndex}][tokens]" placeholder="Tokens" min="1" required />
                    <input type="number" name="token_packages[${packageIndex}][price]" placeholder="Price" step="0.01" min="0.01" required />
                    <button type="button" class="button remove-package">Remove</button>
                </div>
            `;

      $("#token-packages").append(packageHtml);
    }

    removeTokenPackage(e) {
      e.preventDefault();

      if ($(".token-package").length <= 1) {
        alert("At least one token package is required");
        return;
      }

      $(e.currentTarget).closest(".token-package").remove();

      // Reindex packages
      $(".token-package").each(function (index) {
        $(this)
          .find("input")
          .each(function () {
            const name = $(this).attr("name");
            if (name) {
              $(this).attr("name", name.replace(/\[\d+\]/, "[" + index + "]"));
            }
          });
      });
    }

    editPromotion(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const promotionId = $btn.data("id");
      const $row = $btn.closest("tr");

      // Get current values from the row
      const tokenName = $row.find("td:first strong").text();
      const status = $row.find(".status-badge").text().toLowerCase();

      if (status === "active") {
        if (
          !confirm(
            `Are you sure you want to pause the active promotion "${tokenName}"?`
          )
        ) {
          return;
        }

        $btn.text("Pausing...").prop("disabled", true);

        $.ajax({
          url: dredd_admin_ajax.ajax_url,
          type: "POST",
          data: {
            action: "dredd_update_promotion",
            promotion_id: promotionId,
            promotion_action: "pause",
            nonce: dredd_admin_ajax.nonce,
          },
          success: (response) => {
            if (response.success) {
              $row
                .find(".status-badge")
                .removeClass("active")
                .addClass("cancelled")
                .text("Cancelled");
              this.showNotice("Promotion paused", "success");
            } else {
              this.showNotice("Failed to pause promotion", "error");
            }
          },
          error: () => {
            this.showNotice("Pause failed", "error");
          },
          complete: () => {
            $btn.text("Edit").prop("disabled", false);
          },
        });
      } else {
        // For non-active promotions, show a simple info dialog
        alert(`Promotion "${tokenName}" (Status: ${status})

To edit promotion details, you would need to:
1. Cancel this promotion
2. Create a new promotion with updated details`);
      }
    }

    approvePromotion(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const promotionId = $btn.data("id");

      if (!confirm("Approve this promotion?")) {
        return;
      }

      $btn.text("Approving...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_update_promotion",
          promotion_id: promotionId,
          promotion_action: "approve",
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            $btn
              .closest("tr")
              .find(".status-badge")
              .removeClass("pending")
              .addClass("active")
              .text("Active");
            $btn.remove();
            this.showNotice("Promotion approved", "success");
          } else {
            this.showNotice("Failed to approve promotion", "error");
          }
        },
        error: () => {
          this.showNotice("Approval failed", "error");
        },
        complete: () => {
          $btn.prop("disabled", false);
        },
      });
    }

    cancelPromotion(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const promotionId = $btn.data("id");

      if (!confirm("Cancel this promotion?")) {
        return;
      }

      $btn.text("Cancelling...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_update_promotion",
          promotion_id: promotionId,
          promotion_action: "cancel",
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            $btn
              .closest("tr")
              .find(".status-badge")
              .removeClass("active pending")
              .addClass("cancelled")
              .text("Cancelled");
            this.showNotice("Promotion cancelled", "success");
          } else {
            this.showNotice("Failed to cancel promotion", "error");
          }
        },
        error: () => {
          this.showNotice("Cancellation failed", "error");
        },
        complete: () => {
          $btn.text("Cancel").prop("disabled", false);
        },
      });
    }

    deletePromotion(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const promotionId = $btn.data("id");
      const $row = $btn.closest("tr");
      const tokenName = $row.find("td:first strong").text();

      if (
        !confirm(
          `Are you sure you want to permanently delete the promotion "${tokenName}"? This action cannot be undone.`
        )
      ) {
        return;
      }

      $btn.text("🗑️ Deleting...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_delete_promotion",
          promotion_id: promotionId,
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            $row.fadeOut(300, function () {
              $(this).remove();
            });
            this.showNotice("Promotion deleted successfully", "success");
          } else {
            this.showNotice(
              "Failed to delete promotion: " + response.data,
              "error"
            );
          }
        },
        error: () => {
          this.showNotice("Deletion failed", "error");
        },
        complete: () => {
          $btn.text("🗑️ Delete").prop("disabled", false);
        },
      });
    }

    saveSettings(e) {
      e.preventDefault();
      const $form = $(e.currentTarget);
      const $submitBtn = $form.find('input[type="submit"]');

      $submitBtn.val("Saving...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data:
          $form.serialize() +
          "&action=dredd_save_settings&nonce=" +
          dredd_admin_ajax.nonce,
        success: (response) => {
          if (response.success) {
            this.showNotice("Settings saved successfully", "success");
          } else {
            this.showNotice(
              "Failed to save settings: " + response.data,
              "error"
            );
          }
        },
        error: () => {
          this.showNotice("Save failed", "error");
        },
        complete: () => {
          $submitBtn.val("Save Settings").prop("disabled", false);
        },
      });
    }

    addPromotion(e) {
      e.preventDefault();
      const $form = $(e.currentTarget);
      const $submitBtn = $form.find('button[type="submit"]');

      // Client-side validation
      const tokenName = $form.find('input[name="token_name"]').val().trim();
      const tokenSymbol = $form.find('input[name="token_symbol"]').val().trim();
      const contractAddress = $form
        .find('input[name="contract_address"]')
        .val()
        .trim();
      const tagline = $form.find('input[name="tagline"]').val().trim();
      const startDate = $form.find('input[name="start_date"]').val();
      const endDate = $form.find('input[name="end_date"]').val();

      // Validate required fields
      if (!tokenName) {
        this.showNotice("Token name is required", "error");
        return;
      }

      if (!startDate || !endDate) {
        this.showNotice("Start date and end date are required", "error");
        return;
      }

      // Validate field lengths
      if (tokenName.length > 100) {
        this.showNotice(
          "Token name is too long (maximum 100 characters)",
          "error"
        );
        return;
      }

      if (tokenSymbol.length > 20) {
        this.showNotice(
          "Token symbol is too long (maximum 20 characters)",
          "error"
        );
        return;
      }

      if (contractAddress.length > 42) {
        this.showNotice(
          "Contract address is too long (maximum 42 characters)",
          "error"
        );
        return;
      }

      if (tagline.length > 255) {
        this.showNotice(
          "Tagline is too long (maximum 255 characters)",
          "error"
        );
        return;
      }

      // Validate dates
      if (new Date(endDate) <= new Date(startDate)) {
        this.showNotice("End date must be after start date", "error");
        return;
      }

      $submitBtn.text("Adding...").prop("disabled", true);

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data:
          $form.serialize() +
          "&action=dredd_add_promotion&nonce=" +
          dredd_admin_ajax.nonce,
        success: (response) => {
          if (response.success) {
            this.showNotice("Promotion added successfully", "success");
            $form[0].reset();
            // Refresh promotions table
            location.reload();
          } else {
            this.showNotice(
              "Failed to add promotion: " + response.data,
              "error"
            );
          }
        },
        error: () => {
          this.showNotice("Add promotion failed", "error");
        },
        complete: () => {
          $submitBtn.text("Add Promotion").prop("disabled", false);
        },
      });
    }

    loadDashboardData() {
      // Load real-time dashboard statistics
      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_get_dashboard_stats",
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.updateDashboardStats(response.data);
          }
        },
      });
    }

    updateDashboardStats(stats) {
      // Update stat cards with real-time data
      if (stats.analyses_24h !== undefined) {
        $(".stat-card").each(function () {
          const $card = $(this);
          const title = $card.find("h3").text().toLowerCase();

          if (title.includes("analyses")) {
            $card.find(".stat-number").text(stats.analyses_24h || 0);
          } else if (title.includes("revenue")) {
            $card
              .find(".stat-number")
              .text("$" + (stats.revenue_24h || 0).toFixed(2));
          } else if (title.includes("scams")) {
            $card.find(".stat-number").text(stats.scams_detected_24h || 0);
          } else if (title.includes("users")) {
            $card.find(".stat-number").text(stats.active_users_24h || 0);
          }
        });
      }
    }

    updateAnalytics() {
      const dateRange = $("#analytics-date-range").val();
      const [dateFrom, dateTo] = dateRange.split(" to ");

      $.ajax({
        url: dredd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_get_analytics_data",
          date_from: dateFrom,
          date_to: dateTo,
          nonce: dredd_admin_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.renderAnalytics(response.data);
          }
        },
      });
    }

    renderAnalytics(data) {
      // Render analytics charts and data
      if (data.trends && data.trends.daily_analyses) {
        this.renderAnalysisChart(data.trends.daily_analyses);
      }

      if (data.revenue && data.revenue.daily_trend) {
        this.renderRevenueChart(data.revenue.daily_trend);
      }
    }

    renderAnalysisChart(data) {
      // This would integrate with Chart.js or similar library
      // For now, just log the data
      console.log("Analysis chart data:", data);
    }

    renderRevenueChart(data) {
      // This would integrate with Chart.js or similar library
      // For now, just log the data
      console.log("Revenue chart data:", data);
    }

    initCharts() {
      // Initialize any charts that need to be rendered
      // This would typically use Chart.js or similar library

      // Example placeholder for future chart implementation
      $(".analytics-chart").each(function () {
        const $chart = $(this);
        const chartType = $chart.data("chart-type");

        if (chartType) {
          // Initialize specific chart type
          console.log("Initialize chart:", chartType);
        }
      });
    }

    showNotice(message, type = "info") {
      const noticeClass = `notice notice-${type} dredd-notice`;
      const $notice = $(`<div class="${noticeClass}"><p>${message}</p></div>`);

      // Remove existing notices
      $(".dredd-notice").remove();

      // Add new notice
      $(".dredd-admin-wrap").prepend($notice);

      // Auto-hide after 5 seconds
      setTimeout(() => {
        $notice.fadeOut(300, () => $notice.remove());
      }, 5000);

      // Scroll to top to show notice
      $("html, body").animate({ scrollTop: 0 }, 300);
    }

    getDateWeeksAgo(weeks) {
      const date = new Date();
      date.setDate(date.getDate() - weeks * 7);
      return date.toISOString().split("T")[0];
    }

    getTodayDate() {
      return new Date().toISOString().split("T")[0];
    }

    filterUsers() {
      const searchText = $searchInput.val().toLowerCase();
      const filter = $filterSelect.val();
      console.log("Filtering users with", { searchText, filter });
      $(".user-row-epic").each(function () {
        const $row = $(this);
        const username = $row.find(".user-name-epic").text().toLowerCase();
        const email = $row.find(".user-email-epic").text().toLowerCase();
        const totalAnalyses =
          parseInt($row.find(".stat-value").first().text().replace(/,/g, "")) ||
          0;
        const totalSpent =
          parseFloat(
            $row
              .find(".revenue-amount")
              .text()
              .replace(/[^\d.]/g, "")
          ) || 0;

        let show = true;
        if (
          searchText &&
          !(username.includes(searchText) || email.includes(searchText))
        ) {
          show = false;
        }
        if (filter === "active" && totalAnalyses === 0) {
          show = false;
        } else if (filter === "inactive" && totalAnalyses > 0) {
          show = false;
        } else if (filter === "high-spenders" && totalSpent < 100) {
          show = false;
        }

        $row.toggle(show);
      });
    }
  }

  // Initialize admin interface when document is ready
  $(document).ready(function () {
    window.dreddAdmin = new DreddAdmin();

    // Auto-refresh dashboard stats every 5 minutes
    setInterval(() => {
      if (
        window.dreddAdmin &&
        typeof window.dreddAdmin.loadDashboardData === "function"
      ) {
        window.dreddAdmin.loadDashboardData();
      }
    }, 300000); // 5 minutes
  });
})(jQuery);

// Global function for n8n webhook testing
function testN8nConnection() {
  const button = event.target;
  const originalText = button.textContent;

  // Get the webhook URL from the input field
  const webhookInput = document.querySelector('input[name="n8n_webhook"]');
  if (!webhookInput || !webhookInput.value.trim()) {
    alert("Please enter a webhook URL before testing");
    return;
  }

  button.textContent = "Testing...";
  button.disabled = true;

  // Use jQuery for AJAX request
  jQuery.ajax({
    url: dredd_admin_ajax.ajax_url,
    type: "POST",
    data: {
      action: "dredd_test_n8n_webhook",
      webhook_url: webhookInput.value,
      nonce: dredd_admin_ajax.nonce,
    },
    success: function (response) {
      if (response.success) {
        alert(
          "✅ n8n webhook test successful!\n\nResponse: " +
            response.data.message
        );
        // Update status if there's a status indicator
        const statusElement = document.querySelector(".panel-status");
        if (statusElement) {
          statusElement.textContent = "ONLINE";
          statusElement.className = "panel-status online";
        }
      } else {
        alert(
          "❌ n8n webhook test failed!\n\nError: " +
            (response.data || "Unknown error")
        );
        // Update status if there's a status indicator
        const statusElement = document.querySelector(".panel-status");
        if (statusElement) {
          statusElement.textContent = "OFFLINE";
          statusElement.className = "panel-status offline";
        }
      }
    },
    error: function (xhr, status, error) {
      alert("❌ n8n webhook test failed!\n\nNetwork error: " + error);
    },
    complete: function () {
      button.textContent = originalText;
      button.disabled = false;
    },
  });
}
