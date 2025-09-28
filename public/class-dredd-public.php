<?php
/**
 * Public-facing functionality for DREDD AI plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Public
{

    private $database;

    public function __construct()
    {
        $this->database = new Dredd_Database();
    }

    /**
     * Render chat interface shortcode
     */
    public function render_chat_interface($atts)
    {
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px',
            'theme' => 'default'
        ), $atts);

        // Generate unique session ID
        $session_id = 'dredd_' . uniqid() . '_' . time();

        ob_start();
        ?>
        <div class="dredd-chat-wrapper" data-session="<?php echo esc_attr($session_id); ?>">
            <!-- Chat Container -->
            <div class="dredd-chat-container"
                style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">

                <!-- Chat Header -->
                <div class="dredd-chat-header">
                    <div class="dredd-header-left">
                        <div class="dredd-title">
                            <h3>DREDD AI</h3>
                        </div>
                    </div>

                    <div class="dredd-header-center">
                        <div class="dredd-mode-selector">
                            <button class="mode-btn active" data-mode="standard">
                                STANDARD
                            </button>
                            <button class="mode-btn" data-mode="psycho">
                                PSYCHO
                            </button>
                        </div>
                        <!-- <label class="blockchain-select-mobile">Choose Your Chain:</label> -->
                        <!-- Blockchain Selection -->
                        <div class="dredd-chain-selector">
                            <!-- <label class="blockchain-select">Choose Your Chain:</label> -->
                            <select id="blockchain-select" class="chain-dropdown">
                                <?php
                                $chains = dredd_ai_get_option('supported_chains', array(
                                    'ethereum' => array('name' => 'Ethereum', 'enabled' => true),
                                    'bsc' => array('name' => 'Binance Smart Chain', 'enabled' => true),
                                    'polygon' => array('name' => 'Polygon', 'enabled' => true),
                                    'Solana' => array('name' => 'Solana', 'enabled' => true),
                                    'pulsechain' => array('name' => 'PulseChain', 'enabled' => true)
                                ));
                                foreach ($chains as $key => $chain) {
                                    if ($chain['enabled']) {
                                        $selected = $key === 'ethereum' ? 'selected' : '';
                                        echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($chain['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="dredd-header-right">
                        <div class="dredd-status">
                            <span class="status-dot online"></span>
                            <span class="status-text">ONLINE</span>
                        </div>

                        <?php if (is_user_logged_in()): ?>
                            <!-- <div class="dredd-credits">
                            <span class="credits-icon">🪙</span>
                            <span class="credits-count"><?php echo dredd_ai_get_user_credits(get_current_user_id()); ?></span>
                        </div> -->
                            <div class="dredd-user-menu">
                                <button class="user-menu-btn" title="User Menu">
                                    <span class="user-icon">👤</span>
                                    <span class="user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                                </button>
                                <div class="user-dropdown" style="display: none;">
                                    <a href="#" class="user-dashboard-link">Dashboard</a>
                                    <a href="#" class="logout-link" data-action="dredd_logout">Logout</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="dredd-auth-buttons">
                                <button class="auth-btn login-btn" title="Login">
                                    <span class="auth-text">LOGIN</span>
                                </button>
                                <button class="auth-btn signup-btn" title="Sign Up">
                                    <span class="auth-text">SIGN UP</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if (dredd_ai_get_option('show_promotions_sidebar', true)): ?>
                            <button class="promotions-toggle" title="Featured Tokens">
                                <span class="hamburger-icon">🚀</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Dashboard Modal -->
                <div class="dredd-dashboard-modal" id="dredd-dashboard-modal" style="display: none;">
                    <div class="dashboard-modal-overlay"></div>
                    <div class="dashboard-modal-container">
                        <div class="dashboard-modal-header">
                            <h2>DREDD DASHBOARD</h2>
                            <button class="dashboard-modal-close">&times;</button>
                        </div>

                        <div class="dashboard-modal-content">
                            <div class="dashboard-loading" id="dashboard-loading">
                                <p>Loading your DREDD data...</p>
                            </div>

                            <div class="dashboard-content" id="dashboard-content" style="display: none;">
                                <!-- Content will be loaded dynamically -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modern Payment Modal -->
                <div class="dredd-payment-modal" id="dredd-payment-modal" style="display: none;">
                    <div class="payment-modal-overlay"></div>
                    <div class="payment-modal-container">
                        <!-- Step 1: Payment Methods -->
                        <div class="payment-step active" id="payment-step-1">
                            <div class="payment-modal-header">
                                <h2>Payment Options</h2>
                                <button class="payment-modal-close">&times;</button>
                            </div>

                            <div class="payment-modal-content">
                                <!-- Credit Card Section -->
                                <div class="payment-method-section">
                                    <h3>Card</h3>
                                    <p class="payment-range">($3.00 - $100)</p>
                                    <div class="payment-method-card stripe-card" data-method="stripe">
                                        <div class="card-logos">
                                            <img src="<?php echo DREDD_AI_PLUGIN_URL; ?>assets/images/visa.svg" alt="Visa"
                                                class="card-logo">
                                            <img src="<?php echo DREDD_AI_PLUGIN_URL; ?>assets/images/mastercard.svg"
                                                alt="Mastercard" class="card-logo">
                                            <img src="<?php echo DREDD_AI_PLUGIN_URL; ?>assets/images/amex.svg"
                                                alt="American Express" class="card-logo">
                                        </div>
                                    </div>
                                </div>

                                <!-- No-Fee Cryptocurrencies -->
                                <div class="payment-method-section">
                                    <h3>No-Fee Cryptocurrencies</h3>
                                    <p class="payment-range">(Variable - $2000)</p>
                                    <div class="crypto-grid">
                                        <div class="payment-method-card crypto-card" data-method="bitcoin">
                                            <div class="crypto-icon">₿</div>
                                            <span>Bitcoin</span>
                                        </div>
                                        <div class="payment-method-card crypto-card" data-method="litecoin">
                                            <div class="crypto-icon">Ł</div>
                                            <span>Litecoin</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Cryptocurrencies -->
                                <div class="payment-method-section">
                                    <h3>Other Cryptocurrencies</h3>
                                    <p class="payment-range">(Variable - $2000)</p>
                                    <div class="crypto-grid">
                                        <div class="payment-method-card crypto-card" data-method="ethereum">
                                            <div class="crypto-icon">Ξ</div>
                                            <span>Ethereum</span>
                                        </div>
                                        <div class="payment-method-card crypto-card" data-method="usdcoin">
                                            <div class="crypto-icon">$</div>
                                            <span>USD Coin</span>
                                        </div>
                                        <div class="payment-method-card crypto-card" data-method="tether-bep20">
                                            <div class="crypto-icon">₮</div>
                                            <span>Tether BEP20</span>
                                        </div>
                                        <div class="payment-method-card crypto-card" data-method="tether-erc20">
                                            <div class="crypto-icon">₮</div>
                                            <span>Tether ERC20</span>
                                        </div>
                                        <div class="payment-method-card crypto-card" data-method="tether-trc20">
                                            <div class="crypto-icon">₮</div>
                                            <span>Tether TRC20</span>
                                        </div>
                                        <div class="payment-method-card crypto-card" data-method="pulsechain">
                                            <div class="crypto-icon">♥</div>
                                            <span>PulseChain</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-step-actions">
                                <button class="payment-continue-btn">Continue</button>
                            </div>
                        </div>

                        <!-- Step 2: Amount Selection -->
                        <div class="payment-step" id="payment-step-2">
                            <div class="payment-modal-header">
                                <h2>Credits Amount</h2>
                                <button class="payment-modal-close">&times;</button>
                            </div>

                            <div class="payment-modal-content">
                                <div class="amount-section">
                                    <h3>Choose An Amount</h3>
                                    <div class="amount-grid">
                                        <div class="amount-option" data-amount="3.00">$3</div>
                                        <div class="amount-option" data-amount="5.00">$5</div>
                                        <div class="amount-option" data-amount="10.00">$10</div>
                                        <div class="amount-option" data-amount="25.00">$25</div>
                                        <div class="amount-option" data-amount="50.00">$50</div>
                                        <div class="amount-option" data-amount="100.00">$100</div>
                                    </div>
                                </div>

                                <div class="custom-amount-section">
                                    <h3>Custom Amount</h3>
                                    <div class="custom-amount-container">
                                        <div class="amount-display">$<input type="number" id="custom-amount-value"></div>
                                        <!-- <div class="amount-slider-container">
                                            <span class="slider-min">$3.00</span>
                                            <input type="range" id="amount-slider" min="3" max="100" value="3" step="0.50">
                                            <span class="slider-max">$100.00</span>
                                        </div> -->
                                    </div>
                                </div>

                                <div class="payment-step-actions">
                                    <button class="payment-back-btn">Back</button>
                                    <button class="payment-continue-btn">Continue</button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Payment Processing -->
                        <div class="payment-step" id="payment-step-3">
                            <div class="payment-modal-header">
                                <h2>Complete Payment</h2>
                                <button class="payment-modal-close">&times;</button>
                            </div>

                            <div class="payment-modal-content">
                                <!-- Stripe Payment Form -->
                                <div class="payment-form stripe-form" id="stripe-payment-form" style="display: none;">
                                    <div class="payment-summary">
                                        <div class="summary-item">
                                            <span>Amount:</span>
                                            <span class="payment-amount">$0.00</span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Credits:</span>
                                            <span class="payment-credits">0</span>
                                        </div>
                                    </div>
                                    <div id="stripe-card-element"></div>
                                    <div id="stripe-card-errors" class="stripe-card-errors"></div>
                                    <button class="payment-submit-btn" id="stripe-submit-btn">Complete Payment</button>
                                </div>

                                <!-- Crypto Payment Form -->
                                <div class="payment-form crypto-form" id="crypto-payment-form" style="display: none;">
                                    <div class="payment-summary">
                                        <div class="summary-item">
                                            <span>Amount:</span>
                                            <span class="payment-amount">$0.00</span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Currency:</span>
                                            <span class="payment-currency">BTC</span>
                                        </div>
                                    </div>
                                    <div class="crypto-payment-details">
                                        <div class="qr-code-container">
                                            <div id="payment-qr-code"></div>
                                        </div>
                                        <div class="payment-address">
                                            <label>Send to address:</label>
                                            <div class="address-container">
                                                <input type="text" id="payment-address" readonly>
                                                <button class="copy-address-btn">Copy</button>
                                            </div>
                                        </div>
                                        <div class="payment-amount-crypto">
                                            <label>Amount to send:</label>
                                            <div class="amount-container">
                                                <input type="text" id="crypto-amount" readonly>
                                                <button class="copy-amount-btn">Copy</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="payment-status">
                                        <div class="status-indicator">Waiting for payment...</div>
                                        <div class="status-timer">Time remaining: <span id="payment-timer">30:00</span></div>
                                    </div>
                                </div>

                                <!-- PulseChain Payment Form -->
                                <div class="payment-form pulsechain-form" id="pulsechain-payment-form" style="display: none;">
                                    <div class="payment-summary">
                                        <div class="summary-item">
                                            <span>Amount:</span>
                                            <span class="payment-amount">$0.00</span>
                                        </div>
                                        <div class="summary-item">
                                            <span>Network:</span>
                                            <span>PulseChain</span>
                                        </div>
                                    </div>
                                    <div class="wallet-connection">
                                        <button class="connect-wallet-btn" id="connect-pulsechain-wallet">
                                            Connect PulseChain Wallet
                                        </button>
                                        <div class="wallet-info" id="pulsechain-wallet-info" style="display: none;">
                                            <div class="connected-address"></div>
                                            <div class="wallet-balance"></div>
                                        </div>
                                    </div>
                                    <button class="payment-submit-btn" id="pulsechain-submit-btn" style="display: none;">Send
                                        Payment</button>
                                </div>

                                <div class="payment-step-actions">
                                    <button class="payment-back-btn">Back</button>
                                    <button class="payment-close-btn">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Authentication Modal -->
                <div class="dredd-auth-modal" id="dredd-auth-modal" style="display: none;">
                    <div class="auth-modal-overlay"></div>
                    <div class="auth-modal-container">
                        <!-- Login Form -->
                        <div class="auth-form login-form active" id="login-form">
                            <div class="auth-modal-header">
                                <h2>CITIZEN LOGIN</h2>
                                <button class="auth-modal-close">&times;</button>
                            </div>

                            <div class="auth-modal-content">
                                <!-- <div class="dredd-quote">
                                    <p>"I am the BlockChain Enforcer! DREDD is running low on resources, check back soon"</p>
                                </div> -->

                                <form id="dredd-login-form" class="auth-form-inner">
                                    <div class="form-group">
                                        <label for="login-username">👤 Username or Email</label>
                                        <input type="text" id="login-username" name="username" required
                                            placeholder="Enter your username or email">
                                    </div>

                                    <div class="form-group">
                                        <label for="login-password">Password</label>
                                        <div class="password-input-container">
                                            <input type="password" id="login-password" name="password" required
                                                placeholder="Enter your password">
                                            <button type="button" class="password-toggle" data-target="login-password">
                                                <span class="eye-icon">👁️</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="login-remember" name="remember">
                                            <span class="checkmark"></span>
                                            Remember me
                                        </label>
                                    </div>
                                    <!-- reCAPTCHA -->
                                    <div class="form-group captcha-group">
                                        <div id="login-recaptcha" class="g-recaptcha"
                                            data-sitekey="<?php echo esc_attr(dredd_ai_get_option('recaptcha_site_key', '')); ?>"
                                            data-theme="dark"></div>
                                        <div class="captcha-fallback" style="display: none;">
                                            <label for="login-captcha">Security Check: What is <?php echo rand(1, 9); ?> +
                                                <?php echo rand(1, 9); ?>?</label>
                                            <input type="text" id="login-captcha" name="captcha" placeholder="Answer">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="auth-submit-btn login-submit">
                                            ENTER THE SYSTEM
                                        </button>
                                    </div>

                                    <div class="form-links">
                                        <a href="#" class="forgot-password-link">Forgot Password?</a>
                                    </div>
                                </form>

                                <div class="auth-switch">
                                    <p>New citizen? <a href="#" class="switch-to-signup">Register for justice</a></p>
                                </div>
                            </div>
                        </div>

                        <!-- Sign Up Form -->
                        <div class="auth-form signup-form" id="signup-form">
                            <div class="auth-modal-header">
                                <h2>👤 REGISTRATION</h2>
                                <button class="auth-modal-close">&times;</button>
                            </div>

                            <div class="auth-modal-content">
                                <!-- <div class="dredd-quote">
                                    <p>"Justice requires identification. Register to serve the law!"</p>
                                </div> -->

                                <form id="dredd-signup-form" class="auth-form-inner">
                                    <div class="form-group">
                                        <label for="signup-username">👤 Username</label>
                                        <input type="text" id="signup-username" name="username" required
                                            placeholder="Choose a username">
                                    </div>

                                    <div class="form-group">
                                        <label for="signup-email">Email Address</label>
                                        <input type="email" id="signup-email" name="email" required
                                            placeholder="Enter your email address">
                                    </div>

                                    <div class="form-group">
                                        <label for="signup-password">Password</label>
                                        <div class="password-input-container">
                                            <input type="password" id="signup-password" name="password" required
                                                placeholder="Create a secure password">
                                            <button type="button" class="password-toggle" data-target="signup-password">
                                                <span class="eye-icon">👁️</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="signup-confirm-password">Confirm Password</label>
                                        <div class="password-input-container">
                                            <input type="password" id="signup-confirm-password" name="confirm_password" required
                                                placeholder="Confirm your password">
                                            <button type="button" class="password-toggle" data-target="signup-confirm-password">
                                                <span class="eye-icon">👁️</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="signup-terms" name="terms" required>
                                            <span class="checkmark"></span>
                                            I agree to serve justice and follow the law
                                        </label>
                                    </div>

                                    <div class="form-group checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="signup-newsletter" name="newsletter">
                                            <span class="checkmark"></span>
                                            Send me justice updates and crypto alerts
                                        </label>
                                    </div>

                                    <!-- reCAPTCHA -->
                                    <div class="form-group captcha-group">
                                        <div id="signup-recaptcha" class="g-recaptcha"
                                            data-sitekey="<?php echo esc_attr(dredd_ai_get_option('recaptcha_site_key', '')); ?>"
                                            data-theme="dark"></div>
                                        <div class="captcha-fallback" style="display: none;">
                                            <label for="signup-captcha">Security Check: What is <?php echo rand(1, 9); ?> +
                                                <?php echo rand(1, 9); ?>?</label>
                                            <input type="text" id="signup-captcha" name="captcha" placeholder="Answer">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="auth-submit-btn signup-submit">
                                            JOIN THE FORCE
                                        </button>
                                    </div>
                                </form>

                                <div class="auth-switch">
                                    <p>Already a citizen? <a href="#" class="switch-to-login">Login to continue</a></p>
                                </div>
                            </div>
                        </div>

                        <!-- Forgot Password Form -->
                        <div class="auth-form forgot-form" id="forgot-form">
                            <div class="auth-modal-header">
                                <h2>PASSWORD RECOVERY</h2>
                                <button class="auth-modal-close">&times;</button>
                            </div>

                            <div class="auth-modal-content">
                                <div class="dredd-quote">
                                    <p>"Provide your email for password reset. Please check also spam of email"</p>
                                </div>

                                <form id="dredd-forgot-form" class="auth-form-inner">
                                    <div class="form-group">
                                        <label for="forgot-email">Email Address</label>
                                        <input type="email" id="forgot-email" name="email" required
                                            placeholder="Enter your registered email">
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="auth-submit-btn forgot-submit">
                                            RECLAIM ACCESS
                                        </button>
                                    </div>
                                </form>

                                <div class="auth-switch">
                                    <p><a href="#" class="switch-to-login">Back to Login</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Chat Area -->
                <div class="dredd-chat-messages" id="dredd-chat-messages">
                    <div class="dredd-welcome-message">
                        <div class="message-avatar">
                            <img src="https://dredd.ai/wp-content/uploads/2025/09/86215e12-1e3f-4cb0-b851-cfb84d7459a8.png"
                                alt="DREDD Avatar" />
                        </div>
                        <div class="message-content">
                            <div class="message-bubble dredd-message">
                                <p><strong>I AM THE BlOCKCHAIN ENFORCER!</strong></p>
                                <p>What suspicious token needs investigation, citizen?</p>
                            </div>
                            <!-- <div class="message-timestamp"><?php echo current_time('H:i'); ?></div> -->
                        </div>
                    </div>
                </div>

                <!-- Typing Indicator -->
                <div class="dredd-typing-indicator" id="dredd-typing" style="display: none;">
                    <div class="typing-avatar">⚖️</div>
                    <div class="typing-content">
                        <div class="typing-bubble">
                            <div class="typing-dots">
                                <span></span><span></span><span></span>
                            </div>
                            <span class="typing-text">Analyzing...</span>
                        </div>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="dredd-chat-input">
                    <div class="input-container">
                        <input type="text" id="dredd-message-input" placeholder="Contract or Ticker and Chain"
                            maxlength="500" />
                        <button id="dredd-send-btn" class="send-button">
                            <span class="send-text">Analyze</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Token Promotions Sidebar -->
            <?php if (dredd_ai_get_option('show_promotions_sidebar', true)): ?>
                <div class="dredd-promotions-sidebar" id="dredd-promotions">
                    <div class="promotions-header">
                        <h4>FEATURED TOKENS</h4>
                        <button class="promotions-close">×</button>
                    </div>

                    <div class="promotions-content">
                        <?php echo $this->render_promoted_tokens(); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Loading Overlay -->
        <div class="dredd-loading-overlay" id="dredd-loading" style="display: none;">
            <div class="loading-content">
                <div class="loading-logo">🛡️</div>
                <h3>ANALYZING EVIDENCE...</h3>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <p class="loading-text" id="loading-text">Scanning blockchain for criminal activity...</p>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Render user dashboard shortcode
     */
    public function render_user_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access your DREDD dashboard.</p>';
        }

        $user_id = get_current_user_id();
        $user_data = $this->database->get_user_data($user_id);
        $filters = array(
            'mode' => $_GET['mode'] ?? '',
            'verdict' => $_GET['verdict'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        );

        $page = $_GET['analysis_page'] ?? 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $history = $this->database->get_user_analysis_history($user_id, $per_page, $offset, $filters);

        ob_start();
        ?>
        <div class="dredd-user-dashboard">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="dashboard-title">
                    <img src="<?php echo DREDD_AI_PLUGIN_URL; ?>assets/images/dredd-badge.png" alt="DREDD" />
                    <h2>MY DREDD DASHBOARD</h2>
                </div>
                <div class="dashboard-status">
                    <span class="account-type"><?php echo $user_data['tokens']->token_balance > 0 ? 'PREMIUM' : 'FREE'; ?>
                        CITIZEN</span>
                </div>
            </div>

            <!-- Account Overview -->
            <div class="dashboard-section">
                <h3>Account Overview</h3>
                <div class="overview-grid">
                    <div class="overview-card">

                        <div class="card-content">
                            <div class="card-number"><?php echo number_format($user_data['tokens']->token_balance ?? 0); ?>
                            </div>
                            <div class="card-label">Credits Remaining</div>
                        </div>
                    </div>

                    <div class="overview-card">

                        <div class="card-content">
                            <div class="card-number"><?php echo number_format($user_data['stats']->total_analyses ?? 0); ?>
                            </div>
                            <div class="card-label">Total Analyses</div>
                        </div>
                    </div>

                    <div class="overview-card">

                        <div class="card-content">
                            <div class="card-number"><?php echo number_format($user_data['stats']->scams_detected ?? 0); ?>
                            </div>
                            <div class="card-label">Scams Detected</div>
                        </div>
                    </div>

                    <div class="overview-card">

                        <div class="card-content">
                            <div class="card-number"><?php echo number_format($user_data['stats']->psycho_analyses ?? 0); ?>
                            </div>
                            <div class="card-label">Psycho Analyses</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-section">
                <h3>Quick Actions</h3>
                <div class="quick-actions">
                    <a href="#" class="action-btn primary" id="buy-credits">

                        Buy Credits
                    </a>
                    <a href="#" class="action-btn" id="export-data">

                        Export Data
                    </a>
                    <a href="<?php echo home_url(); ?>" class="action-btn">

                        New Analysis
                    </a>
                </div>
            </div>

            <!-- Analysis History -->
            <div class="dashboard-section">
                <h3>Analysis History</h3>

                <!-- Filters -->
                <div class="history-filters">
                    <form method="get" class="filter-form">
                        <select name="mode">
                            <option value="">All Modes</option>
                            <option value="standard" <?php selected($filters['mode'], 'standard'); ?>>Standard</option>
                            <option value="psycho" <?php selected($filters['mode'], 'psycho'); ?>>Psycho</option>
                        </select>

                        <select name="verdict">
                            <option value="">All Verdicts</option>
                            <option value="scam" <?php selected($filters['verdict'], 'scam'); ?>>Scam</option>
                            <option value="legit" <?php selected($filters['verdict'], 'legit'); ?>>Legit</option>
                            <option value="caution" <?php selected($filters['verdict'], 'caution'); ?>>Caution</option>
                        </select>

                        <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"
                            placeholder="From Date" />
                        <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>"
                            placeholder="To Date" />

                        <button type="submit" class="filter-btn">Filter</button>
                        <a href="?" class="reset-btn">Reset</a>
                    </form>
                </div>

                <!-- History Table -->
                <div class="history-table">
                    <?php if (empty($history['results'])): ?>
                        <div class="no-results">
                            <img src="<?php echo DREDD_AI_PLUGIN_URL; ?>assets/images/dredd-logo.png" alt="No Results" />
                            <h4>No analyses found</h4>
                            <p>Start investigating some tokens to see your history here!</p>
                        </div>
                    <?php else: ?>
                        <table class="analysis-table">
                            <thead>
                                <tr>
                                    <th>Token</th>
                                    <th>Chain</th>
                                    <th>Mode</th>
                                    <th>Verdict</th>
                                    <th>Cost</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history['results'] as $analysis): ?>
                                    <tr>
                                        <td>
                                            <div class="token-info">
                                                <strong><?php echo esc_html($analysis->token_name); ?></strong>
                                                <code
                                                    class="contract-short"><?php echo esc_html(substr($analysis->contract_address, 0, 10) . '...'); ?></code>
                                            </div>
                                        </td>
                                        <td><span class="chain-badge"><?php echo esc_html(ucfirst($analysis->chain)); ?></span></td>
                                        <td><span
                                                class="mode-badge <?php echo $analysis->mode; ?>"><?php echo ucfirst($analysis->mode); ?></span>
                                        </td>
                                        <td><span
                                                class="verdict-badge <?php echo $analysis->verdict; ?>"><?php echo ucfirst($analysis->verdict); ?></span>
                                        </td>
                                        <td><?php echo $analysis->token_cost; ?> 🪙</td>
                                        <td><?php echo date('M j, Y', strtotime($analysis->created_at)); ?></td>
                                        <td>
                                            <button class="view-analysis" data-id="<?php echo $analysis->analysis_id; ?>">View</button>
                                            <button class="reanalyze-token" data-contract="<?php echo $analysis->contract_address; ?>"
                                                data-chain="<?php echo $analysis->chain; ?>">Re-analyze</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($history['total'] > $per_page): ?>
                            <div class="pagination">
                                <?php
                                $total_pages = ceil($history['total'] / $per_page);
                                $current_page = $page;

                                for ($i = 1; $i <= $total_pages; $i++):
                                    $class = $i == $current_page ? 'active' : '';
                                    $url = add_query_arg('analysis_page', $i);
                                    ?>
                                    <a href="<?php echo esc_url($url); ?>" class="page-link <?php echo $class; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <?php if (!empty($user_data['transactions'])): ?>
                <div class="dashboard-section">
                    <h3>Recent Purchases</h3>
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Tokens</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_data['transactions'] as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($transaction->created_at)); ?></td>
                                        <td>$<?php echo number_format($transaction->amount, 2); ?></td>
                                        <td><?php echo number_format($transaction->tokens); ?> 🪙</td>
                                        <td><?php echo strtoupper($transaction->payment_method); ?></td>
                                        <td><span
                                                class="status-badge <?php echo $transaction->status; ?>"><?php echo ucfirst($transaction->status); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Analysis Detail Modal -->
        <div id="analysis-modal" class="dredd-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Analysis Details</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body" id="analysis-detail-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Render promoted tokens
     */
    private function render_promoted_tokens()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';

        // Updated query to also check approved_by field for better reliability
        $promotions = $wpdb->get_results(
            "SELECT * FROM {$table} 
             WHERE status = 'active' 
             AND start_date <= NOW() 
             AND end_date >= NOW() 
             AND approved_by IS NOT NULL
             ORDER BY created_at DESC
             LIMIT 5"
        );

        if (empty($promotions)) {
            return '<div class="no-promotions">No featured tokens at the moment.</div>';
        }

        ob_start();
        ?>
        <div class="promoted-tokens">
            <?php foreach ($promotions as $promotion): ?>
                <div class="promotion-card" data-promotion-id="<?php echo $promotion->id; ?>">
                    <div class="promotion-header">
                        <?php if ($promotion->token_logo): ?>
                            <img src="<?php echo esc_url($promotion->token_logo); ?>"
                                alt="<?php echo esc_attr($promotion->token_name); ?>" class="token-logo" />
                        <?php endif; ?>
                        <div class="token-info">
                            <h5><?php echo esc_html($promotion->token_name); ?></h5>
                            <?php if ($promotion->token_symbol): ?>
                                <span class="token-symbol">$<?php echo esc_html($promotion->token_symbol); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="promotion-content">
                        <?php if ($promotion->tagline): ?>
                            <p class="promotion-tagline"><?php echo esc_html($promotion->tagline); ?></p>
                        <?php endif; ?>

                        <?php if ($promotion->chain): ?>
                            <span class="chain-badge"><?php echo esc_html(ucfirst($promotion->chain)); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="promotion-actions">
                        <?php if ($promotion->contract_address): ?>
                            <button class="analyze-promoted" data-contract="<?php echo esc_attr($promotion->contract_address); ?>"
                                data-chain="<?php echo esc_attr($promotion->chain); ?>">
                                ⚡ ANALYZE NOW
                            </button>
                        <?php endif; ?>

                        <?php if ($promotion->wp_post_id): ?>
                            <a href="<?php echo get_permalink($promotion->wp_post_id); ?>" class="read-more" target="_blank">
                                📖 READ MORE
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="promotion-label">
                        <span>SPONSORED</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}
