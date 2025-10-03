<?php
/**
 * PulseChain Integration for DREDD AI
 * Handles direct PulseChain wallet payments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_PulseChain
{

    private $database;
    private $chain_id = 369; // PulseChain mainnet
    private $rpc_url = 'https://rpc.pulsechain.com';

    public function __construct()
    {
        $this->database = new Dredd_Database();

        // Add AJAX handlers
        add_action('wp_ajax_dredd_create_pulsechain_payment', array($this, 'create_payment'));
        add_action('wp_ajax_dredd_verify_pulsechain_payment', array($this, 'verify_payment'));
        add_action('wp_ajax_dredd_get_pulsechain_balance', array($this, 'get_wallet_balance'));
    }

    /**
     * Create PulseChain payment
     */
    public function create_payment()
    {
        // Validate payment request
        $validation = Dredd_Validation::validate_payment_request(array(
            'amount' => $_POST['amount'] ?? 0,
            'method' => 'PLS',
            'wallet_address' => $_POST['wallet_address'] ?? ''
        ));

        if (!$validation['valid']) {
            wp_send_json_error(implode(', ', $validation['errors']));
        }

        $amount = $validation['data']['amount'];
        $credits = $validation['data']['credits'];
        $wallet_address = $validation['data']['wallet_address'];
        $user_id = get_current_user_id();

        // Allow non-logged in users
        if (!$user_id) {
            $user_id = 0;
        }

        try {
            // Get PLS price in USD
            $pls_price = $this->get_pls_usd_price();
            if (!$pls_price) {
                wp_send_json_error('Unable to get PLS price');
            }

            // Calculate PLS amount
            $pls_amount = $amount / $pls_price;
            $pls_amount_wei = $this->to_wei($pls_amount);

            // Generate payment ID
            $payment_id = 'pls_' . uniqid() . '_' . time();
            $transaction_id = 'dredd_' . $payment_id;

            // Get admin PulseChain wallet
            $admin_wallet = dredd_ai_get_option('pulsechain_wallet', '');
            if (empty($admin_wallet)) {
                wp_send_json_error('PulseChain wallet not configured');
            }


            // Store pending transaction
            $this->database->store_transaction(array(
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'tokens' => $credits,
                'payment_method' => 'PLS',
                'status' => 'pending',
                'metadata' => array(
                    'payment_id' => $payment_id,
                    'user_wallet' => $wallet_address,
                    'admin_wallet' => $admin_wallet,
                    'pls_amount' => $pls_amount,
                    'pls_amount_wei' => $pls_amount_wei,
                    'pls_price_usd' => $pls_price,
                    'credits' => $credits
                )
            ));

            wp_send_json_success(array(
                'payment_id' => $payment_id,
                'to_address' => $admin_wallet,
                'amount_usd' => $amount,
                'amount_pls' => $pls_amount,
                'amount_wei' => $pls_amount_wei,
                'chain_id' => $this->chain_id,
                'currency' => 'PLS',
                'credits' => $credits
            ));

        } catch (Exception $e) {
            dredd_ai_log('PulseChain payment creation error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Verify PulseChain payment
     */
    public function verify_payment()
    {
        $tx_hash = sanitize_text_field($_POST['tx_hash'] ?? '');
        $payment_id = sanitize_text_field($_POST['payment_id'] ?? '');

        if (empty($tx_hash) || empty($payment_id)) {
            wp_send_json_error('Missing transaction hash or payment ID');
        }

        try {
            // Get transaction from database
            global $wpdb;
            $table = $wpdb->prefix . 'dredd_transactions';
            $transaction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE transaction_id LIKE %s",
                'dredd_' . $payment_id . '%'
            ));

            if (!$transaction) {
                wp_send_json_error('Transaction not found');
            }

            $metadata = json_decode($transaction->metadata, true);

            // Verify transaction on PulseChain
            $verification_result = $this->verify_blockchain_transaction($tx_hash, $metadata);

            if ($verification_result['success']) {
                // Update transaction status
                $this->database->update_transaction_status($transaction->transaction_id, 'completed', array(
                    'tx_hash' => $tx_hash,
                    'block_number' => $verification_result['block_number'] ?? null,
                    'completed_at' => current_time('mysql')
                ));

                // Add credits to user account (only for logged-in users)
                if ($transaction->user_id > 0) {
                    dredd_ai_add_credits($transaction->user_id, $transaction->tokens);
                    dredd_ai_log("PulseChain payment verified: {$transaction->tokens} credits added to user {$transaction->user_id}");
                }

                wp_send_json_success(array(
                    'message' => 'Payment verified successfully',
                    'credits_added' => $transaction->tokens
                ));

            } else {
                // Mark as failed
                $this->database->update_transaction_status($transaction->transaction_id, 'failed', array(
                    'tx_hash' => $tx_hash,
                    'failure_reason' => $verification_result['error'] ?? 'Verification failed',
                    'failed_at' => current_time('mysql')
                ));

                wp_send_json_error($verification_result['error'] ?? 'Payment verification failed');
            }

        } catch (Exception $e) {
            dredd_ai_log('PulseChain payment verification error: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get wallet balance
     */
    public function get_wallet_balance()
    {
        $wallet_address = sanitize_text_field($_POST['wallet_address'] ?? '');

        if (!$this->is_valid_address($wallet_address)) {
            wp_send_json_error('Invalid wallet address');
        }

        try {
            $balance_wei = $this->get_balance_from_rpc($wallet_address);
            $balance_pls = $this->from_wei($balance_wei);

            // Get USD value
            $pls_price = $this->get_pls_usd_price();
            $balance_usd = $balance_pls * $pls_price;

            wp_send_json_success(array(
                'balance_wei' => $balance_wei,
                'balance_pls' => $balance_pls,
                'balance_usd' => $balance_usd,
                'currency' => 'PLS'
            ));

        } catch (Exception $e) {
            wp_send_json_error('Failed to get balance: ' . $e->getMessage());
        }
    }

    /**
     * Verify blockchain transaction
     */
    private function verify_blockchain_transaction($tx_hash, $metadata)
    {
        try {
            // Get transaction receipt from RPC
            $transaction = $this->get_transaction_from_rpc($tx_hash);

            if (!$transaction) {
                return array('success' => false, 'error' => 'Transaction not found on blockchain');
            }

            // Verify transaction details
            $verification_checks = array();

            // Check recipient address
            if (strtolower($transaction['to']) !== strtolower($metadata['admin_wallet'])) {
                $verification_checks[] = 'Recipient address mismatch';
            }

            // Check sender address
            if (strtolower($transaction['from']) !== strtolower($metadata['user_wallet'])) {
                $verification_checks[] = 'Sender address mismatch';
            }

            // Check amount (allow 5% tolerance for gas fees)
            $expected_amount = $metadata['pls_amount_wei'];
            $actual_amount = hexdec($transaction['value']);
            $tolerance = $expected_amount * 0.05;

            if (abs($actual_amount - $expected_amount) > $tolerance) {
                $verification_checks[] = 'Amount mismatch';
            }

            if (!empty($verification_checks)) {
                return array(
                    'success' => false,
                    'error' => 'Verification failed: ' . implode(', ', $verification_checks)
                );
            }

            return array(
                'success' => true,
                'block_number' => hexdec($transaction['blockNumber'])
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Verification error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get PLS price in USD
     */
    private function get_pls_usd_price()
    {
        $cache_key = 'dredd_pls_price';
        $cached_price = get_transient($cache_key);

        if ($cached_price !== false) {
            return floatval($cached_price);
        }

        // Try multiple price sources
        $price_sources = array(
            'https://api.coingecko.com/api/v3/simple/price?ids=pulsechain&vs_currencies=usd',
            'https://api.coinpaprika.com/v1/tickers/pls-pulsechain'
        );

        foreach ($price_sources as $url) {
            $response = wp_remote_get($url, array('timeout' => 10));

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (strpos($url, 'coingecko') !== false && isset($data['pulsechain']['usd'])) {
                    $price = $data['pulsechain']['usd'];
                    set_transient($cache_key, $price, 300); // Cache for 5 minutes
                    return floatval($price);
                } elseif (strpos($url, 'coinpaprika') !== false && isset($data['quotes']['USD']['price'])) {
                    $price = $data['quotes']['USD']['price'];
                    set_transient($cache_key, $price, 300);
                    return floatval($price);
                }
            }
        }

        // Fallback price if APIs fail
        return 0.0001; // Default PLS price
    }

    /**
     * Get balance from RPC
     */
    private function get_balance_from_rpc($address)
    {
        $request_data = array(
            'jsonrpc' => '2.0',
            'method' => 'eth_getBalance',
            'params' => array($address, 'latest'),
            'id' => 1
        );

        $response = wp_remote_post($this->rpc_url, array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data)
        ));

        if (is_wp_error($response)) {
            throw new Exception('RPC request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            throw new Exception('RPC error: ' . $data['error']['message']);
        }

        return hexdec($data['result']);
    }

    /**
     * Get transaction from RPC
     */
    private function get_transaction_from_rpc($tx_hash)
    {
        $request_data = array(
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionByHash',
            'params' => array($tx_hash),
            'id' => 1
        );

        $response = wp_remote_post($this->rpc_url, array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data)
        ));

        if (is_wp_error($response)) {
            throw new Exception('RPC request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            throw new Exception('RPC error: ' . $data['error']['message']);
        }

        return $data['result'];
    }

    /**
     * Convert to wei
     */
    private function to_wei($amount)
    {
        return $amount * pow(10, 18);
    }

    /**
     * Convert from wei
     */
    private function from_wei($wei)
    {
        return $wei / pow(10, 18);
    }

    /**
     * Validate Ethereum address
     */
    private function is_valid_address($address)
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
}
