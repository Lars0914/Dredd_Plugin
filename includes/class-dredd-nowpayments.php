<?php
/**
 * NOWPayments Integration for DREDD AI
 * Handles cryptocurrency payments via NOWPayments gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_NOWPayments
{

    private $database;
    private $api_key;
    private $api_url;
    private $webhook_secret;

    public function __construct()
    {
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
    public function get_available_currencies()
    {
        // Don't use cache during debugging - always fetch fresh
        $response = $this->make_api_request('full-currencies');

        if ($response['success']) {
            $currencies = $response['data']['currencies'] ?? array();

            // Log all available currencies for debugging
            dredd_ai_log('NOWPayments available currencies: ' . implode(', ', $currencies), 'debug');

            return $currencies;
        }

        dredd_ai_log('Failed to fetch currencies from NOWPayments API', 'error');
        // Return default currencies if API fails
        return array('btc', 'eth', 'usdt', 'usdc', 'bnb', 'pulsechain');
    }

    /**
     * Get minimum payment amount for currency
     */
    public function get_minimum_amount($currency)
    {
        try {
            dredd_ai_log("Getting minimum amount for currency: {$currency}", 'debug');

            $response = $this->make_api_request("min-amount?currency_from={$currency}&currency_to=usd");

            // Log the API response for debugging
            // dredd_ai_log("Minimum amount API response for {$currency}: " . json_encode($response), 'debug');

            if ($response['success'] && isset($response['data'])) {
                $min_amount = $response['data']['fiat_equivalent'] ?? null;

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
    public function create_payment()
    {
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
            $currency_codes = array_column($supported_currencies, 'code');

            if (!empty($supported_currencies) && !in_array(strtolower($currency), array_map('strtolower', $currency_codes))) {
                // dredd_ai_log("Currency '{$currency}' not in supported list. Available: " . implode(', ', array_slice($supported_currencies, 0, 20)) . '...', 'error');
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

            $amount += 1.5;

            $payment_data = array(
                'price_amount' => $amount,
                'price_currency' => 'usd',
                'pay_currency' => strtolower($currency_to_use), // Use the validated currency code
                'order_id' => 'dredd_' . $user_id . '_' . time(),
                'order_description' => ' DREDD AI Credits - $' . $amount,
                'ipn_callback_url' => admin_url('admin-ajax.php?action=dredd_nowpayments_webhook'),
                'success_url' => home_url('/dredd-payment-success/'),
                'cancel_url' => home_url('/dredd-payment-cancel/')
            );

            $response = $this->make_api_request(endpoint: 'payment', data: $payment_data, method: 'POST');

            if ($response['success']) {
                $payment_info = $response['data'];

                $payment_address = $payment_info['pay_address']; // Default to API address
                $package_data = array(
                    'amount' => $amount,
                    'currency' => $currency_to_use, // Use the validated currency
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
    public function check_payment_status()
    {
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
    public function handle_webhook()
    {
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
    private function process_webhook($data)
    {
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
            $payment_id,
            $user_id
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
        if ($status === 'finished' || $status === 'partially_paid') {
            $this->complete_payment($payment_record, $status);
        }

        dredd_ai_log("NOWPayments webhook processed: {$payment_id} - {$status}", 'info');
    }

    /**
     * Complete successful payment
     */
    private function complete_payment($payment_record, $flag)
    {
        $user_id = $payment_record->user_id;
        $package_data = json_decode($payment_record->package_data, true);
        $payment_data = json_decode($payment_record->payment_data, true);
        $amount_paid = json_decode($payment_record->webhook_data, true)['actually_paid'] ?? 0.0;

        if (!$package_data || !isset($package_data['tokens'])) {
            throw new Exception('Invalid package data in payment record');
        }

        if(abs($package_data['amount'] - $amount_paid <= 1.0)){
            $flag = 'finished';
        }

        $date = 0;

        if ($flag === 'finished') {
            $txn = dredd_ai_get_partially_paid_transaction($user_id);
            if ($txn) {
                $date = $this->dredd_days_from_payment($txn->amount + $amount_paid);
                dredd_ai_update_user_expires_at($user_id, $date);
                dredd_ai_update_transaction($txn->transaction_id);
            } else {
                if ($package_data['amount'] == 10)
                    $date = 30;
                if ($package_data['amount'] == 40)
                    $date = 180;
                if ($package_data['amount'] == 90)
                    $date = 365;
                dredd_ai_update_user_expires_at($user_id, $date);
            }
        }

        $this->send_payment_confirmation($user_id, $amount_paid, $payment_record);
        dredd_ai_store_transaction($payment_record->user_id, $payment_data, $flag, $amount_paid);
    }

    /**
     * Send payment confirmation email
     */
    private function send_payment_confirmation($user_id, $amount_paid, $payment_record)
    {
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return;
        }

        $subject = 'DREDD AI - Payment Confirmed';
        $message = "Hello {$user->display_name},\n\n";
        $message .= "Your payment has been confirmed!\n\n";
        $message .= "Amount: \${$amount_paid}\n";
        $message .= "Payment ID: {$payment_record->payment_id}\n";
        $message .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "Thank you for your purchase!\n\n";
        $message .= "The DREDD AI Team";

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Store payment record in database
     */
    private function store_payment_record($user_id, $payment_info, $package_data)
    {
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
    private function make_api_request($endpoint, $data = null, $method = 'GET')
    {
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
    private function verify_webhook_signature($payload, $signature)
    {
        if (empty($this->webhook_secret)) {
            return true; // Allow if no secret configured (for testing)
        }

        $expected_signature = hash_hmac('sha512', $payload, $this->webhook_secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Test NOWPayments API connectivity and get available currencies
     */
    public function test_api_connection()
    {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }

        // Test 1: Get API status
        $status_response = $this->make_api_request('status');

        // Test 2: Get available currencies
        $currencies_response = $this->make_api_request('full-currencies');

        return array(
            'success' => true,
            'api_status' => $status_response,
            'currencies' => $currencies_response,
            'message' => 'API connection test completed'
        );
    }

    private function dredd_days_from_payment(float $usd_amount, bool $allow_multi_year = true): int
    {
        $cents = (int) round($usd_amount * 100);

        if ($cents < 1000) {
            return (int) round(($cents / 100) * 3);
        }

        $months = 0;
        $remaining = $cents;

        if ($allow_multi_year) {
            $years = intdiv($remaining, 9000);
            if ($years > 0) {
                $months += $years * 12;
                $remaining -= $years * 9000;
            }
        } else {
            if ($remaining >= 9000) {
                $remaining -= 9000;
                $days = (12 * 30) + round(($remaining / 100) * 3);
                return (int) $days;
            }
        }

        if ($remaining >= 4000) {
            $months += 6;
            $remaining -= 4000;

            $extra_months = intdiv($remaining, 1000);
            $months += $extra_months;
            $remaining -= $extra_months * 1000;
        } else {
            $months += intdiv($remaining, 1000);
            $remaining -= intdiv($remaining, 1000) * 1000;
        }

        $leftover_usd = $remaining / 100;
        $leftover_days = round($leftover_usd * 3);

        return (int) (($months * 30) + $leftover_days);
    }
}
