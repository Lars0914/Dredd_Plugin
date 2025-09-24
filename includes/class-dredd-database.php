<?php
/**
 * Database management class for DREDD AI plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Database {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->ensure_chat_users_table_exists();
        add_action('plugins_loaded', array($this, 'check_database_upgrade'));
    }

        
    /**
     * Check for database upgrades and create missing tables
     */
    public function check_database_upgrade() {
        $current_version = get_option('dredd_ai_db_version', '1.0.0');

        // Always check if chat users table exists and create if missing
        $this->ensure_chat_users_table_exists();

        // If version is less than 1.0.1, update version
        if (version_compare($current_version, '1.0.1', '<')) {
            update_option('dredd_ai_db_version', '1.0.1');
        }
    }

    
        /**
     * Ensure the chat users table exists
     */
    private function ensure_chat_users_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dredd_chat_users';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_chat_users_table();
        }
    }

    private function create_chat_users_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $chat_users_table = $this->wpdb->prefix . 'dredd_chat_users';
        $sql_chat_users = "CREATE TABLE {$chat_users_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            password varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_username (username),
            UNIQUE KEY unique_email (email),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_chat_users);

        dredd_ai_log('Created dredd_chat_users table via upgrade', 'info');
    }

    /**
     * Create all custom database tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // User tokens table
        $user_tokens_table = $this->wpdb->prefix . 'dredd_user_tokens';
        $sql_user_tokens = "CREATE TABLE {$user_tokens_table} (
            user_id bigint(20) unsigned NOT NULL,
            token_balance int(11) NOT NULL DEFAULT 0,
            total_purchased int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_user_balance (user_id, token_balance)
        ) {$charset_collate};";
        
        // Transactions table
        $transactions_table = $this->wpdb->prefix . 'dredd_transactions';
        $sql_transactions = "CREATE TABLE {$transactions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL,
            tokens int(11) NOT NULL,
            payment_method enum('stripe', 'usdt', 'usdc') NOT NULL,
            chain varchar(50) DEFAULT NULL,
            tx_hash varchar(255) DEFAULT NULL,
            stripe_payment_intent varchar(255) DEFAULT NULL,
            status enum('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
            metadata text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_transaction_id (transaction_id),
            KEY idx_user_transactions (user_id, created_at),
            KEY idx_status (status),
            KEY idx_payment_method (payment_method)
        ) {$charset_collate};";
        
        // Analysis history table
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        $sql_analysis = "CREATE TABLE {$analysis_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            analysis_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            session_id varchar(255) NOT NULL,
            token_name varchar(100) NOT NULL,
            token_symbol varchar(20) DEFAULT NULL,
            contract_address varchar(42) NOT NULL,
            chain varchar(50) NOT NULL,
            mode enum('standard', 'psycho') NOT NULL DEFAULT 'standard',
            token_cost int(11) NOT NULL DEFAULT 0,
            verdict enum('scam', 'caution', 'legit', 'unknown') DEFAULT 'unknown',
            confidence_score decimal(3,2) DEFAULT NULL,
            risk_score decimal(3,2) DEFAULT NULL,
            analysis_data longtext DEFAULT NULL,
            dredd_response longtext DEFAULT NULL,
            api_responses longtext DEFAULT NULL,
            processing_time int(11) DEFAULT NULL,
            wp_post_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_analysis_id (analysis_id),
            KEY idx_user_analysis (user_id, created_at),
            KEY idx_contract_chain (contract_address, chain),
            KEY idx_expires (expires_at),
            KEY idx_verdict (verdict),
            KEY idx_mode (mode),
            KEY idx_session (session_id)
        ) {$charset_collate};";
        
        // Token promotions table
        $promotions_table = $this->wpdb->prefix . 'dredd_promotions';
        $sql_promotions = "CREATE TABLE {$promotions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token_name varchar(100) NOT NULL,
            token_symbol varchar(20) DEFAULT NULL,
            token_logo varchar(255) DEFAULT NULL,
            tagline varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            website_url varchar(255) DEFAULT NULL,
            wp_post_id bigint(20) unsigned DEFAULT NULL,
            contract_address varchar(42) DEFAULT NULL,
            chain varchar(50) DEFAULT NULL,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            status enum('pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
            clicks int(11) NOT NULL DEFAULT 0,
            impressions int(11) NOT NULL DEFAULT 0,
            cost_per_day decimal(10,2) NOT NULL DEFAULT 0.00,
            total_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_status enum('pending', 'paid', 'refunded') NOT NULL DEFAULT 'pending',
            payment_transaction_id varchar(255) DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            approved_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_dates (status, start_date, end_date),
            KEY idx_active_promotions (status, start_date, end_date),
            KEY idx_contract_chain (contract_address, chain),
            KEY idx_created_by (created_by)
        ) {$charset_collate};";
        
        // Cache table
        $cache_table = $this->wpdb->prefix . 'dredd_cache';
        $sql_cache = "CREATE TABLE {$cache_table} (
            cache_key varchar(255) NOT NULL,
            cache_data longtext NOT NULL,
            cache_type varchar(50) NOT NULL DEFAULT 'analysis',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY idx_expires (expires_at),
            KEY idx_type_expires (cache_type, expires_at)
        ) {$charset_collate};";
        
        // User sessions table for chat management
        $sessions_table = $this->wpdb->prefix . 'dredd_user_sessions';
        $sql_sessions = "CREATE TABLE {$sessions_table} (
            session_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            chat_history longtext DEFAULT NULL,
            current_mode enum('standard', 'psycho') NOT NULL DEFAULT 'standard',
            selected_chain varchar(50) DEFAULT 'ethereum',
            extracted_data longtext DEFAULT NULL,
            last_activity datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (session_id),
            KEY idx_user_sessions (user_id, last_activity),
            KEY idx_last_activity (last_activity)
        ) {$charset_collate};";
        
                // Chat users table for storing signup data
        $chat_users_table = $this->wpdb->prefix . 'dredd_chat_users';
        $sql_chat_users = "CREATE TABLE {$chat_users_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            password varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_username (username),
            UNIQUE KEY unique_email (email),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";
        
        // NOWPayments specific payments table
        $payments_table = $this->wpdb->prefix . 'dredd_payments';
        $sql_payments = "CREATE TABLE {$payments_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            payment_id varchar(255) NOT NULL,
            order_id varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(20) NOT NULL,
            status enum('waiting', 'confirming', 'confirmed', 'sending', 'partially_paid', 'finished', 'failed', 'refunded', 'expired') NOT NULL DEFAULT 'waiting',
            payment_method varchar(50) NOT NULL DEFAULT 'nowpayments',
            package_data text DEFAULT NULL,
            payment_data text DEFAULT NULL,
            webhook_data text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_payment_id (payment_id),
            KEY idx_user_payments (user_id, created_at),
            KEY idx_status (status),
            KEY idx_payment_method (payment_method)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_user_tokens);
        dbDelta($sql_transactions);
        dbDelta($sql_analysis);
        dbDelta($sql_promotions);
        dbDelta($sql_cache);
        dbDelta($sql_sessions);
        dbDelta($sql_payments);
        
        // Update database version
        update_option('dredd_ai_db_version', '1.0.0');
    }
    
    /**
     * Get user token data
     */
    public function get_user_data($user_id) {
        $tokens_table = $this->wpdb->prefix . 'dredd_user_tokens';
        $transactions_table = $this->wpdb->prefix . 'dredd_transactions';
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        
        // Get token balance
        $token_data = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$tokens_table} WHERE user_id = %d",
            $user_id
        ));
        
        // Get recent transactions
        $recent_transactions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$transactions_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
            $user_id
        ));
        
        // Get analysis statistics
        $analysis_stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_analyses,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scams_detected,
                COUNT(CASE WHEN verdict = 'legit' THEN 1 END) as legit_tokens,
                COUNT(CASE WHEN verdict = 'caution' THEN 1 END) as caution_tokens,
                COUNT(CASE WHEN mode = 'psycho' THEN 1 END) as psycho_analyses,
                SUM(token_cost) as total_tokens_spent
            FROM {$analysis_table} WHERE user_id = %d",
            $user_id
        ));
        
        return array(
            'tokens' => $token_data,
            'transactions' => $recent_transactions,
            'stats' => $analysis_stats
        );
    }
    
    /**
     * Get user analysis history with pagination
     */
    public function get_user_analysis_history($user_id, $limit = 20, $offset = 0, $filters = array()) {
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        
        $where_conditions = array("user_id = %d");
        $where_values = array($user_id);
        
        // Apply filters
        if (!empty($filters['mode'])) {
            $where_conditions[] = "mode = %s";
            $where_values[] = $filters['mode'];
        }
        
        if (!empty($filters['verdict'])) {
            $where_conditions[] = "verdict = %s";
            $where_values[] = $filters['verdict'];
        }
        
        if (!empty($filters['chain'])) {
            $where_conditions[] = "chain = %s";
            $where_values[] = $filters['chain'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$analysis_table} WHERE {$where_clause}";
        $total_count = $this->wpdb->get_var($this->wpdb->prepare($count_query, $where_values));
        
        // Get paginated results
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        $query = "SELECT * FROM {$analysis_table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $results = $this->wpdb->get_results($this->wpdb->prepare($query, $where_values));
        
        return array(
            'total' => $total_count,
            'results' => $results
        );
    }
    
    /**
     * Store analysis result
     */
    public function store_analysis($data) {
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        
        $retention_days = $this->get_user_retention_days($data['user_id']);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));
        
        return $this->wpdb->insert(
            $analysis_table,
            array(
                'analysis_id' => $data['analysis_id'],
                'user_id' => $data['user_id'],
                'session_id' => $data['session_id'],
                'token_name' => $data['token_name'],
                'token_symbol' => $data['token_symbol'] ?? null,
                'contract_address' => $data['contract_address'],
                'chain' => $data['chain'],
                'mode' => $data['mode'],
                'token_cost' => $data['token_cost'],
                'verdict' => $data['verdict'] ?? 'unknown',
                'confidence_score' => $data['confidence_score'] ?? null,
                'risk_score' => $data['risk_score'] ?? null,
                'analysis_data' => json_encode($data['analysis_data']),
                'dredd_response' => $data['dredd_response'],
                'api_responses' => json_encode($data['api_responses']),
                'processing_time' => $data['processing_time'] ?? null,
                'wp_post_id' => $data['wp_post_id'] ?? null,
                'expires_at' => $expires_at
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%d', '%s')
        );
    }
    
    /**
     * Get cached analysis
     */
    public function get_cached_analysis($contract_address, $chain, $mode) {
        $cache_table = $this->wpdb->prefix . 'dredd_cache';
        $cache_key = md5($contract_address . '_' . $chain . '_' . $mode);
        
        $cached_data = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$cache_table} WHERE cache_key = %s AND expires_at > NOW()",
            $cache_key
        ));
        
        return $cached_data ? json_decode($cached_data->cache_data, true) : false;
    }
    
    /**
     * Store analysis in cache
     */
    public function cache_analysis($contract_address, $chain, $mode, $data) {
        $cache_table = $this->wpdb->prefix . 'dredd_cache';
        $cache_key = md5($contract_address . '_' . $chain . '_' . $mode);
        $cache_duration = dredd_ai_get_option('cache_duration', 24);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$cache_duration} hours"));
        
        return $this->wpdb->replace(
            $cache_table,
            array(
                'cache_key' => $cache_key,
                'cache_data' => json_encode($data),
                'cache_type' => 'analysis',
                'expires_at' => $expires_at
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Store transaction
     */
    public function store_transaction($data) {
        $transactions_table = $this->wpdb->prefix . 'dredd_transactions';
        
        return $this->wpdb->insert(
            $transactions_table,
            array(
                'transaction_id' => $data['transaction_id'],
                'user_id' => $data['user_id'],
                'amount' => $data['amount'],
                'tokens' => $data['tokens'],
                'payment_method' => $data['payment_method'],
                'chain' => $data['chain'] ?? null,
                'tx_hash' => $data['tx_hash'] ?? null,
                'stripe_payment_intent' => $data['stripe_payment_intent'] ?? null,
                'status' => $data['status'],
                'metadata' => json_encode($data['metadata'] ?? array())
            ),
            array('%s', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Update transaction status
     */
    public function update_transaction_status($transaction_id, $status, $metadata = array()) {
        $transactions_table = $this->wpdb->prefix . 'dredd_transactions';
        
        $update_data = array('status' => $status);
        $update_format = array('%s');
        
        if (!empty($metadata)) {
            $update_data['metadata'] = json_encode($metadata);
            $update_format[] = '%s';
        }
        
        return $this->wpdb->update(
            $transactions_table,
            $update_data,
            array('transaction_id' => $transaction_id),
            $update_format,
            array('%s')
        );
    }
    
    /**
     * Get user session data
     */
    public function get_user_session($session_id) {
        $sessions_table = $this->wpdb->prefix . 'dredd_user_sessions';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$sessions_table} WHERE session_id = %s",
            $session_id
        ));
    }
    
    /**
     * Update user session
     */
    public function update_user_session($session_id, $data) {
        $sessions_table = $this->wpdb->prefix . 'dredd_user_sessions';
        
        return $this->wpdb->replace(
            $sessions_table,
            array(
                'session_id' => $session_id,
                'user_id' => $data['user_id'],
                'chat_history' => json_encode($data['chat_history'] ?? array()),
                'current_mode' => $data['current_mode'] ?? 'standard',
                'selected_chain' => $data['selected_chain'] ?? 'ethereum',
                'extracted_data' => json_encode($data['extracted_data'] ?? array()),
                'last_activity' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Cleanup expired data
     */
    public function cleanup_expired_analysis() {
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        
        $deleted = $this->wpdb->query(
            "DELETE FROM {$analysis_table} WHERE expires_at < NOW()"
        );
        
        dredd_ai_log("Cleaned up {$deleted} expired analysis records");
        return $deleted;
    }
    
    /**
     * Cleanup expired cache
     */
    public function cleanup_expired_cache() {
        $cache_table = $this->wpdb->prefix . 'dredd_cache';
        
        $deleted = $this->wpdb->query(
            "DELETE FROM {$cache_table} WHERE expires_at < NOW()"
        );
        
        dredd_ai_log("Cleaned up {$deleted} expired cache entries");
        return $deleted;
    }
    
    /**
     * Cleanup old sessions
     */
    public function cleanup_old_sessions() {
        $sessions_table = $this->wpdb->prefix . 'dredd_user_sessions';
        
        $deleted = $this->wpdb->query(
            "DELETE FROM {$sessions_table} WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        dredd_ai_log("Cleaned up {$deleted} old sessions");
        return $deleted;
    }
    
    /**
     * Get user retention days based on account type
     */
    private function get_user_retention_days($user_id) {
        $user_credits = dredd_ai_get_user_credits($user_id);
        
        if ($user_credits > 0) {
            return dredd_ai_get_option('data_retention_paid', 365);
        } else {
            return dredd_ai_get_option('data_retention_free', 90);
        }
    }
    
    /**
     * Get analytics data for admin dashboard
     */
    public function get_analytics_data($date_from = null, $date_to = null) {
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        $transactions_table = $this->wpdb->prefix . 'dredd_transactions';
        $promotions_table = $this->wpdb->prefix . 'dredd_promotions';
        
        $date_condition = '';
        $date_params = array();
        
        if ($date_from && $date_to) {
            $date_condition = "WHERE created_at BETWEEN %s AND %s";
            $date_params = array($date_from, $date_to);
        }
        
        // Analysis statistics
        $analysis_stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_analyses,
                COUNT(CASE WHEN mode = 'standard' THEN 1 END) as standard_analyses,
                COUNT(CASE WHEN mode = 'psycho' THEN 1 END) as psycho_analyses,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scams_detected,
                COUNT(CASE WHEN verdict = 'legit' THEN 1 END) as legit_tokens,
                COUNT(CASE WHEN verdict = 'caution' THEN 1 END) as caution_tokens,
                AVG(processing_time) as avg_processing_time,
                SUM(token_cost) as total_tokens_used
            FROM {$analysis_table} {$date_condition}",
            $date_params
        ));
        
        // Revenue statistics
        $revenue_stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' AND payment_method = 'stripe' THEN amount ELSE 0 END) as stripe_revenue,
                SUM(CASE WHEN status = 'completed' AND payment_method IN ('usdt', 'usdc') THEN amount ELSE 0 END) as crypto_revenue,
                SUM(CASE WHEN status = 'completed' THEN tokens ELSE 0 END) as total_tokens_sold
            FROM {$transactions_table} {$date_condition}",
            $date_params
        ));
        
        // Active promotions
        $promotion_stats = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total_promotions,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promotions,
                SUM(clicks) as total_clicks,
                SUM(impressions) as total_impressions,
                SUM(CASE WHEN payment_status = 'paid' THEN total_cost ELSE 0 END) as promotion_revenue
            FROM {$promotions_table}"
        );
        
        return array(
            'analyses' => $analysis_stats,
            'revenue' => $revenue_stats,
            'promotions' => $promotion_stats
        );
    }
    
    /**
     * Export user data for GDPR compliance
     */
    public function export_user_data($user_id) {
        $tokens_table = $this->wpdb->prefix . 'dredd_user_tokens';
        $transactions_table = $this->wpdb->prefix . 'dredd_transactions';
        $analysis_table = $this->wpdb->prefix . 'dredd_analysis_history';
        $sessions_table = $this->wpdb->prefix . 'dredd_user_sessions';
        
        $data = array();
        
        // User tokens
        $data['tokens'] = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$tokens_table} WHERE user_id = %d",
            $user_id
        ));
        
        // Transactions
        $data['transactions'] = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$transactions_table} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
        
        // Analysis history
        $data['analyses'] = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$analysis_table} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
        
        // Sessions
        $data['sessions'] = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$sessions_table} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
        
        return $data;
    }
    
    /**
     * Delete all user data for GDPR compliance
     */
    public function delete_user_data($user_id) {
        $tables = array(
            $this->wpdb->prefix . 'dredd_user_tokens',
            $this->wpdb->prefix . 'dredd_transactions',
            $this->wpdb->prefix . 'dredd_analysis_history',
            $this->wpdb->prefix . 'dredd_user_sessions'
        );
        
        $deleted_records = 0;
        
        foreach ($tables as $table) {
            $deleted = $this->wpdb->delete($table, array('user_id' => $user_id), array('%d'));
            $deleted_records += $deleted;
        }
        
        dredd_ai_log("Deleted {$deleted_records} records for user {$user_id}");
        return $deleted_records;
    }
}
