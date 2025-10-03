<?php
/**
 * Plugin Name: DREDD AI - Cryptocurrency Analysis Tool
 * Plugin URI: https://dredd.ai
 * Description: Judge Dredd themed cryptocurrency analysis tool with brutal honesty, powered by local Ollama and n8n workflows.
 * Version: 1.0.0
 * Author: DREDD AI Team
 * License: GPL v2 or later
 * Text Domain: dredd-ai
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DREDD_AI_VERSION', '1.0.0');
define('DREDD_AI_PLUGIN_FILE', __FILE__);
define('DREDD_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DREDD_AI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DREDD_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DREDD_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class DreddAI
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('DreddAI', 'uninstall'));

        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_dredd_chat', array($this, 'handle_chat_ajax'));
        add_action('wp_ajax_nopriv_dredd_chat', array($this, 'handle_chat_ajax'));

        // DEBUG: Add a simple test endpoint
        add_action('wp_ajax_dredd_debug_test', array($this, 'handle_debug_test'));
        add_action('wp_ajax_nopriv_dredd_debug_test', array($this, 'handle_debug_test'));
        add_action('wp_ajax_dredd_view_logs', array($this, 'handle_view_logs'));
        add_action('wp_ajax_dredd_process_payment', array($this, 'handle_payment'));
        add_action('wp_ajax_nopriv_dredd_process_payment', array($this, 'handle_payment'));
        add_action('wp_ajax_dredd_get_user_data', array($this, 'get_user_data'));

        // NOWPayments AJAX actions
        add_action('wp_ajax_dredd_create_nowpayments_payment', array($this, 'handle_nowpayments_create'));
        add_action('wp_ajax_dredd_check_nowpayments_status', array($this, 'handle_nowpayments_status'));
        add_action('wp_ajax_dredd_nowpayments_webhook', array($this, 'handle_nowpayments_webhook'));
        add_action('wp_ajax_nopriv_dredd_nowpayments_webhook', array($this, 'handle_nowpayments_webhook'));
        add_action('wp_ajax_dredd_test_nowpayments_connection', array($this, 'handle_test_nowpayments_connection'));
        add_action('wp_ajax_dredd_test_currency_mapping', array($this, 'handle_test_currency_mapping'));
        add_action('wp_ajax_dredd_debug_payment_flow', array($this, 'handle_debug_payment_flow'));

        // Authentication AJAX actions
        add_action('wp_ajax_dredd_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_dredd_login', array($this, 'handle_login'));
        add_action('wp_ajax_dredd_register', array($this, 'handle_register'));
        add_action('wp_ajax_nopriv_dredd_register', array($this, 'handle_register'));
        add_action('wp_ajax_dredd_forgot_password', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_nopriv_dredd_forgot_password', array($this, 'handle_forgot_password'));

        // Dashboard AJAX actions
        add_action('wp_ajax_dredd_get_user_dashboard_data', array($this, 'handle_get_user_dashboard_data'));
        add_action('wp_ajax_dredd_submit_promotion', array($this, 'handle_submit_promotion'));
        add_action('wp_ajax_dredd_update_user_settings', array($this, 'handle_update_user_settings'));
        add_action('wp_ajax_dredd_update_user_password', array($this, 'handle_update_user_password'));

        // Real-time update handlers
        add_action('wp_ajax_dredd_check_user_updates', array($this, 'handle_check_user_updates'));
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 2);
        add_filter('heartbeat_send', array($this, 'heartbeat_send'));

        // Email verification handler
        add_action('init', array($this, 'handle_email_verification'));
        add_action('init', array($this, 'handle_password_reset'));

        // Logout handler
        add_action('wp_ajax_dredd_logout', array($this, 'handle_logout'));

        // Shortcode registration
        add_shortcode('dredd_chat', array($this, 'chat_shortcode'));
        add_shortcode('dredd_user_dashboard', array($this, 'user_dashboard_shortcode'));

        // Cron hooks
        add_action('dredd_cleanup_expired_data', array($this, 'cleanup_expired_data'));
        add_action('dredd_cleanup_cache', array($this, 'cleanup_cache'));
    }

    private function load_dependencies()
    {
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-database.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-n8n.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-payments.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-nowpayments.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-pulsechain.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-analytics.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-promotions.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-security.php';
        require_once DREDD_AI_PLUGIN_DIR . 'includes/class-dredd-validation.php';
        require_once DREDD_AI_PLUGIN_DIR . 'admin/class-dredd-admin.php';
        require_once DREDD_AI_PLUGIN_DIR . 'public/class-dredd-public.php';
    }

    public function init()
    {
        // Load text domain for translations
        load_plugin_textdomain('dredd-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize components
        new Dredd_Database();
        new Dredd_Admin();
        new Dredd_Public();
        new Dredd_Promotions();
        new Dredd_Security();
        new Dredd_PulseChain();

        // Schedule cron jobs
        if (!wp_next_scheduled('dredd_cleanup_expired_data')) {
            wp_schedule_event(time(), 'daily', 'dredd_cleanup_expired_data');
        }
        if (!wp_next_scheduled('dredd_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'dredd_cleanup_cache');
        }
    }

    public function activate()
    {
        // Create database tables
        $database = new Dredd_Database();
        $database->create_tables();

        // Set default options
        $this->set_default_options();

        // Schedule cron events
        wp_schedule_event(time(), 'daily', 'dredd_cleanup_expired_data');
        wp_schedule_event(time(), 'hourly', 'dredd_cleanup_cache');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('dredd_cleanup_expired_data');
        wp_clear_scheduled_hook('dredd_cleanup_cache');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public static function uninstall()
    {
        // Remove all plugin data if requested
        if (get_option('dredd_ai_remove_data_on_uninstall', false)) {
            global $wpdb;

            // Drop custom tables
            $tables = array(
                $wpdb->prefix . 'dredd_user_tokens',
                $wpdb->prefix . 'dredd_transactions',
                $wpdb->prefix . 'dredd_analysis_history',
                $wpdb->prefix . 'dredd_promotions',
                $wpdb->prefix . 'dredd_cache'
            );

            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS {$table}");
            }

            // Remove options
            $options = array(
                'dredd_ai_version',
                'dredd_ai_ollama_url',
                'dredd_ai_n8n_webhook',
                'dredd_ai_paid_mode_enabled',
                'dredd_ai_stripe_secret_key',
                'dredd_ai_stripe_publishable_key',
                'dredd_ai_crypto_wallets',
                'dredd_ai_token_packages',
                'dredd_ai_analysis_cost',
                'dredd_ai_api_keys',
                'dredd_ai_auto_publish',
                'dredd_ai_cache_duration',
                'dredd_ai_data_retention',
                'dredd_ai_remove_data_on_uninstall'
            );

            foreach ($options as $option) {
                delete_option($option);
            }
        }
    }

    private function set_default_options()
    {
        // Core settings
        update_option('dredd_ai_version', DREDD_AI_VERSION);
        update_option('dredd_ai_paid_mode_enabled', false);
        update_option('dredd_ai_analysis_cost', 5);
        update_option('dredd_ai_cache_duration', 24);
        update_option('dredd_ai_data_retention_free', 90);
        update_option('dredd_ai_data_retention_paid', 365);
        update_option('dredd_ai_auto_publish', true);
        update_option('dredd_ai_show_promotions_sidebar', true);
        update_option('dredd_ai_debug_logging', true); // Enable debug logging by default
        update_option('dredd_ai_welcome_credits', 10); // Welcome credits for new users


        // Default token packages
        $default_packages = array(
            array('tokens' => 50, 'price' => 5.00, 'name' => 'Starter Pack'),
            array('tokens' => 200, 'price' => 15.00, 'name' => 'Popular Pack'),
            array('tokens' => 500, 'price' => 30.00, 'name' => 'Power Pack'),
            array('tokens' => 1500, 'price' => 75.00, 'name' => 'Whale Pack')
        );
        update_option('dredd_ai_token_packages', $default_packages);

        // Default crypto wallets (empty - admin must configure)
        $default_wallets = array(
            'usdt' => '',
            'usdc' => ''
        );
        update_option('dredd_ai_crypto_wallets', $default_wallets);

        // Default supported chains
        $default_chains = array(
            'ethereum' => array('name' => 'Ethereum', 'chain_id' => 1, 'enabled' => true),
            'bsc' => array('name' => 'Binance Smart Chain', 'chain_id' => 56, 'enabled' => true),
            'polygon' => array('name' => 'Polygon', 'chain_id' => 137, 'enabled' => true),
            'arbitrum' => array('name' => 'Arbitrum', 'chain_id' => 42161, 'enabled' => true),
            'pulsechain' => array('name' => 'PulseChain', 'chain_id' => 369, 'enabled' => true)
        );
        update_option('dredd_ai_supported_chains', $default_chains);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('DREDD AI', 'dredd-ai'),
            __('DREDD AI', 'dredd-ai'),
            'manage_options',
            'dredd-ai',
            array($this, 'admin_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#FFFFFF"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>'),
            30
        );

        // Submenus
        add_submenu_page('dredd-ai', __('Dashboard', 'dredd-ai'), __('Dashboard', 'dredd-ai'), 'manage_options', 'dredd-ai', array($this, 'admin_page'));
        add_submenu_page('dredd-ai', __('Users', 'dredd-ai'), __('Users', 'dredd-ai'), 'manage_options', 'dredd-ai-users', array($this, 'users_page'));
        add_submenu_page('dredd-ai', __('Settings', 'dredd-ai'), __('Settings', 'dredd-ai'), 'manage_options', 'dredd-ai-settings', array($this, 'settings_page'));
        add_submenu_page('dredd-ai', __('Payments', 'dredd-ai'), __('Payments', 'dredd-ai'), 'manage_options', 'dredd-ai-payments', array($this, 'payments_page'));
        add_submenu_page('dredd-ai', __('Promotions', 'dredd-ai'), __('Promotions', 'dredd-ai'), 'manage_options', 'dredd-ai-promotions', array($this, 'promotions_page'));
    }

    public function admin_page()
    {
        $admin = new Dredd_Admin();
        $admin->dashboard_page();
    }

    public function settings_page()
    {
        $admin = new Dredd_Admin();
        $admin->settings_page();
    }

    public function payments_page()
    {
        $admin = new Dredd_Admin();
        $admin->payments_page();
    }

    public function promotions_page()
    {
        $admin = new Dredd_Admin();
        $admin->promotions_page();
    }

    public function users_page()
    {
        $admin = new Dredd_Admin();
        $admin->users_page();
    }

    public function enqueue_public_assets()
    {
        if (is_admin())
            return;

        wp_enqueue_script('jquery');

        // Enqueue payment modal CSS first
        wp_enqueue_style('dredd-payment-modal', DREDD_AI_PLUGIN_URL . 'assets/css/payment-modal.css', array(), DREDD_AI_VERSION);

        // Then enqueue main public CSS
        wp_enqueue_style('dredd-ai-public', DREDD_AI_PLUGIN_URL . 'assets/css/public.css', array('dredd-payment-modal'), DREDD_AI_VERSION);

        // Enqueue Stripe.js if Stripe is configured
        if (dredd_ai_get_option('stripe_publishable_key', '')) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), '3.0', true);
        }

        // Enqueue QR Code library for crypto payments
        wp_enqueue_script('qrcode-js', DREDD_AI_PLUGIN_URL . 'assets/js/qrcode.min.js', array('jquery'), DREDD_AI_VERSION, true);

        // Enqueue reCAPTCHA if configured
        $recaptcha_site_key = dredd_ai_get_option('recaptcha_site_key', '');
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }

        wp_enqueue_script('dredd-ai-public', DREDD_AI_PLUGIN_URL . 'assets/js/public.js', array('jquery', 'qrcode-js'), DREDD_AI_VERSION, true);

        // Enqueue WordPress Heartbeat for real-time updates
        if (is_user_logged_in()) {
            wp_enqueue_script('heartbeat');
        }

        // Enqueue wallet connect script if paid mode is enabled
        if (dredd_ai_is_paid_mode_enabled()) {
            wp_enqueue_script('dredd-wallet-connect', DREDD_AI_PLUGIN_URL . 'assets/js/wallet-connect.js', array('jquery', 'dredd-ai-public'), DREDD_AI_VERSION, true);
        }

        wp_localize_script('dredd-ai-public', 'dredd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dredd_admin_nonce'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'plugin_url' => DREDD_AI_PLUGIN_URL,
            'paid_mode_enabled' => dredd_ai_is_paid_mode_enabled() ? 'true' : 'false',
            'analysis_cost' => dredd_ai_get_option('analysis_cost', 5),
            'stripe_publishable_key' => dredd_ai_get_option('stripe_publishable_key', ''),
            'recaptcha_site_key' => dredd_ai_get_option('recaptcha_site_key', ''),
            'current_user' => is_user_logged_in() ? array(
                'id' => get_current_user_id(),
                'username' => wp_get_current_user()->user_login,
                'display_name' => wp_get_current_user()->display_name,
                'credits' => dredd_ai_get_user_credits(get_current_user_id())
            ) : null,
            'strings' => array(
                'loading' => __('Analyzing...', 'dredd-ai'),
                'error' => __('System malfunction! Try again, citizen!', 'dredd-ai'),
                'network_error' => __('Communication breakdown! Check your connection!', 'dredd-ai')
            )
        ));

    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'dredd-ai') === false)
            return;

        wp_enqueue_style('dredd-ai-admin', DREDD_AI_PLUGIN_URL . 'assets/css/admin.css', array(), DREDD_AI_VERSION);
        wp_enqueue_script('dredd-ai-admin', DREDD_AI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), DREDD_AI_VERSION, true);

        wp_localize_script('dredd-ai-admin', 'dredd_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dredd_admin_nonce')
        ));
    }

    public function chat_shortcode($atts)
    {
        $public = new Dredd_Public();
        return $public->render_chat_interface($atts);
    }

    public function user_dashboard_shortcode($atts)
    {
        $public = new Dredd_Public();
        return $public->render_user_dashboard($atts);
    }

    public function handle_chat_ajax()
    {
        try {
            $security = new Dredd_Security();

            // Check if user is logged in for debug info
            $is_logged_in = is_user_logged_in();

            // Pass false to allow non-logged in users for public chat
            if (!$security->verify_ajax_request(false)) {
                wp_die(__('Security check failed', 'dredd-ai'));
            }


            $n8n = new Dredd_N8N();

            $n8n->handle_chat_request();

        } catch (Exception $e) {
            wp_send_json_error('Internal error: ' . $e->getMessage());
        } catch (Error $e) {
            wp_send_json_error('Fatal error: ' . $e->getMessage());
        }
    }

    /**
     * DEBUG: Simple test endpoint to verify AJAX works
     */
    public function handle_debug_test()
    {
        dredd_ai_log('DREDD DEBUG - Test endpoint called', 'debug');
        dredd_ai_log('DREDD DEBUG - POST data: ' . json_encode($_POST), 'debug');

        wp_send_json_success(array(
            'message' => 'DEBUG: AJAX system is working!',
            'timestamp' => current_time('mysql'),
            'post_data' => $_POST
        ));
    }

    /**
     * DEBUG: View recent debug logs
     */
    public function handle_view_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        // Find debug log
        $log_locations = array(
            WP_CONTENT_DIR . '/debug.log',
            ABSPATH . 'wp-content/debug.log',
            ABSPATH . 'debug.log'
        );

        $logs = array();
        foreach ($log_locations as $log_file) {
            if (file_exists($log_file)) {
                $lines = file($log_file);
                $dredd_lines = array();

                // Get last 100 lines that contain DREDD
                foreach (array_reverse($lines) as $line) {
                    if (strpos($line, 'DREDD') !== false) {
                        $dredd_lines[] = $line;
                        if (count($dredd_lines) >= 100)
                            break;
                    }
                }

                $logs[] = array(
                    'file' => $log_file,
                    'lines' => array_reverse($dredd_lines)
                );
                break;
            }
        }

        header('Content-Type: text/html');
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>DREDD Debug Logs</title>
            <style>
                body {
                    font-family: 'Poppins', monospace;
                    background: #000;
                    color: #0f0;
                    padding: 20px;
                }

                .log-entry {
                    margin: 5px 0;
                    padding: 5px;
                    background: #111;
                    border-left: 3px solid #0f0;
                }

                .error {
                    border-left-color: #f00;
                    color: #f88;
                }

                .warning {
                    border-left-color: #fa0;
                    color: #ffa;
                }

                .debug {
                    border-left-color: #08f;
                    color: #8cf;
                }
            </style>
        </head>

        <body>
            <h1>🔍 DREDD Debug Logs</h1>
            <?php if (empty($logs)): ?>
                <p>No debug logs found. Enable WordPress debugging in wp-config.php:</p>
                <pre>define('WP_DEBUG', true);
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        define('WP_DEBUG_LOG', true);</pre>
            <?php else: ?>
                <?php foreach ($logs as $log_info): ?>
                    <h2>📄 <?php echo esc_html($log_info['file']); ?></h2>
                    <?php foreach ($log_info['lines'] as $line): ?>
                        <?php
                        $class = 'log-entry';
                        if (strpos($line, 'error') !== false)
                            $class .= ' error';
                        elseif (strpos($line, 'warning') !== false)
                            $class .= ' warning';
                        elseif (strpos($line, 'debug') !== false)
                            $class .= ' debug';
                        ?>
                        <div class="<?php echo $class; ?>"><?php echo esc_html($line); ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <br><button onclick="location.reload()">🔄 Refresh Logs</button>
        </body>

        </html>
        <?php
        exit;
    }


    public function handle_payment()
    {
        $security = new Dredd_Security();
        if (!$security->verify_ajax_request()) {
            wp_die(__('Security check failed', 'dredd-ai'));
        }

        $payments = new Dredd_Payments();
        $payments->process_payment();
    }

    public function cleanup_expired_data()
    {
        $database = new Dredd_Database();
        $database->cleanup_expired_analysis();
    }

    public function cleanup_cache()
    {
        $database = new Dredd_Database();
        $database->cleanup_expired_cache();
    }

    public function handle_nowpayments_create()
    {
        $nowpayments = new Dredd_NOWPayments();
        $nowpayments->create_payment();
    }

    public function handle_nowpayments_status()
    {
        $nowpayments = new Dredd_NOWPayments();
        $nowpayments->check_payment_status();
    }

    public function handle_nowpayments_webhook()
    {
        $nowpayments = new Dredd_NOWPayments();
        $nowpayments->handle_webhook();
    }

    /**
     * Test NOWPayments connection
     */
    public function handle_test_nowpayments_connection()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $nowpayments = new Dredd_NOWPayments();
        $result = $nowpayments->test_api_connection();

        wp_send_json($result);
    }

    /**
     * Debug complete payment flow
     */
    public function handle_debug_payment_flow()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $test_results = array();

        // Test 1: Database tables exist
        global $wpdb;
        $payments_table = $wpdb->prefix . 'dredd_payments';
        $transactions_table = $wpdb->prefix . 'dredd_transactions';

        $payments_exists = $wpdb->get_var("SHOW TABLES LIKE '{$payments_table}'") === $payments_table;
        $transactions_exists = $wpdb->get_var("SHOW TABLES LIKE '{$transactions_table}'") === $transactions_table;

        $test_results['database'] = array(
            'dredd_payments_table' => $payments_exists ? 'EXISTS' : 'MISSING',
            'dredd_transactions_table' => $transactions_exists ? 'EXISTS' : 'MISSING'
        );

        // Test 2: Currency mapping validation
        $frontend_currencies = array(
            'bitcoin',
            'ethereum',
            'litecoin',
            'dogecoin',
            'tether-trc20',
            'tether-erc20',
            'tether-bep20',
            'usdcoin',
            'pulsechain'
        );

        $mapping_results = array();
        foreach ($frontend_currencies as $frontend_currency) {
            $normalized = Dredd_Validation::normalize_payment_method($frontend_currency);
            $validation = Dredd_Validation::validate_payment_method($frontend_currency);

            $mapping_results[$frontend_currency] = array(
                'normalized' => $normalized,
                'valid' => $validation['valid'],
                'has_hyphens' => strpos($normalized, '-') !== false ? 'YES (ERROR)' : 'NO (GOOD)',
                'error' => $validation['valid'] ? null : $validation['error']
            );
        }

        $test_results['currency_mapping'] = $mapping_results;

        // Test 3: NOWPayments configuration
        $nowpayments = new Dredd_NOWPayments();
        $api_test = $nowpayments->test_api_connection();
        $test_results['nowpayments_api'] = $api_test;

        // Test 4: Payment addresses configuration
        $live_addresses = dredd_ai_get_option('live_crypto_addresses', array());
        $test_results['payment_addresses'] = array(
            'configured_addresses' => count($live_addresses),
            'addresses' => $live_addresses
        );

        // Test 5: Mock validation test
        $mock_payment_data = array(
            'amount' => 10.00,
            'method' => 'ethereum' // Frontend sends this
        );

        $validation_test = Dredd_Validation::validate_payment_request($mock_payment_data);
        $test_results['validation_test'] = array(
            'input' => $mock_payment_data,
            'result' => $validation_test
        );

        wp_send_json_success(array(
            'test_results' => $test_results,
            'summary' => array(
                'database_ready' => $payments_exists && $transactions_exists,
                'currency_mapping_good' => !array_filter($mapping_results, function ($r) {
                    return strpos($r['normalized'], '-') !== false;
                }),
                'api_connected' => $api_test['success'] ?? false,
                'addresses_configured' => count($live_addresses) > 0
            ),
            'message' => 'Complete payment flow debug test completed'
        ));
    }

    /**
     * Authentication Handlers
     */
    public function handle_login()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dredd_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

        if (empty($username) || empty($password)) {
            wp_send_json_error('Username and password are required');
        }

        // Verify reCAPTCHA if configured
        if (!$this->verify_recaptcha($recaptcha_response)) {
            wp_send_json_error('Please complete the security verification');
        }


        // If WordPress auth fails, check our custom table
        global $wpdb;
        $chat_users_table = $wpdb->prefix . 'dredd_chat_users';

        $chat_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$chat_users_table} WHERE (username = %s OR email = %s) AND password = %s",
            $username,
            $username,
            $password
        ));

        if ($chat_user) {
            $wp_user = get_user_by('email', $chat_user->email);// Log in the user using WP auth cookie
            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, $remember);
            $user = $wp_user;

            wp_send_json_success(array(
                'message' => 'Welcome back, citizen! Justice awaits.',
                'user' => array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'credits' => dredd_ai_get_user_credits($user->ID)
                )
            ));
            return;

        } else {
            dredd_ai_log('Login failed for username: ' . $username, 'warning');
            wp_send_json_error('Invalid credentials. Justice denied!');
            return;
        }
    }

    public function handle_register()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dredd_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms = isset($_POST['terms']);
        $newsletter = isset($_POST['newsletter']);
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

        // Basic validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error('All fields are required');
        }

        if ($password !== $confirm_password) {
            wp_send_json_error('Passwords do not match');
        }

        if (strlen($password) < 6) {
            wp_send_json_error('Password must be at least 6 characters long');
        }

        if (!$terms) {
            wp_send_json_error('You must agree to the terms of service');
        }

        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }

        global $wpdb;
        $chat_users_table = $wpdb->prefix . 'dredd_chat_users';
        $wp_user = get_user_by('email', $email);
        if ($wp_user) {
            $user_id = $wp_user->ID;

            $chat_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$chat_users_table} WHERE id = %d",
                $user_id
            ));

            if (!$chat_user) {
                $wpdb->insert(
                    $chat_users_table,
                    array(
                        'id' => $user_id,
                        'username' => $username,
                        'password' => $password,
                        'email' => $email,
                        'created_at' => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
            } else {
                wp_send_json_error('Username or email already exists');
            }
        } else {
            $random_password = wp_generate_password();
            $user_id = wp_create_user($username, $random_password, $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error('Could not create WP user: ' . $user_id->get_error_message());
            }
            $wpdb->insert(
                $chat_users_table,
                array(
                    'id' => $user_id,
                    'username' => $username,
                    'password' => $password,
                    'email' => $email,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
        }

        add_filter('wp_mail', '__return_false');
        $user_id = wp_create_user($username, $password, $email);
        remove_filter('wp_mail', '__return_false');

        if (is_wp_error($user_id)) {
            dredd_ai_log('WordPress user creation failed: ' . $user_id->get_error_message(), 'error');
            $user_id = 0;
        } else {
            // Update user meta only if WordPress user was created
            update_user_meta($user_id, 'dredd_newsletter_subscription', $newsletter);
            update_user_meta($user_id, 'dredd_registration_date', current_time('mysql'));
            update_user_meta($user_id, 'dredd_email_verified', true);

            // Give welcome credits only if paid mode is enabled
            // if (dredd_ai_is_paid_mode_enabled()) {
            //     $welcome_credits = dredd_ai_get_option('welcome_credits', 10);
            //     dredd_ai_add_credits($user_id, $welcome_credits);
            // }
        }

        wp_send_json_success(array(
            'message' => 'Registration successful! Welcome to DREDD AI.',
            'auto_login' => $user_id > 0
        ));
    }

    public function handle_forgot_password()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dredd_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($email)) {
            wp_send_json_error('Email address is required');
        }

        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            // Don't reveal if email exists or not for security
            wp_send_json_success(array(
                'message' => 'This email was not registered, please check email address again.'
            ));
            return;
        }

        // Generate reset key
        $reset_key = get_password_reset_key($user);
        if (is_wp_error($reset_key)) {
            wp_send_json_error('Unable to generate reset key');
        }

        // Get the current page URL to redirect back to after reset
        $current_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url('/');

        // Store the return URL for use after password reset
        update_user_meta($user->ID, 'dredd_reset_return_url', $current_url);

        // Send reset email
        $reset_url = add_query_arg(array(
            'action' => 'rp',
            'key' => $reset_key,
            'login' => rawurlencode($user->user_login),
            'dredd_reset' => '1'
        ), home_url('/'));

        $subject = 'DREDD AI - Password Reset Request';
        $message = "
        <div
      style='
        font-family: Poppins, -apple-system, BlinkMacSystemFont, sans-serif;
        max-width: 500px;
        margin: 0 auto;
        background: #0a0a0a;
        color: #c0c0c0;
      '
    >
      <div
        style='
          background: linear-gradient(
            135deg,
            #0a0a0a 0%,
            #1a1a1a 50%,
            #000000 100%
          );
          border: 2px solid #29c6df;
          border-radius: 20px;
          padding: 32px;
          margin: 20px;
          box-shadow: 0 0 60px rgb(58 154 191 / 40%);
        '
      >
        <div style='text-align: center; margin-bottom: 30px'>
          <h1
            style='color: #ffffff; font-size: 28px; font-weight: 700; margin: 0'
          >
            PASSWORD RESET REQUEST
          </h1>
          <div
            style=' 
              width: 60px;
              height: 4px;
              background: linear-gradient(90deg, #0066ff, #3bdbff);
              margin: 15px auto;
              border-radius: 2px;
            '
          ></div>
        </div>

        <div
          style='
            background: rgba(255, 215, 0, 0.05);
            border: 2px solid #00ffd0;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
          '
        >
          <p
            style='
              font-size: 18px;
              margin-bottom: 20px;
              color: #ffffff;
              font-weight: 600;
            '
          >
            A password reset was requested for your DREDD AI account.
          </p>

          <div
            style='
              background: rgba(26, 26, 26, 0.8);
              border: 1px solid rgba(255, 215, 0, 0.3);
              border-radius: 10px;
              padding: 20px;
              margin: 20px 0;
            '
          >
            <p style='margin: 8px 0; color: #c0c0c0'>
              <strong style='color: #ffffff'>Username:</strong> " . $user->user_login . "
            </p>
            <p style='margin: 8px 0; color: #c0c0c0'>
              <strong style='color: #ffffff'>Email:</strong> " . $user->user_email . "
            </p>
          </div>
        </div>

        <div style='text-align: center; margin: 30px 0'>
          <p style='color: #c0c0c0; margin-bottom: 25px; font-size: 16px'>
            Click the button below to reset your password:
          </p>
          <a
            href='{$reset_url}'
            style='
              display: inline-block;
              background: linear-gradient(135deg, #00ffdc 0%, #02abfb 100%);
              color: #0a0a0a;
              padding: 18px 35px;
              text-decoration: none;
              border-radius: 25px;
              font-weight: 700;
              font-size: 14px;
              text-transform: uppercase;
              letter-spacing: 1px;
              box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
              transition: all 0.3s ease;
            '
            >RESET PASSWORD</a
          >
        </div>

        <div
          style='
            border-top: 1px solid rgba(255, 215, 0, 0.2);
            padding-top: 20px;
            text-align: center;
          '
        >
          <p
            style='
              font-size: 12px;
              color: rgba(192, 192, 192, 0.7);
              margin: 8px 0;
            '
          >
            If you didn't request this, you can safely ignore this email.
          </p>
          <p
            style='
              font-size: 12px;
              color: rgba(192, 192, 192, 0.7);
              margin: 8px 0;
            '
          >
            This link will expire in 24 hours.
          </p>
          <p
            style='
              font-size: 11px;
              color: rgba(192, 192, 192, 0.5);
              margin: 10px 0 0 0;
            '
          >
            Justice never forgets. I AM THE LAW!
          </p>
        </div>
      </div>
    </div>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (wp_mail($user->user_email, $subject, $message, $headers)) {
            dredd_ai_log('Password reset email sent to: ' . $email, 'info');
            wp_send_json_success(array(
                'message' => 'Password reset link has been sent to your email address.'
            ));
        } else {
            dredd_ai_log('Failed to send password reset email to: ' . $email, 'error');
            wp_send_json_error('Failed to send reset email. Please try again.');
        }
    }

    public function handle_email_verification()
    {
        // Check if this is an email verification request
        if (isset($_GET['dredd_verify_email'])) {
            $token = sanitize_text_field($_GET['dredd_verify_email']);

            if (empty($token)) {
                wp_die('Invalid verification token.');
            }

            // Find user by verification token
            $users = get_users(array(
                'meta_key' => 'dredd_email_verification_token',
                'meta_value' => $token
            ));

            if (empty($users)) {
                wp_die('Invalid or expired verification token.');
            }

            $user = $users[0];

            // Get the stored return URL
            $return_url = get_user_meta($user->ID, 'dredd_verification_return_url', true);
            if (empty($return_url)) {
                $return_url = home_url('/');
            }

            // Mark email as verified
            update_user_meta($user->ID, 'dredd_email_verified', true);
            delete_user_meta($user->ID, 'dredd_email_verification_token');
            delete_user_meta($user->ID, 'dredd_verification_return_url'); // Clean up

            // Give welcome credits
            $welcome_credits = dredd_ai_get_option('welcome_credits', 10);
            dredd_ai_add_credits($user->ID, $welcome_credits);

            dredd_ai_log('Email verified for user: ' . $user->user_login . ' (ID: ' . $user->ID . ')', 'info');

            // Redirect to the stored return URL with success message
            $redirect_url = add_query_arg('email_verified', '1', $return_url);

            wp_redirect($redirect_url);
            exit;
        }
    }

    public function handle_logout()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dredd_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Log out the user
        wp_logout();

        dredd_ai_log('User logged out successfully', 'info');

        wp_send_json_success(array(
            'message' => 'Justice served. You have been logged out.',
            'redirect' => false // Don't redirect, stay on current page
        ));
    }

    /**
     * Verify reCAPTCHA response
     */
    private function verify_recaptcha($response)
    {
        $recaptcha_secret = dredd_ai_get_option('recaptcha_secret_key', '');

        // If reCAPTCHA is not configured, skip verification
        if (empty($recaptcha_secret) || empty($response)) {
            return empty($recaptcha_secret); // Return true if not configured, false if configured but no response
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array(
            'secret' => $recaptcha_secret,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        );

        $response = wp_remote_post($verify_url, array(
            'body' => $data,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            dredd_ai_log('reCAPTCHA verification failed: ' . $response->get_error_message(), 'error');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result || !isset($result['success'])) {
            dredd_ai_log('reCAPTCHA verification invalid response: ' . $body, 'error');
            return false;
        }

        if (!$result['success']) {
            dredd_ai_log('reCAPTCHA verification failed: ' . json_encode($result), 'warning');
            return false;
        }

        return true;
    }

    public function handle_password_reset()
    {
        // Check if this is a password reset request
        if (isset($_GET['action']) && $_GET['action'] === 'rp' && isset($_GET['dredd_reset'])) {
            $key = sanitize_text_field($_GET['key'] ?? '');
            $login = sanitize_text_field($_GET['login'] ?? '');

            if (empty($key) || empty($login)) {
                wp_redirect(home_url('/?reset_error=invalid_link'));
                exit;
            }

            // Verify the reset key
            $user = check_password_reset_key($key, $login);
            if (is_wp_error($user)) {
                wp_redirect(home_url('/?reset_error=invalid_or_expired'));
                exit;
            }

            // If POST request, process password reset
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($new_password) || strlen($new_password) < 6) {
                    wp_redirect(home_url('/?reset_error=password_too_short&key=' . $key . '&login=' . $login . '&dredd_reset=1'));
                    exit;
                }

                if ($new_password !== $confirm_password) {
                    wp_redirect(home_url('/?reset_error=passwords_mismatch&key=' . $key . '&login=' . $login . '&dredd_reset=1'));
                    exit;
                }

                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'dredd_chat_users',
                    ['password' => $new_password],
                    ['id' => $user->ID],
                    ['%s'],
                    ['%d']
                );

                $return_url = get_user_meta($user->ID, 'dredd_reset_return_url', true);
                if (empty($return_url)) {
                    $return_url = home_url('/');
                }

                delete_user_meta($user->ID, 'dredd_reset_return_url');

                $redirect_url = add_query_arg('password_reset', '1', $return_url);

                wp_redirect($redirect_url);
                exit;
            }

            // Show password reset form
            $this->show_password_reset_form($key, $login);
            exit;
        }
    }

    private function show_password_reset_form($key, $login)
    {
        $reset_error = $_GET['reset_error'] ?? '';
        $error_message = '';

        switch ($reset_error) {
            case 'password_too_short':
                $error_message = 'Password must be at least 6 characters long.';
                break;
            case 'passwords_mismatch':
                $error_message = 'Passwords do not match.';
                break;
            case 'invalid_or_expired':
                $error_message = 'Invalid or expired reset link.';
                break;
            case 'invalid_link':
                $error_message = 'Invalid reset link.';
                break;
        }

        ?><!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>DREDD AI - Reset Password</title>
            <style>
                body {
                    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #000000 100%);
                    color: #00FFFF;
                    margin: 0;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .reset-container {
                    background: linear-gradient(145deg, rgba(15, 15, 15, 0.95) 0%, rgba(25, 25, 25, 0.95) 100%);
                    border: 2px solid #00FFFF;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 400px;
                    width: 100%;
                    box-shadow: 0 0 60px rgba(0, 255, 255, 0.4);
                }

                h1 {
                    text-align: center;
                    margin-bottom: 30px;
                    color: #00FFFF;
                    text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
                }

                .form-group {
                    margin-bottom: 20px;
                }

                .password-input-container {
                    position: relative;
                    display: flex;
                    align-items: center;
                }

                .password-input-container input {
                    padding-right: 45px;
                }

                .password-toggle {
                    position: absolute;
                    right: 12px;
                    background: linear-gradient(135deg, rgba(0, 255, 255, 0.1), rgba(64, 224, 208, 0.1));
                    border: 1.5px solid rgba(0, 255, 255, 0.3);
                    cursor: pointer;
                    padding: 8px;
                    color: #40E0D0;
                    font-size: 18px;
                    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
                    border-radius: 8px;
                    z-index: 10;
                    pointer-events: auto;
                    user-select: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 36px;
                    min-height: 36px;
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                    box-shadow:
                        0 2px 8px rgba(0, 255, 255, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
                }

                .password-toggle:hover {
                    color: #FFFFFF;
                    background: linear-gradient(135deg, rgba(0, 255, 255, 0.2), rgba(64, 224, 208, 0.2));
                    border-color: #00FFFF;
                    transform: scale(1.05);
                    box-shadow:
                        0 4px 15px rgba(0, 255, 255, 0.3),
                        0 0 20px rgba(0, 255, 255, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
                }

                .password-toggle:active {
                    transform: scale(0.95);
                    background: linear-gradient(135deg, rgba(0, 255, 255, 0.3), rgba(64, 224, 208, 0.3));
                    box-shadow:
                        0 2px 8px rgba(0, 255, 255, 0.4),
                        inset 0 2px 4px rgba(0, 0, 0, 0.2);
                }

                .password-toggle .eye-icon {
                    font-size: 16px;
                    line-height: 1;
                    display: block;
                    text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);
                    transition: all 0.2s ease;
                }

                .password-toggle:hover .eye-icon {
                    text-shadow: 0 0 10px rgba(0, 255, 255, 0.8);
                }

                label {
                    display: block;
                    margin-bottom: 8px;
                    color: #40E0D0;
                    font-weight: 600;
                }

                input[type="password"],
                input[type="text"] {
                    width: 100%;
                    padding: 15px 50px 15px 18px;
                    background: linear-gradient(135deg, rgba(0, 0, 0, 0.7), rgba(20, 20, 20, 0.7));
                    border: 1.5px solid rgba(0, 255, 255, 0.3);
                    border-radius: 12px;
                    color: #FFFFFF !important;
                    caret-color: #00FFFF !important;
                    font-size: 14px;
                    font-family: inherit;
                    box-sizing: border-box;
                    backdrop-filter: blur(15px);
                    -webkit-backdrop-filter: blur(15px);
                    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
                    text-shadow: 0 0 1px rgba(255, 255, 255, 0.8);
                    -webkit-text-fill-color: #FFFFFF !important;
                }

                input[type="password"]:focus,
                input[type="text"]:focus {
                    outline: none;
                    border-color: #00FFFF;
                    box-shadow:
                        0 0 25px rgba(0, 255, 255, 0.4),
                        0 0 50px rgba(0, 255, 255, 0.15),
                        inset 0 1px 0 rgba(0, 255, 255, 0.2);
                    background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(64, 224, 208, 0.05));
                    transform: translateY(-1px);
                }

                input[type="password"]::placeholder,
                input[type="text"]::placeholder {
                    color: rgba(192, 192, 192, 0.6);
                    opacity: 0.8;
                    font-size: 13px;
                }

                .submit-btn {
                    width: 100%;
                    padding: 14px 20px;
                    background: linear-gradient(135deg, #00FFFF 0%, #40E0D0 100%);
                    border: none;
                    border-radius: 12px;
                    color: #0a0a0a;
                    font-weight: 700;
                    font-size: 14px;
                    cursor: pointer;
                    text-transform: uppercase;
                    letter-spacing: 0.8px;
                }

                .submit-btn:hover {
                    background: linear-gradient(135deg, #40E0D0 0%, #0080FF 100%);
                    transform: translateY(-2px);
                }

                .error-message {
                    background: rgba(255, 68, 68, 0.1);
                    border: 1px solid #ff4444;
                    color: #ff6666;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    text-align: center;
                }

                .back-link {
                    text-align: center;
                    margin-top: 20px;
                }

                .back-link a {
                    color: #40E0D0;
                    text-decoration: none;
                    font-size: 12px;
                }

                .back-link a:hover {
                    color: #00FFFF;
                    text-shadow: 0 0 8px rgba(0, 255, 255, 0.6);
                }
            </style>
        </head>

        <body>
            <div class="reset-container">
                <h1>🔑 RESET PASSWORD</h1>

                <?php if ($error_message): ?>
                    <div class="error-message"><?php echo esc_html($error_message); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label for="new_password">🔑 New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="new_password" name="new_password" required
                                placeholder="Enter your new password" minlength="6">
                            <button type="button" class="password-toggle" data-target="new_password">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">🔒 Confirm Password</label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" required
                                placeholder="Confirm your new password" minlength="6">
                            <button type="button" class="password-toggle" data-target="confirm_password">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">RECLAIM ACCESS</button>
                </form>

                <div class="back-link">
                    <a href="javascript:void(0);" onclick="openChatWindow()">Back to DREDD AI</a>
                </div>
            </div>

            <script>
                // Password toggle functionality
                document.addEventListener('DOMContentLoaded', function () {
                    // Password toggle event handlers
                    document.querySelectorAll('.password-toggle').forEach(function (button) {
                        button.addEventListener('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();

                            const targetId = this.getAttribute('data-target');
                            const input = document.getElementById(targetId);
                            const eyeIcon = this.querySelector('.eye-icon');

                            if (input && eyeIcon) {
                                const currentType = input.getAttribute('type');

                                // Add glow effect to button
                                this.style.boxShadow = '0 0 20px rgba(0, 255, 255, 0.6), 0 0 40px rgba(0, 255, 255, 0.3)';

                                if (currentType === 'password') {
                                    input.setAttribute('type', 'text');
                                    eyeIcon.textContent = '🙈'; // See no evil monkey
                                    console.log('Password now visible');
                                } else {
                                    input.setAttribute('type', 'password');
                                    eyeIcon.textContent = '👁️'; // Eye
                                    console.log('Password now hidden');
                                }

                                // Enhanced visual feedback with bounce effect
                                this.style.transform = 'scale(0.9)';
                                eyeIcon.style.transform = 'rotate(10deg)';

                                setTimeout(() => {
                                    this.style.transform = 'scale(1.05)';
                                    eyeIcon.style.transform = 'rotate(0deg)';
                                }, 100);

                                setTimeout(() => {
                                    this.style.transform = 'scale(1)';
                                    this.style.boxShadow = '0 2px 8px rgba(0, 255, 255, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.1)';
                                }, 200);

                                // Focus back to input with cursor at end
                                setTimeout(() => {
                                    input.focus();
                                    const length = input.value.length;
                                    input.setSelectionRange(length, length);
                                }, 50);
                            }
                        });
                    });
                });

                // Function to open chat window
                function openChatWindow() {
                    // Try to find the parent window (if this is opened in a popup/iframe)
                    if (window.opener && !window.opener.closed) {
                        // Close this popup and focus parent
                        window.opener.focus();
                        window.close();
                        return;
                    }

                    // Find a page that has the dredd_chat shortcode
                    // First try current page with chat parameter
                    const homeUrl = '<?php echo home_url(); ?>';
                    const currentUrl = window.location.origin;

                    // Try to find chat interface on common pages
                    const potentialChatPages = [
                        homeUrl + '?open_chat=1',
                        currentUrl + '/?open_chat=1',
                        homeUrl + '/chat/?open_chat=1',
                        homeUrl + '/dredd/?open_chat=1',
                        homeUrl + '/token-analysis/?open_chat=1'
                    ];

                    // Always try homepage first as it's most likely to have the chat
                    window.location.href = potentialChatPages[0];
                }

                // Client-side password confirmation
                document.querySelector('form').addEventListener('submit', function (e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        return false;
                    }

                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }
                });
            </script>
        </body>

        </html>
        <?php
    }

    /**
     * Handle user dashboard data request
     */
    public function handle_get_user_dashboard_data()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce') || !is_user_logged_in()) {
            wp_send_json_error('Invalid request');
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $database = new Dredd_Database();

        try {
            // Get user data
            $user_data = $database->get_user_data($user_id);

            // Get recent analysis history (last 10)
            $history = $database->get_user_analysis_history($user_id, 10, 0, array());



            $response_data = array(
                'user' => array(
                    'id' => $user_id,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'expires_at' => $user_data['user_data']->expires_at,
                ),
                'stats' => array(
                    'total_analyses' => $user_data['stats']->total_analyses ?? 0,
                    'scams_detected' => $user_data['stats']->scams_detected ?? 0,
                    'psycho_analyses' => $user_data['stats']->psycho_analyses ?? 0
                ),
                'history' => $history['results'] ?? array()
            );

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            dredd_ai_log('Dashboard data error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to load dashboard data');
        }
    }
    public function get_user_data()
    {
        $database = new Dredd_Database();
        $user_id = get_current_user_id();
        $user_data = $database->get_user_data($user_id);
        wp_send_json_success([
            'expires_at' => $user_data['user_data']->expires_at
        ]);
    }


    /**
     * Handle promotion submission
     */
    public function handle_submit_promotion()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce') || !is_user_logged_in()) {
            wp_send_json_error('Invalid request');
        }

        $user_id = get_current_user_id();
        $token_name = sanitize_text_field($_POST['token_name'] ?? '');
        $contract_address = sanitize_text_field($_POST['contract_address'] ?? '');
        $chain = sanitize_text_field($_POST['chain'] ?? '');
        $website = esc_url_raw($_POST['website'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        // Validation
        if (empty($token_name) || empty($contract_address) || empty($chain)) {
            wp_send_json_error('Please fill in all required fields');
        }

        // Validate contract address format
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $contract_address)) {
            wp_send_json_error('Invalid contract address format');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';

        try {
            $result = $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'token_name' => $token_name,
                    'contract_address' => $contract_address,
                    'chain' => $chain,
                    'website_url' => $website,
                    'description' => $description,
                    'status' => 'pending',
                    'submitted_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                // Send notification email to admin
                $admin_email = get_option('admin_email');
                $subject = 'New Token Promotion Submission - DREDD AI';
                $message = sprintf(
                    "New token promotion submitted:\n\nToken: %s\nContract: %s\nChain: %s\nWebsite: %s\nDescription: %s\n\nReview at: %s",
                    $token_name,
                    $contract_address,
                    $chain,
                    $website,
                    $description,
                    admin_url('admin.php?page=dredd-ai-promotions')
                );

                wp_mail($admin_email, $subject, $message);

                wp_send_json_success('Promotion submitted successfully!');
            } else {
                wp_send_json_error('Failed to submit promotion');
            }

        } catch (Exception $e) {
            dredd_ai_log('Promotion submission error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Database error occurred');
        }
    }

    /**
     * Handle user settings update
     */
    public function handle_update_user_settings()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce') || !is_user_logged_in()) {
            wp_send_json_error('Invalid request');
        }

        $user_id = get_current_user_id();
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');

        // Validation
        if (empty($display_name) || empty($email)) {
            wp_send_json_error('Please fill in all fields');
        }

        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
        }

        // Check if email is already in use by another user
        if (email_exists($email) && email_exists($email) !== $user_id) {
            wp_send_json_error('This email address is already in use by another user');
        }

        try {
            global $wpdb;
            $users_table = $wpdb->prefix . 'users';
            $chat_table = $wpdb->prefix . 'dredd_chat_users';

            $user_update_result = $wpdb->update(
                $users_table,
                array(
                    'user_login' => $display_name,
                    'user_email' => $email,
                    'display_name' => $display_name,
                    'user_nicename' => $display_name
                ),
                array('ID' => $user_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            $result = $wpdb->update(
                $chat_table,
                array(
                    'username' => $display_name, // or $display_name if preferred
                    'email' => $email
                ),
                array('id' => $user_id),
                array('%s', '%s'),
                array('%d')
            );

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('Profile updated successfully!');
            }

        } catch (Exception $e) {
            dredd_ai_log('User settings update error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to update profile');
        }
    }

    /**
     * Handle password update
     */
    public function handle_update_user_password()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce') || !is_user_logged_in()) {
            wp_send_json_error('Invalid request');
        }

        $user_id = get_current_user_id();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        if (empty($current_password) || empty($new_password)) {
            wp_send_json_error('Please fill in all fields');
        }

        if (strlen($new_password) < 6) {
            wp_send_json_error('New password must be at least 6 characters long');
        }

        global $wpdb;
        $chat_table = $wpdb->prefix . 'dredd_chat_users';
        $chat_user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $chat_table WHERE id = %d", $user_id)
        );
        if ($chat_user->password !== $current_password) {
            wp_send_json_error('Current password is incorrect.');
        }

        try {

            $result = $wpdb->update(
                $chat_table,
                array('password' => $new_password),
                array('id' => $user_id),
                array('%s'),
                array('%d')
            );

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('Password updated successfully!');
            }

        } catch (Exception $e) {
            dredd_ai_log('Password update error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to update password');
        }
    }

    /**
     * Handle real-time user update checks
     */
    public function handle_check_user_updates()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce') || !is_user_logged_in()) {
            wp_send_json_error('Invalid request');
        }

        $user_id = get_current_user_id();
        $last_check = get_user_meta($user_id, 'dredd_last_update_check', true);
        $current_time = current_time('timestamp');

        // Check if there are any pending updates
        $pending_updates = get_user_meta($user_id, 'dredd_pending_updates', true);

        if (!empty($pending_updates)) {
            // Clear pending updates
            delete_user_meta($user_id, 'dredd_pending_updates');

            wp_send_json_success(array(
                'has_updates' => true,
                'updates' => $pending_updates
            ));
        }

        // Update last check time
        update_user_meta($user_id, 'dredd_last_update_check', $current_time);

        wp_send_json_success(array(
            'has_updates' => false
        ));
    }

    /**
     * WordPress Heartbeat integration for real-time updates
     */
    public function heartbeat_received($response, $data)
    {
        if (isset($data['dredd_user_check']) && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_data = $data['dredd_user_check'];

            // Check for credit changes
            $current_credits = dredd_ai_get_user_credits($user_id);
            $last_credits = $user_data['last_credits'] ?? null;

            $updates = array();

            if ($last_credits !== null && $last_credits !== $current_credits) {
                $updates['credits_changed'] = true;
                $updates['new_credits'] = $current_credits;
                $updates['old_credits'] = $last_credits;

                // Check if this was an admin adjustment
                $last_transaction = $this->get_last_admin_transaction($user_id);
                if ($last_transaction && $last_transaction->created_at > date('Y-m-d H:i:s', strtotime('-1 minute'))) {
                    $updates['reason'] = $last_transaction->notes ?? 'Admin adjustment';
                }
            }

            // Check for settings changes
            $settings_updated = get_option('dredd_settings_last_updated', 0);
            $user_last_check = get_user_meta($user_id, 'dredd_settings_last_check', true) ?: 0;

            if ($settings_updated > $user_last_check) {
                $updates['settings_changed'] = true;
                update_user_meta($user_id, 'dredd_settings_last_check', $settings_updated);
            }

            if (!empty($updates)) {
                $response['dredd_admin_updates'] = $updates;
            }
        }

        return $response;
    }

    /**
     * Send data with heartbeat
     */
    public function heartbeat_send()
    {
        // Enable heartbeat for logged-in users
        if (is_user_logged_in()) {
            wp_enqueue_script('heartbeat');
        }
    }

    /**
     * Get last admin transaction for user
     */
    private function get_last_admin_transaction($user_id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dredd_transactions 
             WHERE user_id = %d AND payment_method = 'admin_adjustment' 
             ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }
}

// Initialize the plugin
function dredd_ai_init()
{
    return DreddAI::get_instance();
}
add_action('init', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
});
add_action('plugins_loaded', 'dredd_ai_init');

// Helper functions
function dredd_ai_get_option($option, $default = false)
{
    return get_option('dredd_ai_' . $option, $default);
}

function dredd_ai_update_option($option, $value)
{
    return update_option('dredd_ai_' . $option, $value);
}

function dredd_ai_log($message, $level = 'info')
{
    if (dredd_ai_get_option('debug_logging', false)) {
        error_log('[DREDD AI ' . strtoupper($level) . '] ' . $message);
    }
}

function dredd_ai_is_paid_mode_enabled()
{
    return (bool) dredd_ai_get_option('paid_mode_enabled', false);
}

function dredd_ai_get_user_credits($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'dredd_user_tokens';

    $credits = $wpdb->get_var($wpdb->prepare(
        "SELECT token_balance FROM {$table} WHERE user_id = %d",
        $user_id
    ));

    return $credits ? intval($credits) : 0;
}

function dredd_ai_deduct_credits($user_id, $amount)
{
    global $wpdb;
    $table = $wpdb->prefix . 'dredd_user_tokens';

    return $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET token_balance = GREATEST(0, token_balance - %d), updated_at = NOW() WHERE user_id = %d",
        $amount,
        $user_id
    ));
}

function dredd_ai_add_credits($user_id, $amount)
{
    global $wpdb;
    $table = $wpdb->prefix . 'dredd_user_tokens';

    // Insert or update user credits
    return $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (user_id, token_balance, total_purchased, created_at, updated_at) 
         VALUES (%d, %d, %d, NOW(), NOW()) 
         ON DUPLICATE KEY UPDATE 
         token_balance = token_balance + %d, 
         total_purchased = total_purchased + %d, 
         updated_at = NOW()",
        $user_id,
        $amount,
        $amount,
        $amount,
        $amount
    ));
}

function dredd_ai_update_user_credits($user_id, $new_balance)
{
    global $wpdb;
    $table = $wpdb->prefix . 'dredd_user_tokens';

    // Insert or update user credits to specific balance
    return $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (user_id, token_balance, total_purchased, created_at, updated_at) 
         VALUES (%d, %d, 0, NOW(), NOW()) 
         ON DUPLICATE KEY UPDATE 
         token_balance = %d, 
         updated_at = NOW()",
        $user_id,
        $new_balance,
        $new_balance
    ));
}
