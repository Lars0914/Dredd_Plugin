<?php
/**
 * DREDD AI Enable Debug Tool
 * Enable comprehensive debugging for the plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle debug actions
if ($_POST['action'] ?? '' === 'enable_debug') {
    $debug_level = sanitize_text_field($_POST['debug_level'] ?? 'basic');
    $components = $_POST['components'] ?? [];
    
    enable_dredd_debugging($debug_level, $components);
    $message = "Debug mode enabled successfully!";
}

if ($_POST['action'] ?? '' === 'disable_debug') {
    disable_dredd_debugging();
    $message = "Debug mode disabled successfully!";
}

function enable_dredd_debugging($level, $components) {
    // Set WordPress debug constants
    $wp_config_path = ABSPATH . 'wp-config.php';
    if (file_exists($wp_config_path)) {
        $config_content = file_get_contents($wp_config_path);
        
        // Enable WordPress debugging
        $debug_lines = [
            "define('WP_DEBUG', true);",
            "define('WP_DEBUG_LOG', true);",
            "define('WP_DEBUG_DISPLAY', false);",
            "define('SCRIPT_DEBUG', true);"
        ];
        
        foreach ($debug_lines as $line) {
            if (strpos($config_content, $line) === false) {
                $config_content = str_replace("<?php", "<?php\n" . $line, $config_content);
            }
        }
        
        file_put_contents($wp_config_path, $config_content);
    }
    
    // Set DREDD-specific debug options
    update_option('dredd_debug_enabled', true);
    update_option('dredd_debug_level', $level);
    update_option('dredd_debug_components', $components);
    
    // Create debug log directory
    $debug_dir = WP_CONTENT_DIR . '/dredd-debug';
    if (!file_exists($debug_dir)) {
        wp_mkdir_p($debug_dir);
    }
}

function disable_dredd_debugging() {
    update_option('dredd_debug_enabled', false);
    delete_option('dredd_debug_level');
    delete_option('dredd_debug_components');
}

// Get current debug status
$debug_enabled = get_option('dredd_debug_enabled', false);
$debug_level = get_option('dredd_debug_level', 'basic');
$debug_components = get_option('dredd_debug_components', []);

?>
<!DOCTYPE html>
<html>
<head>
    <title>DREDD Debug Control</title>
    <style>
        body {
            font-family: 'Poppins', monospace;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #000000 100%);
            color: #00ff00;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(15, 15, 15, 0.95);
            padding: 30px;
            border: 2px solid #00ff00;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
        }
        h1 {
            color: #00ffff;
            text-align: center;
            text-shadow: 0 0 15px #00ffff;
            margin-bottom: 30px;
        }
        .debug-section {
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #333;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.6);
        }
        .debug-status {
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid #00ff00;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        .debug-status.disabled {
            background: rgba(255, 0, 0, 0.1);
            border-color: #ff0000;
            color: #ff6666;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group select,
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 5px;
            color: #00ff00;
            font-family: 'Poppins', monospace;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .checkbox-item {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .checkbox-item:hover {
            border-color: #00ffff;
            background: rgba(0, 255, 255, 0.1);
        }
        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }
        .action-button {
            background: linear-gradient(135deg, #00ff00, #40ff40);
            color: #000;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 5px;
            transition: all 0.3s ease;
            font-family: 'Poppins', monospace;
        }
        .action-button:hover {
            background: linear-gradient(135deg, #40ff40, #80ff80);
            transform: translateY(-2px);
        }
        .action-button.danger {
            background: linear-gradient(135deg, #ff0000, #ff4040);
            color: #fff;
        }
        .action-button.danger:hover {
            background: linear-gradient(135deg, #ff4040, #ff8080);
        }
        .log-viewer {
            background: #000;
            border: 2px solid #333;
            border-radius: 5px;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
            font-family: 'Poppins', monospace;
            font-size: 12px;
            color: #00ff00;
        }
        .message {
            background: rgba(0, 255, 255, 0.2);
            border: 1px solid #00ffff;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            color: #00ffff;
        }
        .message.success {
            background: rgba(0, 255, 0, 0.2);
            border-color: #00ff00;
            color: #00ff00;
        }
        .message.error {
            background: rgba(255, 0, 0, 0.2);
            border-color: #ff0000;
            color: #ff6666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 DREDD DEBUG CONTROL CENTER</h1>
        
        <?php if (isset($message)): ?>
        <div class="message success"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        
        <div class="debug-status <?php echo $debug_enabled ? '' : 'disabled'; ?>">
            <h3>Current Status: <?php echo $debug_enabled ? '✅ DEBUG ENABLED' : '❌ DEBUG DISABLED'; ?></h3>
            <?php if ($debug_enabled): ?>
            <p>Debug Level: <strong><?php echo esc_html(strtoupper($debug_level)); ?></strong></p>
            <p>Components: <strong><?php echo empty($debug_components) ? 'All' : implode(', ', $debug_components); ?></strong></p>
            <?php endif; ?>
        </div>
        
        <form method="post">
            <div class="debug-section">
                <h3>🎛️ Debug Configuration</h3>
                
                <div class="form-group">
                    <label for="debug_level">Debug Level:</label>
                    <select name="debug_level" id="debug_level">
                        <option value="basic" <?php selected($debug_level, 'basic'); ?>>Basic - Essential logs only</option>
                        <option value="detailed" <?php selected($debug_level, 'detailed'); ?>>Detailed - Include function calls</option>
                        <option value="verbose" <?php selected($debug_level, 'verbose'); ?>>Verbose - Everything including data dumps</option>
                        <option value="insane" <?php selected($debug_level, 'insane'); ?>>Insane - Every single operation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Components to Debug:</label>
                    <div class="checkbox-group">
                        <?php
                        $components = [
                            'auth' => '🔐 Authentication',
                            'payment' => '💳 Payment System',
                            'n8n' => '🤖 N8N Integration',
                            'database' => '💾 Database Operations',
                            'security' => '🛡️ Security Checks',
                            'ajax' => '⚡ AJAX Requests',
                            'webhooks' => '🔗 Webhooks',
                            'email' => '📧 Email System',
                            'analytics' => '📊 Analytics',
                            'crypto' => '🔗 Crypto Payments'
                        ];
                        
                        foreach ($components as $key => $label):
                        ?>
                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="components[]" value="<?php echo $key; ?>" 
                                       <?php checked(in_array($key, $debug_components)); ?>>
                                <?php echo $label; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button type="submit" name="action" value="enable_debug" class="action-button">
                        🚀 ENABLE DEBUG MODE
                    </button>
                    <button type="submit" name="action" value="disable_debug" class="action-button danger">
                        🛑 DISABLE DEBUG MODE
                    </button>
                </div>
            </div>
        </form>
        
        <div class="debug-section">
            <h3>📊 Debug Tools</h3>
            <button type="button" class="action-button" onclick="viewLogs()">📋 View Debug Logs</button>
            <button type="button" class="action-button" onclick="clearLogs()">🗑️ Clear Logs</button>
            <button type="button" class="action-button" onclick="testConnections()">🔌 Test Connections</button>
            <button type="button" class="action-button" onclick="exportDebugInfo()">📤 Export Debug Info</button>
        </div>
        
        <div class="debug-section">
            <h3>📜 Live Debug Log</h3>
            <div id="log-viewer" class="log-viewer">
                <div style="color: #666;">Debug logs will appear here...</div>
            </div>
            <button type="button" class="action-button" onclick="refreshLogs()">🔄 Refresh Logs</button>
        </div>
        
        <div class="debug-section">
            <h3>⚡ Quick Actions</h3>
            <button type="button" class="action-button" onclick="triggerTestAuth()">Test Authentication</button>
            <button type="button" class="action-button" onclick="triggerTestPayment()">Test Payment</button>
            <button type="button" class="action-button" onclick="triggerTestN8N()">Test N8N Connection</button>
            <button type="button" class="action-button" onclick="triggerTestEmail()">Test Email System</button>
        </div>
    </div>
    
    <script>
        function viewLogs() {
            window.open('<?php echo home_url('/?dredd_action=view_logs'); ?>', '_blank');
        }
        
        function clearLogs() {
            if (confirm('Clear all debug logs?')) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=dredd_debug_clear_logs&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
                })
                .then(response => response.json())
                .then(data => alert(data.success ? 'Logs cleared!' : 'Failed to clear logs'));
            }
        }
        
        function testConnections() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_test_connections&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                const logViewer = document.getElementById('log-viewer');
                logViewer.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            });
        }
        
        function exportDebugInfo() {
            window.open('<?php echo admin_url('admin-ajax.php'); ?>?action=dredd_debug_export&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>', '_blank');
        }
        
        function refreshLogs() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_get_logs&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                const logViewer = document.getElementById('log-viewer');
                if (data.success) {
                    logViewer.innerHTML = data.data.logs || 'No logs found';
                } else {
                    logViewer.innerHTML = '<div style="color: #ff6666;">Error loading logs</div>';
                }
            });
        }
        
        function triggerTestAuth() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_test&component=auth&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => alert('Auth Test: ' + (data.success ? 'PASSED' : 'FAILED')));
        }
        
        function triggerTestPayment() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_test&component=payment&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => alert('Payment Test: ' + (data.success ? 'PASSED' : 'FAILED')));
        }
        
        function triggerTestN8N() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_test&component=n8n&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => alert('N8N Test: ' + (data.success ? 'PASSED' : 'FAILED')));
        }
        
        function triggerTestEmail() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_test&component=email&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => alert('Email Test: ' + (data.success ? 'PASSED' : 'FAILED')));
        }
        
        // Auto-refresh logs if debug is enabled
        <?php if ($debug_enabled): ?>
        setInterval(refreshLogs, 5000);
        <?php endif; ?>
        
        // Load logs on page load
        window.onload = function() {
            refreshLogs();
        };
    </script>
</body>
</html>