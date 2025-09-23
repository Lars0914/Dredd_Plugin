<?php
/**
 * Payment processing for DREDD AI plugin - Stripe integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Payments {
    
    private $database;
    private $stripe_secret_key;
    private $stripe_publishable_key;
    private $webhook_secret;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        $this->stripe_secret_key = dredd_ai_get_option('stripe_secret_key', '');
        $this->stripe_publishable_key = dredd_ai_get_option('stripe_publishable_key', '');
        $this->webhook_secret = dredd_ai_get_option('stripe_webhook_secret', '');
        
        // Add Stripe webhook handler
        add_action('wp_ajax_dredd_stripe_webhook', array($this, 'handle_stripe_webhook'));
        add_action('wp_ajax_nopriv_dredd_stripe_webhook', array($this, 'handle_stripe_webhook'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dredd_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_dredd_process_payment', array($this, 'process_payment'));
    }
    
    /**
     * Process payment request from frontend
     */
    public function process_payment() {
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
        $package_data = $_POST['package'] ?? array();
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        if (empty($package_data) || !isset($package_data['tokens']) || !isset($package_data['price'])) {
            wp_send_json_error('Invalid package data');
        }
        
        try {
            switch ($payment_method) {
                case 'stripe':
                    $result = $this->process_stripe_payment($package_data, $user_id);
                    break;
                    
                case 'crypto':
                    $result = $this->process_crypto_payment($package_data, $user_id);
                    break;
                    
                default:
                    throw new Exception('Invalid payment method');
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            dredd_ai_log('Payment processing error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Create Stripe payment intent
     */
    public function create_payment_intent() {
        if (!$this->is_stripe_configured()) {
            wp_send_json_error('Stripe not configured');
        }
        
        // Validate payment request
        $validation = Dredd_Validation::validate_payment_request(array(
            'amount' => $_POST['amount'] ?? 0,
            'method' => 'stripe'
        ));
        
        if (!$validation['valid']) {
            wp_send_json_error(implode(', ', $validation['errors']));
        }
        
        $amount = $validation['data']['amount'];
        $credits = $validation['data']['credits'];
        $user_id = get_current_user_id();
        
        // Allow non-logged in users for public payments
        if (!$user_id) {
            $user_id = 0; // Use 0 for anonymous users
        }
        
        try {
            $this->load_stripe_library();
            \Stripe\Stripe::setApiKey($this->stripe_secret_key);
            
            $amount_cents = $amount * 100; // Convert to cents
            
            // Generate transaction ID
            $transaction_id = 'dredd_' . uniqid() . '_' . time();
            
            // Create payment intent
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $amount_cents,
                'currency' => 'usd',
                'metadata' => [
                    'transaction_id' => $transaction_id,
                    'user_id' => $user_id,
                    'credits' => $credits,
                    'amount_usd' => $amount,
                    'plugin' => 'dredd-ai'
                ],
                'description' => "DREDD AI Credits: {$credits} credits (\${$amount}) for user {$user_id}"
            ]);
            
            // Store pending transaction
            $this->database->store_transaction(array(
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'tokens' => $credits,
                'payment_method' => 'stripe',
                'stripe_payment_intent' => $payment_intent->id,
                'status' => 'pending',
                'metadata' => array(
                    'amount_usd' => $amount,
                    'credits' => $credits,
                    'stripe_client_secret' => $payment_intent->client_secret
                )
            ));
            
            wp_send_json_success(array(
                'client_secret' => $payment_intent->client_secret,
                'transaction_id' => $transaction_id
            ));
            
        } catch (Exception $e) {
            dredd_ai_log('Stripe payment intent error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Payment setup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_stripe_webhook() {
        if (!$this->is_stripe_configured()) {
            http_response_code(400);
            exit('Stripe not configured');
        }
        
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        try {
            $this->load_stripe_library();
            \Stripe\Stripe::setApiKey($this->stripe_secret_key);
            
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->webhook_secret
            );
            
            // Handle the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handle_payment_success($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handle_payment_failure($event->data->object);
                    break;
                    
                default:
                    dredd_ai_log('Unhandled Stripe webhook event: ' . $event->type);
            }
            
            http_response_code(200);
            echo json_encode(['status' => 'success']);
            
        } catch (\UnexpectedValueException $e) {
            dredd_ai_log('Invalid Stripe webhook payload: ' . $e->getMessage(), 'error');
            http_response_code(400);
            exit('Invalid payload');
            
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            dredd_ai_log('Invalid Stripe webhook signature: ' . $e->getMessage(), 'error');
            http_response_code(400);
            exit('Invalid signature');
            
        } catch (Exception $e) {
            dredd_ai_log('Stripe webhook error: ' . $e->getMessage(), 'error');
            http_response_code(500);
            exit('Webhook error');
        }
        
        exit;
    }
    
    /**
     * Handle successful payment
     */
    private function handle_payment_success($payment_intent) {
        $transaction_id = $payment_intent->metadata->transaction_id ?? '';
        $user_id = intval($payment_intent->metadata->user_id ?? 0);
        $credits = intval($payment_intent->metadata->credits ?? 0);
        
        if (empty($transaction_id) || !$credits) {
            dredd_ai_log('Invalid payment success data', 'error');
            return;
        }
        
        // Skip credit addition for anonymous users (user_id = 0)
        if ($user_id === 0) {
            dredd_ai_log('Anonymous payment completed - no credits added', 'info');
            return;
        }
        
        try {
            // Update transaction status
            $this->database->update_transaction_status($transaction_id, 'completed', array(
                'stripe_payment_intent' => $payment_intent->id,
                'completed_at' => current_time('mysql')
            ));
            
            // Add credits to user account
            dredd_ai_add_credits($user_id, $credits);
            
            dredd_ai_log("Payment completed: {$credits} credits added to user {$user_id}");
            
            // Send confirmation email (optional)
            $this->send_payment_confirmation($user_id, $credits, $payment_intent->amount / 100);
            
        } catch (Exception $e) {
            dredd_ai_log('Error processing successful payment: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Handle failed payment
     */
    private function handle_payment_failure($payment_intent) {
        $transaction_id = $payment_intent->metadata->transaction_id ?? '';
        
        if ($transaction_id) {
            $this->database->update_transaction_status($transaction_id, 'failed', array(
                'failure_reason' => $payment_intent->last_payment_error->message ?? 'Unknown error',
                'failed_at' => current_time('mysql')
            ));
            
            dredd_ai_log("Payment failed for transaction: {$transaction_id}");
        }
    }
    
    /**
     * Send payment confirmation email
     */
    private function send_payment_confirmation($user_id, $tokens, $amount) {
        $user = get_user_by('id', $user_id);
        if (!$user) return;
        
        $subject = 'DREDD AI - Payment Confirmation';
        $message = "
        <div style='font-family: Krona One, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #0a0a0a, #1a1a1a); color: #c0c0c0; padding: 30px; text-align: center;'>
                <h1 style='color: #ffffff; margin-bottom: 20px;'>I AM THE LAW!</h1>
                <p style='font-size: 18px; margin-bottom: 30px;'>Your token purchase has been processed successfully.</p>
                
                <div style='background: rgba(255, 215, 0, 0.1); border: 2px solid #ffd700; padding: 20px; border-radius: 10px; margin-bottom: 30px;'>
                    <h3 style='color: #ffffff; margin-bottom: 15px;'>Purchase Details</h3>
                    <p><strong>Tokens Purchased:</strong> " . number_format($tokens) . " DREDD Credits</p>
                    <p><strong>Amount Paid:</strong> $" . number_format($amount, 2) . " USD</p>
                    <p><strong>Purchase Date:</strong> " . current_time('F j, Y g:i A') . "</p>
                </div>
                
                <p>Your credits are now available for brutal cryptocurrency analysis!</p>
                <p style='margin-top: 30px;'><a href='" . home_url() . "' style='background: #ffd700; color: #0a0a0a; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold;'>START ANALYZING</a></p>
                
                <p style='margin-top: 30px; font-size: 12px; opacity: 0.8;'>Justice never sleeps. Neither do we.</p>
            </div>
        </div>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Load Stripe PHP library
     */
    private function load_stripe_library() {
        if (!class_exists('\Stripe\Stripe')) {
            require_once DREDD_AI_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
        }
    }
    
    /**
     * Check if Stripe is properly configured
     */
    private function is_stripe_configured() {
        return !empty($this->stripe_secret_key) && !empty($this->stripe_publishable_key);
    }
    
    /**
     * Get Stripe publishable key for frontend
     */
    public function get_stripe_publishable_key() {
        return $this->stripe_publishable_key;
    }
    
    /**
     * Validate payment package
     */
    private function validate_package($package_data) {
        $valid_packages = dredd_ai_get_option('token_packages', array());
        
        foreach ($valid_packages as $valid_package) {
            if ($valid_package['tokens'] == $package_data['tokens'] && 
                abs($valid_package['price'] - $package_data['price']) < 0.01) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Process refund
     */
    public function process_refund($transaction_id, $reason = '') {
        if (!$this->is_stripe_configured()) {
            throw new Exception('Stripe not configured');
        }
        
        // Get transaction details
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE transaction_id = %s",
            $transaction_id
        ));
        
        if (!$transaction || $transaction->payment_method !== 'stripe') {
            throw new Exception('Transaction not found or not a Stripe payment');
        }
        
        if ($transaction->status !== 'completed') {
            throw new Exception('Can only refund completed payments');
        }
        
        try {
            $this->load_stripe_library();
            \Stripe\Stripe::setApiKey($this->stripe_secret_key);
            
            // Create refund
            $refund = \Stripe\Refund::create([
                'payment_intent' => $transaction->stripe_payment_intent,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'transaction_id' => $transaction_id,
                    'refund_reason' => $reason
                ]
            ]);
            
            // Update transaction status
            $this->database->update_transaction_status($transaction_id, 'refunded', array(
                'refund_id' => $refund->id,
                'refund_reason' => $reason,
                'refunded_at' => current_time('mysql')
            ));
            
            // Deduct tokens from user account
            dredd_ai_deduct_credits($transaction->user_id, $transaction->tokens);
            
            dredd_ai_log("Refund processed for transaction: {$transaction_id}");
            
            return array(
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100
            );
            
        } catch (Exception $e) {
            dredd_ai_log('Refund error: ' . $e->getMessage(), 'error');
            throw new Exception('Refund failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get payment statistics for admin
     */
    public function get_payment_statistics($date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        
        $where_clause = "WHERE payment_method = 'stripe'";
        $params = array();
        
        if ($date_from && $date_to) {
            $where_clause .= " AND created_at BETWEEN %s AND %s";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
                COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN tokens ELSE 0 END) as total_tokens_sold,
                AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as average_transaction
            FROM {$table} {$where_clause}",
            $params
        ));
        
        return $stats;
    }
    
    /**
     * Export payment data for accounting
     */
    public function export_payment_data($date_from, $date_to, $format = 'csv') {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                transaction_id,
                user_id,
                amount,
                tokens,
                status,
                created_at,
                stripe_payment_intent,
                metadata
            FROM {$table} 
            WHERE payment_method = 'stripe' 
            AND created_at BETWEEN %s AND %s 
            ORDER BY created_at DESC",
            $date_from,
            $date_to
        ));
        
        if ($format === 'csv') {
            return $this->generate_csv_export($transactions);
        } else {
            return $transactions;
        }
    }
    
    /**
     * Generate CSV export
     */
    private function generate_csv_export($transactions) {
        $csv_data = "Transaction ID,User ID,Amount,Tokens,Status,Date,Stripe Payment Intent\n";
        
        foreach ($transactions as $transaction) {
            $csv_data .= sprintf(
                "%s,%d,%.2f,%d,%s,%s,%s\n",
                $transaction->transaction_id,
                $transaction->user_id,
                $transaction->amount,
                $transaction->tokens,
                $transaction->status,
                $transaction->created_at,
                $transaction->stripe_payment_intent
            );
        }
        
        return $csv_data;
    }
}
