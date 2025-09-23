<?php
/**
 * Security and compliance management for DREDD AI plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Security {
    
    private $database;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        
        // Add security hooks
        add_action('wp_ajax_dredd_export_user_data', array($this, 'export_user_data'));
        add_action('wp_ajax_dredd_delete_user_data', array($this, 'delete_user_data'));
        add_action('wp_ajax_dredd_privacy_request', array($this, 'handle_privacy_request'));
        
        // Security headers
        add_action('send_headers', array($this, 'add_security_headers'));
    }
    
    /**
     * Verify AJAX request security
     */
    public function verify_ajax_request($require_login = true) {
        // For chat requests, we make nonce optional for both admin and public users
        // Only check nonce if explicitly required for login-protected features
        if ($require_login) {
            // Check nonce for login-required requests only
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dredd_ai_nonce') && 
                !wp_verify_nonce($_POST['nonce'] ?? '', 'dredd_ai_admin_nonce')) {
                dredd_ai_log('Security: Invalid nonce in login-required request', 'warning');
                return false;
            }
            
            // Check user login for protected features
            if (!is_user_logged_in()) {
                dredd_ai_log('Security: Unauthenticated request requiring login', 'warning');
                return false;
            }
        } else {
            // For public chat requests, nonce is optional - log for monitoring only
            $nonce_provided = !empty($_POST['nonce']);
            $nonce_valid = false;
            
            if ($nonce_provided) {
                $nonce_valid = wp_verify_nonce($_POST['nonce'], 'dredd_ai_nonce') || 
                              wp_verify_nonce($_POST['nonce'], 'dredd_ai_admin_nonce');
            }
            
            dredd_ai_log('Security: Public chat request - Nonce provided: ' . ($nonce_provided ? 'yes' : 'no') . 
                        ', Valid: ' . ($nonce_valid ? 'yes' : 'no'), 'debug');
        }
        

        
        return true;
    }
    
    /**
     * Sanitize and validate input data
     */
    public function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'text':
                return sanitize_text_field($data);
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'email':
                return sanitize_email($data);
                
            case 'url':
                return esc_url_raw($data);
                
            case 'int':
                return intval($data);
                
            case 'float':
                return floatval($data);
                
            case 'bool':
                return (bool) $data;
                
            case 'array':
                if (is_array($data)) {
                    return array_map('sanitize_text_field', $data);
                }
                return array();
                
            case 'json':
                if (is_string($data)) {
                    $decoded = json_decode($data, true);
                    return $decoded !== null ? $decoded : array();
                }
                return $data;
                
            case 'contract_address':
                $data = sanitize_text_field($data);
                if (preg_match('/^0x[a-fA-F0-9]{40}$/', $data)) {
                    return strtolower($data);
                }
                return '';
                
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (is_admin() && strpos($_SERVER['REQUEST_URI'], 'dredd-ai') !== false) {
            // Content Security Policy for admin pages
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.stripe.com;");
        }
        
        // General security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback to base64 if OpenSSL not available
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback for base64
        }
        
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     */
    private function get_encryption_key() {
        $key = get_option('dredd_ai_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('dredd_ai_encryption_key', $key);
        }
        
        return hash('sha256', $key . SECURE_AUTH_KEY);
    }
    
    /**
     * Export user data for GDPR compliance
     */
    public function export_user_data() {
        if (!$this->verify_ajax_request(true)) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        try {
            // Export all user data
            $export_data = $this->database->export_user_data($user_id);
            
            // Add WordPress user data
            $export_data['wordpress_user'] = array(
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered
            );
            
            // Generate filename
            $filename = 'dredd-ai-export-' . $user->user_login . '-' . date('Y-m-d-H-i-s') . '.json';
            
            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen(json_encode($export_data, JSON_PRETTY_PRINT)));
            
            echo json_encode($export_data, JSON_PRETTY_PRINT);
            
            dredd_ai_log("User data exported for user {$user_id}");
            exit;
            
        } catch (Exception $e) {
            dredd_ai_log('Data export error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Export failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete user data for GDPR compliance
     */
    public function delete_user_data() {
        if (!$this->verify_ajax_request(true)) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        $confirm = sanitize_text_field($_POST['confirm'] ?? '');
        
        if ($confirm !== 'DELETE_MY_DATA') {
            wp_send_json_error('Confirmation text required');
        }
        
        try {
            // Delete all plugin-related user data
            $deleted_records = $this->database->delete_user_data($user_id);
            
            dredd_ai_log("User data deleted for user {$user_id}: {$deleted_records} records removed");
            
            wp_send_json_success(array(
                'message' => 'All your DREDD AI data has been permanently deleted.',
                'records_deleted' => $deleted_records
            ));
            
        } catch (Exception $e) {
            dredd_ai_log('Data deletion error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Deletion failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle privacy requests
     */
    public function handle_privacy_request() {
        $request_type = sanitize_text_field($_POST['request_type'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        
        if (empty($user_email) || !is_email($user_email)) {
            wp_send_json_error('Valid email address required');
        }
        
        $user = get_user_by('email', $user_email);
        if (!$user) {
            wp_send_json_error('No account found with that email address');
        }
        
        switch ($request_type) {
            case 'export':
                $request_id = wp_create_user_request($user_email, 'export_personal_data');
                break;
                
            case 'delete':
                $request_id = wp_create_user_request($user_email, 'remove_personal_data');
                break;
                
            default:
                wp_send_json_error('Invalid request type');
        }
        
        if (is_wp_error($request_id)) {
            wp_send_json_error('Failed to create privacy request: ' . $request_id->get_error_message());
        }
        
        wp_send_json_success('Privacy request submitted. Check your email for confirmation.');
    }
    
    /**
     * Validate contract address format
     */
    public function validate_contract_address($address) {
        // Ethereum-style address validation
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return false;
        }
        
        // Optional: Add checksum validation
        return true;
    }
    
    /**
     * Validate blockchain chain
     */
    public function validate_chain($chain) {
        $supported_chains = array('ethereum', 'bsc', 'polygon', 'arbitrum', 'pulsechain');
        return in_array(strtolower($chain), $supported_chains);
    }
    
    /**
     * Validate analysis mode
     */
    public function validate_mode($mode) {
        return in_array(strtolower($mode), array('standard', 'psycho'));
    }
    
    /**
     * Log security events
     */
    public function log_security_event($event, $details = array()) {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $details
        );
        
        dredd_ai_log('Security Event: ' . json_encode($log_data), 'security');
    }
    
    /**
     * Check for suspicious activity
     */
    public function check_suspicious_activity($user_id, $activity_type) {
        $activity_key = "dredd_activity_{$activity_type}_user_{$user_id}";
        $activity_count = get_transient($activity_key);
        
        $thresholds = array(
            'failed_login' => 5,
            'rapid_requests' => 100,
            'payment_attempts' => 10,
            'analysis_requests' => 200
        );
        
        $threshold = $thresholds[$activity_type] ?? 50;
        
        if ($activity_count === false) {
            set_transient($activity_key, 1, HOUR_IN_SECONDS);
            return false;
        } elseif ($activity_count >= $threshold) {
            $this->log_security_event('suspicious_activity', array(
                'activity_type' => $activity_type,
                'count' => $activity_count,
                'threshold' => $threshold
            ));
            return true;
        } else {
            set_transient($activity_key, $activity_count + 1, HOUR_IN_SECONDS);
            return false;
        }
    }
    
    /**
     * Clean up old security logs
     */
    public function cleanup_security_logs() {
        // Remove logs older than 30 days
        $log_file = WP_CONTENT_DIR . '/dredd-ai-security.log';
        
        if (file_exists($log_file)) {
            $logs = file($log_file, FILE_IGNORE_NEW_LINES);
            $cutoff_date = date('Y-m-d', strtotime('-30 days'));
            $filtered_logs = array();
            
            foreach ($logs as $log) {
                if (strpos($log, $cutoff_date) === false || strpos($log, $cutoff_date) > 0) {
                    $filtered_logs[] = $log;
                }
            }
            
            file_put_contents($log_file, implode("\n", $filtered_logs));
        }
    }
    
    /**
     * Generate secure session token
     */
    public function generate_secure_token($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            return wp_generate_password($length, false);
        }
    }
    
    /**
     * Validate API keys
     */
    public function validate_api_key($api_key, $service) {
        if (empty($api_key)) {
            return false;
        }
        
        // Basic format validation
        switch ($service) {
            case 'stripe':
                return preg_match('/^sk_(test_|live_)[a-zA-Z0-9]{24,}$/', $api_key);
                
            case 'etherscan':
                return preg_match('/^[A-Z0-9]{34}$/', $api_key);
                
            case 'grok':
                return strlen($api_key) >= 20; // Basic length check
                
            default:
                return strlen($api_key) >= 10;
        }
    }
    
    /**
     * Sanitize file upload
     */
    public function sanitize_file_upload($file) {
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('File too large');
        }
        
        // Additional security checks
        $file_info = getimagesize($file['tmp_name']);
        if (!$file_info) {
            throw new Exception('Invalid image file');
        }
        
        return true;
    }
}
