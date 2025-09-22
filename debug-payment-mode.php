<?php
/**
 * DREDD AI Debug Payment Mode Tool
 * Test payment system without real transactions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>DREDD Payment Debug Mode</title>
    <style>
        body {
            font-family: 'Poppins', monospace;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #000000 100%);
            color: #00ffff;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(15, 15, 15, 0.95);
            padding: 30px;
            border: 2px solid #00ffff;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
        }
        h1 {
            color: #ffffff;
            text-align: center;
            text-shadow: 0 0 15px #ffd700;
            margin-bottom: 30px;
        }
        .payment-section {
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #444;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.6);
        }
        .payment-method {
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method:hover {
            border-color: #00ffff;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
        }
        .payment-method.active {
            border-color: #ffffff;
            background: rgba(255, 215, 0, 0.1);
        }
        .test-button {
            background: linear-gradient(135deg, #00ffff, #40e0d0);
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
            transition: all 0.3s ease;
        }
        .test-button:hover {
            background: linear-gradient(135deg, #40e0d0, #00bcd4);
            transform: translateY(-2px);
        }
        .status {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-weight: bold;
        }
        .status.success {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff00;
            color: #00ff00;
        }
        .status.error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff6666;
        }
        .status.info {
            background: rgba(0, 255, 255, 0.2);
            border: 1px solid #00ffff;
            color: #00ffff;
        }
        .amount-selector {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        .amount-option {
            background: #333;
            border: 2px solid #666;
            color: #fff;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .amount-option:hover, .amount-option.selected {
            border-color: #ffffff;
            background: rgba(255, 215, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 DREDD PAYMENT DEBUG MODE</h1>
        
        <div class="payment-section">
            <h3>🔧 Debug Controls</h3>
            <button class="test-button" onclick="toggleDebugMode()">Toggle Payment Debug Mode</button>
            <button class="test-button" onclick="clearTestData()">Clear Test Data</button>
            <div id="debug-status" class="status info">Debug Mode: <span id="debug-state">Unknown</span></div>
        </div>
        
        <div class="payment-section">
            <h3>💰 Test Payment Amounts</h3>
            <div class="amount-selector">
                <div class="amount-option" onclick="selectAmount(5)">$5</div>
                <div class="amount-option" onclick="selectAmount(10)">$10</div>
                <div class="amount-option" onclick="selectAmount(25)">$25</div>
                <div class="amount-option" onclick="selectAmount(50)">$50</div>
                <div class="amount-option" onclick="selectAmount(100)">$100</div>
            </div>
            <input type="number" id="custom-amount" placeholder="Custom amount" style="padding: 10px; background: #333; border: 1px solid #666; color: #fff; border-radius: 5px;">
        </div>
        
        <div class="payment-section">
            <h3>💳 Stripe Test Mode</h3>
            <div class="payment-method" onclick="testStripe()">
                <h4>Test Stripe Payment</h4>
                <p>Simulate Stripe payment with test card</p>
                <code>Test Card: 4242424242424242</code>
            </div>
            <div id="stripe-result"></div>
        </div>
        
        <div class="payment-section">
            <h3>🔗 Crypto Test Mode</h3>
            <div class="payment-method" onclick="testCrypto('bitcoin')">
                <h4>Test Bitcoin Payment</h4>
                <p>Simulate Bitcoin payment</p>
            </div>
            <div class="payment-method" onclick="testCrypto('ethereum')">
                <h4>Test Ethereum Payment</h4>
                <p>Simulate Ethereum payment</p>
            </div>
            <div class="payment-method" onclick="testCrypto('pulsechain')">
                <h4>Test PulseChain Payment</h4>
                <p>Simulate PulseChain payment</p>
            </div>
            <div id="crypto-result"></div>
        </div>
        
        <div class="payment-section">
            <h3>📊 Payment Status Simulation</h3>
            <button class="test-button" onclick="simulateSuccess()">Simulate Success</button>
            <button class="test-button" onclick="simulatePending()">Simulate Pending</button>
            <button class="test-button" onclick="simulateFailure()">Simulate Failure</button>
            <div id="simulation-result"></div>
        </div>
        
        <div class="payment-section">
            <h3>🎯 User Credits Testing</h3>
            <button class="test-button" onclick="addTestCredits(100)">Add 100 Credits</button>
            <button class="test-button" onclick="addTestCredits(500)">Add 500 Credits</button>
            <button class="test-button" onclick="resetCredits()">Reset Credits</button>
            <div id="credits-result"></div>
        </div>
    </div>
    
    <script>
        let selectedAmount = 10;
        
        function selectAmount(amount) {
            selectedAmount = amount;
            document.querySelectorAll('.amount-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }
        
        function getAmount() {
            const customAmount = document.getElementById('custom-amount').value;
            return customAmount || selectedAmount;
        }
        
        function toggleDebugMode() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_payment&type=toggle&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('debug-status', data.success ? 'success' : 'error', 
                    data.success ? `Debug Mode: ${data.data.mode}` : 'Failed to toggle debug mode');
                document.getElementById('debug-state').innerText = data.data?.mode || 'Unknown';
            });
        }
        
        function clearTestData() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dredd_debug_payment&type=clear&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('debug-status', data.success ? 'success' : 'error', 
                    data.success ? 'Test data cleared' : 'Failed to clear test data');
            });
        }
        
        function testStripe() {
            const amount = getAmount();
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=dredd_debug_payment&type=stripe&amount=${amount}&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('stripe-result', data.success ? 'success' : 'error', 
                    data.message || (data.success ? 'Stripe test successful' : 'Stripe test failed'));
            });
        }
        
        function testCrypto(currency) {
            const amount = getAmount();
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=dredd_debug_payment&type=crypto&currency=${currency}&amount=${amount}&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('crypto-result', data.success ? 'success' : 'error', 
                    data.message || (data.success ? `${currency} test successful` : `${currency} test failed`));
            });
        }
        
        function simulateSuccess() {
            simulatePaymentStatus('success');
        }
        
        function simulatePending() {
            simulatePaymentStatus('pending');
        }
        
        function simulateFailure() {
            simulatePaymentStatus('failed');
        }
        
        function simulatePaymentStatus(status) {
            const amount = getAmount();
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=dredd_debug_payment&type=simulate&status=${status}&amount=${amount}&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('simulation-result', data.success ? 'success' : 'error', 
                    data.message || `Payment status simulation: ${status}`);
            });
        }
        
        function addTestCredits(credits) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=dredd_debug_payment&type=credits&credits=${credits}&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('credits-result', data.success ? 'success' : 'error', 
                    data.message || `Added ${credits} test credits`);
            });
        }
        
        function resetCredits() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=dredd_debug_payment&type=reset_credits&nonce=<?php echo wp_create_nonce('dredd_debug'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                updateStatus('credits-result', data.success ? 'success' : 'error', 
                    data.message || 'Credits reset');
            });
        }
        
        function updateStatus(elementId, type, message) {
            const element = document.getElementById(elementId);
            element.className = `status ${type}`;
            element.innerHTML = message;
        }
        
        // Initialize on load
        window.onload = function() {
            selectAmount(10);
            toggleDebugMode();
        };
    </script>
</body>
</html>