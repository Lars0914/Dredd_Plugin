<?php
/**
 * URL Helper - Find the correct URL to access DREDD AI fix tools
 */

// Simple URL detection script
?>
<!DOCTYPE html>
<html>
<head>
    <title>DREDD AI - URL Helper</title>
    <style>
        body { 
            font-family: 'Poppins', Arial, sans-serif; 
            background: #0a0a0a; 
            color: #00FFFF; 
            padding: 20px; 
            line-height: 1.6;
        }
        .url-box { 
            background: rgba(26, 26, 26, 0.9); 
            border: 2px solid #40E0D0; 
            padding: 20px; 
            margin: 10px 0; 
            border-radius: 8px;
            text-align: center;
        }
        .url-link {
            background: linear-gradient(135deg, #00FFFF, #40E0D0);
            color: #000;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            display: inline-block;
            margin: 10px;
            transition: all 0.3s ease;
        }
        .url-link:hover {
            background: linear-gradient(135deg, #40E0D0, #00FFFF);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 255, 0.4);
        }
        .instruction {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid #FFFFFF;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .current-url {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid #00FF00;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <h1>🛡️ DREDD AI - URL Helper</h1>
    
    <div class="url-box">
        <h2>📍 Current Script Location</h2>
        <div class="current-url">
            <?php 
            $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            echo htmlspecialchars($currentUrl); 
            ?>
        </div>
    </div>
    
    <div class="instruction">
        <h3>🎯 How to Access DREDD AI Tools</h3>
        <p><strong>If this page loaded successfully, use these URLs:</strong></p>
        <p style="color: #999; font-style: italic;">Debug tools have been removed as promotions are now working properly.</p>
        <p><a href="<?php echo admin_url('admin.php?page=dredd-ai-promotions'); ?>" style="color: #00ffff;">✅ Access Promotions via WordPress Admin</a></p>
    </div>
    
    <div class="instruction">
        <h3>📋 WordPress Admin Access</h3>
        <p><strong>Access promotions management through WordPress admin:</strong></p>
        <ul>
            <li><code>WordPress Admin > DREDD AI > Promotions</code></li>
            <li><code>WordPress Admin > DREDD AI > Settings</code></li>
            <li><code>WordPress Admin > DREDD AI > Payments</code></li>
        </ul>
    </div>
    
    <div class="instruction">
        <h3>⚠️ If This Page Doesn't Load</h3>
        <p>Your web server might not be running. Start your local server:</p>
        <ul>
            <li><strong>XAMPP:</strong> Start Apache from XAMPP Control Panel</li>
            <li><strong>WAMP:</strong> Start all services from WAMP menu</li>
            <li><strong>MAMP:</strong> Click "Start Servers"</li>
            <li><strong>Local by Flywheel:</strong> Start your site</li>
        </ul>
    </div>
    
    <div class="url-box">
        <h3>🔍 Server Information</h3>
        <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></p>
    </div>
</body>
</html>