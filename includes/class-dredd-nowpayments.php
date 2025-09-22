<?php
/**
 * NOWPayments Integration for DREDD AI
 * Handles cryptocurrency payments via NOWPayments gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_NOWPayments {
    
    private $database;
    private $api_key;
    private $api_url;
    private $webhook_secret;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        $this->api_key = dredd_ai_get_option('nowpayments_api_key', '');
        $this->api_url = 'https://api.nowpayments.io/v1/';
        $this->webhook_secret = dredd_ai_get_option('nowpayments_webhook_secret', '');
        
        // Add webhook handler
        add_action('wp_ajax_dredd_nowpayments_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_nopriv_dredd_nowpayments_webhook', array($this, 'handle_webhook'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dredd_create_nowpayments_payment', array($this, 'create_payment'));
        add_action('wp_ajax_dredd_check_nowpayments_status', array($this, 'check_payment_status'));
    }
    
    /**
     * Get available cryptocurrencies
     */
    public function get_available_currencies() {
        // Don't use cache during debugging - always fetch fresh
        $response = $this->make_api_request('currencies');
        
        if ($response['success']) {
            $currencies = $response['data']['currencies'] ?? array();
            
            // Log all available currencies for debugging
            dredd_ai_log('NOWPayments available currencies: ' . implode(', ', $currencies), 'debug');
            
            return $currencies;
        }
        
        dredd_ai_log('Failed to fetch currencies from NOWPayments API', 'error');
        // Return default currencies if API fails
        return array('btc', 'eth', 'usdt', 'usdc', 'bnb');
    }
    
    /**
     * Get minimum payment amount for currency
     */
    public function get_minimum_amount($currency) {
        try {
            dredd_ai_log("Getting minimum amount for currency: {$currency}", 'debug');
            
            $response = $this->make_api_request("min-amount?currency_from={$currency}&currency_to=usd");
            
            // Log the API response for debugging
            dredd_ai_log("Minimum amount API response for {$currency}: " . json_encode($response), 'debug');
            
            if ($response['success'] && isset($response['data'])) {
                $min_amount = $response['data']['min_amount'] ?? null;
                
                if ($min_amount !== null && is_numeric($min_amount)) {
                    dredd_ai_log("Found minimum amount for {$currency}: {$min_amount} USD", 'debug');
                    return floatval($min_amount);
                } else {
                    dredd_ai_log("Invalid or missing min_amount in response for {$currency}: " . json_encode($response['data']), 'warning');
                }
            } else {
                dredd_ai_log("Failed to get minimum amount for {$currency}. Response: " . json_encode($response), 'warning');
            }
            
        } catch (Exception $e) {
            dredd_ai_log("Exception getting minimum amount for {$currency}: " . $e->getMessage(), 'error');
        }
        
        // Return 0 if we can't get the minimum amount (will skip validation)
        dredd_ai_log("Returning 0 minimum amount for {$currency} (will skip validation)", 'debug');
        return 0;
    }
    
    /**
     * Create payment
     */
    public function create_payment() {
        // Validate payment request
        $validation = Dredd_Validation::validate_payment_request(array(
            'amount' => $_POST['amount'] ?? 0,
            'method' => sanitize_text_field($_POST['currency'] ?? 'bitcoin')
        ));
        
        if (!$validation['valid']) {
            dredd_ai_log('Payment validation failed: ' . implode(', ', $validation['errors']), 'error');
            wp_send_json_error('Payment validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $amount = $validation['data']['amount'];
        $credits = $validation['data']['credits'];
        $currency = $validation['data']['method']; // This is now the normalized currency code
        $raw_currency = sanitize_text_field($_POST['currency'] ?? 'bitcoin');
        $user_id = get_current_user_id();
        
        // Allow non-logged in users for public payments
        if (!$user_id) {
            $user_id = 0; // Use 0 for anonymous users
        }
        
        try {
            // Log the currency mapping for debugging
            dredd_ai_log("Currency mapping: Raw '{$raw_currency}' -> Normalized '{$currency}'", 'debug');
            dredd_ai_log("Creating NOWPayments payment with normalized currency: {$currency}", 'debug');
            
            // Validate currency is supported by NOWPayments
            $supported_currencies = $this->get_available_currencies();
            if (!empty($supported_currencies) && !in_array(strtolower($currency), array_map('strtolower', $supported_currencies))) {
                dredd_ai_log("Currency '{$currency}' not in supported list. Available: " . implode(', ', array_slice($supported_currencies, 0, 20)) . '...', 'error');
                throw new Exception("Currency '{$currency}' is not supported by NOWPayments. Please check admin settings for supported currencies.");
            }
            
            $currency_to_use = $currency;
            
            // Check minimum amount for the currency
            $min_amount = $this->get_minimum_amount($currency_to_use);
            if ($min_amount > 0) {
                dredd_ai_log("Minimum amount for {$currency_to_use}: {$min_amount} USD", 'debug');
                if ($amount < $min_amount) {
                    $error_msg = sprintf(
                        "Payment amount $%.2f is below the minimum required amount of $%.2f for %s. Please increase your payment amount.",
                        $amount,
                        $min_amount,
                        strtoupper($currency_to_use)
                    );
                    dredd_ai_log("Minimum amount validation failed: {$error_msg}", 'error');
                    throw new Exception($error_msg);
                }
            } else {
                dredd_ai_log("Skipping minimum amount validation for {$currency_to_use} (minimum amount could not be determined)", 'debug');
            }
            
            $payment_data = array(
                'price_amount' => $amount,
                'price_currency' => 'usd',
                'pay_currency' => strtolower($currency_to_use), // Use the validated currency code
                'order_id' => 'dredd_' . $user_id . '_' . time(),
                'order_description' => $credits . ' DREDD AI Credits - $' . $amount,
                'ipn_callback_url' => admin_url('admin-ajax.php?action=dredd_nowpayments_webhook'),
                'success_url' => home_url('/dredd-payment-success/'),
                'cancel_url' => home_url('/dredd-payment-cancel/')
            );
            
            // Log payment data being sent to NOWPayments
            dredd_ai_log("NOWPayments request data: " . json_encode($payment_data), 'debug');
            
            $response = $this->make_api_request('payment', $payment_data, 'POST');
            
            // Log the response from NOWPayments
            dredd_ai_log("NOWPayments response: " . json_encode($response), 'debug');
            
            if ($response['success']) {
                $payment_info = $response['data'];
                
                // Get appropriate payment address based on mode
                $configured_addresses = $this->get_payment_addresses();
                $payment_address = $payment_info['pay_address']; // Default to API address
                $is_live = !$this->is_sandbox_mode();
                
                // Debug logging
                $sandbox_setting = dredd_ai_get_option('nowpayments_sandbox', '1');
                dredd_ai_log("DEBUG: Sandbox setting: " . $sandbox_setting, 'debug');
                dredd_ai_log("DEBUG: Live mode: " . ($is_live ? 'YES' : 'NO'), 'debug');
                dredd_ai_log("DEBUG: Configured addresses: " . print_r($configured_addresses, true), 'debug');
                dredd_ai_log("DEBUG: Currency: " . $currency, 'debug');
                dredd_ai_log("DEBUG: Original API address: " . $payment_info['pay_address'], 'debug');
                
                // 🎯 MAP NOWPayments currencies to our address keys
                $mapped_currency = $this->map_currency_to_address_key($currency_to_use);
                dredd_ai_log("DEBUG: Currency: " . $currency . ", Used currency: " . $currency_to_use . ", Mapped to: " . $mapped_currency, 'debug');
                
                // Override with configured address if available
                if (!empty($configured_addresses)) {
                    if (is_array($configured_addresses)) {
                        // Use mapped currency to find the RIGHT address - NO FALLBACK!
                        if (isset($configured_addresses[$mapped_currency])) {
                            $payment_address = $configured_addresses[$mapped_currency];
                            dredd_ai_log("DEBUG: ✅ FOUND! Using mapped currency address: " . $payment_address, 'debug');
                        } else {
                            // 🚨 SPECIFIC CURRENCY NOT FOUND - MUST ERROR!
                            $available_currencies = implode(', ', array_keys($configured_addresses));
                            dredd_ai_log("ERROR: No address configured for {$mapped_currency}. Available: {$available_currencies}", 'error');
                            throw new Exception("No wallet address configured for {$mapped_currency}. Please add your {$mapped_currency} address in admin settings. Available currencies: {$available_currencies}");
                        }
                    } else {
                        $payment_address = $configured_addresses; // Single address
                        dredd_ai_log("DEBUG: Using single configured address: " . $payment_address, 'debug');
                    }
                } else {
                    // 🚨 NO CONFIGURED ADDRESSES - ALWAYS ERROR IN LIVE-ONLY MODE
                    dredd_ai_log("ERROR: No live addresses configured for payment!", 'error');
                    throw new Exception('No wallet addresses configured. Please add your wallet addresses in admin settings to accept payments.');
                }
                
                // Store payment in database
                $package_data = array(
                    'amount' => $amount,
                    'credits' => $credits,
                    'currency' => $currency_to_use, // Use the validated currency
                    'tokens' => $credits, // Add tokens for compatibility
                    'price' => $amount   // Add price for email confirmation
                );
                $this->store_payment_record($user_id, $payment_info, $package_data);
                
                // Generate QR code data URL
                $qr_code_url = null;
                if (!empty($payment_address)) {
                    // Create QR code data for crypto address
                    $qr_data = $payment_address;
                    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qr_data);
                }
                
                wp_send_json_success(array(
                    'payment_id' => $payment_info['payment_id'],
                    'payment_address' => $payment_address,
                    'payment_amount' => $payment_info['pay_amount'],
                    'currency' => strtoupper($payment_info['pay_currency']),
                    'order_id' => $payment_info['order_id'],
                    'payment_url' => $payment_info['invoice_url'] ?? '',
                    'qr_code' => $qr_code_url,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes'))
                ));
                
            } else {
                throw new Exception($response['message']);
            }
            
        } catch (Exception $e) {
            dredd_ai_log('NOWPayments error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Check payment status
     */
    public function check_payment_status() {
        $payment_id = sanitize_text_field($_POST['payment_id'] ?? '');
        
        if (empty($payment_id)) {
            wp_send_json_error('Payment ID required');
        }
        
        $response = $this->make_api_request("payment/{$payment_id}");
        
        if ($response['success']) {
            $payment_data = $response['data'];
            
            wp_send_json_success(array(
                'payment_id' => $payment_data['payment_id'],
                'status' => $payment_data['payment_status'],
                'amount_received' => $payment_data['actually_paid'] ?? 0,
                'currency' => strtoupper($payment_data['pay_currency']),
                'created_at' => $payment_data['created_at'],
                'updated_at' => $payment_data['updated_at']
            ));
            
        } else {
            wp_send_json_error($response['message']);
        }
    }
    
    /**
     * Handle NOWPayments webhook
     */
    public function handle_webhook() {
        $input = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
        
        // Verify webhook signature
        if (!$this->verify_webhook_signature($input, $signature)) {
            dredd_ai_log('NOWPayments webhook signature verification failed', 'error');
            http_response_code(400);
            exit('Invalid signature');
        }
        
        $webhook_data = json_decode($input, true);
        
        if (!$webhook_data) {
            dredd_ai_log('NOWPayments webhook invalid JSON', 'error');
            http_response_code(400);
            exit('Invalid JSON');
        }
        
        try {
            $this->process_webhook($webhook_data);
            http_response_code(200);
            exit('OK');
            
        } catch (Exception $e) {
            dredd_ai_log('NOWPayments webhook processing error: ' . $e->getMessage(), 'error');
            http_response_code(500);
            exit('Processing error');
        }
    }
    
    /**
     * Process webhook data
     */
    private function process_webhook($data) {
        $payment_id = $data['payment_id'] ?? '';
        $status = $data['payment_status'] ?? '';
        $order_id = $data['order_id'] ?? '';
        
        if (empty($payment_id) || empty($order_id)) {
            throw new Exception('Missing payment ID or order ID');
        }
        
        // Extract user ID from order ID
        $order_parts = explode('_', $order_id);
        if (count($order_parts) < 3 || $order_parts[0] !== 'dredd') {
            throw new Exception('Invalid order ID format');
        }
        
        $user_id = intval($order_parts[1]);
        
        if (!$user_id) {
            throw new Exception('Invalid user ID');
        }
        
        // Get stored payment record
        global $wpdb;
        $payment_table = $wpdb->prefix . 'dredd_payments';
        
        $payment_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$payment_table} WHERE payment_id = %s AND user_id = %d",
            $payment_id, $user_id
        ));
        
        if (!$payment_record) {
            throw new Exception('Payment record not found');
        }
        
        // Update payment status
        $wpdb->update(
            $payment_table,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql'),
                'webhook_data' => json_encode($data)
            ),
            array('id' => $payment_record->id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Process successful payment
        if ($status === 'finished' || $status === 'confirmed') {
            $this->complete_payment($payment_record, $data);
        }
        
        dredd_ai_log("NOWPayments webhook processed: {$payment_id} - {$status}", 'info');
    }
    
    /**
     * Complete successful payment
     */
    private function complete_payment($payment_record, $webhook_data) {
        $user_id = $payment_record->user_id;
        $package_data = json_decode($payment_record->package_data, true);
        
        if (!$package_data || !isset($package_data['tokens'])) {
            throw new Exception('Invalid package data in payment record');
        }
        
        $tokens_to_add = intval($package_data['tokens']);
        
        // Add tokens to user account
        $current_credits = dredd_ai_get_user_credits($user_id);
        $new_credits = $current_credits + $tokens_to_add;
        
        dredd_ai_update_user_credits($user_id, $new_credits);
        
        // Log the transaction
        dredd_ai_log("Credits added to user {$user_id}: {$tokens_to_add} tokens (new total: {$new_credits})", 'info');
        
        // Send confirmation email (if configured)
        $this->send_payment_confirmation($user_id, $package_data, $payment_record);
    }
    
    /**
     * Send payment confirmation email
     */
    private function send_payment_confirmation($user_id, $package_data, $payment_record) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = 'DREDD AI - Payment Confirmed';
        $message = "Hello {$user->display_name},\n\n";
        $message .= "Your payment has been confirmed!\n\n";
        $message .= "Package: {$package_data['tokens']} tokens\n";
        $message .= "Amount: \${$package_data['price']}\n";
        $message .= "Payment ID: {$payment_record->payment_id}\n";
        $message .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "Your tokens have been added to your account and you can now use DREDD AI.\n\n";
        $message .= "Thank you for your purchase!\n\n";
        $message .= "The DREDD AI Team";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Store payment record in database
     */
    private function store_payment_record($user_id, $payment_info, $package_data) {
        global $wpdb;
        $payment_table = $wpdb->prefix . 'dredd_payments';
        
        $wpdb->insert(
            $payment_table,
            array(
                'user_id' => $user_id,
                'payment_id' => $payment_info['payment_id'],
                'order_id' => $payment_info['order_id'],
                'amount' => $payment_info['pay_amount'],
                'currency' => strtoupper($payment_info['pay_currency']),
                'status' => 'waiting',
                'payment_method' => 'nowpayments',
                'package_data' => json_encode($package_data),
                'payment_data' => json_encode($payment_info),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Make API request to NOWPayments
     */
    private function make_api_request($endpoint, $data = null, $method = 'GET') {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'NOWPayments API key not configured'
            );
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            )
        );
        
        if ($data && ($method === 'POST' || $method === 'PUT')) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $decoded_response = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return array(
                'success' => true,
                'data' => $decoded_response
            );
        } else {
            return array(
                'success' => false,
                'message' => $decoded_response['message'] ?? 'API request failed',
                'code' => $response_code
            );
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($payload, $signature) {
        if (empty($this->webhook_secret)) {
            return true; // Allow if no secret configured (for testing)
        }
        
        $expected_signature = hash_hmac('sha512', $payload, $this->webhook_secret);
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Test NOWPayments API connectivity and get available currencies
     */
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }
        
        // Test 1: Get API status
        $status_response = $this->make_api_request('status');
        
        // Test 2: Get available currencies
        $currencies_response = $this->make_api_request('currencies');
        
        return array(
            'success' => true,
            'api_status' => $status_response,
            'currencies' => $currencies_response,
            'message' => 'API connection test completed'
        );
    }

    /**
     * Check if NOWPayments is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Get payment statistics
     */
    public function get_payment_stats() {
        global $wpdb;
        $payment_table = $wpdb->prefix . 'dredd_payments';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_payments,
                COUNT(CASE WHEN status IN ('finished', 'confirmed') THEN 1 END) as successful_payments,
                SUM(CASE WHEN status IN ('finished', 'confirmed') THEN amount ELSE 0 END) as total_revenue,
                COUNT(CASE WHEN payment_method = 'nowpayments' THEN 1 END) as crypto_payments
            FROM {$payment_table}
            WHERE payment_method = 'nowpayments'
        ");
        
        return array(
            'total_payments' => intval($stats->total_payments ?? 0),
            'successful_payments' => intval($stats->successful_payments ?? 0),
            'total_revenue' => floatval($stats->total_revenue ?? 0),
            'crypto_payments' => intval($stats->crypto_payments ?? 0),
            'success_rate' => $stats->total_payments > 0 ? 
                round(($stats->successful_payments / $stats->total_payments) * 100, 2) : 0
        );
    }
    
    private function is_sandbox_mode() {
        // 🚨 ALWAYS RETURN FALSE - LIVE MODE ONLY
        return false;
    }
    
    /**
     * Get payment addresses - LIVE MODE ONLY
     */
    private function get_payment_addresses() {
        // 🟢 LIVE MODE ONLY - NO TEST SYSTEM
        $live_addresses = dredd_ai_get_option('live_crypto_addresses', array());
        
        // Filter out empty addresses
        $filtered_addresses = array();
        foreach ($live_addresses as $currency => $address) {
            if (!empty(trim($address))) {
                $filtered_addresses[strtolower($currency)] = trim($address);
            }
        }
        
        return $filtered_addresses;
    }
    
    /**
     * Map NOWPayments currency codes to our internal address keys
     */
    private function map_currency_to_address_key($currency) {
        $currency_mapping = array(
            // Bitcoin variations
            'bitcoin' => 'btc',
            'btc' => 'btc',
            
            // Ethereum variations  
            'ethereum' => 'eth',
            'eth' => 'eth',
            
            // Litecoin variations
            'litecoin' => 'ltc', 
            'ltc' => 'ltc',
            
            // Dogecoin variations
            'dogecoin' => 'doge',
            'doge' => 'doge',
            
            // USDT variations (ALL map to 'usdt' for address lookup as per memory)
            'tether' => 'usdt',
            'tether-trc20' => 'usdt',
            'tether-erc20' => 'usdt', 
            'tether-bep20' => 'usdt',
            'tether-omni' => 'usdt',
            'tether-solana' => 'usdt',
            'usdt' => 'usdt',
            'usdttrc20' => 'usdt',
            'usdterc20' => 'usdt',
            'usdtbep20' => 'usdt',
            'usdtbsc' => 'usdt', // BSC USDT
            'usdtarc20' => 'usdt', // Arbitrum USDT
            
            // USDC variations (ALL map to 'usdc' for address lookup)
            'usdcoin' => 'usdc',
            'usdc' => 'usdc',
            'usdcerc20' => 'usdc',
            'usdcbep20' => 'usdc',
            'usdcbsc' => 'usdc', // BSC USDC
            'usdcsol' => 'usdc',
            
            // Other popular cryptos
            'binancecoin' => 'bnb',
            'bnb' => 'bnb',
            'cardano' => 'ada',
            'ada' => 'ada',
            'polkadot' => 'dot',
            'dot' => 'dot',
            'chainlink' => 'link',
            'link' => 'link',
            'polygon' => 'matic',
            'matic' => 'matic',
            'monero' => 'xmr',
            'xmr' => 'xmr'
        );
        
        $normalized_currency = strtolower($currency);
        $mapped_key = $currency_mapping[$normalized_currency] ?? $normalized_currency;
        
        dredd_ai_log("Currency mapping: {$currency} -> {$mapped_key}", 'debug');
        return $mapped_key;
    }
}
