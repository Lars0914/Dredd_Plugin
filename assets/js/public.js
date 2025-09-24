/**
 * DREDD AI Public JavaScript - Frontend Functionality
 */

(function ($) {
  "use strict";

  class DreddChat {
    constructor() {
      this.sessionId = this.generateSessionId();
      this.currentMode = "standard";
      this.selectedChain = "ethereum";
      this.selectedPackage = null;
      this.isProcessing = false;
      this.messageHistory = [];
      this.pendingPsychoActivation = false;
      this.paymentEventsInitialized = false; // Prevent multiple event bindings
      this.lastModalToggle = 0; // Performance throttling
      this.loadingMessages = [
        "Analyzing...",
        "Scanning blockchain for criminal activity...",
        "Investigating token holders...",
        "Analyzing market manipulation...",
        "Cross-referencing scam databases...",
        "Preparing brutal verdict...",
      ];

      this.init();
    }

    init() {
      console.log("DreddChat initializing...");

      // Cache frequently accessed DOM elements for performance
      this.$dashboardModal = $("#dredd-dashboard-modal");
      this.$paymentModal = $("#dredd-payment-modal");
      this.$authModal = $("#dredd-auth-modal");
      this.$body = $("body");
      this.$messageInput = $("#dredd-message-input");

      this.bindEvents();
      this.initStripe();
      this.initWeb3();
      this.loadPromotions();
      this.checkForNotifications();
      this.fixInputVisibility();
      this.initResponsiveFeatures();

      // Debug: Check if auth elements exist
      console.log("Auth buttons found:", $(".login-btn, .signup-btn").length);
      console.log("Auth modal found:", $("#dredd-auth-modal").length);
    }

    generateSessionId() {
      return (
        "dredd_" + Math.random().toString(36).substr(2, 9) + "_" + Date.now()
      );
    }

    bindEvents() {
      console.log("Binding events..."); // Debug log
      console.log("QRCode library available:", typeof QRCode !== "undefined");
      console.log("Login buttons in DOM:", $(".login-btn").length);
      console.log("Signup buttons in DOM:", $(".signup-btn").length);

      // Mode switching
      $(".mode-btn").on("click", (e) => this.switchMode(e));

      // Chain selection
      $("#blockchain-select").on("change", (e) => this.changeChain(e));

      // Chat input
      $("#dredd-message-input").on("keypress", (e) => {
        if (e.which === 13) {
          this.sendMessage();
        }
      });

      $("#dredd-send-btn").on("click", () => this.sendMessage());
      $("#dredd-send-btn").on("touchstart", () => this.sendMessage());

      // Payment panel (old)
      $(".payment-close").on("click", () => this.closePaymentPanel());
      $(".payment-tab").on("click", (e) => this.switchPaymentTab(e));
      $(".token-package").on("click", (e) => this.selectPackage(e));

      // New Payment Modal Events
      $(document).on("click", ".payment-modal-close", () =>
        this.closePaymentModal()
      );
      $(document).on("click", ".payment-modal-overlay", (e) => {
        if (e.target === e.currentTarget) {
          this.closePaymentModal();
        }
      });

      // Payment method selection with enhanced debugging
      $(document).on("click", ".payment-method-card", (e) => {
        console.log("Payment method card clicked!"); // Debug
        e.preventDefault();
        e.stopPropagation();
        this.selectPaymentMethod(e);
      });

      // Amount selection
      $(document).on("click", ".amount-option", (e) => this.selectAmount(e));
      $("#amount-slider").on("input", (e) => this.updateCustomAmount(e));

      // Step navigation - using document delegation for dynamic content
      $(document).on("click", ".payment-back-btn", (e) => {
        e.preventDefault();
        this.goBackStep();
      });
      $(document).on("click", ".payment-continue-btn", (e) => {
        e.preventDefault();
        console.log("Continue button clicked via document delegation");
        this.goNextStep();
      });
      $(document).on("click", ".payment-close-btn", (e) => {
        e.preventDefault();
        this.closePaymentModal();
      });

      // Payment processing
      $("#stripe-submit-btn").on("click", () => this.processStripePayment());
      $("#connect-pulsechain-wallet").on("click", () =>
        this.connectPulseChainWallet()
      );
      $("#pulsechain-submit-btn").on("click", () =>
        this.processPulseChainPayment()
      );

      // Copy buttons
      $(document).on("click", ".copy-address-btn, .copy-amount-btn", (e) =>
        this.copyToClipboard(e)
      );

      // Promotions
      $(".promotions-toggle").on("click", () => this.togglePromotions());
      $(".promotions-close").on("click", () => this.closePromotions());
      $(".analyze-promoted").on("click", (e) => this.analyzePromoted(e));

      // User dashboard
      $(document).on("click", ".user-dashboard-link", (e) => {
        e.preventDefault();
        this.showDashboardModal();
      });
      $(".view-analysis").on("click", (e) => this.viewAnalysis(e));
      $(".reanalyze-token").on("click", (e) => this.reanalyzeToken(e));
      $("#buy-credits").on("click", (e) => this.openPaymentPanel(e));
      $("#export-data").on("click", (e) => this.exportUserData(e));

      // Dashboard modal events
      $(document).on("click", ".dashboard-modal-close", () =>
        this.closeDashboardModal()
      );
      $(document).on("click", ".dashboard-modal-overlay", (e) => {
        if (e.target === e.currentTarget) {
          this.closeDashboardModal();
        }
      });

      // Dashboard actions
      $(document).on("click", ".dashboard-buy-credits", (e) => {
        e.preventDefault();
        this.closeDashboardModal();
        this.showPaymentModal();
      });

      $(document).on("click", ".dashboard-export-data", (e) =>
        this.exportUserData(e)
      );
      $(document).on("click", ".dashboard-view-analysis", (e) =>
        this.viewAnalysis(e)
      );
      $(document).on("click", ".dashboard-reanalyze", (e) =>
        this.reanalyzeToken(e)
      );
      $(document).on("click", ".dashboard-submit-promotion", (e) =>
        this.submitPromotion(e)
      );
      $(document).on("submit", ".dashboard-settings-form", (e) =>
        this.updateUserSettings(e)
      );
      $(document).on("submit", ".dashboard-password-form", (e) =>
        this.updateUserPassword(e)
      );

      // Logout handler
      $(document).on("click", ".logout-link", (e) => {
        e.preventDefault();
        this.handleLogout();
      });

      // Authentication events - using document delegation for better reliability
      $(document).on("click", ".login-btn", (e) => {
        console.log("Login button clicked via document delegation!");
        e.preventDefault();
        this.showAuthModal("login");
      });
      $(document).on("click", ".signup-btn", (e) => {
        console.log("Signup button clicked via document delegation!");
        e.preventDefault();
        this.showAuthModal("signup");
      });
      $(document).on("click", ".user-menu-btn", (e) => {
        e.preventDefault();
        this.toggleUserMenu();
      });

      // Auth modal events
      $(document).on("click", ".auth-modal-close", () => this.closeAuthModal());
      $(document).on("touchstart", ".auth-modal-close", () =>
        this.closeAuthModal()
      );

      $(document).on("click", ".auth-modal-overlay", (e) => {
        if (e.target === e.currentTarget) {
          this.closeAuthModal();
        }
      });

      // Auth form switching
      $(document).on("click", ".switch-to-login", (e) => {
        e.preventDefault();
        this.switchAuthForm("login");
      });
      $(document).on("click", ".switch-to-signup", (e) => {
        e.preventDefault();
        this.switchAuthForm("signup");
      });
      $(document).on("click", ".forgot-password-link", (e) => {
        e.preventDefault();
        this.switchAuthForm("forgot");
      });

      // Auth form submissions
      $(document).on("submit", "#dredd-login-form", (e) => {
        e.preventDefault();
        this.handleLogin();
      });
      $(document).on("submit", "#dredd-signup-form", (e) => {
        e.preventDefault();
        this.handleSignup();
      });
      $(document).on("submit", "#dredd-forgot-form", (e) => {
        e.preventDefault();
        this.handleForgotPassword();
      });

      // Password toggle functionality removed - handled in document ready section

      // Force input text visibility on input events
      // $(document).on(
      //   "input keyup focus click",
      //   '.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"]',
      //   function () {
      //     $(this).css({
      //       color: "#FFFFFF !important",
      //       "caret-color": "#00FFFF !important",
      //       "-webkit-text-fill-color": "#FFFFFF !important",
      //     });
      //   }
      // );

      // Modal
      $(".modal-close").on("click", () => this.closeModal());
      $(document).on("click", ".dredd-modal", (e) => {
        if (e.target === e.currentTarget) {
          this.closeModal();
        }
      });
    }

    switchMode(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const mode = $btn.data("mode");

      if (mode === this.currentMode) return;

      // Check if psycho mode requires payment
      if (mode === "psycho" && dredd_ajax.paid_mode_enabled === "true") {
        if (!dredd_ajax.is_logged_in) {
          // For non-logged users, show login first
          this.showAuthModal("login");
          // Store the intent to activate psycho mode after login
          this.pendingPsychoActivation = true;
          return;
        }

        // Check user credits for logged-in users
        this.checkUserCredits().then((credits) => {
          if (credits >= parseInt(dredd_ajax.analysis_cost)) {
            this.activateMode(mode);
          } else {
            this.showPaymentModal();
          }
        });
      } else {
        this.activateMode(mode);
      }
    }

    activateMode(mode) {
      this.currentMode = mode;

      // Update UI
      $(".mode-btn").removeClass("active");
      $(`.mode-btn[data-mode="${mode}"]`).addClass("active");

      // Update chat header styling
      $(".dredd-chat-header")
        .removeClass("standard-mode psycho-mode")
        .addClass(`${mode}-mode`);

      // Show mode activation message
      let modeMessage;
      if (mode === "psycho") {
        if (dredd_ajax.paid_mode_enabled === "true") {
          modeMessage =
            "PSYCHO MODE ACTIVATED - Brutal honesty unlocked! Credits will be deducted per analysis.";
        } else {
          modeMessage = "PSYCHO MODE ACTIVATED - Prepare for brutal honesty!";
        }
      } else {
        modeMessage = "STANDARD MODE ACTIVATED - Justice with restraint.";
      }

      this.addMessage(modeMessage, "dredd", "system");
    }

    changeChain(e) {
      const newChain = $(e.currentTarget).val();
      this.selectedChain = newChain;

      // Show chain change message
      const chainNames = {
        ethereum: "Ethereum",
        bsc: "Binance Smart Chain",
        polygon: "Polygon",
        Solana: "Solana",
        pulsechain: "PulseChain",
      };

      const chainMessage = `🔗 Blockchain switched to ${chainNames[newChain]}. Ready to Analyze tokens on this chain!`;
      this.addMessage(chainMessage, "dredd", "system");
    }

    showPaymentPanel() {
      this.showPaymentModal();
    }

    closePaymentPanel() {
      this.closePaymentModal();
    }

    showPaymentModal() {
      // Performance throttling to prevent rapid toggles
      const now = Date.now();
      if (now - this.lastModalToggle < 200) return; // 200ms throttle
      this.lastModalToggle = now;

      this.$paymentModal.show(); // Use cached element for instant display
      this.currentPaymentStep = 1;
      this.selectedPaymentMethod = null;
      this.selectedAmount = null;
      this.showPaymentStep(1);

      // Bind events immediately without delay
      this.bindPaymentModalEvents();
    }

    bindPaymentModalEvents() {
      // Use event delegation instead of direct binding to prevent multiple handlers
      if (!this.paymentEventsInitialized) {
        this.paymentEventsInitialized = true;
        console.log("Initializing payment modal events (one-time setup)");

        // These are already handled by document delegation in init()
        // No need to rebind on every modal open
      }
    }

    closePaymentModal() {
      this.$paymentModal.hide(); // Use cached element for instant hide
    }

    showPaymentStep(stepNumber) {
      $(".payment-step").removeClass("active");
      $(`#payment-step-${stepNumber}`).addClass("active");
      this.currentPaymentStep = stepNumber;
    }

    switchPaymentTab(e) {
      const $tab = $(e.currentTarget);
      const tabId = $tab.data("tab");

      $(".payment-tab").removeClass("active");
      $tab.addClass("active");

      $(".payment-tab-content").removeClass("active");
      $(`#${tabId}-tab`).addClass("active");
    }

    selectPackage(e) {
      const $package = $(e.currentTarget);
      const tokens = $package.data("tokens");
      const price = $package.data("price");

      $(".token-package").removeClass("selected");
      $package.addClass("selected");

      this.selectedPackage = { tokens, price };
    }

    sendMessage() {
      const message = this.$messageInput.val().trim(); // Use cached element
      if (!message || this.isProcessing) return;

      this.isProcessing = true;
      this.$messageInput.val(""); // Use cached element

      // Add user message to chat
      this.addMessage(message, "user");

      // Show typing indicator
      this.showTypingIndicator();

      // Send to backend
      this.sendToBackend(message);
    }

    sendToBackend(message) {
      const data = {
        action: "dredd_chat",
        nonce: dredd_ajax.nonce,
        message: message,
        session_id: this.sessionId,
        mode: this.currentMode,
        selected_chain: this.selectedChain, // This is already being sent!
        user_id: dredd_ajax.user_id,
      };

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: data,
        timeout: 300000,
        success: (response) => {
          this.handleResponse(response);
        },
        error: (xhr, status, error) => {
          this.handleError(error);
        },
      });
    }

    handleResponse(response) {
      this.hideTypingIndicator();
      this.isProcessing = false;

      // COMPREHENSIVE FRONTEND DEBUGGING
      console.log("=== DREDD FRONTEND DEBUG START ===");
      console.log("DREDD Frontend - Raw Response:", response);
      console.log("DREDD Frontend - Response Type:", typeof response);
      console.log("DREDD Frontend - Response Success:", response.success);

      if (response.success) {
        console.log("DREDD Frontend - Response Data:", response.data);
        console.log("DREDD Frontend - Data Type:", typeof response.data);
        if (response.data) {
          console.log("DREDD Frontend - Data Action:", response.data.action);
          console.log("DREDD Frontend - Data Message:", response.data.message);
          console.log(
            "DREDD Frontend - Message Length:",
            response.data.message ? response.data.message.length : "N/A"
          );
        }
      } else {
        console.log("DREDD Frontend - Error Data:", response.data);
      }
      console.log("=== DREDD FRONTEND DEBUG END ===");

      if (response.success) {
        const data = response.data;

        // Handle direct message response (most common from n8n)
        if (data.action === "response" && data.message) {
          console.log("DREDD Frontend - Adding message to chat:", data.message);

          // DEBUG: Show alert for first few characters to verify
          if (data.message.includes("test") || data.message.includes("TEST")) {
            alert(
              "DEBUG: Response received! Message starts with: " +
                data.message.substring(0, 50) +
                "..."
            );
          }

          this.addMessage(data.message, "dredd");
          console.log("DREDD Frontend - Message added to chat successfully");
          return;
        }

        // Handle other action types
        switch (data.action) {
          case "analyzing":
            this.addMessage(data.message, "dredd");
            this.showLoadingOverlay();
            this.startProgressAnimation();
            break;

          case "analysis_complete":
            this.hideLoadingOverlay();
            this.addMessage(data.message, "dredd");

            if (data.analysis_data) {
              this.displayAnalysisResults(data.analysis_data);
            }

            // Update user credits if applicable
            if (data.credits_used) {
              this.updateCreditsDisplay(data.remaining_credits);
            }
            break;

          case "error":
            this.addMessage(data.message, "dredd", "error");
            break;

          default:
            // Fallback: if we have a message but unknown action
            if (data.message) {
              this.addMessage(data.message, "dredd");
            } else {
              console.warn("DREDD Frontend - Unknown response format:", data);
              this.addMessage(
                "Analysis completed but response format was unexpected.",
                "dredd",
                "warning"
              );
            }
            break;
        }
      } else {
        console.error("DREDD Frontend - Error response:", response);
        this.handleError(response.data || "Unknown error occurred");
      }
    }

    handleError(error) {
      this.hideTypingIndicator();
      this.hideLoadingOverlay();
      this.isProcessing = false;

      const errorMessage = error || dredd_ajax.strings.error;
      this.addMessage(errorMessage, "dredd", "error");
    }

    addMessage(content, sender, type = "normal") {
      const timestamp = new Date().toLocaleTimeString("en-US", {
        hour12: false,
        hour: "2-digit",
        minute: "2-digit",
      });

      const messageClass = sender === "user" ? "user-message" : "chat-message";
      const bubbleClass = sender === "user" ? "user-message" : "dredd-message";

      let messageHtml = `
                <div class="chat-message ${messageClass}">
                    ${
                      sender !== "user"
                        ? `
                        <div class="message-avatar">
                            <img src="https://dredd.ai/wp-content/uploads/2025/09/86215e12-1e3f-4cb0-b851-cfb84d7459a8.png" alt="DREDD Avatar" />
                        </div>
                    `
                        : ""
                    }
                    <div class="message-content">
                        <div class="message-bubble ${bubbleClass} ${type}">
                            <p>${this.formatMessage(content)}</p>
                        </div>
                    </div>
                    ${
                      sender === "user"
                        ? `
                        <div class="message-avatar">👤</div>
                    `
                        : ""
                    }
                </div>
            `;

      $("#dredd-chat-messages").append(messageHtml);
      this.scrollToBottom();

      // Store in history
      this.messageHistory.push({
        content: content,
        sender: sender,
        type: type,
        timestamp: timestamp,
      });
    }

    formatMessage(message) {
      // Format DREDD messages with emphasis and styling
      return message
        .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
        .replace(/\*(.*?)\*/g, "<em>$1</em>")
        .replace(
          /GUILTY|SCAM|DANGER|CRIMINAL/gi,
          '<span class="danger">$&</span>'
        )
        .replace(
          /LEGIT|SAFE|APPROVED|INNOCENT/gi,
          '<span class="safe">$&</span>'
        )
        .replace(/I AM THE LAW/gi, '<span class="law">$&</span>')
        .replace(
          /CAUTION|WARNING|SUSPICIOUS/gi,
          '<span class="warning">$&</span>'
        );
    }

    showTypingIndicator() {
      $("#dredd-typing").show();
      this.scrollToBottom();
    }

    hideTypingIndicator() {
      $("#dredd-typing").hide();
    }

    showLoadingOverlay() {
      $("#dredd-loading").fadeIn(300);
    }

    hideLoadingOverlay() {
      $("#dredd-loading").fadeOut(300);
    }

    startProgressAnimation() {
      let progress = 0;
      let messageIndex = 0;

      const progressInterval = setInterval(() => {
        if (progress >= 90) {
          clearInterval(progressInterval);
          return;
        }

        progress += Math.random() * 15;
        $("#progress-fill").css("width", Math.min(progress, 90) + "%");

        // Change loading message
        if (Math.random() > 0.7) {
          messageIndex = (messageIndex + 1) % this.loadingMessages.length;
          $("#loading-text").text(this.loadingMessages[messageIndex]);
        }
      }, 800);
    }

    displayAnalysisResults(analysisData) {
      // This could be expanded to show detailed analysis results
      console.log("Analysis completed:", analysisData);
    }

    scrollToBottom() {
      const $messages = $("#dredd-chat-messages");
      $messages.scrollTop($messages[0].scrollHeight);
    }

    togglePromotions() {
      const $sidebar = $("#dredd-promotions");
      if ($sidebar.length) {
        $sidebar.toggleClass("open");

        // Debug logging
        if (window.console) {
          console.log("DREDD: Promotion sidebar toggled");
          console.log(
            "DREDD: Sidebar is now",
            $sidebar.hasClass("open") ? "OPEN" : "CLOSED"
          );
          console.log(
            "DREDD: Sidebar content length:",
            $sidebar.find(".promotions-content").html().length,
            "chars"
          );
          console.log(
            "DREDD: Sidebar HTML:",
            $sidebar.find(".promotions-content").html()
          );
        }
      } else {
        console.error("DREDD: Promotion sidebar element not found!");
      }
    }

    closePromotions() {
      const $sidebar = $("#dredd-promotions");
      if ($sidebar.length) {
        $sidebar.removeClass("open");
      }
    }

    analyzePromoted(e) {
      const $btn = $(e.currentTarget);
      const contract = $btn.data("contract");
      const chain = $btn.data("chain");

      if (contract && chain) {
        const message = `Analyze ${contract} on ${chain}`;
        $("#dredd-message-input").val(message);
        this.sendMessage();
        this.closePromotions();
      }
    }

    loadPromotions() {
      // Track promotion impressions
      $(".promotion-card").each(function () {
        const promotionId = $(this).data("promotion-id");
        if (promotionId) {
          // Track impression via AJAX
          $.ajax({
            url: dredd_ajax.ajax_url,
            type: "POST",
            data: {
              action: "dredd_track_impression",
              promotion_id: promotionId,
              nonce: dredd_ajax.nonce,
            },
          });
        }
      });

      // Track clicks
      $(".analyze-promoted, .read-more").on("click", function () {
        const promotionId = $(this)
          .closest(".promotion-card")
          .data("promotion-id");
        if (promotionId) {
          $.ajax({
            url: dredd_ajax.ajax_url,
            type: "POST",
            data: {
              action: "dredd_track_click",
              promotion_id: promotionId,
              nonce: dredd_ajax.nonce,
            },
          });
        }
      });
    }

    checkUserCredits() {
      return new Promise((resolve) => {
        if (!dredd_ajax.is_logged_in) {
          resolve(0);
          return;
        }

        $.ajax({
          url: dredd_ajax.ajax_url,
          type: "POST",
          data: {
            action: "dredd_get_user_data",
            nonce: dredd_ajax.nonce,
          },
          success: (response) => {
            if (response.success && response.data.tokens) {
              resolve(response.data.tokens.token_balance || 0);
            } else {
              resolve(0);
            }
          },
          error: () => resolve(0),
        });
      });
    }

    updateCreditsDisplay(credits) {
      $(".credits-count").text(credits);

      // Update dashboard if it's open
      if ($("#dashboard-content:visible").length > 0) {
        // Update dashboard stats if displayed
        $(".stat-card .stat-value").first().text(credits);
      }
    }

    // User Dashboard Functions
    viewAnalysis(e) {
      const analysisId = $(e.currentTarget).data("id");

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_get_analysis_detail",
          analysis_id: analysisId,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            $("#analysis-detail-content").html(response.data.html);
            $("#analysis-modal").fadeIn(300);
          }
        },
      });
    }

    reanalyzeToken(e) {
      const $btn = $(e.currentTarget);
      const contract = $btn.data("contract");
      const chain = $btn.data("chain");

      // Redirect to main chat with pre-filled message
      const message = `Re-analyze ${contract} on ${chain}`;
      window.location.href = `${
        window.location.origin
      }?dredd_message=${encodeURIComponent(message)}`;
    }

    openPaymentPanel(e) {
      e.preventDefault();
      this.showPaymentPanel();
    }

    exportUserData(e) {
      e.preventDefault();

      if (confirm("Export all your DREDD AI data? This may take a moment.")) {
        window.location.href =
          dredd_ajax.ajax_url +
          "?action=dredd_export_user_data&nonce=" +
          dredd_ajax.nonce;
      }
    }

    closeModal() {
      $(".dredd-modal").fadeOut(300);
    }

    // User Dashboard Modal Functions
    showDashboardModal() {
      console.log("Opening DREDD Dashboard modal");
      this.$dashboardModal.show(); // Use cached element for instant display
      this.$body.addClass("modal-open"); // Prevent background scrolling
      this.loadDashboardData();

      // Add keyboard support
      $(document).on("keydown.dashboard", (e) => {
        if (e.key === "Escape") {
          this.closeDashboardModal();
        }
      });
    }

    closeDashboardModal() {
      this.$dashboardModal.hide(); // Use cached element for instant hide
      this.$body.removeClass("modal-open");
      $(document).off("keydown.dashboard"); // Remove keyboard listener
    }

    loadDashboardData() {
      $("#dashboard-loading").show();
      $("#dashboard-content").hide();

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_get_user_dashboard_data",
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.renderDashboard(response.data);
          } else {
            this.showDashboardError("Failed to load dashboard data");
          }
        },
        error: () => {
          this.showDashboardError("Connection error. Please try again.");
        },
      });
    }

    renderDashboard(data) {
      const dashboardHtml = `
                <div class="dashboard-nav">
                    <button class="nav-btn active" data-section="overview">📊 Overview</button>
                    <button class="nav-btn" data-section="history">📈 History</button>
                    <button class="nav-btn" data-section="promotions">🚀 Promotions</button>
                    <button class="nav-btn" data-section="settings">⚙️ Settings</button>
                </div>
                
                <div class="dashboard-sections">
                    <!-- Overview Section -->
                    <div class="dashboard-section active" id="overview-section">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">💰</div>
                                <div class="stat-value">${
                                  data.user.credits || 0
                                }</div>
                                <div class="stat-label">Credits</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">📈</div>
                                <div class="stat-value">${
                                  data.stats.total_analyses || 0
                                }</div>
                                <div class="stat-label">Analyses</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">🚨</div>
                                <div class="stat-value">${
                                  data.stats.scams_detected || 0
                                }</div>
                                <div class="stat-label">Scams Detected</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">💀</div>
                                <div class="stat-value">${
                                  data.stats.psycho_analyses || 0
                                }</div>
                                <div class="stat-label">Psycho Mode</div>
                            </div>
                        </div>
                        
                        <div class="quick-actions-grid">
                            <button class="action-card dashboard-buy-credits">
                                <div class="action-icon">💳</div>
                                <div class="action-title">Buy Credits</div>
                                <div class="action-desc">Purchase analysis credits</div>
                            </button>
                            <button class="action-card dashboard-export-data">
                                <div class="action-icon">📁</div>
                                <div class="action-title">Export Data</div>
                                <div class="action-desc">Download your data</div>
                            </button>
                            <button class="action-card" onclick="window.open('${
                              window.location.origin
                            }', '_blank')">
                                <div class="action-icon">⚡</div>
                                <div class="action-title">New Analysis</div>
                                <div class="action-desc">Analyze tokens</div>
                            </button>
                        </div>
                    </div>
                    
                    <!-- History Section -->
                    <div class="dashboard-section" id="history-section">
                        <div class="history-header">
                            <h3>Analysis History</h3>
                            <div class="history-filters">
                                <select class="filter-select" id="mode-filter">
                                    <option value="">All Modes</option>
                                    <option value="standard">Standard</option>
                                    <option value="psycho">Psycho</option>
                                </select>
                                <select class="filter-select" id="verdict-filter">
                                    <option value="">All Verdicts</option>
                                    <option value="scam">Scam</option>
                                    <option value="legit">Legit</option>
                                    <option value="caution">Caution</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="history-list">
                            ${this.renderHistoryItems(data.history || [])}
                        </div>
                    </div>
                    
                    <!-- Promotions Section -->
                    <div class="dashboard-section" id="promotions-section">
                        <h3>Submit Token for Promotion</h3>
                        <div class="promotion-form">
                            <form class="promotion-submit-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Token Name</label>
                                        <input type="text" name="token_name" required placeholder="Enter token name">
                                    </div>
                                    <div class="form-group">
                                        <label>Contract Address</label>
                                        <input type="text" name="contract_address" required placeholder="0x...">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Chain</label>
                                        <select name="chain" required>
                                            <option value="ethereum">Ethereum</option>
                                            <option value="bsc">BSC</option>
                                            <option value="polygon">Polygon</option>
                                            <option value="arbitrum">Arbitrum</option>
                                            <option value="pulsechain">PulseChain</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Website URL</label>
                                        <input type="url" name="website" placeholder="https://...">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" rows="3" placeholder="Brief description of your token..."></textarea>
                                </div>
                                <button type="submit" class="submit-promotion-btn dashboard-submit-promotion">
                                    🚀 Submit for Promotion
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Settings Section -->
                    <div class="dashboard-section" id="settings-section">
                        <div class="settings-tabs">
                            <button class="settings-tab active" data-tab="profile">Profile</button>
                            <button class="settings-tab" data-tab="security">Security</button>
                        </div>
                        
                        <div class="settings-content">
                            <!-- Profile Settings -->
                            <div class="settings-panel active" id="profile-panel">
                                <h4>Profile Settings</h4>
                                <form class="dashboard-settings-form">
                                    <div class="form-group">
                                        <label>Display Name</label>
                                        <input type="text" name="display_name" value="${
                                          data.user.display_name || ""
                                        }" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="email" value="${
                                          data.user.email || ""
                                        }" required>
                                    </div>
                                    <button type="submit" class="save-settings-btn">
                                        💾 Save Profile
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Security Settings -->
                            <div class="settings-panel" id="security-panel">
                                <h4>Change Password</h4>
                                <form class="dashboard-password-form">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <div class="password-input-container">
                                            <input type="password" name="current_password" required>
                                            <button type="button" class="password-toggle" data-target="current_password">
                                                <span class="eye-icon">👁️</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <div class="password-input-container">
                                            <input type="password" name="new_password" required minlength="6">
                                            <button type="button" class="password-toggle" data-target="new_password">
                                                <span class="eye-icon">👁️</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <div class="password-input-container">
                                            <input type="password" name="confirm_password" required minlength="6">
                                            <button type="button" class="password-toggle" data-target="confirm_password">
                                                <span class="eye-icon">👁️</span>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="submit" class="save-password-btn">
                                        🔒 Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      $("#dashboard-content").html(dashboardHtml).show();
      $("#dashboard-loading").hide();

      // Bind dashboard navigation
      this.bindDashboardNavigation();
    }

    renderHistoryItems(history) {
      if (!history.length) {
        return `
                    <div class="no-history">
                        <div class="no-history-icon">⚖️</div>
                        <h4>No analyses yet</h4>
                        <p>Start investigating tokens to see your history here!</p>
                    </div>
                `;
      }

      return history
        .map(
          (item) => `
                <div class="history-item">
                    <div class="history-token">
                        <div class="token-name">${
                          item.token_name || "Unknown Token"
                        }</div>
                        <div class="token-address">${
                          item.contract_address
                            ? item.contract_address.substring(0, 10) + "..."
                            : "N/A"
                        }</div>
                    </div>
                    <div class="history-details">
                        <span class="chain-badge">${item.chain}</span>
                        <span class="mode-badge ${item.mode}">${
            item.mode
          }</span>
                        <span class="verdict-badge ${item.verdict}">${
            item.verdict
          }</span>
                    </div>
                    <div class="history-meta">
                        <div class="history-date">${new Date(
                          item.created_at
                        ).toLocaleDateString()}</div>
                        <div class="history-cost">${item.token_cost} 💰</div>
                    </div>
                    <div class="history-actions">
                        <button class="history-btn dashboard-view-analysis" data-id="${
                          item.analysis_id
                        }">🔍 View</button>
                        <button class="history-btn dashboard-reanalyze" data-contract="${
                          item.contract_address
                        }" data-chain="${item.chain}">♾️ Re-analyze</button>
                    </div>
                </div>
            `
        )
        .join("");
    }

    bindDashboardNavigation() {
      // Navigation between sections
      $(document).on("click", ".nav-btn", function () {
        const section = $(this).data("section");

        $(".nav-btn").removeClass("active");
        $(this).addClass("active");

        $(".dashboard-section").removeClass("active");
        $(`#${section}-section`).addClass("active");
      });

      // Settings tabs
      $(document).on("click", ".settings-tab", function () {
        const tab = $(this).data("tab");

        $(".settings-tab").removeClass("active");
        $(this).addClass("active");

        $(".settings-panel").removeClass("active");
        $(`#${tab}-panel`).addClass("active");
      });
    }

    showDashboardError(message) {
      $("#dashboard-loading").hide();
      $("#dashboard-content")
        .html(
          `
                <div class="dashboard-error">
                    <div class="error-icon">⚠️</div>
                    <h4>Error Loading Dashboard</h4>
                    <p>${message}</p>
                    <button class="retry-btn" onclick="window.dreddChat.loadDashboardData()">🔄 Retry</button>
                </div>
            `
        )
        .show();
    }

    submitPromotion(e) {
      e.preventDefault();

      const $form = $(e.target).closest("form");
      const formData = new FormData($form[0]);
      const $submitBtn = $form.find(".dashboard-submit-promotion");

      $submitBtn.text("🔄 Submitting...").prop("disabled", true);

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_submit_promotion",
          nonce: dredd_ajax.nonce,
          token_name: formData.get("token_name"),
          contract_address: formData.get("contract_address"),
          chain: formData.get("chain"),
          website: formData.get("website"),
          description: formData.get("description"),
        },
        success: (response) => {
          if (response.success) {
            this.showMessage(
              "Promotion submitted successfully! It will be reviewed by our team.",
              "success"
            );
            $form[0].reset();
          } else {
            this.showMessage(
              "Failed to submit promotion: " + response.data,
              "error"
            );
          }
        },
        error: () => {
          this.showMessage("Network error. Please try again.", "error");
        },
        complete: () => {
          $submitBtn.text("🚀 Submit for Promotion").prop("disabled", false);
        },
      });
    }

    updateUserSettings(e) {
      e.preventDefault();

      const $form = $(e.target);
      const formData = new FormData($form[0]);
      const $submitBtn = $form.find(".save-settings-btn");

      $submitBtn.text("🔄 Saving...").prop("disabled", true);

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_update_user_settings",
          nonce: dredd_ajax.nonce,
          display_name: formData.get("display_name"),
          email: formData.get("email"),
        },
        success: (response) => {
          if (response.success) {
            this.showMessage("Profile updated successfully!", "success");
            // Update header display name
            $(".user-name").text(formData.get("display_name"));
          } else {
            this.showMessage(
              "Failed to update profile: " + response.data,
              "error"
            );
          }
        },
        error: () => {
          this.showMessage("Network error. Please try again.", "error");
        },
        complete: () => {
          $submitBtn.text("💾 Save Profile").prop("disabled", false);
        },
      });
    }

    updateUserPassword(e) {
      e.preventDefault();

      const $form = $(e.target);
      const formData = new FormData($form[0]);
      const $submitBtn = $form.find(".save-password-btn");

      // Validate passwords match
      if (formData.get("new_password") !== formData.get("confirm_password")) {
        this.showMessage("New passwords do not match!", "error");
        return;
      }

      $submitBtn.text("🔄 Updating...").prop("disabled", true);

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_update_user_password",
          nonce: dredd_ajax.nonce,
          current_password: formData.get("current_password"),
          new_password: formData.get("new_password"),
        },
        success: (response) => {
          if (response.success) {
            this.showMessage("Password updated successfully!", "success");
            $form[0].reset();
          } else {
            this.showMessage(
              "Failed to update password: " + response.data,
              "error"
            );
          }
        },
        error: () => {
          this.showMessage("Network error. Please try again.", "error");
        },
        complete: () => {
          $submitBtn.text("🔒 Update Password").prop("disabled", false);
        },
      });
    }

    // Payment Processing
    initStripe() {
      if (typeof Stripe === "undefined") {
        console.warn("Stripe.js not loaded");
        $("#stripe-submit")
          .prop("disabled", true)
          .text("STRIPE NOT CONFIGURED");
        return;
      }

      if (!dredd_ajax.stripe_publishable_key) {
        console.warn("Stripe publishable key not configured");
        $("#stripe-submit")
          .prop("disabled", true)
          .text("STRIPE NOT CONFIGURED");
        return;
      }

      this.stripe = Stripe(dredd_ajax.stripe_publishable_key);
      this.elements = this.stripe.elements();

      // Create card element
      this.cardElement = this.elements.create("card", {
        style: {
          base: {
            fontSize: "16px",
            color: "#c0c0c0",
            "::placeholder": {
              color: "#666",
            },
          },
        },
      });

      this.cardElement.mount("#stripe-elements");

      // Handle form submission
      $("#stripe-submit").on("click", () => this.processStripePayment());
    }

    processStripePayment() {
      if (!this.selectedPackage) {
        alert("Please select a token package first.");
        return;
      }

      const $btn = $("#stripe-submit");
      $btn.prop("disabled", true).text("PROCESSING...");

      // Create payment intent
      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_create_payment_intent",
          package: this.selectedPackage,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.stripe
              .confirmCardPayment(response.data.client_secret, {
                payment_method: {
                  card: this.cardElement,
                },
              })
              .then((result) => {
                if (result.error) {
                  this.handlePaymentError(result.error.message);
                } else {
                  this.handlePaymentSuccess(result.paymentIntent);
                }
                $btn.prop("disabled", false).text("PURCHASE TOKENS");
              });
          } else {
            this.handlePaymentError(response.data);
            $btn.prop("disabled", false).text("PURCHASE TOKENS");
          }
        },
        error: () => {
          this.handlePaymentError("Payment processing failed");
          $btn.prop("disabled", false).text("PURCHASE TOKENS");
        },
      });
    }

    handlePaymentSuccess(paymentIntent) {
      this.closePaymentPanel();
      this.addMessage(
        `✅ Payment successful! ${this.selectedPackage.tokens} tokens added to your account.`,
        "dredd",
        "success"
      );

      // Update credits display
      this.checkUserCredits().then((credits) => {
        this.updateCreditsDisplay(credits);
      });

      // Activate psycho mode if that was the intent
      if (this.currentMode !== "psycho") {
        this.activateMode("psycho");
      }
    }

    handlePaymentError(error) {
      this.addMessage(`❌ Payment failed: ${error}`, "dredd", "error");
    }

    // Web3 Integration
    initWeb3() {
      this.web3 = null;
      this.userAccount = null;

      $("#connect-metamask").on("click", () => this.connectMetaMask());
      $("#connect-walletconnect").on("click", () =>
        this.connectWalletConnect()
      );

      $(".crypto-option").on("click", (e) => this.selectCrypto(e));
    }

    async connectMetaMask() {
      if (typeof window.ethereum === "undefined") {
        alert(
          "MetaMask is not installed. Please install MetaMask to continue."
        );
        return;
      }

      try {
        const accounts = await window.ethereum.request({
          method: "eth_requestAccounts",
        });
        this.userAccount = accounts[0];
        this.web3 = window.ethereum;

        $("#connect-metamask").text(
          "Connected: " +
            this.userAccount.substr(0, 6) +
            "..." +
            this.userAccount.substr(-4)
        );

        // Enable crypto payment options
        $(".crypto-option").prop("disabled", false);
      } catch (error) {
        console.error("MetaMask connection failed:", error);
        alert("Failed to connect to MetaMask");
      }
    }

    async connectWalletConnect() {
      // WalletConnect integration would go here
      alert("WalletConnect integration coming soon!");
    }

    selectCrypto(e) {
      if (!this.userAccount) {
        alert("Please connect your wallet first");
        return;
      }

      const currency = $(e.currentTarget).data("currency");
      const chain = $("#crypto-chain").val();

      if (!this.selectedPackage) {
        alert("Please select a token package first");
        return;
      }

      this.processCryptoPayment(currency, chain);
    }

    async processCryptoPayment(currency, chain) {
      try {
        // Get payment details from backend
        const response = await $.ajax({
          url: dredd_ajax.ajax_url,
          type: "POST",
          data: {
            action: "dredd_create_crypto_payment",
            currency: currency,
            chain: chain,
            package: this.selectedPackage,
            user_account: this.userAccount,
            nonce: dredd_ajax.nonce,
          },
        });

        if (response.success) {
          const paymentData = response.data;

          // Execute the transaction
          const txHash = await this.sendTransaction(paymentData);

          // Verify payment on backend
          this.verifyCryptoPayment(txHash, paymentData.payment_id);
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        console.error("Crypto payment failed:", error);
        alert("Crypto payment failed: " + error.message);
      }
    }

    async sendTransaction(paymentData) {
      const transactionParameters = {
        to: paymentData.to_address,
        from: this.userAccount,
        value: paymentData.value,
        data: paymentData.data || "0x",
      };

      const txHash = await window.ethereum.request({
        method: "eth_sendTransaction",
        params: [transactionParameters],
      });

      return txHash;
    }

    verifyCryptoPayment(txHash, paymentId) {
      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_verify_crypto_payment",
          tx_hash: txHash,
          payment_id: paymentId,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.handlePaymentSuccess({ id: txHash });
          } else {
            this.handlePaymentError(response.data);
          }
        },
        error: () => {
          this.handlePaymentError("Payment verification failed");
        },
      });
    }

    // New Payment Modal Methods
    selectPaymentMethod(e) {
      const $card = $(e.currentTarget);
      const method = $card.data("method");

      console.log("Payment method clicked:", method); // Debug log

      // Remove previous selections
      $(".payment-method-card").removeClass("selected selecting");

      // Add selection immediately without animation delay
      $card.addClass("selected");

      this.selectedPaymentMethod = method;

      // Minimal visual feedback
      $card.css("transform", "scale(0.98)");
      setTimeout(() => {
        $card.css("transform", "");
      }, 100); // Reduced from 400ms to 100ms
    }

    selectAmount(e) {
      const $option = $(e.currentTarget);
      const amount = parseFloat($option.data("amount"));

      $(".amount-option").removeClass("selected");
      $option.addClass("selected");

      this.selectedAmount = amount;
      $("#amount-slider").val(amount);
      $("#custom-amount-value").text(amount.toFixed(2));
    }

    updateCustomAmount(e) {
      const amount = parseFloat($(e.currentTarget).val());
      this.selectedAmount = amount;
      $("#custom-amount-value").text(amount.toFixed(2));

      // Deselect preset amounts
      $(".amount-option").removeClass("selected");
    }

    goBackStep() {
      if (this.currentPaymentStep > 1) {
        this.showPaymentStep(this.currentPaymentStep - 1);
      }
    }

    goNextStep() {
      console.log("goNextStep called, current step:", this.currentPaymentStep);
      console.log("Selected payment method:", this.selectedPaymentMethod);

      if (this.currentPaymentStep === 1) {
        if (!this.selectedPaymentMethod) {
          alert("Please select a payment method first");
          return;
        }
        console.log("Moving to step 2");
        this.showPaymentStep(2);
      } else if (this.currentPaymentStep === 2) {
        if (!this.selectedAmount || this.selectedAmount < 3) {
          alert("Please enter an amount (minimum $3.00)");
          return;
        }
        console.log("Moving to step 3, setting up payment form");
        this.setupPaymentForm();
        this.showPaymentStep(3);
      }
    }

    setupPaymentForm() {
      // Hide all payment forms
      $(".payment-form").hide();

      // Update payment summary
      $(".payment-amount").text("$" + this.selectedAmount.toFixed(2));

      if (this.selectedPaymentMethod === "stripe") {
        this.setupStripeForm();
      } else if (this.selectedPaymentMethod === "pulsechain") {
        this.setupPulseChainForm();
      } else {
        this.setupCryptoForm();
      }
    }

    setupStripeForm() {
      $("#stripe-payment-form").show();

      // Calculate credits (assuming $1 = 10 credits)
      const credits = Math.floor(this.selectedAmount * 10);
      $(".payment-credits").text(credits);

      // Initialize Stripe elements if not already done
      this.initStripeElements();
    }

    setupCryptoForm() {
      $("#crypto-payment-form").show();
      $(".payment-currency").text(this.selectedPaymentMethod.toUpperCase());

      // Ensure QR container exists
      setTimeout(() => {
        const $qrCheck = $("#payment-qr-code");
        console.log("QR container check after form setup:", $qrCheck.length);
        if ($qrCheck.length === 0) {
          console.error("QR container not found! Adding manually...");
          $(".qr-code-container").html('<div id="payment-qr-code"></div>');
        }
      }, 50);

      // Create payment via NOWPayments
      this.createCryptoPayment();
    }

    setupPulseChainForm() {
      $("#pulsechain-payment-form").show();
    }

    initStripeElements() {
      if (!this.stripeCardElement) {
        if (
          typeof Stripe !== "undefined" &&
          dredd_ajax.stripe_publishable_key
        ) {
          this.stripe = Stripe(dredd_ajax.stripe_publishable_key);
          this.elements = this.stripe.elements();

          this.stripeCardElement = this.elements.create("card", {
            style: {
              base: {
                fontSize: "14px",
                color: "#0a0a0a",
                fontWeight: "500",
                "::placeholder": {
                  color: "#666666",
                },
                iconColor: "#0a0a0a",
              },
              invalid: {
                color: "#ff0000",
                iconColor: "#ff0000",
              },
            },
            hidePostalCode: true,
          });

          this.stripeCardElement.mount("#stripe-card-element");

          // Handle errors
          this.stripeCardElement.on("change", (event) => {
            const displayError = document.getElementById("stripe-card-errors");
            if (event.error) {
              displayError.textContent = event.error.message;
            } else {
              displayError.textContent = "";
            }
          });
        } else {
          // Show demo message if Stripe not configured
          $("#stripe-card-element").html(`
                        <div style="padding: 20px; text-align: center; color: #c0c0c0; border: 2px dashed #666666; border-radius: 6px;">
                            <div style="font-size: 14px; margin-bottom: 8px;">💳 Demo Mode</div>
                            <div style="font-size: 12px;">Configure Stripe keys in admin to enable</div>
                        </div>
                    `);
        }
      }
    }

    processStripePayment() {
      if (!this.stripe || !this.stripeCardElement) {
        alert("Stripe not properly initialized");
        return;
      }

      const $btn = $("#stripe-submit-btn");
      $btn.prop("disabled", true).text("Processing...");

      // Create payment intent
      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_create_payment_intent",
          amount: this.selectedAmount,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.stripe
              .confirmCardPayment(response.data.client_secret, {
                payment_method: {
                  card: this.stripeCardElement,
                },
              })
              .then((result) => {
                if (result.error) {
                  alert("Payment failed: " + result.error.message);
                } else {
                  this.handlePaymentSuccess("stripe");
                }
                $btn.prop("disabled", false).text("Complete Payment");
              });
          } else {
            alert("Payment setup failed: " + response.data);
            $btn.prop("disabled", false).text("Complete Payment");
          }
        },
        error: () => {
          alert("Payment processing failed");
          $btn.prop("disabled", false).text("Complete Payment");
        },
      });
    }

    createCryptoPayment() {
      // For demo purposes - show demo data if API not configured
      console.log(
        "Creating crypto payment for:",
        this.selectedPaymentMethod,
        "Amount:",
        this.selectedAmount
      );

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_create_nowpayments_payment",
          amount: this.selectedAmount,
          currency: this.selectedPaymentMethod,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.displayCryptoPaymentInfo(response.data);
            this.startPaymentTimer();
          } else {
            // 🚨 BACKEND ERROR - SHOW SPECIFIC MESSAGE
            console.error("Backend payment error:", response);
            const errorMsg = response.data || "Payment setup failed";

            // Check if it's address configuration error
            if (
              errorMsg.includes("address") ||
              errorMsg.includes("configured")
            ) {
              alert("🚨 Address Configuration Error: " + errorMsg);
              $("#payment-address").val("ERROR: " + errorMsg);
              $("#payment-address").css({
                background: "linear-gradient(135deg, #c62828, #d32f2f)",
                color: "#ffffff",
                border: "2px solid #f44336",
              });
            } else {
              alert("🚨 Payment Error: " + errorMsg);
            }

            // 🚨 NO DEMO/TEST SYSTEM - ONLY SHOW REAL ERRORS
            $("#payment-address").val("PAYMENT SETUP FAILED");
            $("#crypto-amount").val("ERROR");
            console.error(
              "Payment setup failed - no fallback to demo/test system"
            );
          }
        },
        error: (xhr, status, error) => {
          // 🚨 AJAX ERROR
          console.error("AJAX payment error:", xhr, status, error);
          alert(
            "🚨 Connection Error: Could not connect to payment system. Please try again."
          );

          $("#payment-address").val("ERROR: Connection failed");
          $("#payment-address").css({
            background: "linear-gradient(135deg, #c62828, #d32f2f)",
            color: "#ffffff",
            border: "2px solid #f44336",
          });
        },
      });
    }

    // 🚨 DEMO/TEST SYSTEM COMPLETELY REMOVED
    // Only live payments allowed

    // 🚨 DEMO ADDRESS SYSTEM COMPLETELY REMOVED

    // 🚨 DEMO AMOUNT SYSTEM COMPLETELY REMOVED

    displayCryptoPaymentInfo(paymentData) {
      console.log("Displaying crypto payment info:", paymentData);

      // 🚨 VALIDATE ADDRESS BEFORE DISPLAYING
      if (
        !paymentData.payment_address ||
        paymentData.payment_address === null ||
        paymentData.payment_address.trim() === ""
      ) {
        console.error("CRITICAL ERROR: No payment address provided!");
        console.error("Payment data:", paymentData);

        // Show error in address field
        $("#payment-address").val("ERROR: No address configured!");
        $("#payment-address").css({
          background: "linear-gradient(135deg, #c62828, #d32f2f)",
          color: "#ffffff",
          border: "2px solid #f44336",
          "font-weight": "bold",
        });

        // Show error message
        alert(
          "🚨 Payment Error: No wallet address configured for " +
            (paymentData.currency || "this currency") +
            ". Please contact administrator."
        );
        return;
      }

      // ✅ ADDRESS IS VALID - DISPLAY IT
      $("#payment-address").val(paymentData.payment_address);
      $("#payment-address").css({
        background: "linear-gradient(135deg, #1a252f, #2c3e50)",
        color: "#ffffff",
        border: "2px solid var(--primary-cyan, #00bcd4)",
        "font-weight": "bold",
      });

      $("#crypto-amount").val(
        paymentData.payment_amount + " " + paymentData.currency
      );

      console.log(
        "✅ Address successfully displayed:",
        paymentData.payment_address
      );

      // Display QR code from API response
      const $qrContainer = $("#payment-qr-code");
      console.log("QR Container found:", $qrContainer.length);
      console.log("Payment data QR code:", paymentData.qr_code);

      $qrContainer.empty();

      // If API returns QR code, display it
      if (paymentData.qr_code) {
        console.log("Displaying QR code from API");
        $qrContainer.html(`<img src="${paymentData.qr_code}" alt="QR Code">`);
      } else {
        console.log("No QR code from API, generating demo QR");
        // Generate demo QR code for demo mode
        const demoQrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(
          paymentData.payment_address
        )}`;
        $qrContainer.html(`<img src="${demoQrUrl}" alt="Demo QR Code">`);
      }

      this.paymentId = paymentData.payment_id;
    }

    startPaymentTimer() {
      let timeLeft = 30 * 60; // 30 minutes

      this.paymentTimer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        $("#payment-timer").text(
          `${minutes}:${seconds.toString().padStart(2, "0")}`
        );

        timeLeft--;

        if (timeLeft < 0) {
          clearInterval(this.paymentTimer);
          alert("Payment timer expired");
          this.closePaymentModal();
        }
      }, 1000);

      // Check payment status periodically
      this.statusChecker = setInterval(() => {
        this.checkPaymentStatus();
      }, 10000);
    }

    checkPaymentStatus() {
      if (!this.paymentId) return;

      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_check_nowpayments_status",
          payment_id: this.paymentId,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success && response.data.status === "finished") {
            clearInterval(this.paymentTimer);
            clearInterval(this.statusChecker);
            this.handlePaymentSuccess("crypto");
          }
        },
      });
    }

    connectPulseChainWallet() {
      // PulseChain wallet connection logic
      if (typeof window.ethereum === "undefined") {
        alert("Please install MetaMask or a compatible wallet");
        return;
      }

      window.ethereum
        .request({
          method: "wallet_switchEthereumChain",
          params: [{ chainId: "0x171" }], // PulseChain chain ID
        })
        .then(() => {
          return window.ethereum.request({ method: "eth_requestAccounts" });
        })
        .then((accounts) => {
          this.pulseChainAccount = accounts[0];
          $(".connected-address").text(accounts[0]);
          $("#pulsechain-wallet-info").show();
          $("#pulsechain-submit-btn").show();
          $("#connect-pulsechain-wallet").hide();
        })
        .catch((error) => {
          console.error("PulseChain connection failed:", error);
          alert("Failed to connect to PulseChain wallet");
        });
    }

    processPulseChainPayment() {
      if (!this.pulseChainAccount) {
        alert("Please connect your PulseChain wallet first");
        return;
      }

      const $btn = $("#pulsechain-submit-btn");
      $btn.prop("disabled", true).text("Processing...");

      // Create payment on backend
      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_create_pulsechain_payment",
          amount: this.selectedAmount,
          wallet_address: this.pulseChainAccount,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.sendPulseChainTransaction(response.data)
              .then((txHash) => {
                // Verify payment
                this.verifyPulseChainPayment(txHash, response.data.payment_id);
              })
              .catch((error) => {
                alert("Transaction failed: " + error.message);
                $btn.prop("disabled", false).text("Send Payment");
              });
          } else {
            alert("Payment setup failed: " + response.data);
            $btn.prop("disabled", false).text("Send Payment");
          }
        },
        error: () => {
          alert("Payment setup failed");
          $btn.prop("disabled", false).text("Send Payment");
        },
      });
    }

    async sendPulseChainTransaction(paymentData) {
      const transactionParameters = {
        to: paymentData.to_address,
        from: this.pulseChainAccount,
        value: "0x" + paymentData.amount_wei.toString(16),
        gas: "0x5208", // Standard gas limit for PLS transfer
      };

      const txHash = await window.ethereum.request({
        method: "eth_sendTransaction",
        params: [transactionParameters],
      });

      return txHash;
    }

    verifyPulseChainPayment(txHash, paymentId) {
      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_verify_pulsechain_payment",
          tx_hash: txHash,
          payment_id: paymentId,
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.handlePaymentSuccess("pulsechain");
          } else {
            alert("Payment verification failed: " + response.data);
          }
        },
        error: () => {
          alert("Payment verification failed");
        },
      });
    }

    handlePaymentSuccess(method) {
      this.closePaymentModal();
      this.addMessage(
        `✅ Payment successful via ${method}! Credits added to your account.`,
        "dredd",
        "success"
      );

      // Update credits display in header
      this.checkUserCredits().then((credits) => {
        this.updateCreditsDisplay(credits);

        // Update global state for logged in status
        dredd_ajax.is_logged_in = true;

        // Activate psycho mode if that was the intent
        if (this.currentMode !== "psycho") {
          this.activateMode("psycho");
        }
      });
    }

    copyToClipboard(e) {
      const $btn = $(e.currentTarget);
      const $input = $btn.siblings("input");

      $input.select();
      document.execCommand("copy");

      const originalText = $btn.text();
      $btn.text("Copied!");
      setTimeout(() => {
        $btn.text(originalText);
      }, 2000);
    }

    // Utility Functions
    showMessage(message, type = "info") {
      // Simple notification system
      const notification = $(`
                <div class="dredd-notification ${type}">
                    ${message}
                </div>
            `);

      $("body").append(notification);

      setTimeout(() => {
        notification.fadeOut(300, () => notification.remove());
      }, 5000);
    }

    // Authentication Methods
    showAuthModal(formType = "login") {
      console.log("showAuthModal called with:", formType);
      console.log("Modal element found:", this.$authModal.length);

      // Prevent background scrolling in chat window
      $(".dredd-chat-container").css("overflow", "hidden");

      this.$authModal.show();
      this.switchAuthForm(formType);

      // Initialize reCAPTCHA and input styling immediately
      this.initRecaptcha();

      // Ensure input text is visible after modal opens
      $(
        '.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"]'
      ).css({
        color: "#FFFFFF !important",
        "caret-color": "#00FFFF !important",
        "-webkit-text-fill-color": "#FFFFFF !important",
      });
    }

    initRecaptcha() {
      // Skip if reCAPTCHA not loaded or already initialized
      if (typeof grecaptcha === "undefined" || window.recaptchaInitialized) {
        return;
      }

      // Initialize login reCAPTCHA
      const loginContainer = document.getElementById("login-recaptcha");
      if (loginContainer && !window.loginRecaptchaWidget) {
        try {
          window.loginRecaptchaWidget = grecaptcha.render("login-recaptcha", {
            sitekey: dredd_ajax.recaptcha_site_key,
            theme: "dark",
            size: "compact",
          });
          console.log("Login reCAPTCHA initialized");
        } catch (e) {
          console.error("Failed to initialize login reCAPTCHA:", e);
        }
      }

      // Initialize signup reCAPTCHA
      const signupContainer = document.getElementById("signup-recaptcha");
      if (signupContainer && !window.signupRecaptchaWidget) {
        try {
          window.signupRecaptchaWidget = grecaptcha.render("signup-recaptcha", {
            sitekey: dredd_ajax.recaptcha_site_key,
            theme: "dark",
            size: "compact",
          });
          console.log("Signup reCAPTCHA initialized");
        } catch (e) {
          console.error("Failed to initialize signup reCAPTCHA:", e);
        }
      }

      window.recaptchaInitialized = true;
    }

    closeAuthModal() {
      $("#dredd-auth-modal").fadeOut(300);

      // Restore background scrolling in chat window
      $(".dredd-chat-container").css("overflow", "auto");

      this.clearAuthForms();
    }

    switchAuthForm(formType) {
      $(".auth-form").removeClass("active");
      $(`.${formType}-form`).addClass("active");
    }

    clearAuthForms() {
      $("#dredd-login-form")[0].reset();
      $("#dredd-signup-form")[0].reset();
      $("#dredd-forgot-form")[0].reset();
      $(".auth-form-inner .form-group").removeClass("error");
      $(".error-message").remove();
    }

    toggleUserMenu() {
      $(".user-dropdown").toggle();
    }

    // Legacy togglePassword method removed - now handled by document-level event handler

    handleLogin() {
      const $form = $("#dredd-login-form");
      const $submitBtn = $(".login-submit");

      // Get reCAPTCHA response
      let recaptchaResponse = "";
      if (typeof grecaptcha !== "undefined") {
        recaptchaResponse = grecaptcha.getResponse(window.loginRecaptchaWidget);
      }

      // Get form data
      const formData = {
        action: "dredd_login",
        nonce: dredd_ajax.nonce,
        username: $("#login-username").val(),
        password: $("#login-password").val(),
        remember: $("#login-remember").is(":checked"),
        "g-recaptcha-response": recaptchaResponse,
      };

      // Validate
      if (!formData.username || !formData.password) {
        this.showAuthError("login", "Please fill in all fields");
        return;
      }

      // Show loading state
      $submitBtn
        .prop("disabled", true)
        .html('<span class="btn-icon">⏳</span>AUTHENTICATING...');

      // Send AJAX request
      $.post(dredd_ajax.ajax_url, formData)
        .done((response) => {
          if (response.success) {
            this.showMessage(
              "Login successful! Welcome back, citizen!",
              "success"
            );
            this.closeAuthModal();
            this.handleLoginSuccess(response.data.user);
          } else {
            this.showAuthError("login", response.data || "Login failed");
          }
        })
        .fail(() => {
          this.showAuthError("login", "Connection failed. Please try again.");
        })
        .always(() => {
          $submitBtn
            .prop("disabled", false)
            .html('<span class="btn-icon">⚔️</span>ENTER THE SYSTEM');
        });
    }

    handleSignup() {
      const $form = $("#dredd-signup-form");
      const $submitBtn = $(".signup-submit");

      // Get reCAPTCHA response
      let recaptchaResponse = "";
      if (typeof grecaptcha !== "undefined") {
        recaptchaResponse = grecaptcha.getResponse(
          window.signupRecaptchaWidget
        );
      }

      // Get form data
      const formData = {
        action: "dredd_register",
        nonce: dredd_ajax.nonce,
        username: $("#signup-username").val(),
        email: $("#signup-email").val(),
        password: $("#signup-password").val(),
        confirm_password: $("#signup-confirm-password").val(),
        terms: $("#signup-terms").is(":checked"),
        newsletter: $("#signup-newsletter").is(":checked"),
        "g-recaptcha-response": recaptchaResponse,
      };

      // Client-side validation
      const validation = this.validateSignupForm(formData);
      if (!validation.valid) {
        this.showAuthError("signup", validation.message);
        return;
      }

      // Show loading state
      $submitBtn
        .prop("disabled", true)
        .html('<span class="btn-icon">⏳</span>JOINING FORCE...');

      // Send AJAX request
      $.post(dredd_ajax.ajax_url, formData)
        .done((response) => {
          if (response.success) {
            this.showMessage(
              "Registration successful! Welcome to DREDD AI.",
              "success"
            );
            this.closeAuthModal();
            // Don't auto-login, require email verification
            this.showAuthModal("login");
          } else {
            this.showAuthError(
              "signup",
              response.data || "Registration failed"
            );
          }
        })
        .fail(() => {
          this.showAuthError("signup", "Connection failed. Please try again.");
        })
        .always(() => {
          $submitBtn
            .prop("disabled", false)
            .html('<span class="btn-icon">🔥</span>JOIN THE FORCE');
        });
    }

    handleForgotPassword() {
      const $form = $("#dredd-forgot-form");
      const $submitBtn = $(".forgot-submit");

      // Get form data
      const formData = {
        action: "dredd_forgot_password",
        nonce: dredd_ajax.nonce,
        email: $("#forgot-email").val(),
      };

      // Validate
      if (!formData.email) {
        this.showAuthError("forgot", "Email address is required");
        return;
      }

      if (!this.isValidEmail(formData.email)) {
        this.showAuthError("forgot", "Please enter a valid email address");
        return;
      }

      // Show loading state
      $submitBtn
        .prop("disabled", true)
        .html('<span class="btn-icon">⏳</span>SENDING...');

      // Send AJAX request
      $.post(dredd_ajax.ajax_url, formData)
        .done((response) => {
          if (response.success) {
            this.showMessage(
              "Password reset link has been sent to your email.",
              "success"
            );
            this.switchAuthForm("login");
          } else {
            this.showAuthError("forgot", response.data || "Reset failed");
          }
        })
        .fail(() => {
          this.showAuthError("forgot", "Connection failed. Please try again.");
        })
        .always(() => {
          $submitBtn
            .prop("disabled", false)
            .html('<span class="btn-icon">📨</span>SEND RESET LINK');
        });
    }

    validateSignupForm(formData) {
      if (!formData.username || !formData.email || !formData.password) {
        return { valid: false, message: "All fields are required" };
      }

      if (formData.username.length < 3) {
        return {
          valid: false,
          message: "Username must be at least 3 characters long",
        };
      }

      if (!this.isValidEmail(formData.email)) {
        return { valid: false, message: "Please enter a valid email address" };
      }

      if (formData.password.length < 6) {
        return {
          valid: false,
          message: "Password must be at least 6 characters long",
        };
      }

      if (formData.password !== formData.confirm_password) {
        return { valid: false, message: "Passwords do not match" };
      }

      if (!formData.terms) {
        return {
          valid: false,
          message: "You must agree to the terms of service",
        };
      }

      return { valid: true };
    }

    isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    showAuthError(formType, message) {
      const $form = $(`.${formType}-form`);

      // Remove existing error messages
      $form.find(".error-message").remove();

      // Add new error message
      const $errorDiv = $(`<div class="error-message">${message}</div>`);
      $form.find(".auth-form-inner").prepend($errorDiv);

      // Auto-remove after 5 seconds
      setTimeout(() => {
        $errorDiv.fadeOut(300, () => $errorDiv.remove());
      }, 5000);
    }

    handleLoginSuccess(user) {
      // Update UI to reflect logged-in state
      this.updateUIAfterLogin(user);

      // Check if user was trying to activate psycho mode
      if (this.pendingPsychoActivation) {
        this.pendingPsychoActivation = false;

        // Check if paid mode is enabled and user needs credits
        if (dredd_ajax.paid_mode_enabled === "true") {
          // Check user credits
          this.checkUserCredits().then((credits) => {
            if (credits >= parseInt(dredd_ajax.analysis_cost)) {
              this.activateMode("psycho");
            } else {
              // Show payment modal for psycho mode
              setTimeout(() => {
                this.showPaymentModal();
              }, 1000);
            }
          });
        } else {
          // Paid mode not enabled, activate directly
          this.activateMode("psycho");
        }
        return;
      }

      // Reload the page to update server-side state
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    }

    handleLogout() {
      $.ajax({
        url: dredd_ajax.ajax_url,
        type: "POST",
        data: {
          action: "dredd_logout",
          nonce: dredd_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showMessage("Logged out successfully!", "success");
            // Reload the page to reflect logged-out state
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            this.showMessage("Logout failed: " + response.data, "error");
          }
        },
        error: () => {
          this.showMessage("Logout failed. Please try again.", "error");
        },
      });
    }

    updateUIAfterLogin(user) {
      // Update header to show user info
      const $authButtons = $(".dredd-auth-buttons");
      const $userMenu = $(`
                <div class="dredd-user-menu">
                    <button class="user-menu-btn" title="User Menu">
                        <span class="user-icon">👤</span>
                        <span class="user-name">${user.display_name}</span>
                    </button>
                    <div class="user-dropdown" style="display: none;">
                        <a href="#" class="user-dashboard-link">📊 Dashboard</a>
                        <a href="${window.location.href}" class="logout-link">🚪 Logout</a>
                    </div>
                </div>
                <div class="dredd-credits">
                    <span class="credits-icon">🪙</span>
                    <span class="credits-count">${user.credits}</span>
                </div>
            `);

      $authButtons.replaceWith($userMenu);

      // Re-bind events
      $(".user-menu-btn").on("click", () => this.toggleUserMenu());
    }

    checkForNotifications() {
      // Check URL parameters for notifications
      const urlParams = new URLSearchParams(window.location.search);
      let shouldCleanUrl = false;

      if (urlParams.get("email_verified") === "1") {
        console.log("Email verification success detected");
        this.showMessage(
          "Email verified successfully! Welcome to DREDD AI. You can now log in.",
          "success"
        );
        this.addMessage(
          "✨ Email verified successfully! Welcome to DREDD AI. You can now log in and start analyzing tokens!",
          "dredd",
          "success"
        );
        shouldCleanUrl = true;
      }

      if (urlParams.get("password_reset") === "1") {
        console.log("Password reset success detected");
        this.showMessage(
          "Password reset successfully! You can now log in with your new password.",
          "success"
        );
        this.addMessage(
          "🔑 Password reset successfully! You can now log in with your new password.",
          "dredd",
          "success"
        );
        shouldCleanUrl = true;
      }

      const resetError = urlParams.get("reset_error");
      if (resetError) {
        let errorMsg = "Password reset failed.";
        switch (resetError) {
          case "invalid_link":
            errorMsg = "Invalid password reset link.";
            break;
          case "invalid_or_expired":
            errorMsg = "Password reset link is invalid or expired.";
            break;
        }
        this.showMessage(errorMsg, "error");
        shouldCleanUrl = true;
      }

      // Clean URL parameters after showing notifications
      if (shouldCleanUrl) {
        this.cleanUrlParameters([
          "email_verified",
          "password_reset",
          "reset_error",
        ]);
      }
    }

    cleanUrlParameters(paramsToRemove) {
      const url = new URL(window.location);
      let hasChanges = false;

      paramsToRemove.forEach((param) => {
        if (url.searchParams.has(param)) {
          url.searchParams.delete(param);
          hasChanges = true;
        }
      });

      if (hasChanges) {
        // Update URL without reloading the page
        window.history.replaceState({}, document.title, url.toString());
      }
    }

    fixInputVisibility() {
      // Critical: Strong CSS-based text visibility enforcement
      const inputSelector =
        '.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"]';

      // Create and inject critical CSS styles
      const criticalCSS = `
                <style id="dredd-input-visibility-fix">
                .dredd-auth-modal .auth-form .form-group input[type="text"],
                .dredd-auth-modal .auth-form .form-group input[type="email"],
                .dredd-auth-modal .auth-form .form-group input[type="password"] {
                    color: #FFFFFF !important;
                    -webkit-text-fill-color: #FFFFFF !important;
                    -moz-text-fill-color: #FFFFFF !important;
                    text-fill-color: #FFFFFF !important;
                    caret-color: #00FFFF !important;
                    background: #1a1a1a !important;
                    opacity: 1 !important;
                    visibility: visible !important;
                }
                </style>
            `;

      // Inject critical CSS if not already present
      if ($("#dredd-input-visibility-fix").length === 0) {
        $("head").append(criticalCSS);
      }

      // Force visibility on all existing inputs immediately
      const forceTextVisibility = () => {
        $(inputSelector).each(function () {
          const $input = $(this);
          $input.css({
            color: "#FFFFFF !important",
            "caret-color": "#00FFFF !important",
            "-webkit-text-fill-color": "#FFFFFF !important",
            "-moz-text-fill-color": "#FFFFFF !important",
            background: "#1a1a1a !important",
            opacity: "1 !important",
            visibility: "visible !important",
          });

          // Force re-render
          // const value = $input.val();
          // if (value) {
          //   $input.val("");
          //   setTimeout(() => $input.val(value), 1);
          // }
        });
      };

      // Apply immediately
      forceTextVisibility();

      // Re-apply on focus/input events
      // $(document).on("focus input keyup click", inputSelector, function () {
      //   const $this = $(this);
      //   setTimeout(() => {
      //     $this.css({
      //       color: "#FFFFFF !important",
      //       "caret-color": "#00FFFF !important",
      //       "-webkit-text-fill-color": "#FFFFFF !important",
      //       background: "#222222 !important",
      //     });
      //   }, 10);
      // });

      // Fix for when auth modal is opened - more aggressive
      $(document).on("click", ".login-btn, .signup-btn", function () {
        setTimeout(() => {
          forceTextVisibility();

          // Set up continuous monitoring for modal inputs
          const modalVisibilityInterval = setInterval(() => {
            if ($("#dredd-auth-modal:visible").length > 0) {
              forceTextVisibility();
            } else {
              clearInterval(modalVisibilityInterval);
            }
          }, 500);
        }, 100);
      });

      // Monitor for modal state changes
      const observer = new MutationObserver(() => {
        if ($("#dredd-auth-modal:visible").length > 0) {
          setTimeout(forceTextVisibility, 50);
        }
      });

      if ($("#dredd-auth-modal").length > 0) {
        observer.observe($("#dredd-auth-modal")[0], {
          attributes: true,
          attributeFilter: ["style"],
        });
      }
    }

    // Enhanced Responsive Features
    initResponsiveFeatures() {
      // Add viewport meta tag if not present
      if (!$('meta[name="viewport"]').length) {
        $("head").append(
          '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">'
        );
      }

      // Touch-friendly interactions
      this.initTouchSupport();

      // Mobile-specific adjustments
      this.initMobileAdjustments();

      // Window resize handler
      $(window).on("resize", () => {
        this.handleResize();
      });

      // Initial orientation check
      this.handleResize();
    }

    initTouchSupport() {
      // Better touch events for mobile
      $(document).on(
        "touchend",
        ".auth-btn, .user-menu-btn, .mode-btn, .payment-method-card, .amount-option",
        function (e) {
          e.preventDefault();
          $(this).trigger("click");
        }
      );

      // Prevent double-tap zoom on buttons
      $(document).on(
        "touchend",
        "button, .btn, .auth-btn, .payment-continue-btn",
        function (e) {
          e.preventDefault();
        }
      );

      // Enhanced password toggle for touch
      $(document).on("touchend", ".password-toggle", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).trigger("click");
      });
    }

    initMobileAdjustments() {
      const isMobile = window.innerWidth <= 768;

      if (isMobile) {
        // Add mobile class to body
        $("body").addClass("dredd-mobile");

        // Adjust modal behavior
        this.adjustModalsForMobile();

        // Adjust chat input for mobile keyboards
        this.adjustChatInputForMobile();

        // Improve touch scrolling
        $(".dredd-chat-messages, .auth-modal-content").css({
          "-webkit-overflow-scrolling": "touch",
          "scroll-behavior": "smooth",
        });
      } else {
        $("body").removeClass("dredd-mobile");
      }
    }

    adjustModalsForMobile() {
      // Prevent background scrolling when modal is open
      $(document).on(
        "show.bs.modal",
        ".dredd-auth-modal, .dredd-payment-modal",
        function () {
          $("body").css("overflow", "hidden");
        }
      );

      $(document).on(
        "hide.bs.modal",
        ".dredd-auth-modal, .dredd-payment-modal",
        function () {
          $("body").css("overflow", "auto");
        }
      );

      // Close modal when clicking overlay on mobile
      $(document).on(
        "touchend",
        ".auth-modal-overlay, .payment-modal-overlay",
        function (e) {
          if (e.target === e.currentTarget) {
            $(this)
              .closest(".dredd-auth-modal, .dredd-payment-modal")
              .fadeOut(300);
          }
        }
      );
    }

    adjustChatInputForMobile() {
      const $chatInput = $("#dredd-message-input");

      // Adjust input when virtual keyboard appears
      // $chatInput.on("focus", function () {
      //   if (window.innerWidth <= 768) {
      //     setTimeout(() => {
      //       const chatContainer = $(".dredd-chat-container");
      //       const inputOffset = $chatInput.offset().top;
      //       const windowHeight = $(window).height();

      //       if (inputOffset > windowHeight * 0.5) {
      //         chatContainer.css("transform", "translateY(-100px)");
      //       }
      //     }, 300);
      //   }
      // });

      $chatInput.on("blur", function () {
        if (window.innerWidth <= 768) {
          $(".dredd-chat-container").css("transform", "translateY(0)");
        }
      });
    }

    handleResize() {
      const isMobile = window.innerWidth <= 768;
      const isSmallMobile = window.innerWidth <= 480;

      // Update mobile adjustments
      this.initMobileAdjustments();

      // Adjust modal heights
      if (isMobile) {
        $(".auth-modal-container, .payment-modal-container").css({
          "max-height": "85vh",
          "overflow-y": "auto",
        });
      }

      // Adjust very small screens
      if (isSmallMobile) {
        $(".auth-modal-container, .payment-modal-container").css({
          "max-height": "90vh",
          width: "98%",
        });
      }

      // Force re-render of visible modals
      if (
        $(".dredd-auth-modal:visible, .dredd-payment-modal:visible").length > 0
      ) {
        this.forceModalRerender();
      }
    }

    forceModalRerender() {
      // Trigger reflow to ensure proper modal positioning
      const $visibleModals = $(
        ".dredd-auth-modal:visible, .dredd-payment-modal:visible"
      );
      $visibleModals.each(function () {
        const display = $(this).css("display");
        $(this).css("display", "none");
        setTimeout(() => {
          $(this).css("display", display);
        }, 10);
      });
    }
  }

  // Initialize when document is ready
  $(document).ready(function () {
    console.log("Document ready, initializing DreddChat...");
    window.dreddChat = new DreddChat();

    // Password toggle functionality - Enhanced version with better debugging
    $(document).on("click", ".password-toggle", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $button = $(this);
      const targetId = $button.data("target");
      const $input = $("#" + targetId);
      const $eyeIcon = $button.find(".eye-icon");

      console.log("🔍 Password toggle clicked for:", targetId);
      console.log("🔍 Button element:", $button[0]);
      console.log("🔍 Input found:", $input.length);
      console.log("🔍 Eye icon found:", $eyeIcon.length);
      console.log("🔍 Current input type:", $input.attr("type"));

      if ($input.length === 0) {
        console.error("❌ Password input not found for target:", targetId);
        alert("Error: Password input field not found!");
        return;
      }

      if ($eyeIcon.length === 0) {
        console.error("❌ Eye icon not found in button");
        alert("Error: Eye icon not found!");
        return;
      }

      // Toggle password visibility
      const currentType = $input.attr("type");
      if (currentType === "password") {
        $input.attr("type", "text");
        $eyeIcon.text("🙈"); // See no evil monkey
        console.log("✅ Password now visible");
      } else {
        $input.attr("type", "password");
        $eyeIcon.text("👁️"); // Eye
        console.log("✅ Password now hidden");
      }

      // Visual feedback
      $button.css("transform", "scale(0.9)");
      setTimeout(() => {
        $button.css("transform", "scale(1)");
      }, 100);

      // Force focus back to input to maintain cursor position
      setTimeout(() => {
        $input.focus();
      }, 50);
    });

    // Additional click handler for eye-icon specifically
    $(document).on("click", ".eye-icon", function (e) {
      e.preventDefault();
      e.stopPropagation();
      console.log("🔍 Eye icon clicked directly, triggering parent button");
      $(this).closest(".password-toggle").trigger("click");
    });

    // Additional fallback event binding for auth buttons
    setTimeout(() => {
      console.log("Fallback binding check...");
      if ($(".login-btn, .signup-btn").length > 0) {
        console.log("Auth buttons found, ensuring events are bound");

        // Unbind and rebind to ensure events work
        $(".login-btn")
          .off("click.auth")
          .on("click.auth", function (e) {
            console.log("Fallback login button clicked!");
            e.preventDefault();
            if (window.dreddChat && window.dreddChat.showAuthModal) {
              window.dreddChat.showAuthModal("login");
            }
          });

        $(".signup-btn")
          .off("click.auth")
          .on("click.auth", function (e) {
            console.log("Fallback signup button clicked!");
            e.preventDefault();
            if (window.dreddChat && window.dreddChat.showAuthModal) {
              window.dreddChat.showAuthModal("signup");
            }
          });
      }
    }, 500);

    // Handle pre-filled messages from URL
    const urlParams = new URLSearchParams(window.location.search);

    // Check for chat auto-open parameter
    if (urlParams.get("open_chat") === "1") {
      console.log("Auto-opening chat from URL parameter");

      // Enhanced chat opening experience
      setTimeout(() => {
        const $chatInput = $("#dredd-message-input");
        const $chatContainer = $(".dredd-chat-container");

        if ($chatInput.length) {
          // Add visual indication that chat is active
          $chatContainer.addClass("chat-auto-opened");

          // Smooth scroll to chat
          $chatInput[0].scrollIntoView({
            behavior: "smooth",
            block: "center",
            inline: "nearest",
          });

          // Focus with slight delay for better UX
          setTimeout(() => {
            $chatInput.focus();

            // Add welcome message for password reset return
            window.dreddChat.addMessage(
              "🔑 Password reset complete! Welcome back, citizen. Ready to Analyze some tokens?",
              "dredd",
              "success"
            );

            // Pulse effect on input
            $chatInput.css({
              animation: "pulse 1s ease-in-out 2",
            });

            setTimeout(() => {
              $chatInput.css("animation", "");
              $chatContainer.removeClass("chat-auto-opened");
            }, 2000);
          }, 300);
        }

        // Clean up URL parameter
        window.dreddChat.cleanUrlParameters(["open_chat"]);
      }, 500);
    }

    // Handle pre-filled messages
    const prefilledMessage = urlParams.get("dredd_message");
    if (prefilledMessage) {
      $("#dredd-message-input").val(prefilledMessage);
      // Auto-send after a short delay
      setTimeout(() => {
        window.dreddChat.sendMessage();
        // Clean up URL parameter
        window.dreddChat.cleanUrlParameters(["dredd_message"]);
      }, 1000);
    }
  });
})(jQuery);

// === REAL-TIME UPDATE SYSTEM FOR ADMIN CHANGES ===

// Initialize real-time updates when document is ready
$(document).ready(function () {
  // Only initialize for logged-in users
  if (dredd_ajax.is_logged_in && typeof dredd_ajax.user_id !== "undefined") {
    initializeRealTimeUpdates();
  }
});

function initializeRealTimeUpdates() {
  console.log("🔄 Initializing real-time update system...");

  // Store last known credit balance
  let lastKnownCredits = null;

  // Check for user data updates every 30 seconds (reduced frequency)
  setInterval(function () {
    checkForUserUpdates();
  }, 30000);

  // Enhanced dashboard refresh when modal is open - reduced frequency
  setInterval(function () {
    if ($("#dredd-dashboard-modal:visible").length > 0) {
      // Silently refresh dashboard data
      if (window.dreddChat && window.dreddChat.loadDashboardData) {
        console.log("🔄 Refreshing dashboard data...");
        window.dreddChat.loadDashboardData();
      }
    }
  }, 60000); // Increased from 30s to 60s

  // WordPress Heartbeat integration for real-time admin updates
  if (typeof wp !== "undefined" && wp.heartbeat) {
    // Send user ID with heartbeat
    $(document).on("heartbeat-send", function (e, data) {
      data["dredd_user_check"] = {
        user_id: dredd_ajax.user_id,
        last_credits: lastKnownCredits,
      };
    });

    // Receive admin updates via heartbeat
    $(document).on("heartbeat-tick", function (e, data) {
      if (data["dredd_admin_updates"]) {
        handleAdminUpdates(data["dredd_admin_updates"]);
      }
    });
  }

  function handleAdminUpdates(updates) {
    console.log("📡 Received admin updates:", updates);

    if (updates.credits_changed) {
      console.log("💰 Admin credit update detected");

      // Update credit display immediately
      window.dreddChat.updateCreditsDisplay(updates.new_credits);

      // Show notification with reason if provided
      let message = "💰 Admin Update: Your credits have been updated!";
      if (updates.reason) {
        message += ` Reason: ${updates.reason}`;
      }

      window.dreddChat.showMessage(message, "dredd", "success");

      // Refresh dashboard
      if ($("#dredd-dashboard-modal:visible").length > 0) {
        setTimeout(() => {
          window.dreddChat.loadDashboardData();
        }, 500);
      }

      lastKnownCredits = updates.new_credits;
    }

    if (updates.settings_changed) {
      console.log("⚙️ Admin settings update detected");

      // Show notification about settings change
      window.dreddChat.showMessage(
        "⚙️ System Update: Admin has updated credit settings.",
        "dredd",
        "info"
      );

      // Refresh dashboard to show new rates
      if ($("#dredd-dashboard-modal:visible").length > 0) {
        setTimeout(() => {
          window.dreddChat.loadDashboardData();
        }, 500);
      }
    }
  }

  function checkForUserUpdates() {
    if (!window.dreddChat) return;

    // Check current credit balance
    window.dreddChat.checkUserCredits().then((currentCredits) => {
      if (lastKnownCredits !== null && lastKnownCredits !== currentCredits) {
        console.log(
          `💰 Credits changed: ${lastKnownCredits} → ${currentCredits}`
        );

        // Update UI
        window.dreddChat.updateCreditsDisplay(currentCredits);

        // Show notification
        const difference = currentCredits - lastKnownCredits;
        const action = difference > 0 ? "added" : "deducted";
        const emoji = difference > 0 ? "💰" : "📉";

        if (window.dreddChat && window.dreddChat.showMessage) {
          window.dreddChat.showMessage(
            `${emoji} Admin Update: ${Math.abs(
              difference
            )} credits ${action} to your account!`,
            "dredd",
            "info"
          );
        } else {
          // Fallback notification
          console.log(`${emoji} Credits ${action}: ${Math.abs(difference)}`);
        }

        // Refresh dashboard if open
        if ($("#dredd-dashboard-modal:visible").length > 0) {
          setTimeout(() => {
            window.dreddChat.loadDashboardData();
          }, 1000);
        }
      }

      lastKnownCredits = currentCredits;
    });
  }

  // Listen for visibility change to refresh when user returns to tab
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden && dredd_ajax.is_logged_in) {
      console.log("👁️ Tab became visible, checking for updates...");
      setTimeout(checkForUserUpdates, 1000);
    }
  });

  // Listen for window focus to refresh data
  $(window).on("focus", function () {
    if (dredd_ajax.is_logged_in) {
      console.log("🎯 Window focused, checking for updates...");
      setTimeout(checkForUserUpdates, 500);
    }
  });

  console.log("✅ Real-time update system initialized successfully");
}
