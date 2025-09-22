<?php
/**
 * DREDD AI Debug Bypass Tool
 * Quick access to bypass certain checks for testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>DREDD Debug Bypass</title>
    <style>
        body {
            font-family: 'Poppins', monospace;
            background: #000;
            color: #00ff00;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #111;
            padding: 20px;
            border: 2px solid #00ff00;
            border-radius: 10px;
        }
        h1 {
            color: #00ffff;
            text-align: center;
            text-shadow: 0 0 10px #00ffff;
        }
        .bypass-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 5px;
            background: #0a0a0a;
        }
        .bypass-button {
            background: #00ff00;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }
        .bypass-button:hover {
            background: #00ffff;
        }
        .status {
            color: #ffff00;
            font-weight: bold;
        }
        .warning {
            color: #ff0000;
            font-weight: bold;
            background: #330000;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 DREDD DEBUG BYPASS TOOL</h1>
        
        <div class="warning">
            ⚠️ WARNING: This tool bypasses security checks. Use only for testing!
        </div>
        
        <div class="bypass-section">
            <h3>🔐 Authentication Bypass</h3>
            <p>Bypass login requirements for testing</p>
            <button class="bypass-button" onclick="bypassAuth()">Bypass Authentication</button>
            <span id="auth-status" class="status"></span>
        </div>
        
        <div class="bypass-section">
            <h3>💳 Payment Bypass</h3>
            <p>Enable premium features without payment</p>
            <button class="bypass-button" onclick="bypassPayment()">Bypass Payment</button>
            <span id="payment-status" class="status"></span>
        </div>
        
        <div class="bypass-section">
            <h3>📧 Email Verification Bypass</h3>
            <p>Skip email verification step</p>
            <button class="bypass-button" onclick="bypassEmail()">Bypass Email Verification</button>
            <span id="email-status" class="status"></span>
        </div>
        
        <div class="bypass-section">
            <h3>🤖 reCAPTCHA Bypass</h3>
            <p>Skip CAPTCHA verification</p>
            <button class="bypass-button" onclick="bypassCaptcha()">Bypass reCAPTCHA</button>
            <span id="captcha-status" class="status"></span>
        </div>
        
        <div class="bypass-section">
            <h3>🔄 Reset All</h3>
            <p>Restore normal functionality</p>
            <button class="bypass-button" onclick="resetBypass()" style="background: #ff4444;">Reset All Bypasses</button>
            <span id="reset-status" class="status"></span>
        </div>
    </div>
    
    <script>
        function bypassAuth() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dredd_debug_bypass&type=auth&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('auth-status').innerText = data.success ? '✅ BYPASSED' : '❌ FAILED';
            });
        }
        
        function bypassPayment() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dredd_debug_bypass&type=payment&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('payment-status').innerText = data.success ? '✅ BYPASSED' : '❌ FAILED';
            });
        }
        
        function bypassEmail() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dredd_debug_bypass&type=email&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('email-status').innerText = data.success ? '✅ BYPASSED' : '❌ FAILED';
            });
        }
        
        function bypassCaptcha() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dredd_debug_bypass&type=captcha&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('captcha-status').innerText = data.success ? '✅ BYPASSED' : '❌ FAILED';
            });
        }
        
        function resetBypass() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dredd_debug_bypass&type=reset&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('reset-status').innerText = data.success ? '🔄 RESET' : '❌ FAILED';
                // Clear all other status indicators
                document.getElementById('auth-status').innerText = '';
                document.getElementById('payment-status').innerText = '';
                document.getElementById('email-status').innerText = '';
                document.getElementById('captcha-status').innerText = '';
            });
        }
    </script>
</body>
</html>