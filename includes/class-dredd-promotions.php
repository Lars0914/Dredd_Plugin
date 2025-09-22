<?php
/**
 * Token promotion system for DREDD AI plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Promotions {
    
    private $database;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        
        // Add AJAX handlers
        add_action('wp_ajax_dredd_track_impression', array($this, 'track_impression'));
        add_action('wp_ajax_nopriv_dredd_track_impression', array($this, 'track_impression'));
        add_action('wp_ajax_dredd_track_click', array($this, 'track_click'));
        add_action('wp_ajax_nopriv_dredd_track_click', array($this, 'track_click'));
        add_action('wp_ajax_dredd_add_promotion', array($this, 'add_promotion'));
        add_action('wp_ajax_dredd_update_promotion', array($this, 'update_promotion'));
        add_action('wp_ajax_dredd_delete_promotion', array($this, 'delete_promotion'));
        
        // Cron job for promotion management
        add_action('dredd_check_promotion_status', array($this, 'check_promotion_status'));
        
        if (!wp_next_scheduled('dredd_check_promotion_status')) {
            wp_schedule_event(time(), 'hourly', 'dredd_check_promotion_status');
        }
    }
    
    /**
     * Get active promotions for display
     */
    public function get_active_promotions($limit = 5) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        $promotions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE status = 'active' 
             AND start_date <= NOW() 
             AND end_date >= NOW() 
             ORDER BY RAND() 
             LIMIT %d",
            $limit
        ));
        
        return $promotions;
    }
    
    /**
     * Track promotion impression
     */
    public function track_impression() {
        $promotion_id = intval($_POST['promotion_id'] ?? 0);
        
        if (!$promotion_id) {
            wp_send_json_error('Invalid promotion ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET impressions = impressions + 1 WHERE id = %d AND status = 'active'",
            $promotion_id
        ));
        
        if ($result) {
            wp_send_json_success('Impression tracked');
        } else {
            wp_send_json_error('Failed to track impression');
        }
    }
    
    /**
     * Track promotion click
     */
    public function track_click() {
        $promotion_id = intval($_POST['promotion_id'] ?? 0);
        
        if (!$promotion_id) {
            wp_send_json_error('Invalid promotion ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET clicks = clicks + 1 WHERE id = %d AND status = 'active'",
            $promotion_id
        ));
        
        if ($result) {
            wp_send_json_success('Click tracked');
        } else {
            wp_send_json_error('Failed to track click');
        }
    }
    
    /**
     * Add new promotion
     */
    public function add_promotion() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $security = new Dredd_Security();
        if (!$security->verify_ajax_request()) {
            wp_send_json_error('Security check failed');
        }
        
        $data = array(
            'token_name' => sanitize_text_field($_POST['token_name'] ?? ''),
            'token_symbol' => !empty($_POST['token_symbol']) ? sanitize_text_field($_POST['token_symbol']) : null,
            'token_logo' => !empty($_POST['token_logo']) ? esc_url_raw($_POST['token_logo']) : null,
            'tagline' => !empty($_POST['tagline']) ? sanitize_text_field($_POST['tagline']) : null,
            'description' => !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : null,
            'website_url' => !empty($_POST['website_url']) ? esc_url_raw($_POST['website_url']) : null,
            'contract_address' => !empty($_POST['contract_address']) ? $security->sanitize_input($_POST['contract_address'], 'contract_address') : null,
            'chain' => sanitize_text_field($_POST['chain'] ?? 'ethereum'),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'cost_per_day' => floatval($_POST['cost_per_day'] ?? 0)
        );
        
        // Validation
        if (empty($data['token_name']) || empty($data['start_date']) || empty($data['end_date'])) {
            wp_send_json_error('Required fields missing: token name, start date, and end date are required');
        }
        
        // Validate field lengths to match database schema
        if (strlen($data['token_name']) > 100) {
            wp_send_json_error('Token name is too long (maximum 100 characters)');
        }
        
        if (!empty($data['token_symbol']) && strlen($data['token_symbol']) > 20) {
            wp_send_json_error('Token symbol is too long (maximum 20 characters)');
        }
        
        if (!empty($data['tagline']) && strlen($data['tagline']) > 255) {
            wp_send_json_error('Tagline is too long (maximum 255 characters)');
        }
        
        if (!empty($data['contract_address']) && strlen($data['contract_address']) > 42) {
            wp_send_json_error('Contract address is too long (maximum 42 characters)');
        }
        
        if (strlen($data['chain']) > 50) {
            wp_send_json_error('Chain name is too long (maximum 50 characters)');
        }
        
        // Validate dates
        if (strtotime($data['start_date']) === false || strtotime($data['end_date']) === false) {
            wp_send_json_error('Invalid date format. Please use YYYY-MM-DD HH:MM format.');
        }
        
        if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
            wp_send_json_error('End date must be after start date');
        }
        
        if (!$security->validate_chain($data['chain'])) {
            wp_send_json_error('Invalid blockchain chain');
        }
        
        if (!empty($data['contract_address']) && !$security->validate_contract_address($data['contract_address'])) {
            wp_send_json_error('Invalid contract address format');
        }
        
        // Calculate total cost
        $start_timestamp = strtotime($data['start_date']);
        $end_timestamp = strtotime($data['end_date']);
        $days = max(1, ceil(($end_timestamp - $start_timestamp) / DAY_IN_SECONDS));
        $total_cost = $days * $data['cost_per_day'];
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'dredd_promotions';
            
            // Check if table exists and create if necessary
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            if (!$table_exists) {
                // Create table if it doesn't exist
                $database = new Dredd_Database();
                $database->create_tables();
                
                // Check again
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
                if (!$table_exists) {
                    throw new Exception('Could not create promotions table');
                }
            }
            
            $result = $wpdb->insert($table, array(
                'token_name' => $data['token_name'],
                'token_symbol' => $data['token_symbol'],
                'token_logo' => $data['token_logo'],
                'tagline' => $data['tagline'],
                'description' => $data['description'],
                'website_url' => $data['website_url'],
                'contract_address' => $data['contract_address'],
                'chain' => $data['chain'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'pending',
                'cost_per_day' => $data['cost_per_day'],
                'total_cost' => $total_cost,
                'payment_status' => 'pending',
                'created_by' => get_current_user_id(),
                'approved_by' => null
            ));
            
            if ($result === false) {
                // Log the actual database error
                $db_error = $wpdb->last_error;
                dredd_ai_log('Database insertion error: ' . $db_error, 'error');
                throw new Exception('Database insertion failed: ' . ($db_error ?: 'Unknown database error'));
            }
            
            $promotion_id = $wpdb->insert_id;
            
            // Create WordPress post if requested
            if (!empty($_POST['create_post'])) {
                $post_id = $this->create_promotion_post($promotion_id, $data);
                if ($post_id) {
                    $wpdb->update($table, 
                        array('wp_post_id' => $post_id),
                        array('id' => $promotion_id)
                    );
                }
            }
            
            dredd_ai_log("New promotion created: {$data['token_name']} (ID: {$promotion_id})");
            
            wp_send_json_success(array(
                'promotion_id' => $promotion_id,
                'message' => 'Promotion created successfully',
                'total_cost' => $total_cost,
                'days' => $days
            ));
            
        } catch (Exception $e) {
            dredd_ai_log('Promotion creation error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to create promotion: ' . $e->getMessage());
        }
    }
    
    /**
     * Update promotion
     */
    public function update_promotion() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $security = new Dredd_Security();
        if (!$security->verify_ajax_request()) {
            wp_send_json_error('Security check failed');
        }
        
        $promotion_id = intval($_POST['promotion_id'] ?? 0);
        $action = sanitize_text_field($_POST['promotion_action'] ?? '');
        
        if (!$promotion_id) {
            wp_send_json_error('Invalid promotion ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        try {
            switch ($action) {
                case 'approve':
                    // Get the current promotion to check dates
                    $current_promotion = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE id = %d",
                        $promotion_id
                    ));
                    
                    if (!$current_promotion) {
                        throw new Exception('Promotion not found');
                    }
                    
                    $update_data = array(
                        'status' => 'active',
                        'approved_by' => get_current_user_id()
                    );
                    
                    // Ensure dates are valid for immediate sidebar display
                    $now = current_time('mysql');
                    $start_time = strtotime($current_promotion->start_date);
                    $end_time = strtotime($current_promotion->end_date);
                    $current_time = time();
                    
                    // If start date is in the future, set it to now
                    if ($start_time > $current_time) {
                        $update_data['start_date'] = $now;
                    }
                    
                    // If end date is in the past or less than 24 hours from now, extend it
                    if ($end_time <= $current_time || ($end_time - $current_time) < DAY_IN_SECONDS) {
                        $update_data['end_date'] = date('Y-m-d H:i:s', strtotime('+7 days'));
                    }
                    
                    $result = $wpdb->update($table, $update_data, array('id' => $promotion_id));
                    $message = 'Promotion approved and activated for immediate display';
                    break;
                    
                case 'reject':
                    $result = $wpdb->update($table,
                        array('status' => 'cancelled'),
                        array('id' => $promotion_id)
                    );
                    $message = 'Promotion rejected';
                    break;
                    
                case 'pause':
                case 'cancel':
                    $result = $wpdb->update($table,
                        array('status' => 'cancelled'),
                        array('id' => $promotion_id)
                    );
                    $message = ($action === 'pause') ? 'Promotion paused' : 'Promotion cancelled';
                    break;
                    
                case 'extend':
                    $new_end_date = sanitize_text_field($_POST['new_end_date'] ?? '');
                    if (empty($new_end_date)) {
                        throw new Exception('New end date required');
                    }
                    
                    $result = $wpdb->update($table,
                        array('end_date' => $new_end_date),
                        array('id' => $promotion_id)
                    );
                    $message = 'Promotion extended';
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            
            if ($result !== false) {
                dredd_ai_log("Promotion updated: ID {$promotion_id}, action: {$action}");
                wp_send_json_success($message);
            } else {
                throw new Exception('Database update failed');
            }
            
        } catch (Exception $e) {
            dredd_ai_log('Promotion update error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to update promotion: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete promotion
     */
    public function delete_promotion() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $security = new Dredd_Security();
        if (!$security->verify_ajax_request()) {
            wp_send_json_error('Security check failed');
        }
        
        $promotion_id = intval($_POST['promotion_id'] ?? 0);
        
        if (!$promotion_id) {
            wp_send_json_error('Invalid promotion ID');
        }
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'dredd_promotions';
            
            // Get promotion data before deletion
            $promotion = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $promotion_id
            ));
            
            if (!$promotion) {
                throw new Exception('Promotion not found');
            }
            
            // Delete associated WordPress post if exists
            if ($promotion->wp_post_id) {
                wp_delete_post($promotion->wp_post_id, true);
            }
            
            // Delete promotion
            $result = $wpdb->delete($table, array('id' => $promotion_id));
            
            if ($result) {
                dredd_ai_log("Promotion deleted: {$promotion->token_name} (ID: {$promotion_id})");
                wp_send_json_success('Promotion deleted successfully');
            } else {
                throw new Exception('Database deletion failed');
            }
            
        } catch (Exception $e) {
            dredd_ai_log('Promotion deletion error: ' . $e->getMessage(), 'error');
            wp_send_json_error('Failed to delete promotion: ' . $e->getMessage());
        }
    }
    
    /**
     * Check promotion status and update expired promotions
     */
    public function check_promotion_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        // Mark expired promotions
        $expired_count = $wpdb->query(
            "UPDATE {$table} 
             SET status = 'expired' 
             WHERE status = 'active' 
             AND end_date < NOW()"
        );
        
        if ($expired_count > 0) {
            dredd_ai_log("Marked {$expired_count} promotions as expired");
        }
        
        // Mark future promotions as active if their start date has arrived
        $activated_count = $wpdb->query(
            "UPDATE {$table} 
             SET status = 'active' 
             WHERE status = 'pending' 
             AND start_date <= NOW() 
             AND end_date >= NOW()
             AND approved_by IS NOT NULL"
        );
        
        if ($activated_count > 0) {
            dredd_ai_log("Activated {$activated_count} scheduled promotions");
        }
    }
    
    /**
     * Create WordPress post for promotion
     */
    private function create_promotion_post($promotion_id, $data) {
        $post_title = "Featured Token: {$data['token_name']} ({$data['token_symbol']})";
        $post_content = $this->generate_promotion_post_content($data);
        
        $post_data = array(
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_category' => array(get_cat_ID('Token Promotions')),
            'tags_input' => array('promoted-token', 'featured', $data['chain'], $data['token_symbol']),
            'meta_input' => array(
                'dredd_promotion_id' => $promotion_id,
                'dredd_token_name' => $data['token_name'],
                'dredd_token_symbol' => $data['token_symbol'],
                'dredd_contract_address' => $data['contract_address'],
                'dredd_chain' => $data['chain'],
                'dredd_promotion_start' => $data['start_date'],
                'dredd_promotion_end' => $data['end_date']
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            return $post_id;
        }
        
        return null;
    }
    
    /**
     * Generate promotion post content
     */
    private function generate_promotion_post_content($data) {
        $content = '<div class="dredd-promotion-post">';
        
        // Header
        $content .= '<div class="promotion-header">';
        if (!empty($data['token_logo'])) {
            $content .= '<img src="' . esc_url($data['token_logo']) . '" alt="' . esc_attr($data['token_name']) . '" class="token-logo" style="width: 80px; height: 80px; float: left; margin-right: 20px; border-radius: 50%;" />';
        }
        $content .= '<h2>' . esc_html($data['token_name']) . ' (' . esc_html($data['token_symbol']) . ')</h2>';
        if (!empty($data['tagline'])) {
            $content .= '<p class="tagline"><em>' . esc_html($data['tagline']) . '</em></p>';
        }
        $content .= '</div>';
        
        // Clear float
        $content .= '<div style="clear: both;"></div>';
        
        // Token details
        $content .= '<div class="token-details">';
        $content .= '<h3>Token Information</h3>';
        $content .= '<ul>';
        $content .= '<li><strong>Name:</strong> ' . esc_html($data['token_name']) . '</li>';
        $content .= '<li><strong>Symbol:</strong> ' . esc_html($data['token_symbol']) . '</li>';
        $content .= '<li><strong>Blockchain:</strong> ' . esc_html(ucfirst($data['chain'])) . '</li>';
        if (!empty($data['contract_address'])) {
            $content .= '<li><strong>Contract:</strong> <code>' . esc_html($data['contract_address']) . '</code></li>';
        }
        if (!empty($data['website_url'])) {
            $content .= '<li><strong>Website:</strong> <a href="' . esc_url($data['website_url']) . '" target="_blank" rel="noopener">' . esc_html($data['website_url']) . '</a></li>';
        }
        $content .= '</ul>';
        $content .= '</div>';
        
        // Description
        if (!empty($data['description'])) {
            $content .= '<div class="token-description">';
            $content .= '<h3>About This Token</h3>';
            $content .= '<p>' . wp_kses_post(nl2br($data['description'])) . '</p>';
            $content .= '</div>';
        }
        
        // Analysis CTA
        if (!empty($data['contract_address'])) {
            $analysis_url = home_url('?dredd_message=' . urlencode("Analyze {$data['contract_address']} on {$data['chain']}"));
            $content .= '<div class="analysis-cta">';
            $content .= '<h3>Get DREDD\'s Analysis</h3>';
            $content .= '<p>Want Judge Dredd\'s brutal honest opinion about this token?</p>';
            $content .= '<p><a href="' . esc_url($analysis_url) . '" class="button button-primary">⚡ ANALYZE WITH DREDD</a></p>';
            $content .= '</div>';
        }
        
        // Disclaimer
        $content .= '<div class="promotion-disclaimer">';
        $content .= '<p><small><strong>Sponsored Content:</strong> This is a paid promotion. The content above is provided by the token project and does not represent the opinion of Judge Dredd or DREDD AI. Always do your own research before making any investment decisions.</small></p>';
        $content .= '</div>';
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Get promotion statistics
     */
    public function get_promotion_statistics($date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        $where_clause = "WHERE 1=1";
        $params = array();
        
        if ($date_from && $date_to) {
            $where_clause .= " AND created_at BETWEEN %s AND %s";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_promotions,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promotions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_promotions,
                COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_promotions,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_promotions,
                SUM(clicks) as total_clicks,
                SUM(impressions) as total_impressions,
                SUM(CASE WHEN payment_status = 'paid' THEN total_cost ELSE 0 END) as total_revenue,
                AVG(clicks / GREATEST(impressions, 1)) as average_ctr
            FROM {$table} {$where_clause}",
            $params
        ));
        
        return $stats;
    }
    
    /**
     * Get top performing promotions
     */
    public function get_top_promotions($metric = 'clicks', $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        $allowed_metrics = array('clicks', 'impressions', 'total_cost');
        if (!in_array($metric, $allowed_metrics)) {
            $metric = 'clicks';
        }
        
        $promotions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                token_name,
                token_symbol,
                chain,
                clicks,
                impressions,
                total_cost,
                (clicks / GREATEST(impressions, 1)) as ctr,
                start_date,
                end_date,
                status
            FROM {$table} 
            WHERE status IN ('active', 'expired')
            ORDER BY {$metric} DESC 
            LIMIT %d",
            $limit
        ));
        
        return $promotions;
    }
    
    /**
     * Calculate promotion ROI
     */
    public function calculate_promotion_roi($promotion_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        $promotion = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $promotion_id
        ));
        
        if (!$promotion) {
            return null;
        }
        
        $ctr = $promotion->impressions > 0 ? ($promotion->clicks / $promotion->impressions) * 100 : 0;
        $cost_per_click = $promotion->clicks > 0 ? $promotion->total_cost / $promotion->clicks : 0;
        $cost_per_impression = $promotion->impressions > 0 ? $promotion->total_cost / $promotion->impressions : 0;
        
        return array(
            'clicks' => $promotion->clicks,
            'impressions' => $promotion->impressions,
            'ctr' => round($ctr, 2),
            'total_cost' => $promotion->total_cost,
            'cost_per_click' => round($cost_per_click, 2),
            'cost_per_impression' => round($cost_per_impression, 4),
            'days_active' => $this->calculate_days_active($promotion),
            'daily_average_clicks' => $this->calculate_daily_average($promotion, 'clicks'),
            'daily_average_impressions' => $this->calculate_daily_average($promotion, 'impressions')
        );
    }
    
    /**
     * Calculate days active for promotion
     */
    private function calculate_days_active($promotion) {
        $start = strtotime($promotion->start_date);
        $end = min(strtotime($promotion->end_date), time());
        
        return max(1, ceil(($end - $start) / DAY_IN_SECONDS));
    }
    
    /**
     * Calculate daily average for metric
     */
    private function calculate_daily_average($promotion, $metric) {
        $days_active = $this->calculate_days_active($promotion);
        $value = $promotion->{$metric};
        
        return $days_active > 0 ? round($value / $days_active, 2) : 0;
    }
}
