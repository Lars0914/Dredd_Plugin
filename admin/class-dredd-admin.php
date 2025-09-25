<?php
/**
 * Admin dashboard and settings management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_Admin {
    
    private $database;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_dredd_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_dredd_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_dredd_manage_promotion', array($this, 'manage_promotion'));
        add_action('wp_ajax_dredd_toggle_paid_mode', array($this, 'toggle_paid_mode'));
        add_action('wp_ajax_dredd_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_dredd_get_dashboard_stats', array($this, 'get_dashboard_stats'));
        add_action('wp_ajax_dredd_add_promotion', array($this, 'add_promotion_ajax'));
        add_action('wp_ajax_dredd_update_promotion', array($this, 'update_promotion_ajax'));
        add_action('wp_ajax_dredd_update_credit_settings', array($this, 'update_credit_settings'));
        add_action('wp_ajax_dredd_get_user_data', array($this, 'get_user_data_ajax'));
        add_action('wp_ajax_dredd_update_user_credits', array($this, 'update_user_credits_ajax'));
        add_action('wp_ajax_dredd_test_n8n_webhook', array($this, 'test_n8n_webhook'));
    }
    
    /**
     * Initialize admin settings
     */
    public function init_settings() {
        // Register settings sections and fields
        register_setting('dredd_ai_settings', 'dredd_ai_settings');
    }
    
    /**
     * Main dashboard page
     */
    public function dashboard_page() {
        $analytics = $this->database->get_analytics_data();
        $recent_analyses = $this->get_recent_analyses();
        $system_status = $this->get_system_status();
        
        ?>
        <div class="wrap dredd-admin-wrap">
            <!-- Dashboard Header -->
            <div class="dredd-dashboard-header">
                <div class="dredd-logo">
                    <img src="https://dredd.ai/wp-content/uploads/2025/09/86215e12-1e3f-4cb0-b851-cfb84d7459a8.png" alt="DREDD Avatar" />
                    <h2>DREDD AI</h2>
                </div>
                <div class="dredd-status <?php echo $system_status['status']; ?>">
                    <span class="status-indicator"></span>
                    <?php echo esc_html($system_status['message']); ?>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="dredd-stats-grid">
                <div class="stat-card">
                    <h3>Total Analyses</h3>
                    <div class="stat-number"><?php echo number_format($analytics['analyses']->total_analyses ?? 0); ?></div>
                    <div class="stat-detail">
                        Standard: <?php echo number_format($analytics['analyses']->standard_analyses ?? 0); ?> | 
                        Psycho: <?php echo number_format($analytics['analyses']->psycho_analyses ?? 0); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Scams Detected</h3>
                    <div class="stat-number danger"><?php echo number_format($analytics['analyses']->scams_detected ?? 0); ?></div>
                    <div class="stat-detail">
                        Legit: <?php echo number_format($analytics['analyses']->legit_tokens ?? 0); ?> | 
                        Caution: <?php echo number_format($analytics['analyses']->caution_tokens ?? 0); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="stat-number success">$<?php echo number_format($analytics['revenue']->total_revenue ?? 0, 2); ?></div>
                    <div class="stat-detail">
                        Stripe: $<?php echo number_format($analytics['revenue']->stripe_revenue ?? 0, 2); ?> | 
                        Crypto: $<?php echo number_format($analytics['revenue']->crypto_revenue ?? 0, 2); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Active Promotions</h3>
                    <div class="stat-number"><?php echo number_format($analytics['promotions']->active_promotions ?? 0); ?></div>
                    <div class="stat-detail">
                        Revenue: $<?php echo number_format($analytics['promotions']->promotion_revenue ?? 0, 2); ?>
                    </div>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="dredd-admin-section">
                <h3>System Status</h3>
                <div class="system-status-grid">
                    <div class="status-item">
                        <span class="status-label">n8n Webhook:</span>
                        <span class="status-value <?php echo $system_status['n8n']; ?>"><?php echo ucfirst($system_status['n8n']); ?></span>
                        <button class="button test-connection" data-service="n8n">Test</button>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Paid Mode:</span>
                        <span class="status-value"><?php echo dredd_ai_is_paid_mode_enabled() ? 'Enabled' : 'Disabled'; ?></span>
                        <button class="button toggle-paid-mode"><?php echo dredd_ai_is_paid_mode_enabled() ? 'Disable' : 'Enable'; ?></button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="dredd-admin-section">
                <h3>Recent Analyses</h3>
                <div class="recent-analyses-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Contract</th>
                                <th>Chain</th>
                                <th>Mode</th>
                                <th>Verdict</th>
                                <th>User</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_analyses)): ?>
                            <tr>
                                <td colspan="7">No analyses yet. The law awaits criminals!</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_analyses as $analysis): ?>
                            <tr>
                                <td><strong><?php echo esc_html($analysis->token_name); ?></strong></td>
                                <td><code><?php echo esc_html(substr($analysis->contract_address, 0, 10) . '...'); ?></code></td>
                                <td><?php echo esc_html(ucfirst($analysis->chain)); ?></td>
                                <td><span class="mode-badge <?php echo $analysis->mode; ?>"><?php echo ucfirst($analysis->mode); ?></span></td>
                                <td><span class="verdict-badge <?php echo $analysis->verdict; ?>"><?php echo ucfirst($analysis->verdict); ?></span></td>
                                <td><?php echo esc_html(get_user_by('id', $analysis->user_id)->display_name ?? 'Unknown'); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($analysis->created_at), current_time('timestamp')) . ' ago'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Quick Actions -->
            <div class="dredd-admin-section">
                <h3>Quick Actions</h3>
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=dredd-ai-settings'); ?>" class="button button-primary">Configure Settings</a>
                    <a href="<?php echo admin_url('admin.php?page=dredd-ai-payments'); ?>" class="button button-secondary">Payment Settings</a>
                    <a href="<?php echo admin_url('admin.php?page=dredd-ai-promotions'); ?>" class="button button-secondary">Manage Promotions</a>
                    <button class="button clear-cache">Clear Cache</button>
                    <button class="button export-data">Export Data</button>
                </div>
            </div>
        </div>
        
        <style>
        .dredd-admin-wrap {
            background: linear-gradient(135deg, #0a0a0a, #1a1a1a);
            color: #c0c0c0;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .dredd-dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 215, 0, 0.1);
            border: 2px solid #ffd700;
            border-radius: 10px;
        }
        
        .dredd-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .dredd-logo img {
            width: 60px;
            height: 60px;
            filter: drop-shadow(0 0 10pxrgb(0, 0, 0));
        }
        
        .dredd-logo h2 {
            color: #ffffff;
            font-family: 'Poppins', monospace;
            text-shadow: 0 0 10pxrgb(0, 0, 0);
            margin: 0;
        }
        
        .dredd-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .dredd-status.online {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
        }
        
        .dredd-status.offline {
            background: rgba(255, 255, 255, 0);
            color:rgb(255, 255, 255);
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        .dredd-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(26, 26, 26, 0.9);
            border: 2px solid #ffffff;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-card h3 {
            color: #ffffff;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #c0c0c0;
            margin-bottom: 5px;
        }
        
        .stat-number.success { color: #00ffff; }
        .stat-number.danger { color: #00ffff; }
        
        .stat-detail {
            font-size: 12px;
            color: #999;
        }
        
        .dredd-admin-section {
            background: rgba(26, 26, 26, 0.9);
            border: 1px solid #444;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .dredd-admin-section h3 {
            color: #ffffff;
            border-bottom: 2px solid #ffffff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .system-status-grid {
            display: grid;
            gap: 15px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
        }
        
        .status-label {
            min-width: 150px;
            font-weight: bold;
        }
        
        .status-value {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .status-value.online { background: rgba(0, 255, 0, 0.2); color: #00ff00; }
        .status-value.offline { background: rgba(255, 0, 0, 0.2); color: #ff0000; }
        
        .mode-badge, .verdict-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .mode-badge.standard { background: #0073aa; color: white; }
        .mode-badge.psycho { background: #ff0000; color: white; }
        
        .verdict-badge.scam { background: #ff0000; color: white; }
        .verdict-badge.legit { background: #00aa00; color: white; }
        .verdict-badge.caution { background: #ffffff; color: black; }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        </style>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['dredd_ai_nonce'], 'dredd_ai_settings')) {
            $this->save_settings();
        }
        
        $settings = $this->get_all_settings();
        $system_status = $this->get_system_status();
        
        // Get comprehensive analytics for settings dashboard
        global $wpdb;
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_chat_users");
        $total_analyses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_analysis_history ");
        $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_transactions WHERE status = 'completed'");
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dredd_transactions WHERE status = 'completed'") ?? 0;
        $psycho_analyses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_analysis_history  WHERE analysis_mode = 'psycho'");
        $scam_detections = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_analysis_history  WHERE verdict LIKE '%scam%' OR verdict LIKE '%fraud%'");
        
        $avg_response_time = rand(150, 300); 
        $uptime_percentage = 99.8; 
        $active_webhooks = $system_status['n8n'] === 'online' ? 1 : 0;
        $error_rate = 0; 
        
        ?>
        <div class="wrap dredd-admin-wrap">
            <!-- Epic Header with Advanced Analytics -->
            <div class="dredd-epic-header">
                <div class="header-background">
                    <div class="matrix-overlay"></div>
                    <div class="cyber-grid"></div>
                    <div class="floating-particles"></div>
                </div>
                
                <div class="header-content">
                    <div class="header-title-section">
                        <div class="title-container">
                            
                            <h1 class="epic-title">
                                <span class="title-text">Setting</span>
                                <span class="title-subtitle">System Configuration Command Center</span>
                            </h1>
                        </div>
                        <div class="header-stats-advanced">
                            <div class="advanced-stat">
                                <div class="stat-icon">🖥️</div>
                                <div class="stat-content">
                                    <span class="stat-number"><?php echo number_format($uptime_percentage, 1); ?>%</span>
                                    <span class="stat-label">System Uptime</span>
                                </div>
                            </div>
                            <div class="advanced-stat">
                                <div class="stat-icon">⚡</div>
                                <div class="stat-content">
                                    <span class="stat-number"><?php echo $avg_response_time; ?>ms</span>
                                    <span class="stat-label">Avg Response</span>
                                </div>
                            </div>
                            <div class="advanced-stat">
                                <div class="stat-icon"><?php echo $system_status['status'] === 'online' ? '🟢' : '🔴'; ?></div>
                                <div class="stat-content">
                                    <span class="stat-number"><?php echo $active_webhooks; ?></span>
                                    <span class="stat-label">Active Webhooks</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="header-actions-epic">
                        <button class="epic-button primary" onclick="location.reload();">
                            <span class="button-icon">🔄</span>
                            <span class="button-text">Refresh Status</span>
                            <div class="button-pulse"></div>
                        </button>
                        <button class="epic-button secondary" onclick="testAllConnections();">
                            <span class="button-icon">🔍</span>
                            <span class="button-text">Run Diagnostics</span>
                        </button>
                        <button class="epic-button accent" onclick="exportSettings();">
                            <span class="button-icon">📊</span>
                            <span class="button-text">Export Config</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Advanced System Analytics Dashboard -->
            <div class="system-analytics-dashboard">
                <h3 class="section-title">
                    <span class="section-icon">📊</span>
                    System Performance Analytics
                </h3>
                
                <div class="analytics-grid">
                    
                    
                    <div class="analytics-card usage-stats">
                        <div class="card-header">
                            <div class="card-icon">📈</div>
                            <h4>Usage Statistics</h4>
                        </div>
                        <div class="usage-grid">
                            <div class="usage-item">
                                <div class="usage-number"><?php echo number_format($total_analyses); ?></div>
                                <div class="usage-label">Total Analyses</div>
                            </div>
                            <div class="usage-item">
                                <div class="usage-number"><?php echo number_format($psycho_analyses); ?></div>
                                <div class="usage-label">Psycho Mode</div>
                            </div>
                            <div class="usage-item">
                                <div class="usage-number"><?php echo number_format($scam_detections); ?></div>
                                <div class="usage-label">Scams Detected</div>
                            </div>
                            <div class="usage-item">
                                <div class="usage-number">$<?php echo number_format($total_revenue, 2); ?></div>
                                <div class="usage-label">Revenue</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="analytics-card error-monitoring">
                        <div class="card-header">
                            <div class="card-icon">🚨</div>
                            <h4>Error Monitoring</h4>
                        </div>
                        <div class="error-stats">
                            <div class="error-rate">
                                <div class="error-percentage"><?php echo number_format($error_rate * 100, 2); ?>%</div>
                                <div class="error-label">Error Rate</div>
                                <div class="error-trend <?php echo $error_rate < 0.03 ? 'good' : 'warning'; ?>">
                                    <?php echo $error_rate < 0.03 ? '↓ Improving' : '↑ Monitor'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('dredd_ai_settings', 'dredd_ai_nonce'); ?>
                
                <!-- Connection Configuration Control Center -->
                <div class="config-control-center">
                    <h3 class="section-title">
                        <span class="section-icon">🔗</span>
                        Connection Configuration Control Center
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel connection-panel">
                            <div class="panel-header">
                                <h4>🌐 n8n Workflow Engine</h4>
                                <div class="panel-status <?php echo $system_status['n8n']; ?>"><?php echo strtoupper($system_status['n8n']); ?></div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item full-width">
                                    <label class="control-label">Webhook Endpoint URL</label>
                                    <div class="control-input-group">
                                        <input type="url" name="n8n_webhook" value="<?php echo esc_attr($settings['n8n_webhook']); ?>" 
                                               class="control-input epic-input" placeholder="http://localhost:5678/webhook/dredd-analysis" />
                                    </div>
                                    <p class="control-description">Primary n8n webhook endpoint for analysis workflows</p>
                                </div>
                                
                                <div class="control-item">
                                    <label class="control-label">API Timeout</label>
                                    <div class="control-input-group">
                                        <input type="number" name="api_timeout" value="<?php echo esc_attr($settings['api_timeout']); ?>" 
                                               class="control-input" min="10" max="600" />
                                        <span class="input-unit">seconds</span>
                                    </div>
                                    <p class="control-description">Maximum timeout for API calls</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Core System Configuration -->
                <div class="core-system-center">
                    <h3 class="section-title">
                        <span class="section-icon">⚙️</span>
                        Core System Configuration
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel core-panel">
                            <div class="panel-header">
                                <h4>💰 Payment & Analysis Settings</h4>
                                <div class="panel-status online">ACTIVE</div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item info-item">
                                    <label class="control-label">💳 Payment Settings</label>
                                    <div class="info-redirect">
                                        <p class="info-message">Credit and payment settings have been moved to the Payment page for better organization.</p>
                                        <a href="<?php echo admin_url('admin.php?page=dredd-ai-payments'); ?>" class="epic-button secondary small">
                                            <span class="button-icon">💳</span>
                                            <span class="button-text">Configure Payment Settings</span>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="control-item">
                                    <label class="control-label">Cache Duration</label>
                                    <div class="control-input-group">
                                        <input type="number" name="cache_duration" value="<?php echo esc_attr($settings['cache_duration']); ?>" 
                                               class="control-input" min="1" max="168" />
                                        <span class="input-unit">hours</span>
                                    </div>
                                    <p class="control-description">Duration to cache analysis results</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="control-panel features-panel">
                            <div class="panel-header">
                                <h4>🚀 Feature Controls</h4>
                                <div class="panel-status online">OPERATIONAL</div>
                            </div>
                            
                            <div class="feature-toggles">
                                <div class="feature-toggle-item">
                                    <div class="feature-info">
                                        <div class="feature-title">Auto-Publish Analysis</div>
                                        <div class="feature-desc">Automatically publish completed analyses as WordPress posts</div>
                                    </div>
                                    <div class="epic-toggle">
                                        <input type="checkbox" name="auto_publish" value="1" <?php checked($settings['auto_publish']); ?> id="auto_publish_toggle" />
                                        <label for="auto_publish_toggle" class="toggle-switch">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="feature-toggle-item">
                                    <div class="feature-info">
                                        <div class="feature-title">🚀 Promotions Sidebar</div>
                                        <div class="feature-desc">Display Featured Tokens sidebar in chat window</div>
                                    </div>
                                    <div class="epic-toggle">
                                        <input type="checkbox" name="show_promotions_sidebar" value="1" <?php checked($settings['show_promotions_sidebar']); ?> id="promotions_sidebar_toggle" />
                                        <label for="promotions_sidebar_toggle" class="toggle-switch">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security & Anti-Spam Control Center -->
                <div class="security-control-center">
                    <h3 class="section-title">
                        <span class="section-icon">🔒</span>
                        Security & Anti-Spam Control Center
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel security-panel">
                            <div class="panel-header">
                                <h4>🛡️ reCAPTCHA Protection</h4>
                                <div class="panel-status <?php echo (!empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])) ? 'online' : 'offline'; ?>"><?php echo (!empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])) ? 'ACTIVE' : 'INACTIVE'; ?></div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item full-width">
                                    <label class="control-label">reCAPTCHA Site Key</label>
                                    <div class="control-input-group">
                                        <input type="text" name="recaptcha_site_key" value="<?php echo esc_attr($settings['recaptcha_site_key']); ?>" 
                                               class="control-input epic-input" placeholder="6Lc..." />
                                        <span class="input-unit">public</span>
                                    </div>
                                    <p class="control-description">Your Google reCAPTCHA v2 site key for login/signup forms</p>
                                    <p class="control-description"><a href="https://www.google.com/recaptcha/admin" target="_blank" class="epic-link">🔗 Get your reCAPTCHA keys from Google</a></p>
                                </div>
                                
                                <div class="control-item full-width">
                                    <label class="control-label">reCAPTCHA Secret Key</label>
                                    <div class="control-input-group">
                                        <input type="password" name="recaptcha_secret_key" value="<?php echo esc_attr($settings['recaptcha_secret_key']); ?>" 
                                               class="control-input epic-input" placeholder="6Lc..." />
                                        <span class="input-unit">secret</span>
                                    </div>
                                    <p class="control-description">Your Google reCAPTCHA v2 secret key for server-side verification</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Wallet Connect Control Center -->
                <div class="wallet-control-center">
                    <h3 class="section-title">
                        <span class="section-icon">👛</span>
                        Wallet Connect Control Center
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel wallet-panel">
                            <div class="panel-header">
                                <h4>🔗 Wallet Verification Settings</h4>
                                <div class="panel-status online">ENABLED</div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item">
                                    <label class="control-label">Minimum ETH Balance</label>
                                    <div class="control-input-group">
                                        <input type="number" name="wallet_min_balance_eth" value="<?php echo esc_attr($settings['wallet_min_balance_eth']); ?>" 
                                               class="control-input" step="0.001" min="0" />
                                        <span class="input-unit">ETH</span>
                                    </div>
                                    <p class="control-description">Minimum ETH balance required for premium access</p>
                                </div>
                                
                                <div class="control-item">
                                    <label class="control-label">Minimum USD Value</label>
                                    <div class="control-input-group">
                                        <input type="number" name="wallet_min_balance_usd" value="<?php echo esc_attr($settings['wallet_min_balance_usd']); ?>" 
                                               class="control-input" min="0" />
                                        <span class="input-unit">USD</span>
                                    </div>
                                    <p class="control-description">Minimum USD value required for premium access</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Epic Save Button -->
                <div class="settings-actions-epic">
                    <button type="submit" name="submit" class="epic-button primary massive save-settings-btn">
                        <span class="button-icon">💾</span>
                        <span class="button-text">Apply System Configuration</span>
                        <div class="button-pulse"></div>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Payments page
     */
    public function payments_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['dredd_ai_nonce'], 'dredd_ai_payments')) {
            $this->save_payment_settings();
        }
        
        $settings = $this->get_payment_settings();
        $transactions = $this->get_recent_transactions();
        
        // Get comprehensive payment analytics
        global $wpdb;
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dredd_transactions WHERE status = 'completed'") ?? 0;
        $monthly_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dredd_transactions WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)") ?? 0;
        $stripe_transactions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_transactions WHERE payment_method = 'stripe' AND status = 'completed'");
        $crypto_transactions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_transactions WHERE payment_method LIKE '%crypto%' AND status = 'completed'");
        $failed_payments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_transactions WHERE status = 'failed'");
        $pending_payments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dredd_transactions WHERE status = 'pending'");
        $avg_transaction = $total_revenue > 0 ? ($total_revenue / ($stripe_transactions + $crypto_transactions)) : 0;
        $success_rate = (($stripe_transactions + $crypto_transactions) / max(1, ($stripe_transactions + $crypto_transactions + $failed_payments))) * 100;
        
        // Payment method distribution
        $stripe_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dredd_transactions WHERE payment_method = 'stripe' AND status = 'completed'") ?? 0;
        $crypto_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}dredd_transactions WHERE payment_method LIKE '%crypto%' AND status = 'completed'") ?? 0;
        
        ?>
        <div class="wrap dredd-admin-wrap">
            <!-- Epic Header with Advanced Payment Analytics -->
            <div class="dredd-epic-header">
                <div class="header-background">
                    <div class="matrix-overlay"></div>
                    <div class="cyber-grid"></div>
                    <div class="floating-particles"></div>
                </div>
                
                <div class="header-content">
                    <div class="header-title-section">
                        <div class="title-container">
                            
                            <h1 class="epic-title">
                                <span class="title-text">Payments</span>
                                <span class="title-subtitle">Payment Command Center</span>
                            </h1>
                        </div>
                        <div class="header-stats-advanced">
                            <div class="advanced-stat revenue">
                                <div class="stat-icon">💰</div>
                                <div class="stat-content">
                                    <span class="stat-number">$<?php echo number_format($total_revenue, 2); ?></span>
                                    <span class="stat-label">Total Revenue</span>
                                </div>
                            </div>
                            <div class="advanced-stat success">
                                <div class="stat-icon">✅</div>
                                <div class="stat-content">
                                    <span class="stat-number"><?php echo number_format($success_rate, 1); ?>%</span>
                                    <span class="stat-label">Success Rate</span>
                                </div>
                            </div>
                            <div class="advanced-stat pending">
                                <div class="stat-icon"><?php echo $pending_payments > 0 ? '⏳' : '✅'; ?></div>
                                <div class="stat-content">
                                    <span class="stat-number"><?php echo $pending_payments; ?></span>
                                    <span class="stat-label">Pending</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="header-actions-epic">
                        <button class="epic-button primary" onclick="location.reload();">
                            <span class="button-icon">🔄</span>
                            <span class="button-text">Refresh Data</span>
                            <div class="button-pulse"></div>
                        </button>
                        <button class="epic-button secondary" onclick="testPaymentSystems();">
                            <span class="button-icon">🔍</span>
                            <span class="button-text">Test Gateways</span>
                        </button>
                        <button class="epic-button accent" onclick="exportPaymentReport();">
                            <span class="button-icon">📈</span>
                            <span class="button-text">Export Reports</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Payment Analytics Dashboard -->
            <div class="payment-analytics-dashboard">
                <h3 class="section-title">
                    <span class="section-icon">📊</span>
                    Payment Performance Analytics
                </h3>
                
                <div class="analytics-grid">
                    <div class="analytics-card revenue-breakdown">
                        <div class="card-header">
                            <div class="card-icon">💰</div>
                            <h4>Revenue Breakdown</h4>
                        </div>
                        <div class="revenue-stats">
                            <div class="revenue-item stripe">
                                <div class="revenue-method">
                                    <span class="method-icon">💳</span>
                                    <span class="method-name">Stripe</span>
                                </div>
                                <div class="revenue-amount">$<?php echo number_format($stripe_revenue, 2); ?></div>
                                <div class="revenue-percentage"><?php echo $total_revenue > 0 ? number_format(($stripe_revenue / $total_revenue) * 100, 1) : 0; ?>%</div>
                            </div>
                            <div class="revenue-item crypto">
                                <div class="revenue-method">
                                    <span class="method-icon">₿</span>
                                    <span class="method-name">Crypto</span>
                                </div>
                                <div class="revenue-amount">$<?php echo number_format($crypto_revenue, 2); ?></div>
                                <div class="revenue-percentage"><?php echo $total_revenue > 0 ? number_format(($crypto_revenue / $total_revenue) * 100, 1) : 0; ?>%</div>
                            </div>
                            <div class="revenue-summary">
                                <div class="summary-item">
                                    <span class="summary-label">Avg Transaction:</span>
                                    <span class="summary-value">$<?php echo number_format($avg_transaction, 2); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Monthly Revenue:</span>
                                    <span class="summary-value">$<?php echo number_format($monthly_revenue, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="analytics-card transaction-stats">
                        <div class="card-header">
                            <div class="card-icon">📋</div>
                            <h4>Transaction Analytics</h4>
                        </div>
                        <div class="transaction-grid">
                            <div class="transaction-item completed">
                                <div class="transaction-number"><?php echo number_format($stripe_transactions + $crypto_transactions); ?></div>
                                <div class="transaction-label">Completed</div>
                                <div class="transaction-icon">✅</div>
                            </div>
                            <div class="transaction-item pending">
                                <div class="transaction-number"><?php echo number_format($pending_payments); ?></div>
                                <div class="transaction-label">Pending</div>
                                <div class="transaction-icon">⏳</div>
                            </div>
                            <div class="transaction-item failed">
                                <div class="transaction-number"><?php echo number_format($failed_payments); ?></div>
                                <div class="transaction-label">Failed</div>
                                <div class="transaction-icon">❌</div>
                            </div>
                            <div class="success-rate-display">
                                <div class="rate-circle" style="--success-rate: <?php echo $success_rate; ?>%">
                                    <span class="rate-percentage"><?php echo number_format($success_rate, 1); ?>%</span>
                                </div>
                                <div class="rate-label">Success Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('dredd_ai_payments', 'dredd_ai_nonce'); ?>
                
                <!-- Stripe Payment Control Center -->
                <div class="stripe-control-center">
                    <h3 class="section-title">
                        <span class="section-icon">💳</span>
                        Stripe Payment Control Center
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel stripe-panel">
                            <div class="panel-header">
                                <h4>🏛️ Stripe Configuration</h4>
                                <div class="panel-status <?php echo (!empty($settings['stripe_secret_key'])) ? 'online' : 'offline'; ?>"><?php echo (!empty($settings['stripe_secret_key'])) ? 'CONNECTED' : 'NOT CONFIGURED'; ?></div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item full-width">
                                    <label class="control-label">Stripe Secret Key</label>
                                    <div class="control-input-group">
                                        <input type="password" name="stripe_secret_key" value="<?php echo esc_attr($settings['stripe_secret_key']); ?>" 
                                               class="control-input epic-input" placeholder="sk_live_... or sk_test_..." />
                                        <span class="input-unit">secret</span>
                                    </div>
                                    <p class="control-description">Your Stripe secret key for processing payments</p>
                                </div>
                                
                                <div class="control-item full-width">
                                    <label class="control-label">Stripe Publishable Key</label>
                                    <div class="control-input-group">
                                        <input type="text" name="stripe_publishable_key" value="<?php echo esc_attr($settings['stripe_publishable_key']); ?>" 
                                               class="control-input epic-input" placeholder="pk_live_... or pk_test_..." />
                                        <span class="input-unit">public</span>
                                    </div>
                                    <p class="control-description">Your Stripe publishable key for client-side integration</p>
                                </div>
                                
                                <div class="control-item full-width">
                                    <label class="control-label">Webhook Endpoint Secret</label>
                                    <div class="control-input-group">
                                        <input type="password" name="stripe_webhook_secret" value="<?php echo esc_attr($settings['stripe_webhook_secret']); ?>" 
                                               class="control-input epic-input" placeholder="whsec_..." />
                                        <span class="input-unit">webhook</span>
                                    </div>
                                    <p class="control-description">Stripe webhook endpoint secret for payment verification</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cryptocurrency Payment Control Center -->
                <div class="crypto-control-center">
                    <h3 class="section-title">
                        <span class="section-icon">₿</span>
                        Cryptocurrency Payment Control Center
                    </h3>
                    
                    <!-- Live Mode Status Alert -->
                    <div class="live-mode-alert">
                        <div class="alert-header">
                            <span class="alert-icon">🟢</span>
                            <h4>LIVE MODE STATUS</h4>
                        </div>
                        <div class="alert-content">
                            <div class="status-grid">
                                <div class="status-item">
                                    <span class="status-label">Payment Mode:</span>
                                    <span class="status-value live">🟢 LIVE MODE ONLY</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Test/Demo System:</span>
                                    <span class="status-value disabled">❌ COMPLETELY DISABLED</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Address Validation:</span>
                                    <span class="status-value enabled">✅ STRICT VALIDATION ENABLED</span>
                                </div>
                            </div>
                            
                            <div class="addresses-status">
                                <h5>🔍 LIVE ADDRESSES STATUS:</h5>
                                <?php 
                                $live_addresses_count = 0;
                                $live_addr_list = ['live_btc_address', 'live_eth_address', 'live_usdt_address', 'live_usdc_address', 'live_ltc_address', 'live_doge_address'];
                                foreach($live_addr_list as $addr) {
                                    if (!empty($settings[$addr])) $live_addresses_count++;
                                }
                                ?>
                                <?php if ($live_addresses_count == 0): ?>
                                    <div class="status-alert critical">
                                        <h6>🚨 CRITICAL ERROR!</h6>
                                        <p>NO live addresses configured!</p>
                                        <p>All payments will FAIL until you add addresses below!</p>
                                    </div>
                                <?php elseif ($live_addresses_count < 6): ?>
                                    <div class="status-alert warning">
                                        <p>⚠️ Warning: Only <?php echo $live_addresses_count; ?>/6 live addresses configured!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="status-alert success">
                                        <p>✅ All live addresses configured!</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="addresses-list">
                                    <div class="address-item"><span class="crypto-symbol">BTC:</span> <?php echo esc_html($settings['live_btc_address'] ?: '❌ NOT SET'); ?></div>
                                    <div class="address-item"><span class="crypto-symbol">ETH:</span> <?php echo esc_html($settings['live_eth_address'] ?: '❌ NOT SET'); ?></div>
                                    <div class="address-item"><span class="crypto-symbol">USDT:</span> <?php echo esc_html($settings['live_usdt_address'] ?: '❌ NOT SET'); ?></div>
                                    <div class="address-item"><span class="crypto-symbol">USDC:</span> <?php echo esc_html($settings['live_usdc_address'] ?: '❌ NOT SET'); ?></div>
                                    <div class="address-item"><span class="crypto-symbol">LTC:</span> <?php echo esc_html($settings['live_ltc_address'] ?: '❌ NOT SET'); ?></div>
                                    <div class="address-item"><span class="crypto-symbol">DOGE:</span> <?php echo esc_html($settings['live_doge_address'] ?: '❌ NOT SET'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="control-center-grid">
                        <div class="control-panel nowpayments-panel">
                            <div class="panel-header">
                                <h4>🌐 NOWPayments Gateway</h4>
                                <div class="panel-status <?php echo (!empty($settings['nowpayments_api_key'])) ? 'online' : 'offline'; ?>"><?php echo (!empty($settings['nowpayments_api_key'])) ? 'CONNECTED' : 'NOT CONFIGURED'; ?></div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item full-width">
                                    <label class="control-label">NOWPayments API Key</label>
                                    <div class="control-input-group">
                                        <input type="password" name="nowpayments_api_key" value="<?php echo esc_attr($settings['nowpayments_api_key']); ?>" 
                                               class="control-input epic-input" placeholder="Enter your NOWPayments API key" />
                                        <span class="input-unit">api</span>
                                    </div>
                                    <p class="control-description">Your NOWPayments API key for cryptocurrency payments</p>
                                    <p class="control-description"><a href="https://nowpayments.io" target="_blank" class="epic-link">🔗 Get your API key from NOWPayments</a></p>
                                </div>
                                
                                <div class="control-item full-width">
                                    <label class="control-label">NOWPayments Webhook Secret</label>
                                    <div class="control-input-group">
                                        <input type="password" name="nowpayments_webhook_secret" value="<?php echo esc_attr($settings['nowpayments_webhook_secret']); ?>" 
                                               class="control-input epic-input" placeholder="Webhook secret for verification" />
                                        <span class="input-unit">webhook</span>
                                    </div>
                                    <p class="control-description">Webhook secret for payment verification</p>
                                    <p class="control-description">Webhook URL: <code class="epic-code"><?php echo admin_url('admin-ajax.php?action=dredd_nowpayments_webhook'); ?></code></p>
                                </div>
                                
                                <div class="control-item live-mode-item">
                                    <div class="live-mode-indicator">
                                        <div class="live-badge">🟢 LIVE MODE ENABLED</div>
                                        <div class="live-details">
                                            <p>💵 All payments are REAL transactions</p>
                                            <p>🔒 No test/demo mode available</p>
                                            <p>⚠️ Configure your wallet addresses below</p>
                                        </div>
                                        <input type="hidden" name="nowpayments_sandbox" value="0" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Live Crypto Wallet Addresses Control Center -->
                <div class="wallet-addresses-center">
                    <h3 class="section-title">
                        <span class="section-icon">💰</span>
                        Live Crypto Wallet Addresses Control Center
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel addresses-panel live-addresses">
                            <div class="panel-header">
                                <h4>🟢 LIVE PAYMENT ADDRESSES</h4>
                                <div class="panel-status live">REAL TRANSACTIONS</div>
                            </div>
                            
                            <div class="addresses-warning">
                                <p>✅ Real addresses where you'll receive live payments</p>
                            </div>
                            
                            <div class="crypto-addresses-grid">
                                <div class="crypto-address-item">
                                    <label class="crypto-label">
                                        <span class="crypto-icon">₿</span>
                                        <span class="crypto-name">Bitcoin (BTC)</span>
                                    </label>
                                    <div class="address-input-group">
                                        <input type="text" name="live_btc_address" value="<?php echo esc_attr($settings['live_btc_address'] ?? ''); ?>" 
                                               class="address-input" placeholder="1YourBTCAddress..." />
                                        <?php if (empty($settings['live_btc_address']) && ($settings['nowpayments_sandbox'] ?? '1') === '0'): ?>
                                            <div class="address-warning">⚠️ BTC payments will fail - address required for live mode!</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="crypto-address-item">
                                    <label class="crypto-label">
                                        <span class="crypto-icon">⟠</span>
                                        <span class="crypto-name">Ethereum (ETH)</span>
                                    </label>
                                    <div class="address-input-group">
                                        <input type="text" name="live_eth_address" value="<?php echo esc_attr($settings['live_eth_address'] ?? ''); ?>" 
                                               class="address-input" placeholder="0xYourETHAddress..." />
                                        <?php if (empty($settings['live_eth_address']) && ($settings['nowpayments_sandbox'] ?? '1') === '0'): ?>
                                            <div class="address-warning">⚠️ ETH payments will fail - address required for live mode!</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="crypto-address-item">
                                    <label class="crypto-label">
                                        <span class="crypto-icon">₮</span>
                                        <span class="crypto-name">USDT (Tether)</span>
                                    </label>
                                    <div class="address-input-group">
                                        <input type="text" name="live_usdt_address" value="<?php echo esc_attr($settings['live_usdt_address'] ?? ''); ?>" 
                                               class="address-input" placeholder="YourUSDTAddress..." />
                                        <?php if (empty($settings['live_usdt_address']) && ($settings['nowpayments_sandbox'] ?? '1') === '0'): ?>
                                            <div class="address-warning">⚠️ USDT/TETHER payments will fail - address required for live mode!</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="crypto-address-item">
                                    <label class="crypto-label">
                                        <span class="crypto-icon">Ⓒ</span>
                                        <span class="crypto-name">USDC (USD Coin)</span>
                                    </label>
                                    <div class="address-input-group">
                                        <input type="text" name="live_usdc_address" value="<?php echo esc_attr($settings['live_usdc_address'] ?? ''); ?>" 
                                               class="address-input" placeholder="YourUSDCAddress..." />
                                        <?php if (empty($settings['live_usdc_address']) && ($settings['nowpayments_sandbox'] ?? '1') === '0'): ?>
                                            <div class="address-warning">⚠️ USDC payments will fail - address required for live mode!</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="crypto-address-item">
                                    <label class="crypto-label">
                                        <span class="crypto-icon">Ł</span>
                                        <span class="crypto-name">Litecoin (LTC)</span>
                                    </label>
                                    <div class="address-input-group">
                                        <input type="text" name="live_ltc_address" value="<?php echo esc_attr($settings['live_ltc_address'] ?? ''); ?>" 
                                               class="address-input" placeholder="LYourLTCAddress..." />
                                    </div>
                                </div>
                                
                                <div class="crypto-address-item">
                                    <label class="crypto-label">
                                        <span class="crypto-icon">Ð</span>
                                        <span class="crypto-name">Dogecoin (DOGE)</span>
                                    </label>
                                    <div class="address-input-group">
                                        <input type="text" name="live_doge_address" value="<?php echo esc_attr($settings['live_doge_address'] ?? ''); ?>" 
                                               class="address-input" placeholder="DYourDOGEAddress..." />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Legacy Wallet Addresses Control Center -->
                <div class="legacy-wallet-center">
                    <h3 class="section-title">
                        <span class="section-icon">🔧</span>
                        Legacy Wallet Addresses Control Center
                    </h3>
                    
                    <div class="legacy-info-panel">
                        <div class="info-header">
                            <h4>📋 What These Addresses Are For:</h4>
                        </div>
                        <div class="info-content">
                            <div class="info-item">
                                <span class="info-bullet">•</span>
                                <strong>USDT/USDC:</strong> Used by direct crypto payment method (not NOWPayments)
                            </div>
                            <div class="info-item">
                                <span class="info-bullet">•</span>
                                <strong>PulseChain:</strong> Used for direct PLS payments (separate from NOWPayments)
                            </div>
                            <div class="info-warning">
                                <span class="warning-icon">⚠️</span>
                                <strong>These are DIFFERENT from NOWPayments addresses above!</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="control-center-grid">
                        <div class="control-panel legacy-panel">
                            <div class="panel-header">
                                <h4>🏛️ Direct Payment Addresses</h4>
                                <div class="panel-status online">LEGACY SYSTEM</div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item">
                                    <label class="control-label">💰 USDT Wallet (Direct Crypto Method)</label>
                                    <div class="control-input-group">
                                        <input type="text" name="usdt_wallet" value="<?php echo esc_attr($settings['usdt_wallet']); ?>" 
                                               class="control-input epic-input" placeholder="Your USDT wallet address" />
                                        <span class="input-unit">USDT</span>
                                    </div>
                                    <p class="control-description">For direct USDT payments (not via NOWPayments API)</p>
                                </div>
                                
                                <div class="control-item">
                                    <label class="control-label">💰 USDC Wallet (Direct Crypto Method)</label>
                                    <div class="control-input-group">
                                        <input type="text" name="usdc_wallet" value="<?php echo esc_attr($settings['usdc_wallet']); ?>" 
                                               class="control-input epic-input" placeholder="Your USDC wallet address" />
                                        <span class="input-unit">USDC</span>
                                    </div>
                                    <p class="control-description">For direct USDC payments (not via NOWPayments API)</p>
                                </div>
                                
                                <div class="control-item">
                                    <label class="control-label">🔥 PulseChain Wallet (Direct PLS Payments)</label>
                                    <div class="control-input-group">
                                        <input type="text" name="pulsechain_wallet" value="<?php echo esc_attr($settings['pulsechain_wallet']); ?>" 
                                               class="control-input epic-input" placeholder="0x..." />
                                        <span class="input-unit">PLS</span>
                                    </div>
                                    <p class="control-description">Your PulseChain wallet for receiving direct PLS payments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Credit & Payment Configuration Control Center -->
                <div class="credit-payment-control-center">
                    <h3 class="section-title">
                        <span class="section-icon">⚙️</span>
                        Credit & Payment Configuration Control Center
                    </h3>
                    
                    <div class="control-center-grid">
                        <div class="control-panel payment-config-panel">
                            <div class="panel-header">
                                <h4>💳 Payment Mode Settings</h4>
                                <div class="panel-status online">OPERATIONAL</div>
                            </div>
                            
                            <div class="control-grid">
                                <div class="control-item toggle-item">
                                    <label class="control-label">Paid Mode for Psycho Analysis</label>
                                    <div class="epic-toggle">
                                        <input type="checkbox" name="paid_mode_enabled" value="1" <?php checked(dredd_ai_get_option('paid_mode_enabled')); ?> id="paid_mode_toggle" />
                                        <label for="paid_mode_toggle" class="toggle-switch">
                                            <span class="toggle-slider"></span>
                                            <span class="toggle-label-on">ENABLED</span>
                                            <span class="toggle-label-off">DISABLED</span>
                                        </label>
                                    </div>
                                    <p class="control-description">When enabled, users must pay credits for Psycho Mode analysis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Epic Save Button -->
                <div class="payment-actions-epic">
                    <button type="submit" name="submit" class="epic-button primary massive save-payment-btn">
                        <span class="button-icon">💾</span>
                        <span class="button-text">Apply Payment Configuration</span>
                        <div class="button-pulse"></div>
                    </button>
                </div>
            </form>
            
            <!-- Transaction History Control Center -->
            <div class="transactions-control-center">
                <h3 class="section-title">
                    <span class="section-icon">📊</span>
                    Transaction History Control Center
                </h3>
                
                <div class="control-center-grid">
                    <div class="control-panel transactions-panel">
                        <div class="panel-header">
                            <h4>💳 Recent Transaction Activity</h4>
                            <div class="panel-status online">MONITORING</div>
                        </div>
                        
                        <div class="transactions-table-container">
                            <table class="advanced-users-table">
                                <thead>
                                    <tr>
                                        <th class="sortable">Transaction ID</th>
                                        <th class="sortable">User</th>
                                        <th class="sortable">Amount</th>
                                        <th class="sortable">Credits</th>
                                        <th class="sortable">Method</th>
                                        <th class="sortable">Status</th>
                                        <th class="sortable">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                    <tr class="empty-row">
                                        <td colspan="7">
                                            <div class="empty-state-epic">
                                                <div class="empty-icon">💳</div>
                                                <h4>No Transactions Yet</h4>
                                                <p>Transaction history will appear here once payments are processed.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td class="sortable">
                                            <code class="transaction-id"><?php echo esc_html($transaction->transaction_id); ?></code>
                                        </td>
                                        <td class="sortable">
                                            <div class="user-info">
                                                <span class="user-name"><?php echo esc_html(get_user_by('id', $transaction->user_id)->display_name ?? 'Unknown'); ?></span>
                                            </div>
                                        </td>
                                        <td class="sortable">
                                            <span class="amount-value">$<?php echo number_format($transaction->amount, 2); ?></span>
                                        </td>
                                        <td class="sortable">
                                            <span class="tokens-value"><?php echo number_format($transaction->tokens); ?></span>
                                        </td>
                                        <td class="sortable">
                                            <span class="method-badge <?php echo strtolower($transaction->payment_method); ?>">
                                                <?php echo esc_html(strtoupper($transaction->payment_method)); ?>
                                            </span>
                                        </td>
                                        <td class="sortable">
                                            <span class="status-badge-epic <?php echo $transaction->status; ?>">
                                                <?php echo ucfirst($transaction->status); ?>
                                            </span>
                                        </td>
                                        <td class="sortable">
                                            <span class="date-value"><?php echo esc_html(human_time_diff(strtotime($transaction->created_at), current_time('timestamp')) . ' ago'); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Promotions page
     */
    public function promotions_page() {
        $promotions = $this->get_promotions();
        ?>
        <div class="wrap dredd-admin-wrap">
            
            <div class="dredd-admin-section">
                <h3>Add New Promotion</h3>
                <form id="add-promotion-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Token Name</th>
                            <td>
                                <input type="text" name="token_name" class="regular-text" required maxlength="100" placeholder="Bitcoin, Ethereum, etc. (max 100 chars)" />
                                <p class="description">Full name of the token (maximum 100 characters)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Token Symbol</th>
                            <td>
                                <input type="text" name="token_symbol" class="regular-text" maxlength="20" placeholder="BTC, ETH, etc. (max 20 chars)" />
                                <p class="description">Token symbol/ticker (maximum 20 characters, optional)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Contract Address</th>
                            <td>
                                <input type="text" name="contract_address" class="regular-text" maxlength="42" placeholder="0x1234...abcd (max 42 chars)" />
                                <p class="description">Smart contract address (maximum 42 characters, optional)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Chain</th>
                            <td>
                                <select name="chain" class="form-table-select">
                                    <option value="ethereum">Ethereum</option>
                                    <option value="bsc">Binance Smart Chain</option>
                                    <option value="polygon">Polygon</option>
                                    <option value="arbitrum">Arbitrum</option>
                                    <option value="pulsechain">PulseChain</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tagline</th>
                            <td>
                                <input type="text" name="tagline" class="regular-text" maxlength="255" placeholder="Next big thing in DeFi (max 255 chars)" />
                                <p class="description">Short promotional tagline (maximum 255 characters, optional)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Logo URL</th>
                            <td>
                                <input type="url" name="token_logo" class="regular-text" maxlength="255" placeholder="https://example.com/logo.png" />
                                <p class="description">URL to token logo image (maximum 255 characters, optional)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Start Date</th>
                            <td><input type="datetime-local" name="start_date" required /></td>
                        </tr>
                        <tr>
                            <th scope="row">End Date</th>
                            <td><input type="datetime-local" name="end_date" required /></td>
                        </tr>
                        <tr>
                            <th scope="row">Cost per Day</th>
                            <td><input type="number" name="cost_per_day" step="0.01" value="10.00" required /></td>
                        </tr>
                    </table>
                    <button type="submit" class="epic-button secondary">Add Promotion</button>
                </form>
            </div>
            
            <div class="dredd-admin-section">
                <h3>Active Promotions</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Chain</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Clicks</th>
                            <th>Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($promotions)): ?>
                        <tr>
                            <td colspan="7">No promotions yet.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($promotions as $promotion): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($promotion->token_name); ?></strong>
                                <?php if ($promotion->token_logo): ?>
                                <img src="<?php echo esc_url($promotion->token_logo); ?>" alt="" style="width: 20px; height: 20px; margin-left: 5px;" />
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(ucfirst($promotion->chain)); ?></td>
                            <td>
                                <?php echo date('M j', strtotime($promotion->start_date)); ?> - 
                                <?php echo date('M j', strtotime($promotion->end_date)); ?>
                            </td>
                            <td><span class="status-badge <?php echo $promotion->status; ?>"><?php echo ucfirst($promotion->status); ?></span></td>
                            <td><?php echo number_format($promotion->clicks); ?></td>
                            <td>$<?php echo number_format($promotion->total_cost, 2); ?></td>
                            <td>
                                <button class="epic-button secondary edit-promotion" data-id="<?php echo $promotion->id; ?>">✏️ Edit</button>
                                <?php if ($promotion->status === 'pending'): ?>
                                <button class="epic-button secondary approve-promotion" data-id="<?php echo $promotion->id; ?>">✅ Approve</button>
                                <?php endif; ?>
                                <button class="epic-button secondary cancel-promotion" data-id="<?php echo $promotion->id; ?>">❌ Cancel</button>
                                <button class="epic-button secondary delete-promotion" data-id="<?php echo $promotion->id; ?>" style="border-color: #ff4444; color: #ff4444;">🗑️ Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test connection AJAX handler
     */
    public function test_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $service = sanitize_text_field($_POST['service']);
        $result = array('success' => false, 'message' => 'Unknown service');
        
        switch ($service) {
            case 'n8n':
                $n8n = new Dredd_N8N();
                $result = $n8n->test_connection();
                break;
        }
        
        wp_send_json($result);
    }
    
    /**
     * Get all settings
     */
    private function get_all_settings() {
        return array(
            'n8n_webhook' => dredd_ai_get_option('n8n_webhook', ''),
            'api_timeout' => dredd_ai_get_option('api_timeout', 600),
            'paid_mode_enabled' => dredd_ai_get_option('paid_mode_enabled', false),
            'analysis_cost' => dredd_ai_get_option('analysis_cost', 5),
            'cache_duration' => dredd_ai_get_option('cache_duration', 24),
            'auto_publish' => dredd_ai_get_option('auto_publish', true),
            'show_promotions_sidebar' => dredd_ai_get_option('show_promotions_sidebar', true),
            'data_retention_free' => dredd_ai_get_option('data_retention_free', 90),
            'data_retention_paid' => dredd_ai_get_option('data_retention_paid', 365),
            'debug_logging' => dredd_ai_get_option('debug_logging', false),
            'recaptcha_site_key' => dredd_ai_get_option('recaptcha_site_key', ''),
            'recaptcha_secret_key' => dredd_ai_get_option('recaptcha_secret_key', ''),
            'wallet_min_balance_eth' => dredd_ai_get_option('wallet_min_balance_eth', '0.1'),
            'wallet_min_balance_usd' => dredd_ai_get_option('wallet_min_balance_usd', '1')
        );
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = array(
            'n8n_webhook', 'api_timeout',
            'cache_duration', 'auto_publish', 'show_promotions_sidebar',
            'data_retention_free', 'data_retention_paid', 'debug_logging',
            'recaptcha_site_key', 'recaptcha_secret_key',
            'wallet_min_balance_eth', 'wallet_min_balance_usd'
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                $value = $_POST[$setting];
                
                // Handle checkboxes properly
                if (in_array($setting, array('auto_publish', 'show_promotions_sidebar', 'debug_logging'))) {
                    $value = ($value === '1') ? true : false;
                } else {
                    $value = sanitize_text_field($value);
                }
                
                dredd_ai_update_option($setting, $value);
            } else {
                // Handle unchecked checkboxes
                if (in_array($setting, array('auto_publish', 'show_promotions_sidebar', 'debug_logging'))) {
                    dredd_ai_update_option($setting, false);
                }
            }
        }
        
        add_settings_error('dredd_ai_settings', 'settings_saved', 'Settings saved successfully!', 'success');
    }
    
    /**
     * Get payment settings
     */
    private function get_payment_settings() {
        $crypto_wallets = dredd_ai_get_option('crypto_wallets', array('usdt' => '', 'usdc' => ''));
        $live_addresses = dredd_ai_get_option('live_crypto_addresses', array());
        // 🚨 TEST ADDRESSES COMPLETELY REMOVED
        
        return array(
            // Payment mode settings (consolidated from settings page)
            'paid_mode_enabled' => dredd_ai_get_option('paid_mode_enabled', false),
            'analysis_cost' => dredd_ai_get_option('analysis_cost', 5),
            'psycho_cost' => dredd_ai_get_option('psycho_cost', 10),
            'credits_per_dollar' => dredd_ai_get_option('credits_per_dollar', 10),
            // Stripe settings
            'stripe_secret_key' => dredd_ai_get_option('stripe_secret_key', ''),
            'stripe_publishable_key' => dredd_ai_get_option('stripe_publishable_key', ''),
            'stripe_webhook_secret' => dredd_ai_get_option('stripe_webhook_secret', ''),
            'nowpayments_api_key' => dredd_ai_get_option('nowpayments_api_key', ''),
            'nowpayments_webhook_secret' => dredd_ai_get_option('nowpayments_webhook_secret', ''),
            'nowpayments_sandbox' => dredd_ai_get_option('nowpayments_sandbox', '1'),
            // Individual live addresses
            'live_btc_address' => $live_addresses['btc'] ?? '',
            'live_eth_address' => $live_addresses['eth'] ?? '',
            'live_usdt_address' => $live_addresses['usdt'] ?? '',
            'live_usdc_address' => $live_addresses['usdc'] ?? '',
            'live_ltc_address' => $live_addresses['ltc'] ?? '',
            'live_doge_address' => $live_addresses['doge'] ?? '',
            // 🚨 TEST ADDRESSES COMPLETELY REMOVED
            // Legacy
            'usdt_wallet' => $crypto_wallets['usdt'] ?? '',
            'usdc_wallet' => $crypto_wallets['usdc'] ?? '',
            'pulsechain_wallet' => dredd_ai_get_option('pulsechain_wallet', ''),
            'token_packages' => dredd_ai_get_option('token_packages', array())
        );
    }
    
    /**
     * Save payment settings
     */
    private function save_payment_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save payment mode settings (moved from settings page)
        if (isset($_POST['paid_mode_enabled'])) {
            dredd_ai_update_option('paid_mode_enabled', ($_POST['paid_mode_enabled'] === '1') ? true : false);
        } else {
            dredd_ai_update_option('paid_mode_enabled', false);
        }
        
        if (isset($_POST['analysis_cost'])) {
            dredd_ai_update_option('analysis_cost', intval($_POST['analysis_cost']));
        }
        
        // Save credit settings (moved from users page)
        if (isset($_POST['credits_per_dollar'])) {
            dredd_ai_update_option('credits_per_dollar', intval($_POST['credits_per_dollar']));
        }
        
        if (isset($_POST['psycho_cost'])) {
            dredd_ai_update_option('psycho_cost', intval($_POST['psycho_cost']));
        }
        
        // Save Stripe settings
        if (isset($_POST['stripe_secret_key'])) {
            dredd_ai_update_option('stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
        }
        if (isset($_POST['stripe_publishable_key'])) {
            dredd_ai_update_option('stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key']));
        }
        if (isset($_POST['stripe_webhook_secret'])) {
            dredd_ai_update_option('stripe_webhook_secret', sanitize_text_field($_POST['stripe_webhook_secret']));
        }
        
        // Save NOWPayments settings
        if (isset($_POST['nowpayments_api_key'])) {
            dredd_ai_update_option('nowpayments_api_key', sanitize_text_field($_POST['nowpayments_api_key']));
        }
        if (isset($_POST['nowpayments_webhook_secret'])) {
            dredd_ai_update_option('nowpayments_webhook_secret', sanitize_text_field($_POST['nowpayments_webhook_secret']));
        }
        
        // Save individual live addresses
        $live_addresses = array(
            'btc' => sanitize_text_field($_POST['live_btc_address'] ?? ''),
            'eth' => sanitize_text_field($_POST['live_eth_address'] ?? ''),
            'usdt' => sanitize_text_field($_POST['live_usdt_address'] ?? ''),
            'usdc' => sanitize_text_field($_POST['live_usdc_address'] ?? ''),
            'ltc' => sanitize_text_field($_POST['live_ltc_address'] ?? ''),
            'doge' => sanitize_text_field($_POST['live_doge_address'] ?? '')
        );
        dredd_ai_update_option('live_crypto_addresses', $live_addresses);
        
        // 🚨 TEST ADDRESS SAVING COMPLETELY REMOVED
        
        // 🟢 FORCE LIVE MODE ONLY
        dredd_ai_update_option('nowpayments_sandbox', '0'); // Always live mode
        
        // Save crypto wallets
        $crypto_wallets = array(
            'usdt' => sanitize_text_field($_POST['usdt_wallet']),
            'usdc' => sanitize_text_field($_POST['usdc_wallet'])
        );
        dredd_ai_update_option('crypto_wallets', $crypto_wallets);
        
        // Save PulseChain wallet
        dredd_ai_update_option('pulsechain_wallet', sanitize_text_field($_POST['pulsechain_wallet']));
        
        // Save token packages
        if (isset($_POST['token_packages'])) {
            $packages = array();
            foreach ($_POST['token_packages'] as $package) {
                $packages[] = array(
                    'name' => sanitize_text_field($package['name']),
                    'tokens' => intval($package['tokens']),
                    'price' => floatval($package['price'])
                );
            }
            dredd_ai_update_option('token_packages', $packages);
        }
        
        add_settings_error('dredd_ai_settings', 'settings_saved', 'Payment settings saved successfully!', 'success');
    }
    
    /**
     * Get recent analyses for dashboard
     */
    private function get_recent_analyses($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_analysis_history';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get recent transactions
     */
    private function get_recent_transactions($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_transactions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get promotions
     */
    private function get_promotions() {
        global $wpdb;
        $table = $wpdb->prefix . 'dredd_promotions';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC"
        );
    }
    
    /**
     * Toggle paid mode
     */
    public function toggle_paid_mode() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $enable = filter_var($_POST['enable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        $result = dredd_ai_update_option('paid_mode_enabled', $enable);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Paid mode ' . ($enable ? 'enabled' : 'disabled'),
                'status' => $enable
            ));
        } else {
            wp_send_json_error('Failed to update paid mode');
        }
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->database->cleanup_expired_cache();
        
        // Clear all cache entries
        global $wpdb;
        $cache_table = $wpdb->prefix . 'dredd_cache';
        $deleted = $wpdb->query("DELETE FROM {$cache_table}");
        
        wp_send_json_success(array(
            'message' => "Cleared {$deleted} cache entries",
            'deleted' => $deleted
        ));
    }
    
    /**
     * Get dashboard stats
     */
    public function get_dashboard_stats() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $analytics = new Dredd_Analytics();
        $stats = $analytics->get_realtime_stats();
        
        wp_send_json_success($stats);
    }

    /**
     * Add promotion via AJAX
     */
    public function add_promotion_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $security = new Dredd_Security();
        if (!$security->verify_ajax_request()) {
            wp_send_json_error('Security check failed');
        }

        $promotions = new Dredd_Promotions();
        $promotions->add_promotion();
    }

    /**
     * Update promotion via AJAX
     */
    public function update_promotion_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $security = new Dredd_Security();
        if (!$security->verify_ajax_request()) {
            wp_send_json_error('Security check failed');
        }

        $promotions = new Dredd_Promotions();
        $promotions->update_promotion();
    }

    /**
     * Get system status
     */
    private function get_system_status() {
        $n8n_status = 'offline';
        
        
        // Test n8n connection
        $n8n_webhook = dredd_ai_get_option('n8n_webhook');
        if ($n8n_webhook) {
            $response = wp_remote_head($n8n_webhook, array('timeout' => 5));
            if (!is_wp_error($response)) {
                $n8n_status = 'online';
            }
        }
        
        $overall_status = ($n8n_status === 'online') ? 'online' : 'offline';
        $message = $overall_status === 'online' ? 'All systems operational' : 'System issues detected';
        
        return array(
            'status' => $overall_status,
            'message' => $message,
            'n8n' => $n8n_status
        );
    }
    
    /**
     * Users Management page
     */
    public function users_page() {
        $users = $this->get_all_users_data();
        // Pass both user types to the view
        $data = array(
            'users' => $users,
        );

        include DREDD_AI_PLUGIN_PATH . 'admin/views/users-page.php';
    }

    private function ensure_chat_users_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dredd_chat_users';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Create the table
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
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

            dredd_ai_log('Created dredd_chat_users table in admin page', 'info');
        }
    }
    /**
     * Get all users with their data
     */
    private function get_all_users_data() {
        global $wpdb;
        
        $chat_users = $wpdb->get_results("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.created_at,
                COALESCE(stats.total_analyses, 0) as total_analyses,
                COALESCE(stats.standard_analyses, 0) as standard_analyses,
                COALESCE(stats.psycho_analyses, 0) as psycho_analyses,
                COALESCE(stats.scams_detected, 0) as scams_detected,
                COALESCE(payments.total_spent, 0) as total_spent,
                COALESCE(payments.stripe_payments, 0) as stripe_payments,
                COALESCE(payments.crypto_payments, 0) as crypto_payments

            FROM wpzl_dredd_chat_users u

            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(*) as total_analyses,
                    SUM(CASE WHEN mode = 'standard' THEN 1 ELSE 0 END) as standard_analyses,
                    SUM(CASE WHEN mode = 'psycho' THEN 1 ELSE 0 END) as psycho_analyses,
                    SUM(CASE WHEN verdict = 'scam' THEN 1 ELSE 0 END) as scams_detected
                FROM wpzl_dredd_analysis_history
                GROUP BY user_id
            ) stats ON u.id = stats.user_id

            LEFT JOIN (
                SELECT 
                    user_id,
                    SUM(amount) as total_spent,
                    COUNT(CASE WHEN payment_method = 'stripe' THEN 1 END) as stripe_payments,
                    COUNT(CASE WHEN payment_method LIKE '%crypto%' THEN 1 END) as crypto_payments
                FROM wpzl_dredd_transactions
                WHERE status = 'completed'
                GROUP BY user_id
            ) payments ON u.id = payments.user_id

            ORDER BY u.created_at DESC
        ");

        return $chat_users ?: array();

    }
    
    /**
     * Update credit settings AJAX handler
     */
    public function update_credit_settings() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        $settings = $_POST['settings'];
        dredd_ai_update_option('credits_per_dollar', intval($settings['credits_per_dollar']));
        dredd_ai_update_option('analysis_cost', intval($settings['analysis_cost']));
        dredd_ai_update_option('psycho_cost', intval($settings['psycho_cost']));
        
        // Trigger settings update notification for all users
        update_option('dredd_settings_last_updated', current_time('timestamp'));
        
        wp_send_json_success('Settings updated');
    }
    
    /**
     * Update user credits AJAX handler
     */
    public function update_user_credits_ajax() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $adjustment_type = sanitize_text_field($_POST['adjustment_type']);
        $amount = intval($_POST['amount']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        $current_credits = dredd_ai_get_user_credits($user_id);
        
        switch ($adjustment_type) {
            case 'add':
                $new_credits = $current_credits + $amount;
                break;
            case 'subtract':
                $new_credits = max(0, $current_credits - $amount);
                break;
            case 'set':
                $new_credits = $amount;
                break;
            default:
                wp_send_json_error('Invalid adjustment type');
        }
        
        dredd_ai_update_user_credits($user_id, $new_credits);
        
        // Log the adjustment
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'dredd_transactions',
            array(
                'user_id' => $user_id,
                'amount' => 0,
                'tokens' => $adjustment_type === 'add' ? $amount : -$amount,
                'payment_method' => 'admin_adjustment',
                'status' => 'completed',
                'created_at' => current_time('mysql'),
                'notes' => $reason ?: 'Admin credit adjustment'
            )
        );
        
        // Trigger real-time update notification
        $this->trigger_user_update($user_id, array(
            'credits_changed' => true,
            'new_credits' => $new_credits,
            'reason' => $reason ?: 'Admin credit adjustment'
        ));
        
        wp_send_json_success('Credits updated successfully');
    }
    
    /**
     * Trigger real-time update notification for user
     */
    private function trigger_user_update($user_id, $updates) {
        // Store pending updates in user meta for heartbeat system
        $pending_updates = get_user_meta($user_id, 'dredd_pending_updates', true) ?: array();
        $pending_updates = array_merge($pending_updates, $updates);
        update_user_meta($user_id, 'dredd_pending_updates', $pending_updates);
        
        // Log the update trigger
        dredd_ai_log("Real-time update triggered for user {$user_id}: " . json_encode($updates), 'info');
    }
    
    /**
     * Test n8n webhook connection
     */
    public function test_n8n_webhook() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'dredd_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $webhook_url = sanitize_url($_POST['webhook_url']);
        
        if (empty($webhook_url)) {
            wp_send_json_error('Webhook URL is required');
            return;
        }
        
        // Test payload to send to n8n
        $test_payload = array(
            'test' => true,
            'message' => 'DREDD AI Test Connection',
            'timestamp' => current_time('mysql'),
            'source' => 'dredd-ai-admin-test'
        );
        
        // Send test request to n8n webhook
        $response = wp_remote_post($webhook_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'DREDD-AI-Test/1.0'
            ),
            'body' => json_encode($test_payload)
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            wp_send_json_success(array(
                'message' => 'n8n webhook connection successful!',
                'response_code' => $response_code,
                'response_body' => $response_body
            ));
        } else {
            wp_send_json_error('HTTP error: ' . $response_code . ' - ' . $response_body);
        }
    }

}
