<?php
/**
 * Payment Validation Helper for DREDD AI
 * Ensures consistent validation across all payment methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Validation
{

    const MIN_AMOUNT = 1.00;
    const MAX_AMOUNT = 100.00;

    /**
     * Validate payment amount
     */
    public static function validate_amount($amount)
    {
        $amount = floatval($amount);

        if ($amount < self::MIN_AMOUNT) {
            return array(
                'valid' => false,
                'error' => 'Minimum payment amount is $' . number_format(self::MIN_AMOUNT, 2)
            );
        }

        if ($amount > self::MAX_AMOUNT) {
            return array(
                'valid' => false,
                'error' => 'Maximum payment amount is $' . number_format(self::MAX_AMOUNT, 2)
            );
        }

        return array(
            'valid' => true,
            'amount' => $amount
        );
    }

    /**
     * Calculate credits from amount
     */
    public static function calculate_credits($amount)
    {
        return intval($amount * 10); // $1 = 10 credits
    }

    /**
     * Validate wallet address
     */
    public static function validate_wallet_address($address)
    {
        // Basic Ethereum address validation
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return array(
                'valid' => false,
                'error' => 'Invalid wallet address format'
            );
        }

        return array(
            'valid' => true,
            'address' => $address
        );
    }

    /**
     * Validate user permissions for payment
     */
    public static function validate_user_payment_permission()
    {
        // Check if paid mode is enabled
        if (!dredd_ai_is_paid_mode_enabled()) {
            return array(
                'valid' => false,
                'error' => 'Payments are currently disabled'
            );
        }

        return array('valid' => true);
    }

    /**
     * Sanitize and validate transaction ID
     */
    public static function validate_transaction_id($transaction_id)
    {
        $transaction_id = sanitize_text_field($transaction_id);

        if (empty($transaction_id)) {
            return array(
                'valid' => false,
                'error' => 'Transaction ID is required'
            );
        }

        if (strlen($transaction_id) > 100) {
            return array(
                'valid' => false,
                'error' => 'Transaction ID is too long'
            );
        }

        return array(
            'valid' => true,
            'transaction_id' => $transaction_id
        );
    }

    /**
     * Map payment method to normalized form
     */
    public static function normalize_payment_method($method)
    {
        $method_mapping = array(
            // NOWPayments API expects these exact currency codes
            'bitcoin' => 'btc',
            'btc' => 'btc',
            'ethereum' => 'eth',
            'eth' => 'eth',
            'litecoin' => 'ltc',
            'ltc' => 'ltc',
            'dogecoin' => 'doge',
            'doge' => 'doge',

            // USDT variations - Use the exact codes NOWPayments supports
            'tether' => 'usdttrc20', // Default to TRC20
            'tether-trc20' => 'usdttrc20',
            'tether-erc20' => 'usdterc20',
            'tether-bep20' => 'usdtbsc',
            'usdt' => 'usdttrc20', // Default to TRC20

            // USDC variations - Use the exact codes NOWPayments supports
            'usdcoin' => 'usdcbsc', // Default to BSC
            'usdc' => 'usdc', // Generic USDC is supported

            // Other currencies
            'binancecoin' => 'bnb',
            'bnb' => 'bnb',
            'stripe' => 'stripe',
            'PLS' => 'PLS'
        );

        return $method_mapping[strtolower($method)] ?? strtolower($method);
    }

    /**
     * Validate payment method
     */
    public static function validate_payment_method($method)
    {
        $normalized_method = self::normalize_payment_method($method);

        $allowed_methods = array(
            'stripe',
            'btc',
            'eth',
            'ltc',
            'doge',
            'usdttrc20', // TRC20 USDT
            'usdterc20', // ERC20 USDT
            'usdtbsc',   // BSC USDT
            'usdc',      // Generic USDC
            'usdcbsc',   // BSC USDC
            'bnb',
            'pls'
        );

        if (!in_array($normalized_method, $allowed_methods)) {
            return array(
                'valid' => false,
                'error' => 'Invalid payment method: ' . $method
            );
        }

        return array(
            'valid' => true,
            'method' => $normalized_method  // Return normalized method
        );
    }

    /**
     * Comprehensive payment validation
     */
    public static function validate_payment_request($data)
    {
        $errors = array();

        // Validate permission
        $permission_check = self::validate_user_payment_permission();
        if (!$permission_check['valid']) {
            $errors[] = $permission_check['error'];
        }

        // Validate amount
        if (isset($data['amount'])) {
            $amount_check = self::validate_amount($data['amount']);
            if (!$amount_check['valid']) {
                $errors[] = $amount_check['error'];
            }
        } else {
            $errors[] = 'Payment amount is required';
        }

        // Validate payment method
        $normalized_method = null;
        if (isset($data['method'])) {
            $method_check = self::validate_payment_method($data['method']);
            if (!$method_check['valid']) {
                $errors[] = $method_check['error'];
            } else {
                $normalized_method = $method_check['method']; // Use the normalized method
            }
        } else {
            $errors[] = 'Payment method is required';
        }

        // Validate wallet address for crypto payments
        if (isset($data['wallet_address']) && !empty($data['wallet_address'])) {
            $wallet_check = self::validate_wallet_address($data['wallet_address']);
            if (!$wallet_check['valid']) {
                $errors[] = $wallet_check['error'];
            }
        }

        if (!empty($errors)) {
            return array(
                'valid' => false,
                'errors' => $errors
            );
        }

        return array(
            'valid' => true,
            'data' => array(
                'amount' => floatval($data['amount']),
                'credits' => self::calculate_credits($data['amount']),
                'method' => $normalized_method, // Return the normalized method, not the raw input
                'wallet_address' => isset($data['wallet_address']) ? sanitize_text_field($data['wallet_address']) : null
            )
        );
    }
}
