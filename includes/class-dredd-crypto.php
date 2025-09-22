<?php
/**
 * Cryptocurrency payment processing for DREDD AI plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Crypto {
    
    private $database;
    private $crypto_wallets;
    private $supported_chains;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        $this->crypto_wallets = dredd_ai_get_option('crypto_wallets', array());
        $this->supported_chains = dredd_ai_get_option('supported_chains', array());
        
        // Add AJAX handlers
        add_action('wp_ajax_dredd_create_crypto_payment', array($this, 'create_crypto_payment'));
        add_action('wp_ajax_dredd_verify_crypto_payment', array($this, 'verify_crypto_payment'));
        add_action('wp_ajax_dredd_get_wallet_address', array($this, 'get_wallet_address'));
    }
    
    /**
     * Create crypto payment request
     */
    public function create_crypto_payment() {
        $currency = sanitize_text_field($_POST['currency'] ?? '');
        $chain = sanitize_text_field($_POST['chain'] ?? 'ethereum');
        $package_data = $_POST['package'] ?? array();
        $user_account = sanitize_text_field($_POST['user_account'] ?? '');
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        if (!in_array($currency, array('usdt', 'usdc'))) {
            wp_send_json_error('Unsupported currency');
        }
        
        if (empty($package_data) || !isset($package_data['tokens']) || !isset($package_data['price'])) {
            wp_send_json_error('Invalid package data');
        }
        
        if (!$this->is_valid_address($user_account)) {
            wp_send_json_error('Invalid wallet address');
        }
        
        try {
            // Get admin wallet address for this currency
            $admin_wallet = $this->crypto_wallets[strtolower($currency)] ?? '';
            if (empty($admin_wallet)) {
                throw new Exception('Admin wallet not configured for ' . strtoupper($currency));
            }
            
            // Generate payment ID
            $payment_id = 'crypto_' . uniqid() . '_' . time();
            $transaction_id = 'dredd_' . $payment_id;
            
            // Get token contract address and decimals
            $token_info = $this->get_token_info($currency, $chain);
            if (!$token_info) {
                throw new Exception('Token configuration not found for ' . strtoupper($currency) . ' on ' . $chain);
            }
            
            // Calculate amount in token units (considering decimals)
            $amount_usd = floatval($package_data['price']);
            $amount_tokens = $amount_usd; // Assuming 1:1 with USD for stablecoins
            $amount_wei = $this->to_wei($amount_tokens, $token_info['decimals']);
            
            // Store pending transaction
            $this->database->store_transaction(array(
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'amount' => $amount_usd,
                'tokens' => intval($package_data['tokens']),
                'payment_method' => strtolower($currency),
                'chain' => $chain,
                'status' => 'pending',
                'metadata' => array(
                    'payment_id' => $payment_id,
                    'currency' => $currency,
                    'chain' => $chain,
                    'user_wallet' => $user_account,
                    'admin_wallet' => $admin_wallet,
                    'token_contract' => $token_info['contract'],
                    'amount_wei' => $amount_wei,
                    'package_data' => $package_data
                )
            ));
            
            // Prepare transaction data for frontend
            $transaction_data = array(
                'payment_id' => $payment_id,
                'to_address' => $admin_wallet,
                'token_contract' => $token_info['contract'],
                'amount' => $amount_tokens,
                'amount_wei' => $amount_wei,
                'currency' => strtoupper($currency),
                'chain' => $chain,
                'chain_id' => $this->get_chain_id($chain),
                'data' => $this->build_transfer_data($token_info['contract'], $admin_wallet, $amount_wei)
            );
            
            wp_send_json_success($transaction_data);
            
        } catch (Exception $e) {
            dredd_ai_log('Crypto payment creation error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Verify crypto payment transaction
     */
    public function verify_crypto_payment() {
        $tx_hash = sanitize_text_field($_POST['tx_hash'] ?? '');
        $payment_id = sanitize_text_field($_POST['payment_id'] ?? '');
        
        if (empty($tx_hash) || empty($payment_id)) {
            wp_send_json_error('Missing transaction hash or payment ID');
        }
        
        try {
            // Get transaction details from database
            global $wpdb;
            $table = $wpdb->prefix . 'dredd_transactions';
            $transaction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE transaction_id LIKE %s AND JSON_EXTRACT(metadata, '$.payment_id') = %s",
                'dredd_' . $payment_id . '%',
                $payment_id
            ));
            
            if (!$transaction) {
                throw new Exception('Transaction not found');
            }
            
            $metadata = json_decode($transaction->metadata, true);
            $chain = $metadata['chain'];
            
            // Verify transaction on blockchain
            $verification_result = $this->verify_blockchain_transaction($tx_hash, $metadata, $chain);
            
            if ($verification_result['success']) {
                // Update transaction status
                $this->database->update_transaction_status($transaction->transaction_id, 'completed', array(
                    'tx_hash' => $tx_hash,
                    'block_number' => $verification_result['block_number'] ?? null,
                    'gas_used' => $verification_result['gas_used'] ?? null,
                    'completed_at' => current_time('mysql'),
                    'verified_amount' => $verification_result['amount'] ?? null
                ));
                
                // Add tokens to user account
                dredd_ai_add_credits($transaction->user_id, $transaction->tokens);
                
                dredd_ai_log("Crypto payment verified: {$transaction->tokens} tokens added to user {$transaction->user_id}");
                
                wp_send_json_success(array(
                    'message' => 'Payment verified successfully',
                    'tokens_added' => $transaction->tokens
                ));
                
            } else {
                // Mark as failed
                $this->database->update_transaction_status($transaction->transaction_id, 'failed', array(
                    'tx_hash' => $tx_hash,
                    'failure_reason' => $verification_result['error'] ?? 'Verification failed',
                    'failed_at' => current_time('mysql')
                ));
                
                throw new Exception($verification_result['error'] ?? 'Payment verification failed');
            }
            
        } catch (Exception $e) {
            dredd_ai_log('Crypto payment verification error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get wallet address for currency
     */
    public function get_wallet_address() {
        $currency = sanitize_text_field($_POST['currency'] ?? '');
        
        if (!in_array($currency, array('usdt', 'usdc'))) {
            wp_send_json_error('Unsupported currency');
        }
        
        $wallet_address = $this->crypto_wallets[strtolower($currency)] ?? '';
        
        if (empty($wallet_address)) {
            wp_send_json_error('Wallet address not configured');
        }
        
        wp_send_json_success(array(
            'address' => $wallet_address,
            'currency' => strtoupper($currency)
        ));
    }
    
    /**
     * Verify blockchain transaction
     */
    private function verify_blockchain_transaction($tx_hash, $metadata, $chain) {
        $explorer_api = $this->get_explorer_api($chain);
        $api_key = $this->get_api_key($chain);
        
        if (!$explorer_api || !$api_key) {
            return array('success' => false, 'error' => 'Explorer API not configured for ' . $chain);
        }
        
        // Build API URL
        $api_url = $explorer_api . '?module=proxy&action=eth_getTransactionByHash&txhash=' . $tx_hash . '&apikey=' . $api_key;
        
        $response = wp_remote_get($api_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => 'API request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['result'])) {
            return array('success' => false, 'error' => 'Invalid API response');
        }
        
        $tx_data = $data['result'];
        
        if (!$tx_data) {
            return array('success' => false, 'error' => 'Transaction not found on blockchain');
        }
        
        // Verify transaction details
        $verification_checks = array();
        
        // Check recipient address
        if (strtolower($tx_data['to']) !== strtolower($metadata['admin_wallet'])) {
            $verification_checks[] = 'Recipient address mismatch';
        }
        
        // Check sender address
        if (strtolower($tx_data['from']) !== strtolower($metadata['user_wallet'])) {
            $verification_checks[] = 'Sender address mismatch';
        }
        
        // For token transfers, verify the input data
        if (isset($metadata['token_contract']) && !empty($metadata['token_contract'])) {
            $expected_data = $metadata['data'] ?? '';
            if (!empty($expected_data) && strtolower($tx_data['input']) !== strtolower($expected_data)) {
                // For token transfers, we need to decode the input data
                $decoded_transfer = $this->decode_transfer_data($tx_data['input']);
                if ($decoded_transfer) {
                    if (strtolower($decoded_transfer['to']) !== strtolower($metadata['admin_wallet'])) {
                        $verification_checks[] = 'Token transfer recipient mismatch';
                    }
                    if ($decoded_transfer['amount'] !== $metadata['amount_wei']) {
                        $verification_checks[] = 'Token transfer amount mismatch';
                    }
                }
            }
        }
        
        // Check transaction status
        $receipt_url = $explorer_api . '?module=proxy&action=eth_getTransactionReceipt&txhash=' . $tx_hash . '&apikey=' . $api_key;
        $receipt_response = wp_remote_get($receipt_url, array('timeout' => 15));
        
        if (!is_wp_error($receipt_response)) {
            $receipt_body = wp_remote_retrieve_body($receipt_response);
            $receipt_data = json_decode($receipt_body, true);
            
            if ($receipt_data && isset($receipt_data['result'])) {
                $receipt = $receipt_data['result'];
                if ($receipt['status'] !== '0x1') {
                    $verification_checks[] = 'Transaction failed on blockchain';
                }
            }
        }
        
        if (!empty($verification_checks)) {
            return array(
                'success' => false, 
                'error' => 'Verification failed: ' . implode(', ', $verification_checks)
            );
        }
        
        return array(
            'success' => true,
            'block_number' => hexdec($tx_data['blockNumber']),
            'gas_used' => hexdec($tx_data['gas']),
            'amount' => $metadata['amount_wei']
        );
    }
    
    /**
     * Get token information for currency and chain
     */
    private function get_token_info($currency, $chain) {
        $token_contracts = array(
            'ethereum' => array(
                'usdt' => array('contract' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'decimals' => 6),
                'usdc' => array('contract' => '0xA0b86a33E6417E4d6ae8B6bF4B5c4E0F4c7F8b6D', 'decimals' => 6)
            ),
            'bsc' => array(
                'usdt' => array('contract' => '0x55d398326f99059fF775485246999027B3197955', 'decimals' => 18),
                'usdc' => array('contract' => '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', 'decimals' => 18)
            ),
            'polygon' => array(
                'usdt' => array('contract' => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', 'decimals' => 6),
                'usdc' => array('contract' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', 'decimals' => 6)
            ),
            'arbitrum' => array(
                'usdt' => array('contract' => '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9', 'decimals' => 6),
                'usdc' => array('contract' => '0xFF970A61A04b1cA14834A43f5dE4533eBDDB5CC8', 'decimals' => 6)
            )
        );
        
        return $token_contracts[$chain][$currency] ?? null;
    }
    
    /**
     * Build transfer data for ERC20 token
     */
    private function build_transfer_data($token_contract, $to_address, $amount) {
        // ERC20 transfer function signature: transfer(address,uint256)
        $function_signature = '0xa9059cbb';
        
        // Remove 0x prefix and pad addresses/amounts
        $to_address = str_pad(substr($to_address, 2), 64, '0', STR_PAD_LEFT);
        $amount_hex = str_pad(dechex($amount), 64, '0', STR_PAD_LEFT);
        
        return $function_signature . $to_address . $amount_hex;
    }
    
    /**
     * Decode transfer data from transaction input
     */
    private function decode_transfer_data($input_data) {
        if (strlen($input_data) < 138) { // 10 chars for function sig + 128 chars for parameters
            return false;
        }
        
        $function_sig = substr($input_data, 0, 10);
        if ($function_sig !== '0xa9059cbb') { // transfer function signature
            return false;
        }
        
        $to_address = '0x' . substr($input_data, 34, 40); // Skip padding
        $amount = hexdec(substr($input_data, 74, 64));
        
        return array(
            'to' => $to_address,
            'amount' => $amount
        );
    }
    
    /**
     * Convert amount to wei (considering token decimals)
     */
    private function to_wei($amount, $decimals) {
        return $amount * pow(10, $decimals);
    }
    
    /**
     * Convert wei to readable amount
     */
    private function from_wei($wei, $decimals) {
        return $wei / pow(10, $decimals);
    }
    
    /**
     * Get chain ID for blockchain
     */
    private function get_chain_id($chain) {
        $chain_ids = array(
            'ethereum' => 1,
            'bsc' => 56,
            'polygon' => 137,
            'arbitrum' => 42161,
            'pulsechain' => 369
        );
        
        return $chain_ids[$chain] ?? 1;
    }
    
    /**
     * Get explorer API for blockchain
     */
    private function get_explorer_api($chain) {
        $explorer_apis = array(
            'ethereum' => 'https://api.etherscan.io/api',
            'bsc' => 'https://api.bscscan.com/api',
            'polygon' => 'https://api.polygonscan.com/api',
            'arbitrum' => 'https://api.arbiscan.io/api'
        );
        
        return $explorer_apis[$chain] ?? null;
    }
    
    /**
     * Get API key for blockchain explorer
     */
    private function get_api_key($chain) {
        $api_keys = array(
            'ethereum' => dredd_ai_get_option('etherscan_api_key', ''),
            'bsc' => dredd_ai_get_option('bscscan_api_key', ''),
            'polygon' => dredd_ai_get_option('polygonscan_api_key', ''),
            'arbitrum' => dredd_ai_get_option('arbiscan_api_key', '')
        );
        
        return $api_keys[$chain] ?? '';
    }
    
    /**
     * Validate Ethereum address
     */
    private function is_valid_address($address) {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
    
    /**
     * Get crypto payment statistics
     */
    public function get_crypto_statistics($date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        
        $where_clause = "WHERE payment_method IN ('usdt', 'usdc')";
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
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN tokens ELSE 0 END) as total_tokens_sold,
                COUNT(CASE WHEN status = 'completed' AND payment_method = 'usdt' THEN 1 END) as usdt_payments,
                COUNT(CASE WHEN status = 'completed' AND payment_method = 'usdc' THEN 1 END) as usdc_payments
            FROM {$table} {$where_clause}",
            $params
        ));
        
        return $stats;
    }
    
    /**
     * Monitor pending crypto payments
     */
    public function monitor_pending_payments() {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        
        // Get pending crypto payments older than 10 minutes
        $pending_payments = $wpdb->get_results(
            "SELECT * FROM {$table} 
             WHERE payment_method IN ('usdt', 'usdc') 
             AND status = 'pending' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        foreach ($pending_payments as $payment) {
            $metadata = json_decode($payment->metadata, true);
            
            // Check if there are any transactions from the user wallet to admin wallet
            $this->check_pending_payment($payment, $metadata);
        }
    }
    
    /**
     * Check individual pending payment
     */
    private function check_pending_payment($payment, $metadata) {
        $chain = $metadata['chain'];
        $user_wallet = $metadata['user_wallet'];
        $admin_wallet = $metadata['admin_wallet'];
        
        $explorer_api = $this->get_explorer_api($chain);
        $api_key = $this->get_api_key($chain);
        
        if (!$explorer_api || !$api_key) {
            return;
        }
        
        // Get recent transactions for the user wallet
        $api_url = $explorer_api . '?module=account&action=txlist&address=' . $user_wallet . 
                  '&startblock=0&endblock=99999999&sort=desc&apikey=' . $api_key;
        
        $response = wp_remote_get($api_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['result']) || !is_array($data['result'])) {
            return;
        }
        
        // Look for matching transaction
        foreach ($data['result'] as $tx) {
            if (strtolower($tx['to']) === strtolower($admin_wallet) && 
                $tx['timeStamp'] > strtotime($payment->created_at)) {
                
                // Found potential matching transaction
                $verification_result = $this->verify_blockchain_transaction($tx['hash'], $metadata, $chain);
                
                if ($verification_result['success']) {
                    // Update payment status
                    $this->database->update_transaction_status($payment->transaction_id, 'completed', array(
                        'tx_hash' => $tx['hash'],
                        'block_number' => $tx['blockNumber'],
                        'completed_at' => current_time('mysql'),
                        'auto_detected' => true
                    ));
                    
                    // Add tokens to user account
                    dredd_ai_add_credits($payment->user_id, $payment->tokens);
                    
                    dredd_ai_log("Auto-detected crypto payment: {$payment->transaction_id}");
                    break;
                }
            }
        }
    }
}
