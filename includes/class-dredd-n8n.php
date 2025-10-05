<?php
/**
 * n8n workflow integration for DREDD AI analysis
 * Simplified version - direct communication only
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dredd_N8N
{

    private $database;
    private $webhook_url;
    private $api_timeout;

    public function __construct()
    {
        $this->database = new Dredd_Database();
        $this->webhook_url = dredd_ai_get_option('n8n_webhook', '');
        $this->api_timeout = dredd_ai_get_option('api_timeout', 300);
    }

    /**
     * Handle chat request - send directly to n8n and return response
     */
    public function handle_chat_request()
    {
        $message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $user_id = get_current_user_id();
        $mode = sanitize_text_field($_POST['mode'] ?? 'standard');
        $selected_chain = sanitize_text_field($_POST['selected_chain'] ?? 'ethereum');
        $expires_at = sanitize_text_field($_POST['expires_at'] ?? '');

        $extracted_data = $this->extract_token_information($message);

        $contract_addresses = null;
        $token_names = null;

        if (count($extracted_data['contract_addresses']) == 1) {
            $contract_addresses = $extracted_data['contract_addresses'][0];
            $token_names = $extracted_data['token_names'][0];
        }
        
        if($mode != 'standard' && $expires_at <= current_time('mysql')) {
            wp_send_json_error(array('message' => 'Your psycho mode period was exprired.', 'action' => 'error'));
        }
        $payload = array(
            'user_message' => $message,          // keep full text
            'session_id' => $session_id,
            'user_id' => $user_id,
            'mode' => $mode,
            'blockchain' => $selected_chain,
            'contract_addresses' => $contract_addresses,
            'token_names' => $token_names,
            'expires_at' => $expires_at,
            'timestamp' => current_time('mysql')
        );

        $response = $this->send_to_n8n_direct($payload);

        if ($response && isset($response['action'])) {

            if ($response['contract_address'] !== 'Unknown' && $response['contract_address'] !== '') {
                $this->database->store_analysis($response);
            }
            wp_send_json_success($response);
        } else {
            wp_send_json_error(array('message' => 'DREDD is running low on resources, check back soon.', 'action' => 'error'));
        }
    }

    /**
     * Extract token information from user message
     */
    private function extract_token_information($message)
    {
        $extracted = array(
            'token_names' => array(),
            'contract_addresses' => array(),
        );
        if (preg_match_all('/0x[a-fA-F0-9]{40}/', $message, $matches)) {
            $extracted['contract_addresses'] = $matches[0];
        }
        if (preg_match_all('/\b[1-9A-HJ-NP-Za-km-z]{32,50}\b/', $message, $matches)) {
            $extracted['contract_addresses'] = $matches[0];
        }
        if (preg_match_all('/\$([A-Z0-9]{2,10})\b/', $message, $matches)) {
            $extracted['token_names'] = array_merge($extracted['token_names'], $matches[1]);
        }
        if (preg_match_all('/([A-Za-z][A-Za-z0-9\s]{1,20})\s+(0x[a-fA-F0-9]{40})/', $message, $matches)) {
            foreach ($matches[1] as $name) {
                $extracted['token_names'][] = trim($name);
            }
        }
        if (preg_match_all('/\b([A-Z][a-z]*(?:\s+[A-Z][a-z]*)*(?:\s+(?:Token|Coin|Protocol|Finance|Swap|Inu|Doge|Safe|Moon)))\b/', $message, $matches)) {
            $extracted['token_names'] = array_merge($extracted['token_names'], $matches[1]);
        }
        $extracted['token_names'] = array_values(array_unique($extracted['token_names']));
        $extracted['contract_addresses'] = array_values(array_unique($extracted['contract_addresses']));
        return $extracted;
    }


    /**
     * Send data directly to n8n and wait for response
     */
    private function send_to_n8n_direct($payload)
    {
        if (empty($this->webhook_url)) {
            dredd_ai_log('DREDD N8N - No webhook URL configured', 'error');
            return array('message' => 'n8n webhook not configured', 'action' => 'error');
        }

        $response = wp_remote_post($this->webhook_url, array(
            'timeout' => $this->api_timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'DREDD-AI-WordPress-Plugin/' . DREDD_AI_VERSION
            ),
            'body' => json_encode($payload)
        ));

        if (is_wp_error($response)) {
            return array('message' => 'Connection failed: ' . $response->get_error_message(), 'action' => 'error');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);


        if ($response_code !== 200) {
            return array('message' => 'DREDD is running low on resources, check back soon.', 'action' => 'error');
        }

        $body = wp_remote_retrieve_body($response);
        return $this->parse_n8n_response($body);
    }

    /**
     * Parse n8n response - separated for testing
     */
    private function parse_n8n_response($body)
    {
        $n8n_response = json_decode($body, true);
        if ($n8n_response) {
            $data = $n8n_response;
            $action = $data['action'] ?? '';
            $message = $data['message'] ?? '';
            $mode = $data['mode'] ?? '';
            $token_name = $data['token_name'] ?? '';
            $token_symbol = $data['token_symbol'] ?? '';
            $contract_address = $data['contract_address'] ?? '';
            $chain = $data['chain'] ?? '';
            $verdict = $data['verdict'] ?? '';
            $token_cost = $data['token_cost'] ?? 0;
            $risk_score = $data['risk_score'] ?? 0;
            $confidence_score = $data['confidence_score'] ?? 0;
            $is_honeypot = $data['isHoneypot'] ?? false;
            $session_id = $data['session_id'] ?? '';
            $user_id = $data['user_id'] ?? '';
            return array(
                'action' => $action,
                'message' => $message,
                'mode' => $mode,
                'token_name' => $token_name,
                'token_symbol' => $token_symbol,
                'contract_address' => $contract_address,
                'chain' => $chain,
                'verdict' => $verdict,
                'token_cost' => $token_cost,
                'risk_score' => $risk_score,
                'confidence_score' => $confidence_score,
                'is_honeypot' => $is_honeypot,
                'session_id' => $session_id,
                'user_id' => $user_id,
            );
        } else {
            if (empty($body) || strlen($body) < 5) {
                return array(
                    'message' => 'DREDD is running low on resources, check back soon.',
                    'action' => 'error'
                );
            }
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
    public function test_connection()
    {
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