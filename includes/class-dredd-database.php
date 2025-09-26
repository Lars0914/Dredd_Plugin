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

        // Ensure all tables exist
        $this->ensure_all_tables_exist();

        // Hook to plugins_loaded to handle DB version upgrades
        add_action('plugins_loaded', array($this, 'check_database_upgrade'));
    }

    public function check_database_upgrade() {
        $current_version = get_option('dredd_ai_db_version', '1.0.0');

        // Ensure tables are in place
        $this->ensure_all_tables_exist();

        // Example version check
        if (version_compare($current_version, '1.0.1', '<')) {
            // Potential upgrade logic here
            update_option('dredd_ai_db_version', '1.0.1');
        }
    }

    /**
     * Run all table existence checks
     */
    private function ensure_all_tables_exist() {
        $this->ensure_chat_users_table_exists();
        $this->ensure_user_tokens_table_exists();
        $this->ensure_transactions_table_exists();
        $this->ensure_analysis_table_exists();
        $this->ensure_promotions_table_exists();
        $this->ensure_cache_table_exists();
        $this->ensure_sessions_table_exists();
        $this->ensure_payments_table_exists();
    }

    // Repeat this pattern for each table below

    private function ensure_chat_users_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_chat_users';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_chat_users_table();
        }
    }

    private function create_chat_users_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_chat_users';

        $sql = "CREATE TABLE {$table} (
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
        dbDelta($sql);
    }

    private function ensure_user_tokens_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_user_tokens';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_user_tokens_table();
        }
    }

    private function create_user_tokens_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_user_tokens';

        $sql = "CREATE TABLE {$table} (
            user_id bigint(20) unsigned NOT NULL,
            token_balance int(11) NOT NULL DEFAULT 0,
            total_purchased int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_user_balance (user_id, token_balance)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function ensure_transactions_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_transactions';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_transactions_table();
        }
    }

    private function create_transactions_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_transactions';

        $sql = "CREATE TABLE {$table} (
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function ensure_analysis_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_analysis_history';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_analysis_table();
        }
    }

    private function create_analysis_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_analysis_history';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            analysis_id bigint(20) NOT NULL AUTO_INCREMENT,
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
            processing_time int(11) DEFAULT 300,
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function ensure_promotions_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_promotions';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_promotions_table();
        }
    }

    private function create_promotions_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_promotions';

        $sql = "CREATE TABLE {$table} (
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function ensure_cache_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_cache';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_cache_table();
        }
    }

    private function create_cache_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_cache';

        $sql = "CREATE TABLE {$table} (
            cache_key varchar(255) NOT NULL,
            cache_data longtext NOT NULL,
            cache_type varchar(50) NOT NULL DEFAULT 'analysis',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY idx_expires (expires_at),
            KEY idx_type_expires (cache_type, expires_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function ensure_sessions_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_user_sessions';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_sessions_table();
        }
    }

    private function create_sessions_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_user_sessions';

        $sql = "CREATE TABLE {$table} (
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function ensure_payments_table_exists() {
        $table = $this->wpdb->prefix . 'dredd_payments';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_payments_table();
        }
    }

    private function create_payments_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'dredd_payments';

        $sql = "CREATE TABLE {$table} (
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
        dbDelta($sql);
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
                'processing_time' => $data['processing_time'] ?? null,
                'wp_post_id' => $data['wp_post_id'] ?? null,
                'expires_at' => $expires_at ?? null
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%d', '%s')
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
