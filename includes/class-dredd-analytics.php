<?php
/**
 * Analytics and reporting for DREDD AI plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Analytics {
    
    private $database;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        
        // Add AJAX handlers for analytics
        add_action('wp_ajax_dredd_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_dredd_export_analytics', array($this, 'export_analytics'));
    }
    
    /**
     * Get comprehensive analytics data
     */
    public function get_analytics_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));
        
        try {
            $analytics = array(
                'overview' => $this->get_overview_stats($date_from, $date_to),
                'analyses' => $this->get_analysis_stats($date_from, $date_to),
                'revenue' => $this->get_revenue_stats($date_from, $date_to),
                'users' => $this->get_user_stats($date_from, $date_to),
                'promotions' => $this->get_promotion_stats($date_from, $date_to),
                'performance' => $this->get_performance_stats($date_from, $date_to),
                'trends' => $this->get_trend_data($date_from, $date_to)
            );
            
            wp_send_json_success($analytics);
            
        } catch (Exception $e) {
            dredd_ai_log('Analytics error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to load analytics data');
        }
    }
    
    /**
     * Get overview statistics
     */
    private function get_overview_stats($date_from, $date_to) {
        global $wpdb;
        
        // Total analyses
        $analysis_table = $wpdb->prefix . 'dredd_analysis_history';
        $total_analyses = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analysis_table} WHERE created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Total revenue
        $transactions_table = $wpdb->prefix . 'dredd_transactions';
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$transactions_table} WHERE status = 'completed' AND created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Active users
        $active_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$analysis_table} WHERE created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Scams detected
        $scams_detected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$analysis_table} WHERE verdict = 'scam' AND created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        return array(
            'total_analyses' => intval($total_analyses),
            'total_revenue' => floatval($total_revenue),
            'active_users' => intval($active_users),
            'scams_detected' => intval($scams_detected)
        );
    }
    
    /**
     * Get analysis statistics
     */
    private function get_analysis_stats($date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_analysis_history';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_analyses,
                COUNT(CASE WHEN mode = 'standard' THEN 1 END) as standard_analyses,
                COUNT(CASE WHEN mode = 'psycho' THEN 1 END) as psycho_analyses,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scam_verdicts,
                COUNT(CASE WHEN verdict = 'legit' THEN 1 END) as legit_verdicts,
                COUNT(CASE WHEN verdict = 'caution' THEN 1 END) as caution_verdicts,
                AVG(processing_time) as avg_processing_time,
                AVG(confidence_score) as avg_confidence,
                AVG(risk_score) as avg_risk_score
            FROM {$table} 
            WHERE created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Chain analysis
        $chain_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                chain,
                COUNT(*) as count,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scams
            FROM {$table} 
            WHERE created_at BETWEEN %s AND %s 
            GROUP BY chain 
            ORDER BY count DESC",
            $date_from, $date_to
        ));
        
        return array(
            'overview' => $stats,
            'by_chain' => $chain_stats
        );
    }
    
    /**
     * Get revenue statistics
     */
    private function get_revenue_stats($date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        
        $revenue_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_transactions,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' AND payment_method = 'stripe' THEN amount ELSE 0 END) as stripe_revenue,
                SUM(CASE WHEN status = 'completed' AND payment_method IN ('usdt', 'usdc') THEN amount ELSE 0 END) as crypto_revenue,
                SUM(CASE WHEN status = 'completed' THEN tokens ELSE 0 END) as tokens_sold,
                AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction_value
            FROM {$table} 
            WHERE created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Daily revenue trend
        $daily_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as revenue,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as transactions
            FROM {$table} 
            WHERE created_at BETWEEN %s AND %s 
            GROUP BY DATE(created_at) 
            ORDER BY date",
            $date_from, $date_to
        ));
        
        return array(
            'overview' => $revenue_stats,
            'daily_trend' => $daily_revenue
        );
    }
    
    /**
     * Get user statistics
     */
    private function get_user_stats($date_from, $date_to) {
        global $wpdb;
        $analysis_table = $wpdb->prefix . 'dredd_analysis_history';        
        // User activity stats
        $user_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT user_id) as active_users,
                COUNT(DISTINCT CASE WHEN mode = 'psycho' THEN user_id END) as psycho_users,
                AVG(analyses_per_user.count) as avg_analyses_per_user
            FROM {$analysis_table} a
            JOIN (
                SELECT user_id, COUNT(*) as count 
                FROM {$analysis_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY user_id
            ) analyses_per_user ON a.user_id = analyses_per_user.user_id
            WHERE a.created_at BETWEEN %s AND %s",
            $date_from, $date_to, $date_from, $date_to
        ));
        
        // Top users by analysis count
        $top_users = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                user_id,
                COUNT(*) as analysis_count,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scams_found,
                COUNT(CASE WHEN mode = 'psycho' THEN 1 END) as psycho_analyses
            FROM {$analysis_table} 
            WHERE created_at BETWEEN %s AND %s 
            GROUP BY user_id 
            ORDER BY analysis_count DESC 
            LIMIT 10",
            $date_from, $date_to
        ));
        
        // User retention (users who came back)
        $retention_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT first_analysis.user_id) as new_users,
                COUNT(DISTINCT return_users.user_id) as returning_users
            FROM (
                SELECT user_id, MIN(created_at) as first_analysis_date
                FROM {$analysis_table}
                WHERE created_at BETWEEN %s AND %s
                GROUP BY user_id
            ) first_analysis
            LEFT JOIN (
                SELECT DISTINCT user_id
                FROM {$analysis_table}
                WHERE created_at < %s
            ) return_users ON first_analysis.user_id = return_users.user_id",
            $date_from, $date_to, $date_from
        ));
        
        return array(
            'overview' => $user_stats,
            'top_users' => $top_users,
            'retention' => $retention_stats
        );
    }
    
    /**
     * Get promotion statistics
     */
    private function get_promotion_stats($date_from, $date_to) {
        $promotions = new Dredd_Promotions();
        return $promotions->get_promotion_statistics($date_from, $date_to);
    }
    
    /**
     * Get performance statistics
     */
    private function get_performance_stats($date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_analysis_history';
        
        $performance = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(processing_time) as avg_processing_time,
                MIN(processing_time) as min_processing_time,
                MAX(processing_time) as max_processing_time,
                COUNT(CASE WHEN processing_time > 60 THEN 1 END) as slow_analyses,
                COUNT(CASE WHEN processing_time <= 30 THEN 1 END) as fast_analyses
            FROM {$table} 
            WHERE created_at BETWEEN %s AND %s 
            AND processing_time IS NOT NULL",
            $date_from, $date_to
        ));
        
        // Cache hit rate (if we're tracking it)
        $cache_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_cache_entries,
                COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_cache_entries
            FROM {$wpdb->prefix}dredd_cache"
        );
        
        return array(
            'processing' => $performance,
            'cache' => $cache_stats
        );
    }
    
    /**
     * Get trend data for charts
     */
    private function get_trend_data($date_from, $date_to) {
        global $wpdb;
        $analysis_table = $wpdb->prefix . 'dredd_analysis_history';
        
        // Daily analysis trend
        $daily_analyses = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_analyses,
                COUNT(CASE WHEN mode = 'standard' THEN 1 END) as standard_analyses,
                COUNT(CASE WHEN mode = 'psycho' THEN 1 END) as psycho_analyses,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scams_detected
            FROM {$analysis_table} 
            WHERE created_at BETWEEN %s AND %s 
            GROUP BY DATE(created_at) 
            ORDER BY date",
            $date_from, $date_to
        ));
        
        // Hourly analysis pattern
        $hourly_pattern = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as analysis_count
            FROM {$analysis_table} 
            WHERE created_at BETWEEN %s AND %s 
            GROUP BY HOUR(created_at) 
            ORDER BY hour",
            $date_from, $date_to
        ));
        
        return array(
            'daily_analyses' => $daily_analyses,
            'hourly_pattern' => $hourly_pattern
        );
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        try {
            $data = $this->get_export_data($date_from, $date_to);
            
            if ($format === 'csv') {
                $this->export_csv($data, $date_from, $date_to);
            } else {
                $this->export_json($data, $date_from, $date_to);
            }
            
        } catch (Exception $e) {
            dredd_ai_log('Analytics export error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Export failed');
        }
    }
    
    /**
     * Get data for export
     */
    private function get_export_data($date_from, $date_to) {
        global $wpdb;
        
        // Get detailed analysis data
        $analysis_table = $wpdb->prefix . 'dredd_analysis_history';
        $analyses = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                analysis_id,
                user_id,
                token_name,
                contract_address,
                chain,
                mode,
                verdict,
                confidence_score,
                risk_score,
                token_cost,
                processing_time,
                created_at
            FROM {$analysis_table} 
            WHERE created_at BETWEEN %s AND %s 
            ORDER BY created_at DESC",
            $date_from, $date_to
        ));
        
        // Get transaction data
        $transactions_table = $wpdb->prefix . 'dredd_transactions';
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                transaction_id,
                user_id,
                amount,
                tokens,
                payment_method,
                status,
                created_at
            FROM {$transactions_table} 
            WHERE created_at BETWEEN %s AND %s 
            ORDER BY created_at DESC",
            $date_from, $date_to
        ));
        
        return array(
            'analyses' => $analyses,
            'transactions' => $transactions,
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            )
        );
    }
    
    /**
     * Export as CSV
     */
    private function export_csv($data, $date_from, $date_to) {
        $filename = 'dredd-ai-analytics-' . $date_from . '-to-' . $date_to . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Analyses CSV
        fputcsv($output, array('=== ANALYSES ==='));
        fputcsv($output, array('Analysis ID', 'User ID', 'Token Name', 'Contract', 'Chain', 'Mode', 'Verdict', 'Confidence', 'Risk Score', 'Token Cost', 'Processing Time', 'Date'));
        
        foreach ($data['analyses'] as $analysis) {
            fputcsv($output, array(
                $analysis->analysis_id,
                $analysis->user_id,
                $analysis->token_name,
                $analysis->contract_address,
                $analysis->chain,
                $analysis->mode,
                $analysis->verdict,
                $analysis->confidence_score,
                $analysis->risk_score,
                $analysis->token_cost,
                $analysis->processing_time,
                $analysis->created_at
            ));
        }
        
        // Transactions CSV
        fputcsv($output, array(''));
        fputcsv($output, array('=== TRANSACTIONS ==='));
        fputcsv($output, array('Transaction ID', 'User ID', 'Amount', 'Tokens', 'Payment Method', 'Status', 'Date'));
        
        foreach ($data['transactions'] as $transaction) {
            fputcsv($output, array(
                $transaction->transaction_id,
                $transaction->user_id,
                $transaction->amount,
                $transaction->tokens,
                $transaction->payment_method,
                $transaction->status,
                $transaction->created_at
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export as JSON
     */
    private function export_json($data, $date_from, $date_to) {
        $filename = 'dredd-ai-analytics-' . $date_from . '-to-' . $date_to . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Get real-time statistics for dashboard widgets
     */
    public function get_realtime_stats() {
        global $wpdb;
        
        // Last 24 hours stats
        $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $analysis_table = $wpdb->prefix . 'dredd_analysis_history';
        $transactions_table = $wpdb->prefix . 'dredd_transactions';
        
        $stats = array(
            'analyses_24h' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$analysis_table} WHERE created_at >= %s",
                $last_24h
            )),
            'revenue_24h' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM {$transactions_table} WHERE status = 'completed' AND created_at >= %s",
                $last_24h
            )),
            'scams_detected_24h' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$analysis_table} WHERE verdict = 'scam' AND created_at >= %s",
                $last_24h
            )),
            'active_users_24h' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$analysis_table} WHERE created_at >= %s",
                $last_24h
            ))
        );
        
        return $stats;
    }
    
    /**
     * Get popular tokens analysis
     */
    public function get_popular_tokens($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_analysis_history';
        
        $popular_tokens = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                token_name,
                contract_address,
                chain,
                COUNT(*) as analysis_count,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as scam_count,
                COUNT(CASE WHEN verdict = 'legit' THEN 1 END) as legit_count,
                MAX(created_at) as last_analyzed
            FROM {$table} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY contract_address, chain 
            ORDER BY analysis_count DESC 
            LIMIT %d",
            $limit
        ));
        
        return $popular_tokens;
    }
    
    /**
     * Get scam detection accuracy metrics
     */
    public function get_accuracy_metrics() {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_analysis_history';
        
        // This would require manual verification data to calculate true accuracy
        // For now, we'll provide confidence-based metrics
        $metrics = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_analyses,
                AVG(confidence_score) as avg_confidence,
                COUNT(CASE WHEN confidence_score >= 0.8 THEN 1 END) as high_confidence_analyses,
                COUNT(CASE WHEN confidence_score >= 0.8 AND verdict = 'scam' THEN 1 END) as high_confidence_scams,
                COUNT(CASE WHEN verdict = 'scam' THEN 1 END) as total_scams,
                COUNT(CASE WHEN verdict = 'legit' THEN 1 END) as total_legit
            FROM {$table} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $metrics;
    }
}
