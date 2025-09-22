<?php
/**
 * Epic Users Management Dashboard - DREDD AI
 * Advanced User Management with Comprehensive Analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get all users with their data
global $wpdb;
$users_query = "
    SELECT 
        u.ID,
        u.display_name,
        u.user_email,
        u.user_registered,
        ut.token_balance as credits,
        ut.total_purchased,
        COALESCE(stats.total_analyses, 0) as total_analyses,
        COALESCE(stats.standard_analyses, 0) as standard_analyses,
        COALESCE(stats.psycho_analyses, 0) as psycho_analyses,
        COALESCE(stats.scams_detected, 0) as scams_detected,
        COALESCE(payments.total_spent, 0) as total_spent,
        COALESCE(payments.stripe_payments, 0) as stripe_payments,
        COALESCE(payments.crypto_payments, 0) as crypto_payments
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->prefix}dredd_user_tokens ut ON u.ID = ut.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as total_analyses,
            SUM(CASE WHEN analysis_mode = 'standard' THEN 1 ELSE 0 END) as standard_analyses,
            SUM(CASE WHEN analysis_mode = 'psycho' THEN 1 ELSE 0 END) as psycho_analyses,
            SUM(CASE WHEN verdict LIKE '%scam%' OR verdict LIKE '%fraud%' THEN 1 ELSE 0 END) as scams_detected
        FROM {$wpdb->prefix}dredd_analyses 
        GROUP BY user_id
    ) stats ON u.ID = stats.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            SUM(amount) as total_spent,
            SUM(CASE WHEN payment_method = 'stripe' THEN 1 ELSE 0 END) as stripe_payments,
            SUM(CASE WHEN payment_method LIKE '%crypto%' THEN 1 ELSE 0 END) as crypto_payments
        FROM {$wpdb->prefix}dredd_transactions 
        WHERE status = 'completed'
        GROUP BY user_id
    ) payments ON u.ID = payments.user_id
    ORDER BY u.user_registered DESC
";

$users = $wpdb->get_results($users_query);

// Get credit settings
$credit_settings = get_option('dredd_credit_settings', [
    'credits_per_dollar' => 10,
    'analysis_cost' => 5,
    'psycho_cost' => 10
]);

// Calculate statistics
$total_users = count($users);
$total_credits = array_sum(array_column($users, 'credits'));
$total_analyses = array_sum(array_column($users, 'total_analyses'));
$total_revenue = array_sum(array_column($users, 'total_spent'));
$active_users = count(array_filter($users, function($user) { return $user->total_analyses > 0; }));
?>

<div class="wrap dredd-admin-wrap">
    <!-- Epic Header with Statistics Dashboard -->
    <div class="dredd-epic-header">
        <div class="header-background">
            <div class="matrix-overlay"></div>
            <div class="cyber-grid"></div>
        </div>
        
        <div class="header-content">
            <div class="header-title-section">
                <div class="title-container">
                    
                    <h1 class="epic-title">
                        <span class="title-text">DREDD AI</span>
                        <span class="title-subtitle">User Command Center</span>
                    </h1>
                </div>
                <div class="header-stats-mini">
                    <div class="mini-stat">
                        <span class="mini-stat-number"><?php echo number_format($total_users); ?></span>
                        <span class="mini-stat-label">Total Users</span>
                    </div>
                    <div class="mini-stat">
                        <span class="mini-stat-number"><?php echo number_format($active_users); ?></span>
                        <span class="mini-stat-label">Active Users</span>
                    </div>
                </div>
            </div>
            
            <div class="header-actions-epic">
                <button class="epic-button primary" onclick="location.reload();">
                    <span class="button-icon">🔄</span>
                    <span class="button-text">Refresh Data</span>
                </button>
                <button class="epic-button secondary" onclick="exportUserData();">
                    <span class="button-icon">📊</span>
                    <span class="button-text">Export Data</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Real-time Statistics Dashboard -->
    <div class="statistics-command-center">
        <h3 class="section-title">
            <span class="section-icon">📊</span>
            Command Center Analytics
        </h3>
        
        <div class="stats-mega-grid">
            <div class="mega-stat-card revenue">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-trend">
                        <span class="trend-indicator up">↗️</span>
                        <span class="trend-text">+12.5% this month</span>
                    </div>
                </div>
                <div class="stat-glow revenue-glow"></div>
            </div>
            
            <div class="mega-stat-card users">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Registered Users</div>
                    <div class="stat-trend">
                        <span class="trend-indicator up">↗️</span>
                        <span class="trend-text"><?php echo $active_users; ?> active</span>
                    </div>
                </div>
                <div class="stat-glow users-glow"></div>
            </div>
            
            <div class="mega-stat-card analyses">
                <div class="stat-icon">🔬</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_analyses); ?></div>
                    <div class="stat-label">Total Analyses</div>
                    <div class="stat-trend">
                        <span class="trend-indicator up">↗️</span>
                        <span class="trend-text">+8.3% today</span>
                    </div>
                </div>
                <div class="stat-glow analyses-glow"></div>
            </div>
            
            <div class="mega-stat-card credits">
                <div class="stat-icon">🪙</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_credits); ?></div>
                    <div class="stat-label">Active Credits</div>
                    <div class="stat-trend">
                        <span class="trend-indicator neutral">➡️</span>
                        <span class="trend-text">In circulation</span>
                    </div>
                </div>
                <div class="stat-glow credits-glow"></div>
            </div>
        </div>
    </div>

    <!-- Credit Management Redirect Notice -->
    <div class="credit-management-notice">
        <h3 class="section-title">
            <span class="section-icon">⚙️</span>
            Credit & Economy Management
        </h3>
        
        <div class="management-redirect-panel">
            <div class="redirect-info">
                <div class="redirect-icon">💳</div>
                <div class="redirect-content">
                    <h4>Payment Settings Management</h4>
                    <p>Credit rates, package pricing, and economic settings have been centralized in the Payment page for better organization and consistency.</p>
                    <div class="redirect-actions">
                        <a href="<?php echo admin_url('admin.php?page=dredd-ai-payments'); ?>" class="epic-button primary">
                            <span class="button-icon">⚙️</span>
                            <span class="button-text">Manage Credit Settings</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=dredd-ai-payments#token-packages'); ?>" class="epic-button secondary">
                            <span class="button-icon">📦</span>
                            <span class="button-text">Configure Packages</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced User Database -->
    <div class="user-database-section">
        <div class="database-header">
            <h3 class="section-title">
                <span class="section-icon">🗄️</span>
                User Database Command Interface
            </h3>
            
            <div class="database-controls">
                <div class="search-control">
                    <input type="text" id="user-search" class="search-input" placeholder="🔍 Search users..." />
                </div>
                <div class="filter-controls">
                    <select id="user-filter" class="filter-select">
                        <option value="all">All Users</option>
                        <option value="active">Active Users</option>
                        <option value="inactive">Inactive Users</option>
                        <option value="high-spenders">High Spenders</option>
                    </select>
                </div>
                <div class="view-controls">
                    <button class="view-toggle active" data-view="table">📊 Table</button>
                    <button class="view-toggle" data-view="cards">🃏 Cards</button>
                </div>
            </div>
        </div>
        
        <!-- Table View -->
        <div id="table-view" class="database-view active">
            <div class="advanced-table-container">
                <table class="advanced-users-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name">
                                <span class="th-content">👤 User</span>
                            </th>
                            <th class="sortable" data-sort="credits">
                                <span class="th-content">🪙 Credits</span>
                            </th>
                            <th class="sortable" data-sort="analyses">
                                <span class="th-content">🔬 Analysis Stats</span>
                            </th>
                            <th class="sortable" data-sort="revenue">
                                <span class="th-content">💰 Revenue</span>
                            </th>
                            <th class="sortable" data-sort="registered">
                                <span class="th-content">📅 Joined</span>
                            </th>
                            <th class="actions-header">⚡ Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php if (empty($users)): ?>
                        <tr class="empty-row">
                            <td colspan="6">
                                <div class="empty-state-epic">
                                    <div class="empty-icon">🌌</div>
                                    <h4>No Users Detected</h4>
                                    <p>The user database appears to be empty. New registrations will appear here.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="user-row-epic" data-user-id="<?php echo $user->ID; ?>">
                            <td class="user-info-cell">
                                <div class="user-avatar-container">
                                    <?php echo get_avatar($user->ID, 60, '', '', array('class' => 'epic-avatar')); ?>
                                    <div class="user-status-indicator"></div>
                                </div>
                                <div class="user-details-epic">
                                    <div class="user-name-epic"><?php echo esc_html($user->display_name); ?></div>
                                    <div class="user-email-epic"><?php echo esc_html($user->user_email); ?></div>
                                    <div class="user-id-badge">ID: <?php echo $user->ID; ?></div>
                                </div>
                            </td>
                            <td class="credits-cell">
                                <div class="credits-display">
                                    <div class="credits-main">
                                        <span class="credits-icon">🪙</span>
                                        <span class="credits-amount"><?php echo number_format($user->credits ?? 0); ?></span>
                                    </div>
                                    <div class="credits-actions-epic">
                                        <button class="action-btn add-credits" data-user-id="<?php echo $user->ID; ?>" title="Add Credits">➕</button>
                                        <button class="action-btn edit-credits" data-user-id="<?php echo $user->ID; ?>" title="Edit Credits">✏️</button>
                                    </div>
                                </div>
                            </td>
                            <td class="analysis-stats-cell">
                                <div class="stats-container">
                                    <div class="stat-row">
                                        <span class="stat-icon">📊</span>
                                        <span class="stat-value"><?php echo number_format($user->total_analyses ?? 0); ?></span>
                                        <span class="stat-label">Total</span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-icon">🔬</span>
                                        <span class="stat-value"><?php echo number_format($user->standard_analyses ?? 0); ?></span>
                                        <span class="stat-label">Standard</span>
                                    </div>
                                    <div class="stat-row psycho">
                                        <span class="stat-icon">💀</span>
                                        <span class="stat-value"><?php echo number_format($user->psycho_analyses ?? 0); ?></span>
                                        <span class="stat-label">Psycho</span>
                                    </div>
                                    <div class="stat-row danger">
                                        <span class="stat-icon">🚨</span>
                                        <span class="stat-value"><?php echo number_format($user->scams_detected ?? 0); ?></span>
                                        <span class="stat-label">Scams</span>
                                    </div>
                                </div>
                            </td>
                            <td class="revenue-cell">
                                <div class="revenue-display">
                                    <div class="revenue-amount">$<?php echo number_format($user->total_spent ?? 0, 2); ?></div>
                                    <div class="payment-methods-epic">
                                        <?php if (($user->stripe_payments ?? 0) > 0): ?>
                                        <span class="payment-badge stripe">💳 <?php echo $user->stripe_payments; ?></span>
                                        <?php endif; ?>
                                        <?php if (($user->crypto_payments ?? 0) > 0): ?>
                                        <span class="payment-badge crypto">₿ <?php echo $user->crypto_payments; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="date-cell">
                                <div class="date-display">
                                    <div class="date-main"><?php echo date('M j, Y', strtotime($user->user_registered)); ?></div>
                                    <div class="date-time"><?php echo date('H:i', strtotime($user->user_registered)); ?></div>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <div class="action-buttons-epic">
                                    <button class="action-btn view-details" data-user-id="<?php echo $user->ID; ?>" title="View Details">👁️</button>
                                    <button class="action-btn view-history" data-user-id="<?php echo $user->ID; ?>" title="Analysis History">📊</button>
                                    <button class="action-btn user-settings" data-user-id="<?php echo $user->ID; ?>" title="User Settings">⚙️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Cards View -->
        <div id="cards-view" class="database-view">
            <div class="user-cards-grid">
                <?php foreach ($users as $user): ?>
                <div class="user-card-epic" data-user-id="<?php echo $user->ID; ?>">
                    <div class="card-header">
                        <div class="card-avatar">
                            <?php echo get_avatar($user->ID, 80, '', '', array('class' => 'card-avatar-img')); ?>
                            <div class="card-status-dot"></div>
                        </div>
                        <div class="card-user-info">
                            <h4 class="card-user-name"><?php echo esc_html($user->display_name); ?></h4>
                            <p class="card-user-email"><?php echo esc_html($user->user_email); ?></p>
                        </div>
                    </div>
                    
                    <div class="card-stats">
                        <div class="card-stat">
                            <span class="card-stat-icon">🪙</span>
                            <span class="card-stat-value"><?php echo number_format($user->credits ?? 0); ?></span>
                            <span class="card-stat-label">Credits</span>
                        </div>
                        <div class="card-stat">
                            <span class="card-stat-icon">📊</span>
                            <span class="card-stat-value"><?php echo number_format($user->total_analyses ?? 0); ?></span>
                            <span class="card-stat-label">Analyses</span>
                        </div>
                        <div class="card-stat">
                            <span class="card-stat-icon">💰</span>
                            <span class="card-stat-value">$<?php echo number_format($user->total_spent ?? 0, 2); ?></span>
                            <span class="card-stat-label">Spent</span>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <button class="card-action-btn primary" data-user-id="<?php echo $user->ID; ?>">View Details</button>
                        <button class="card-action-btn secondary edit-credits" data-user-id="<?php echo $user->ID; ?>">Edit Credits</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Epic Credit Adjustment Modal -->
<div id="epic-credit-modal" class="epic-modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="epic-modal-content">
        <div class="modal-header-epic">
            <div class="modal-title-container">
                <span class="modal-icon">🪙</span>
                <h3 id="modal-title-epic">Credit Management System</h3>
            </div>
            <button class="modal-close-epic">&times;</button>
        </div>
        
        <div class="modal-body-epic">
            <div class="user-profile-display">
                <div class="profile-avatar" id="modal-user-avatar"></div>
                <div class="profile-info">
                    <div class="profile-name" id="modal-user-name"></div>
                    <div class="profile-credits" id="modal-current-credits"></div>
                </div>
            </div>
            
            <div class="adjustment-interface">
                <div class="adjustment-types">
                    <label class="adjustment-type-option active">
                        <input type="radio" name="adjustment_type" value="add" checked>
                        <span class="option-icon">➕</span>
                        <span class="option-text">Add Credits</span>
                    </label>
                    <label class="adjustment-type-option">
                        <input type="radio" name="adjustment_type" value="set">
                        <span class="option-icon">🎯</span>
                        <span class="option-text">Set Credits</span>
                    </label>
                    <label class="adjustment-type-option">
                        <input type="radio" name="adjustment_type" value="subtract">
                        <span class="option-icon">➖</span>
                        <span class="option-text">Subtract Credits</span>
                    </label>
                </div>
                
                <div class="amount-input-section">
                    <label class="input-label">Credit Amount</label>
                    <input type="number" id="credit-amount-epic" class="epic-input" min="0" placeholder="Enter amount">
                </div>
                
                <div class="reason-input-section">
                    <label class="input-label">Adjustment Reason</label>
                    <textarea id="adjustment-reason-epic" class="epic-textarea" placeholder="Enter reason for this adjustment (optional)"></textarea>
                </div>
            </div>
        </div>
        
        <div class="modal-footer-epic">
            <button class="epic-button secondary cancel-adjustment">Cancel</button>
            <button class="epic-button primary save-adjustment">
                <span class="button-icon">💾</span>
                <span class="button-text">Apply Changes</span>
            </button>
        </div>
    </div>
</div>

<style>
/* Epic Users Management Styles */
.dredd-epic-header {
    position: relative;
    margin-bottom: 30px;
    border-radius: 20px;
    overflow: hidden;
    border: 3px solid var(--primary-cyan);
    box-shadow: 0 0 50px rgba(0, 255, 255, 0.3);
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(0, 0, 0, 0.95) 0%, 
        rgba(10, 10, 10, 0.9) 50%, 
        rgba(0, 0, 0, 0.95) 100%);
}

.matrix-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(0, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(64, 224, 208, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(0, 128, 255, 0.1) 0%, transparent 50%);
    animation: matrixFloat 10s ease-in-out infinite;
}

.cyber-grid {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        linear-gradient(rgba(0, 255, 255, 0.1) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 255, 255, 0.1) 1px, transparent 1px);
    background-size: 30px 30px;
    opacity: 0.3;
    animation: gridMove 20s linear infinite;
}

@keyframes matrixFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    33% { transform: translateY(-10px) rotate(1deg); }
    66% { transform: translateY(-5px) rotate(-1deg); }
}

@keyframes gridMove {
    0% { transform: translate(0, 0); }
    100% { transform: translate(30px, 30px); }
}

.header-content {
    position: relative;
    z-index: 2;
    padding: 30px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title-section {
    display: flex;
    align-items: center;
    gap: 30px;
}

.title-container {
    display: flex;
    align-items: center;
    gap: 20px;
}



.epic-title {
    margin: 0;
}

.title-text {
    display: block;
    font-size: 32px;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary-cyan), var(--primary-turquoise), var(--accent-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
    letter-spacing: 2px;
}

.title-subtitle {
    display: block;
    font-size: 14px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 3px;
    margin-top: 5px;
    opacity: 0.8;
}

.header-stats-mini {
    display: flex;
    gap: 20px;
}

.mini-stat {
    text-align: center;
    padding: 10px 15px;
    background: rgba(0, 255, 255, 0.1);
    border: 1px solid var(--primary-cyan);
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.mini-stat-number {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--primary-cyan);
    text-shadow: 0 0 15px currentColor;
}

.mini-stat-label {
    display: block;
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 3px;
}

.header-actions-epic {
    display: flex;
    gap: 15px;
}

.epic-button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 25px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 2px solid;
}

.epic-button.primary {
    background: linear-gradient(45deg, var(--primary-cyan), var(--primary-turquoise));
    color: var(--dark-bg);
    border-color: var(--primary-cyan);
}

.epic-button.secondary {
    background: transparent;
    color: var(--primary-cyan);
    border-color: var(--primary-cyan);
}

.epic-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 255, 255, 0.4);
}

.epic-button.primary:hover {
    background: linear-gradient(45deg, var(--primary-turquoise), var(--accent-blue));
}

.epic-button.secondary:hover {
    background: var(--primary-cyan);
    color: var(--dark-bg);
}

.button-icon {
    font-size: 16px;
    filter: drop-shadow(0 0 8px currentColor);
}

.statistics-command-center {
    margin-bottom: 30px;
    padding: 25px;
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(10, 10, 10, 0.95));
    border: 2px solid var(--border-secondary);
    border-radius: 15px;
    position: relative;
}

.section-title {
    color: var(--primary-cyan);
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    text-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
}

.section-icon {
    font-size: 24px;
    filter: drop-shadow(0 0 10px currentColor);
}

.stats-mega-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.mega-stat-card {
    position: relative;
    background: rgba(0, 0, 0, 0.6);
    border-radius: 15px;
    padding: 25px;
    border: 2px solid transparent;
    transition: all 0.4s ease;
    overflow: hidden;
}

.mega-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.03), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mega-stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-cyan);
    box-shadow: 0 15px 40px rgba(0, 255, 255, 0.2);
}

.mega-stat-card:hover::before {
    opacity: 1;
}

.mega-stat-card.revenue {
    border-color: var(--success-color);
}

.mega-stat-card.users {
    border-color: var(--primary-cyan);
}

.mega-stat-card.analyses {
    border-color: var(--accent-blue);
}

.mega-stat-card.credits {
    border-color: var(--warning-color);
}

.stat-icon {
    font-size: 40px;
    margin-bottom: 15px;
    display: block;
    filter: drop-shadow(0 0 15px currentColor);
}

.stat-content {
    position: relative;
    z-index: 2;
}

.stat-number {
    font-size: 36px;
    font-weight: 900;
    color: var(--primary-cyan);
    text-shadow: 0 0 20px currentColor;
    display: block;
    margin-bottom: 8px;
    font-family: 'Poppins', monospace;
}

.stat-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
    display: block;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 6px;
}

.trend-indicator {
    font-size: 16px;
}

.trend-indicator.up {
    color: var(--success-color);
}

.trend-indicator.neutral {
    color: var(--warning-color);
}

.trend-text {
    font-size: 11px;
    color: var(--text-muted);
    opacity: 0.8;
}

.stat-glow {
    position: absolute;
    top: 50%;
    right: -20px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0.1;
    filter: blur(20px);
    animation: statGlow 4s ease-in-out infinite;
}

@keyframes statGlow {
    0%, 100% { transform: translateY(-50%) scale(1); opacity: 0.1; }
    50% { transform: translateY(-50%) scale(1.2); opacity: 0.2; }
}

.revenue-glow {
    background: var(--success-color);
}

.users-glow {
    background: var(--primary-cyan);
}

.analyses-glow {
    background: var(--accent-blue);
}

.credits-glow {
    background: var(--warning-color);
}

.credit-control-center {
    margin-bottom: 30px;
    padding: 25px;
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(10, 10, 10, 0.95));
    border: 2px solid var(--border-secondary);
    border-radius: 15px;
}

.control-center-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

.control-panel {
    background: rgba(0, 0, 0, 0.4);
    border: 2px solid var(--border-secondary);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.control-panel:hover {
    border-color: var(--primary-cyan);
    box-shadow: 0 5px 20px rgba(0, 255, 255, 0.1);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-secondary);
}

.panel-header h4 {
    color: var(--primary-cyan);
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
}

.panel-status {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.panel-status.online {
    background: rgba(64, 224, 208, 0.2);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.control-grid {
    display: grid;
    gap: 15px;
}

.control-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.control-label {
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.control-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.control-input {
    background: rgba(26, 26, 26, 0.8);
    border: 2px solid var(--border-secondary);
    color: var(--primary-cyan);
    padding: 12px 15px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    width: 100px;
    text-align: center;
    transition: all 0.3s ease;
}

.control-input:focus {
    border-color: var(--primary-cyan);
    box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
    outline: none;
}

.input-unit {
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
    opacity: 0.8;
}

.preview-grid {
    display: grid;
    gap: 10px;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(0, 128, 255, 0.05);
    border: 1px solid var(--accent-blue);
    border-radius: 6px;
}

.preview-scenario {
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
}

.preview-result {
    color: var(--accent-blue);
    font-size: 14px;
    font-weight: 700;
    text-shadow: 0 0 8px rgba(0, 128, 255, 0.3);
}

.control-actions {
    text-align: center;
}

.epic-button.large {
    padding: 15px 30px;
    font-size: 16px;
    position: relative;
}

.button-pulse {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: buttonPulse 2s ease-in-out infinite;
    border-radius: inherit;
}

@keyframes buttonPulse {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.user-database-section {
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(10, 10, 10, 0.95));
    border: 2px solid var(--border-secondary);
    border-radius: 15px;
    padding: 25px;
}

.database-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.database-controls {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    background: rgba(26, 26, 26, 0.8);
    border: 2px solid var(--border-secondary);
    color: var(--primary-cyan);
    padding: 10px 15px;
    border-radius: 20px;
    width: 250px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-input:focus {
    border-color: var(--primary-cyan);
    box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
    outline: none;
}

.filter-select {
    background: rgba(26, 26, 26, 0.8);
    border: 2px solid var(--border-secondary);
    color: var(--primary-cyan);
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: var(--primary-cyan);
    outline: none;
}

.view-controls {
    display: flex;
    gap: 5px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    padding: 3px;
}

.view-toggle {
    padding: 8px 15px;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-toggle.active {
    background: var(--primary-cyan);
    color: var(--dark-bg);
}

.database-view {
    display: none;
}

.database-view.active {
    display: block;
}

.advanced-table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border-secondary);
    background: rgba(0, 0, 0, 0.3);
}

.advanced-users-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: transparent;
}

.advanced-users-table thead th {
    background: linear-gradient(135deg, rgba(0, 255, 255, 0.15), rgba(64, 224, 208, 0.1));
    color: var(--primary-cyan);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 18px 15px;
    border-bottom: 2px solid var(--primary-cyan);
    text-shadow: 0 0 8px rgba(0, 255, 255, 0.3);
    font-size: 12px;
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
}

.advanced-users-table thead th:hover {
    background: linear-gradient(135deg, rgba(0, 255, 255, 0.2), rgba(64, 224, 208, 0.15));
}

.th-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sort-indicator {
    font-size: 10px;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.sortable:hover .sort-indicator {
    opacity: 1;
}

.advanced-users-table tbody td {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: transparent;
    transition: all 0.3s ease;
}

.user-row-epic:hover td {
    background: rgba(0, 255, 255, 0.05);
    border-color: rgba(0, 255, 255, 0.2);
}

.user-info-cell {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-avatar-container {
    position: relative;
}

.epic-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid var(--border-secondary);
    transition: all 0.3s ease;
}

.user-row-epic:hover .epic-avatar {
    border-color: var(--primary-cyan);
    box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);
}

.user-status-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 16px;
    height: 16px;
    background: var(--success-color);
    border: 2px solid var(--dark-bg);
    border-radius: 50%;
    animation: statusPulse 2s ease-in-out infinite;
}

@keyframes statusPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(64, 224, 208, 0.7); }
    50% { box-shadow: 0 0 0 4px rgba(64, 224, 208, 0); }
}

.user-details-epic {
    flex: 1;
}

.user-name-epic {
    color: var(--primary-cyan);
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
}

.user-email-epic {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 6px;
}

.user-id-badge {
    background: rgba(0, 128, 255, 0.2);
    color: var(--accent-blue);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    display: inline-block;
}

.credits-display {
    text-align: center;
}

.credits-main {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 10px;
}

.credits-icon {
    font-size: 20px;
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.6));
}

.credits-amount {
    color: var(--warning-color);
    font-size: 20px;
    font-weight: 700;
    text-shadow: 0 0 10px rgba(0, 128, 255, 0.4);
    font-family: 'Poppins', monospace;
}

.credits-actions-epic {
    display: flex;
    gap: 6px;
    justify-content: center;
}

.action-btn {
    background: rgba(0, 255, 255, 0.1);
    border: 1px solid var(--primary-cyan);
    color: var(--primary-cyan);
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
}

.action-btn:hover {
    background: var(--primary-cyan);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 255, 255, 0.3);
}

.analysis-stats-cell {
    text-align: center;
}

.stats-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.stat-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-row.psycho {
    border-color: var(--danger-color);
    background: rgba(160, 32, 240, 0.1);
}

.stat-row.danger {
    border-color: #FF4444;
    background: rgba(255, 68, 68, 0.1);
}

.stat-icon {
    font-size: 14px;
}

.stat-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-secondary);
    font-family: 'Poppins', monospace;
}

.stat-row.psycho .stat-value {
    color: var(--danger-color);
    text-shadow: 0 0 6px rgba(160, 32, 240, 0.4);
}

.stat-row.danger .stat-value {
    color: #FF4444;
    text-shadow: 0 0 6px rgba(255, 68, 68, 0.4);
}

.stat-label {
    font-size: 10px;
    color: var(--text-muted);
    opacity: 0.7;
    text-transform: uppercase;
}

.revenue-display {
    text-align: center;
}

.revenue-amount {
    color: var(--success-color);
    font-size: 18px;
    font-weight: 700;
    display: block;
    margin-bottom: 8px;
    text-shadow: 0 0 10px rgba(64, 224, 208, 0.4);
    font-family: 'Poppins', monospace;
}

.payment-methods-epic {
    display: flex;
    gap: 4px;
    justify-content: center;
    flex-wrap: wrap;
}

.payment-badge {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border-secondary);
    color: var(--text-secondary);
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.payment-badge.stripe {
    border-color: var(--accent-blue);
    color: var(--accent-blue);
    background: rgba(0, 128, 255, 0.1);
}

.payment-badge.crypto {
    border-color: var(--warning-color);
    color: var(--warning-color);
    background: rgba(255, 255, 255, 0.1);
}

.date-display {
    text-align: center;
}

.date-main {
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
    display: block;
    margin-bottom: 4px;
}

.date-time {
    color: var(--text-muted);
    font-size: 12px;
    opacity: 0.7
</`

```

```
