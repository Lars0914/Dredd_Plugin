<?php
/**
 * n8n workflow integration for DREDD AI analysis
 * Simplified version - direct communication only
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_N8N {
    
    private $database;
    private $webhook_url;
    private $api_timeout;
    
    public function __construct() {
        $this->database = new Dredd_Database();
        $this->webhook_url = dredd_ai_get_option('n8n_webhook', '');
        $this->api_timeout = dredd_ai_get_option('api_timeout', 300);
    }
    
    /**
     * Handle chat request - send directly to n8n and return response
     */
    public function handle_chat_request() {
        $message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $user_id = get_current_user_id();
        $mode = sanitize_text_field($_POST['mode'] ?? 'standard');
        $selected_chain = sanitize_text_field($_POST['selected_chain'] ?? 'ethereum');
        
        // TEMPORARY TEST: Return a hardcoded response to test frontend
        if (strpos($message, 'test') !== false) {
            dredd_ai_log('DREDD N8N - TEST MODE ACTIVATED', 'debug');
            wp_send_json_success(array(
                'action' => 'response',
                'message' => 'TEST RESPONSE: This is a test message to verify the frontend display works. Your message was: ' . $message
            ));
            return;
        }
        
        // Raw debug mode - show exactly what n8n returns
        if (strpos($message, 'rawdebug') !== false) {
            dredd_ai_log('DREDD N8N - Raw debug mode - sending to real n8n', 'debug');
            $payload = array(
                'message' => '0x2170Ed0880ac9A755fd29B2688956BD959F933F8',
                'user_id' => get_current_user_id(),
                'timestamp' => time(),
                'blockchain' => 'ethereum'
            );
            
            $result = $this->send_to_n8n_direct($payload);
            
            // Send raw debug info to frontend
            wp_send_json_success(array(
                'action' => 'response',
                'message' => 'RAW DEBUG RESULT: ' . json_encode($result, JSON_PRETTY_PRINT) . 
                           '\n\nWebhook URL: ' . $this->webhook_url . 
                           '\n\nIf you see this, n8n communication is working!'
            ));
            return;
        }
        
        // Super detailed debug mode - log everything step by step
        if (strpos($message, 'superlog') !== false) {
            dredd_ai_log('=== SUPER DEBUG MODE START ===', 'debug');
            dredd_ai_log('1. Message received: ' . $message, 'debug');
            dredd_ai_log('2. Webhook URL: ' . $this->webhook_url, 'debug');
            dredd_ai_log('3. Creating payload...', 'debug');
            
            $payload = array(
                'message' => '0x2170Ed0880ac9A755fd29B2688956BD959F933F8',
                'user_id' => get_current_user_id(),
                'timestamp' => time(),
                'blockchain' => 'ethereum'
            );
            
            dredd_ai_log('4. Payload created: ' . json_encode($payload), 'debug');
            dredd_ai_log('5. Calling send_to_n8n_direct...', 'debug');
            
            $result = $this->send_to_n8n_direct($payload);
            
            dredd_ai_log('6. Result received: ' . json_encode($result), 'debug');
            dredd_ai_log('7. Sending JSON response...', 'debug');
            dredd_ai_log('=== SUPER DEBUG MODE END ===', 'debug');
            
            wp_send_json_success(array(
                'action' => 'response',
                'message' => 'SUPER DEBUG COMPLETE - Check WordPress debug logs for detailed step-by-step trace'
            ));
            return;
        }
        
        // AJAX DEBUG TEST: Call a separate simple AJAX endpoint
        if (strpos($message, 'ajaxtest') !== false) {
            dredd_ai_log('DREDD N8N - AJAX TEST MODE ACTIVATED', 'debug');
            wp_send_json_success(array(
                'action' => 'response',
                'message' => 'AJAX TEST: If you see this, the AJAX system is working correctly and the issue is in n8n communication.'
            ));
            return;
        }
        
        // COMPREHENSIVE DEBUG: Log everything step by step
        if (strpos($message, 'debugall') !== false) {
            dredd_ai_log('=== DREDD COMPREHENSIVE DEBUG START ===', 'debug');
            dredd_ai_log('DREDD DEBUG - Message: ' . $message, 'debug');
            dredd_ai_log('DREDD DEBUG - Session ID: ' . $session_id, 'debug');
            dredd_ai_log('DREDD DEBUG - User ID: ' . $user_id, 'debug');
            dredd_ai_log('DREDD DEBUG - Mode: ' . $mode, 'debug');
            dredd_ai_log('DREDD DEBUG - Chain: ' . $selected_chain, 'debug');
            dredd_ai_log('DREDD DEBUG - Webhook URL: ' . $this->webhook_url, 'debug');
            
            // Test different response types
            $test_responses = array(
                'simple' => array('action' => 'response', 'message' => 'Simple test message'),
                'long' => array('action' => 'response', 'message' => 'This is a longer test message to verify that longer responses work correctly. It contains multiple sentences and should display properly in the chat window.'),
                'formatted' => array('action' => 'response', 'message' => "**BOLD TEXT**\n\n*Italic text*\n\nNormal text with line breaks.")
            );
            
            $test_type = $_POST['debug_type'] ?? 'simple';
            $response = $test_responses[$test_type] ?? $test_responses['simple'];
            
            dredd_ai_log('DREDD DEBUG - Test type: ' . $test_type, 'debug');
            dredd_ai_log('DREDD DEBUG - Response to send: ' . json_encode($response), 'debug');
            dredd_ai_log('=== DREDD COMPREHENSIVE DEBUG END ===', 'debug');
            
            wp_send_json_success($response);
            return;
        }
        
        // N8N CONNECTION TEST: Test actual n8n without real processing
        if (strpos($message, 'testn8n') !== false) {
            dredd_ai_log('DREDD N8N - Connection test mode activated', 'debug');
            
            if (empty($this->webhook_url)) {
                wp_send_json_success(array(
                    'action' => 'response',
                    'message' => 'ERROR: n8n webhook URL is not configured in admin settings!'
                ));
                return;
            }
            
            // Send a simple test payload to n8n
            $test_payload = array(
                'test' => true,
                'user_message' => 'Test connection',
                'timestamp' => current_time('mysql')
            );
            
            $result = $this->send_to_n8n_direct($test_payload);
            
            // Return the actual result from n8n for debugging
            wp_send_json_success(array(
                'action' => 'response',
                'message' => 'N8N Test Result: ' . json_encode($result)
            ));
            return;
        }
        
        // TEMPORARY TEST: Simulate exact n8n response
        if (strpos($message, 'simulate') !== false) {
            dredd_ai_log('DREDD N8N - SIMULATION MODE ACTIVATED', 'debug');
            
            // Use your EXACT n8n response format
            $simulated_n8n_response = array(
                array(
                    'content' => array(
                        'parts' => array(
                            array('text' => "That's an *Ethereum address.\n\nIt represents an account on the Ethereum blockchain that can hold Ether (ETH) and ERC-20 tokens, send/receive transactions, and interact with smart contracts.\n\nTo see its current balance, transaction history, token holdings, and any associated labels, you can check it on a blockchain explorer like Etherscan:\n\n*[https://etherscan.io/address/0x2170Ed0880ac9A755fd29B2688956BD959F933F8](https://etherscan.io/address/0x2170Ed0880ac9A755fd29B2688956BD959F933F8)**\n\nBy visiting that link, you'll be able to see all the public on-chain activity for this specific address.")
                        ),
                        'role' => 'model'
                    ),
                    'finishReason' => 'STOP',
                    'index' => 0
                )
            );
            
            dredd_ai_log('DREDD N8N - Simulated response: ' . json_encode($simulated_n8n_response), 'debug');
            
            // Test the parsing logic step by step with detailed logging
            dredd_ai_log('DREDD N8N - Step 1: Checking if response exists...', 'debug');
            if ($simulated_n8n_response) {
                dredd_ai_log('DREDD N8N - Step 2: Response exists, checking index [0]...', 'debug');
                
                if (isset($simulated_n8n_response[0]['content']['parts'][0]['text'])) {
                    $analysis_text = $simulated_n8n_response[0]['content']['parts'][0]['text'];
                    
                    dredd_ai_log('DREDD N8N - Step 3: Successfully extracted text, length: ' . strlen($analysis_text), 'debug');
                    dredd_ai_log('DREDD N8N - Step 4: Text preview: ' . substr($analysis_text, 0, 100) . '...', 'debug');
                    
                    wp_send_json_success(array(
                        'action' => 'response',
                        'message' => $analysis_text
                    ));
                    return;
                } else {
                    dredd_ai_log('DREDD N8N - PARSING FAILED: Could not access text field', 'error');
                    dredd_ai_log('DREDD N8N - Available structure: ' . json_encode($simulated_n8n_response), 'error');
                    
                    wp_send_json_error('Simulation parsing failed');
                    return;
                }
            } else {
                dredd_ai_log('DREDD N8N - PARSING FAILED: Response is empty', 'error');
                wp_send_json_error('Simulation response empty');
                return;
            }
        }
        
        // TEMPORARY TEST: Test broken response format
        if (strpos($message, 'testbroken') !== false) {
            dredd_ai_log('DREDD N8N - BROKEN RESPONSE TEST MODE ACTIVATED', 'debug');
            
            // Simulate broken/unexpected response formats
            $broken_responses = array(
                'empty' => null,
                'wrong_structure' => array('message' => 'direct message'),
                'missing_text' => array(array('content' => array('parts' => array(array('no_text_key' => 'value'))))),
                'string_response' => 'This is just a string'
            );
            
            $test_type = $_POST['test_broken_type'] ?? 'empty';
            $test_response = $broken_responses[$test_type] ?? $broken_responses['empty'];
            
            dredd_ai_log('DREDD N8N - Testing broken response type: ' . $test_type, 'debug');
            dredd_ai_log('DREDD N8N - Broken response: ' . json_encode($test_response), 'debug');
            
            // Run through the real parsing logic
            $parsed_result = $this->parse_n8n_response(json_encode($test_response));
            
            wp_send_json_success($parsed_result);
            return;
        }
        
        // Extract token information from message
        $extracted_data = $this->extract_token_information($message);

        if (count($extracted_data['contract_addresses']) > 1) {
            $contract_addresses = [];
            $token_names = [];
        } else {
            $contract_addresses = $extracted_data['contract_addresses'];
            $token_names = $extracted_data['token_names'];
        }
        // Build enhanced payload for n8n
        $payload = array(
            'user_message' => $message,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'mode' => $mode,
            'blockchain' => $selected_chain,
            'token_name'       => !empty($extracted_data['token_names']) ? $extracted_data['token_names'][0] : null,
            'contract_address' => !empty($extracted_data['contract_addresses']) ? $extracted_data['contract_addresses'][0] : null,
            'user_credits' => dredd_ai_get_user_credits($user_id),
            'timestamp' => current_time('mysql')
        );
        
        // Send to n8n and wait for response
        $response = $this->send_to_n8n_direct($payload);
        
        // Force debug logging
        dredd_ai_log('DREDD N8N - Final response before sending to frontend: ' . json_encode($response), 'debug');
        
        if ($response && isset($response['action'])) {
            dredd_ai_log('DREDD N8N - Sending success response to frontend', 'debug');
            wp_send_json_success($response);
        } else {
            dredd_ai_log('DREDD N8N - Sending error response: ' . json_encode($response), 'error');
            wp_send_json_error($response ? $response : 'Analysis failed');
        }
    }
    
    /**
     * Check if message is likely a token analysis request
     */
    private function is_token_analysis($message) {
        $message_lower = strtolower($message);
        
        // Common analysis keywords
        $analysis_keywords = array(
            'analyze', 'analysis', 'check', 'audit', 'review', 'evaluate',
            'research', 'investigate', 'examine', 'assess', 'study'
        );
        
        // Token/contract indicators
        $token_indicators = array(
            '0x', 'contract', 'token', 'coin', 'address', 'ca:', 'contract address'
        );
        
        // Check for analysis keywords
        foreach ($analysis_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // Check for token indicators
        foreach ($token_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return true;
            }
        }
        
        // Check if message contains potential contract address (0x followed by 40 chars)
        if (preg_match('/0x[a-fA-F0-9]{40}/', $message)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract token information from user message
     */
    private function extract_token_information($message) {
        $extracted = array(
            'token_names' => array(),
            'contract_addresses' => array(),
        );

        // ✅ Ethereum-style contract addresses (0x + 40 hex chars)
        if (preg_match_all('/0x[a-fA-F0-9]{40}/', $message, $matches)) {
            $extracted['contract_addresses'] = $matches[0];
        }

        // ✅ Solana base58 addresses (32–50 chars, excludes 0, O, I, l)
        if (preg_match_all('/\b[1-9A-HJ-NP-Za-km-z]{32,50}\b/', $message, $matches)) {
            $extracted['contract_addresses'] = $matches[0];
        }

        // ✅ Token symbols in $SYMBOL format (e.g. $ETH, $USDT)
        if (preg_match_all('/\$([A-Z0-9]{2,10})\b/', $message, $matches)) {
            $extracted['token_names'] = array_merge($extracted['token_names'], $matches[1]);
        }

        // ✅ Token name before Ethereum contract address
        if (preg_match_all('/([A-Za-z][A-Za-z0-9\s]{1,20})\s+(0x[a-fA-F0-9]{40})/', $message, $matches)) {
            foreach ($matches[1] as $name) {
                $extracted['token_names'][] = trim($name);
            }
        }

        // ✅ Common token name patterns (Token, Coin, Swap, Inu, etc.)
        if (preg_match_all('/\b([A-Z][a-z]*(?:\s+[A-Z][a-z]*)*(?:\s+(?:Token|Coin|Protocol|Finance|Swap|Inu|Doge|Safe|Moon)))\b/', $message, $matches)) {
            $extracted['token_names'] = array_merge($extracted['token_names'], $matches[1]);
        }

        // ✅ Deduplicate everything
        $extracted['token_names'] = array_values(array_unique($extracted['token_names']));
        $extracted['contract_addresses'] = array_values(array_unique($extracted['contract_addresses']));
        return $extracted;
    }

    
    /**
     * Send data directly to n8n and wait for response
     */
    private function send_to_n8n_direct($payload) {
        if (empty($this->webhook_url)) {
            dredd_ai_log('DREDD N8N - No webhook URL configured', 'error');
            return array('message' => 'n8n webhook not configured', 'action' => 'error');
        }
        
        dredd_ai_log('DREDD N8N - Sending to webhook: ' . $this->webhook_url, 'debug');
        dredd_ai_log('DREDD N8N - Payload: ' . json_encode($payload), 'debug');
        
        $response = wp_remote_post($this->webhook_url, array(
            'timeout' => $this->api_timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'DREDD-AI-WordPress-Plugin/' . DREDD_AI_VERSION
            ),
            'body' => json_encode($payload)
        ));
        
        if (is_wp_error($response)) {
            dredd_ai_log('DREDD N8N - WordPress HTTP Error: ' . $response->get_error_message(), 'error');
            return array('message' => 'Connection failed: ' . $response->get_error_message(), 'action' => 'error');
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        dredd_ai_log('DREDD N8N - HTTP Status: ' . $response_code, 'debug');
        dredd_ai_log('DREDD N8N - Response Headers: ' . json_encode($response_headers), 'debug');
        
        if ($response_code !== 200) {
            dredd_ai_log('DREDD N8N - HTTP error: ' . $response_code, 'error');
            return array('message' => 'Please check connection and ask team', 'action' => 'error');
        }
        
        $body = wp_remote_retrieve_body($response);
        dredd_ai_log('DREDD N8N - Raw response body: ' . $body, 'debug');
        dredd_ai_log('DREDD N8N - Body length: ' . strlen($body), 'debug');
        dredd_ai_log('DREDD N8N - Body type: ' . gettype($body), 'debug');
        dredd_ai_log('DREDD N8N - Body empty check: ' . (empty($body) ? 'EMPTY' : 'NOT_EMPTY'), 'debug');
        
        return $this->parse_n8n_response($body);
    }
    
    /**
     * Parse n8n response - separated for testing
     */
    private function parse_n8n_response($body) {
        $n8n_response = json_decode($body, true);
        
        dredd_ai_log('n8n raw response: ' . $body, 'debug');
        dredd_ai_log('n8n decoded response: ' . json_encode($n8n_response), 'debug');
        
        // Handle the actual n8n response format
        if ($n8n_response) {
            dredd_ai_log('n8n response exists, checking structure...', 'debug');
            
            // n8n returns an array with content structure
            if (isset($n8n_response[0]['content']['parts'][0]['text'])) {
                $analysis_text = $n8n_response[0]['content']['parts'][0]['text'];
                
                dredd_ai_log('DREDD N8N - Successfully extracted text: ' . substr($analysis_text, 0, 100) . '...', 'debug');
                
                return array(
                    'action' => 'response',
                    'message' => $analysis_text
                );
            }
            // Fallback: check if it's a simple text response
            elseif (isset($n8n_response['message'])) {
                dredd_ai_log('DREDD N8N - Using fallback: direct message format', 'debug');
                return array(
                    'action' => 'response', 
                    'message' => $n8n_response['message']
                );
            }
            // Fallback: if it's just a string
            elseif (is_string($n8n_response)) {
                dredd_ai_log('DREDD N8N - Using fallback: string response', 'debug');
                return array(
                    'action' => 'response',
                    'message' => $n8n_response
                );
            }
            // If it's an array but doesn't match expected format
            else {
                dredd_ai_log('Unexpected n8n response format: ' . json_encode($n8n_response), 'warning');
                return array(
                    'action' => 'response',
                    'message' => 'Analysis completed, but response format was unexpected. Please check the analysis.'
                );
            }
        } else {
            // JSON decode failed - this might be a PLAIN TEXT response (which is valid!)
            dredd_ai_log('n8n response is not JSON - checking if it\'s plain text', 'debug');
            dredd_ai_log('Raw body content: "' . $body . '"', 'debug');
            dredd_ai_log('Body length: ' . strlen($body), 'debug');
            dredd_ai_log('Body is empty: ' . (empty($body) ? 'YES' : 'NO'), 'debug');
            
            // Check if body is empty - n8n workflow might not be triggering
            if (empty($body) || strlen($body) < 5) {
                return array(
                    'message' => 'Please check your connection and token address. Contact support. If the issue persists, please contact support.',
                    'action' => 'error'
                );
            }
            
            // If we have meaningful text content, treat it as a valid plain text response
            if (strlen($body) > 10) {
                dredd_ai_log('DREDD N8N - Plain text response detected and accepted: ' . substr($body, 0, 100) . '...', 'debug');
                return array(
                    'action' => 'response',
                    'message' => trim($body)
                );
            }
            
            return array('message' => 'Response too short or invalid: ' . substr($body, 0, 100) . '...', 'action' => 'error');
        }
    }
    
    /**
     * Test n8n connection
     */
    public function test_connection() {
        if (empty($this->webhook_url)) {
            return array(
                'success' => false,
                'message' => 'n8n webhook URL not configured'
            );
        }
        
        $test_payload = array(
            'test' => true,
            'timestamp' => current_time('mysql'),
            'source' => 'dredd-ai-plugin'
        );
        
        $response = wp_remote_post($this->webhook_url, array(
            'timeout' => 300,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($test_payload)
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'n8n webhook responding correctly'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'HTTP error: ' . $response_code
            );
        }
    }
}