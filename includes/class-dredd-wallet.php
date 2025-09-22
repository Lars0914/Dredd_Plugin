<?php
/**
 * Wallet Connect Integration for DREDD AI
 * Handles wallet connection, balance verification, and premium feature unlocking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Wallet {
    
    private $database;
    private $supported_chains;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        $this->supported_chains = array(
            'ethereum' => array(
                'name' => 'Ethereum',
                'rpc' => 'https://eth-mainnet.public.blastapi.io',
                'chain_id' => 1,
                'currency' => 'ETH',
                'explorer' => 'https://etherscan.io'
            ),
            'bsc' => array(
                'name' => 'Binance Smart Chain',
                'rpc' => 'https://bsc-dataseed.binance.org/',
                'chain_id' => 56,
                'currency' => 'BNB',
                'explorer' => 'https://bscscan.com'
            ),
            'polygon' => array(
                'name' => 'Polygon',
                'rpc' => 'https://polygon-rpc.com/',
                'chain_id' => 137,
                'currency' => 'MATIC',
                'explorer' => 'https://polygonscan.com'
            ),
            'pulsechain' => array(
                'name' => 'PulseChain',
                'rpc' => 'https://rpc.pulsechain.com',
                'chain_id' => 369,
                'currency' => 'PLS',
                'explorer' => 'https://scan.pulsechain.com'
            ),
            'arbitrum' => array(
                'name' => 'Arbitrum One',
                'rpc' => 'https://arb1.arbitrum.io/rpc',
                'chain_id' => 42161,
                'currency' => 'ETH',
                'explorer' => 'https://arbiscan.io'
            )
        );
    }
    
    /**
     * Get supported blockchain networks
     */
    public function get_supported_chains() {
        return $this->supported_chains;
    }
    
    /**
     * Verify wallet balance for premium access
     */
    public function verify_wallet_balance() {
        $wallet_address = sanitize_text_field($_POST['wallet_address'] ?? '');
        $chain = sanitize_text_field($_POST['chain'] ?? 'ethereum');
        $user_id = get_current_user_id();
        
        if (empty($wallet_address)) {
            wp_send_json_error('Wallet address required');
        }
        
        if (!$this->is_valid_wallet_address($wallet_address)) {
            wp_send_json_error('Invalid wallet address format');
        }
        
        if (!isset($this->supported_chains[$chain])) {
            wp_send_json_error('Unsupported blockchain network');
        }
        
        // Get minimum balance requirements
        $min_balance_eth = dredd_ai_get_option('wallet_min_balance_eth', '0.1');
        $min_balance_usd = dredd_ai_get_option('wallet_min_balance_usd', '100');
        
        // Check wallet balance
        $balance_result = $this->check_wallet_balance($wallet_address, $chain);
        
        if ($balance_result['success']) {
            $balance_eth = $balance_result['balance_eth'];
            $balance_usd = $balance_result['balance_usd'];
            
            // Check if balance meets minimum requirements
            $has_sufficient_balance = ($balance_eth >= floatval($min_balance_eth)) || 
                                    ($balance_usd >= floatval($min_balance_usd));
            
            if ($has_sufficient_balance) {
                // Store wallet verification for user
                $this->store_wallet_verification($user_id, $wallet_address, $chain, $balance_result);
                
                wp_send_json_success(array(
                    'verified' => true,
                    'balance_eth' => $balance_eth,
                    'balance_usd' => $balance_usd,
                    'chain' => $this->supported_chains[$chain]['name'],
                    'premium_unlocked' => true,
                    'message' => 'Wallet verified! Premium features unlocked.'
                ));
            } else {
                wp_send_json_success(array(
                    'verified' => false,
                    'balance_eth' => $balance_eth,
                    'balance_usd' => $balance_usd,
                    'min_required_eth' => $min_balance_eth,
                    'min_required_usd' => $min_balance_usd,
                    'premium_unlocked' => false,
                    'message' => 'Insufficient balance for premium access.'
                ));
            }
        } else {
            wp_send_json_error($balance_result['message']);
        }
    }
    
    /**
     * Check wallet balance via RPC call
     */
    private function check_wallet_balance($wallet_address, $chain) {
        $chain_config = $this->supported_chains[$chain];
        $rpc_url = $chain_config['rpc'];
        
        // Prepare RPC request
        $request_data = array(
            'jsonrpc' => '2.0',
            'method' => 'eth_getBalance',
            'params' => array($wallet_address, 'latest'),
            'id' => 1
        );
        
        $response = wp_remote_post($rpc_url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to connect to blockchain: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => 'Blockchain RPC error: ' . $response_code
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $rpc_response = json_decode($body, true);
        
        if (isset($rpc_response['error'])) {
            return array(
                'success' => false,
                'message' => 'RPC Error: ' . $rpc_response['error']['message']
            );
        }
        
        if (!isset($rpc_response['result'])) {
            return array(
                'success' => false,
                'message' => 'Invalid RPC response format'
            );
        }
        
        // Convert hex balance to decimal (Wei to ETH)
        $balance_wei = hexdec($rpc_response['result']);
        $balance_eth = $balance_wei / pow(10, 18);
        
        // Get USD price for the native token
        $usd_price = $this->get_token_usd_price($chain_config['currency']);
        $balance_usd = $balance_eth * $usd_price;
        
        return array(
            'success' => true,
            'balance_wei' => $balance_wei,
            'balance_eth' => $balance_eth,
            'balance_usd' => $balance_usd,
            'currency' => $chain_config['currency'],
            'chain' => $chain_config['name']
        );
    }
    
    /**
     * Get USD price for native token
     */
    private function get_token_usd_price($currency) {
        $price_cache_key = 'dredd_token_price_' . strtolower($currency);
        $cached_price = get_transient($price_cache_key);
        
        if ($cached_price !== false) {
            return floatval($cached_price);
        }
        
        // Map currencies to CoinGecko IDs
        $coingecko_ids = array(
            'ETH' => 'ethereum',
            'BNB' => 'binancecoin',
            'MATIC' => 'matic-network',
            'PLS' => 'pulsechain',
            'ARB' => 'arbitrum'
        );
        
        $coingecko_id = $coingecko_ids[$currency] ?? 'ethereum';
        
        $response = wp_remote_get(
            "https://api.coingecko.com/api/v3/simple/price?ids={$coingecko_id}&vs_currencies=usd",
            array('timeout' => 10)
        );
        
        if (is_wp_error($response)) {
            return 2000; // Fallback price
        }
        
        $body = wp_remote_retrieve_body($response);
        $price_data = json_decode($body, true);
        
        $price = $price_data[$coingecko_id]['usd'] ?? 2000;
        
        // Cache price for 5 minutes
        set_transient($price_cache_key, $price, 300);
        
        return floatval($price);
    }
    
    /**
     * Store wallet verification for user
     */
    private function store_wallet_verification($user_id, $wallet_address, $chain, $balance_data) {
        $verification_data = array(
            'user_id' => $user_id,
            'wallet_address' => $wallet_address,
            'chain' => $chain,
            'balance_eth' => $balance_data['balance_eth'],
            'balance_usd' => $balance_data['balance_usd'],
            'verified_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        );
        
        // Store in user meta
        update_user_meta($user_id, 'dredd_wallet_verification', $verification_data);
        
        // Log verification
        dredd_ai_log("Wallet verified for user {$user_id}: {$wallet_address} on {$chain}", 'info');
    }
    
    /**
     * Check if user has verified wallet for premium access
     */
    public function user_has_premium_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Check if user has credits
        $credits = dredd_ai_get_user_credits($user_id);
        if ($credits > 0) {
            return true;
        }
        
        // Check wallet verification
        $wallet_verification = get_user_meta($user_id, 'dredd_wallet_verification', true);
        
        if (empty($wallet_verification)) {
            return false;
        }
        
        // Check if verification is still valid
        $expires_at = strtotime($wallet_verification['expires_at']);
        if (time() > $expires_at) {
            // Verification expired, remove it
            delete_user_meta($user_id, 'dredd_wallet_verification');
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate wallet address format
     */
    private function is_valid_wallet_address($address) {
        // Basic Ethereum address validation (40 hex characters with 0x prefix)
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
    
    /**
     * Get user's wallet verification status
     */
    public function get_wallet_verification_status() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        $wallet_verification = get_user_meta($user_id, 'dredd_wallet_verification', true);
        
        if (empty($wallet_verification)) {
            wp_send_json_success(array(
                'verified' => false,
                'premium_access' => false,
                'message' => 'No wallet connected'
            ));
        }
        
        // Check if verification is still valid
        $expires_at = strtotime($wallet_verification['expires_at']);
        $is_valid = time() <= $expires_at;
        
        if (!$is_valid) {
            delete_user_meta($user_id, 'dredd_wallet_verification');
            wp_send_json_success(array(
                'verified' => false,
                'premium_access' => false,
                'message' => 'Wallet verification expired'
            ));
        }
        
        wp_send_json_success(array(
            'verified' => true,
            'premium_access' => true,
            'wallet_address' => $wallet_verification['wallet_address'],
            'chain' => $wallet_verification['chain'],
            'balance_eth' => $wallet_verification['balance_eth'],
            'balance_usd' => $wallet_verification['balance_usd'],
            'expires_at' => $wallet_verification['expires_at'],
            'message' => 'Wallet verified and premium access active'
        ));
    }
    
    /**
     * Disconnect wallet
     */
    public function disconnect_wallet() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        delete_user_meta($user_id, 'dredd_wallet_verification');
        
        wp_send_json_success(array(
            'message' => 'Wallet disconnected successfully'
        ));
    }
}
