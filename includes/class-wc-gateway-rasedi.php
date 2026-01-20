<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Rasedi extends WC_Payment_Gateway {

    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $private_key;
    public $secret_key;
    public $testmode;
    public $supports; // Fix dynamic property warning

    public function __construct() {
        $this->id = 'rasedi';
        $this->icon = ''; // Add icon URL here if needed
        $this->has_fields = false;
        $this->method_title = __('Rasedi', 'rasedi-woocommerce');
        $this->method_description = __('Accept payments securely via Rasedi Payment Gateway.', 'rasedi-woocommerce');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->private_key = $this->get_option('private_key');
        $this->secret_key = $this->get_option('secret_key');
        $this->testmode = 'yes' === $this->get_option('testmode');
        
        $this->supports = array(
            'products'
        );

        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_rasedi', array($this, 'webhook'));
    
    }

    /**
     * Logging method using WC_Logger
     */
    public function log($message, $level = 'info') {
        if ($this->testmode || $this->enabled === 'yes') {
            $logger = wc_get_logger();
            $context = array('source' => 'rasedi-payment-gateway');
            
            if ($level === 'error') {
                $logger->error($message, $context);
            } else {
                $logger->info($message, $context);
            }
        }
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Rasedi Payment', 'rasedi-woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Rasedi Payment', 'rasedi-woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay securely using Rasedi.', 'rasedi-woocommerce'),
            ),
            'testmode' => array(
                'title' => __('Test Mode', 'woocommerce'),
                'label' => __('Enable Test Mode', 'woocommerce'),
                'type' => 'checkbox',
                'description' => __('Place the gateway in test mode using test API keys.', 'woocommerce'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'key_id' => array(
                'title' => __('Key ID / Secret Key Identifier', 'rasedi-woocommerce'),
                'type' => 'text',
                'description' => __('The Key ID associated with your private key. (You use any ID you want)', 'rasedi-woocommerce'),
                'desc_tip' => true,
            ),
            'private_key' => array(
                'title' => __('Private Key', 'rasedi-woocommerce'),
                'type' => 'textarea',
                'css' => 'height: 150px;',
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'rasedi-woocommerce'),
                'type' => 'password',
            ),
            'gateways' => array(
                'title'             => __('Payment Options', 'rasedi-woocommerce'),
                'type'              => 'multiselect',
                'description'       => __('Select the payment methods you want to offer.', 'rasedi-woocommerce'),
                'default'           => array('FIB', 'ZAIN', 'ASIA_PAY', 'FAST_PAY', 'NASS_WALLET', 'CREDIT_CARD'),
                'options'           => array(
                    'FIB'           => 'FIB',
                    'ZAIN'          => 'Zain',
                    'ASIA_PAY'      => 'AsiaPay',
                    'FAST_PAY'      => 'FastPay',
                    'NASS_WALLET'   => 'Nass Wallet',
                    'CREDIT_CARD'   => 'Credit Card',
                ),
                'class'             => 'wc-enhanced-select',
                'desc_tip'          => true,
            ),
            'collect_fee' => array(
                'title'       => __('Collect Fee', 'rasedi-woocommerce'),
                'label'       => __('Collect Fees from Customer', 'rasedi-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('If enabled, transaction fees will be collected from the customer.', 'rasedi-woocommerce'),
                'default'     => 'yes',
            ),
            'collect_email' => array(
                'title'       => __('Collect Email', 'rasedi-woocommerce'),
                'label'       => __('Collect Customer Email', 'rasedi-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('If enabled, the customer email will be sent to Rasedi.', 'rasedi-woocommerce'),
                'default'     => 'yes',
            ),
            'collect_phone' => array(
                'title'       => __('Collect Phone', 'rasedi-woocommerce'),
                'label'       => __('Collect Customer Phone', 'rasedi-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('If enabled, the customer phone number will be sent to Rasedi.', 'rasedi-woocommerce'),
                'default'     => 'yes',
            )
        );
    }



    public function is_available() {
        $available = true;
        if ($this->enabled === 'no') {
            $available = false;
        }
        
        // Log availability check (throttled to avoid log spam, or just log for now since debugging)
        // Check if we are in admin to avoid spamming logs on backend
        if (!is_admin()) {
             $this->log('Checking availability. Enabled: ' . $this->enabled . ' Result: ' . ($available ? 'yes' : 'no'));
        }
        
        return $available;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if ($this->testmode) {
            $base_url = 'https://api.rasedi.com/v1/payment/rest/test';
            $relative_url = '/v1/payment/rest/test';
        } else {
            $base_url = 'https://api.rasedi.com/v1/payment/rest/live';
            $relative_url = '/v1/payment/rest/live';
        }
        $endpoint = '/create';
        
        $callback_url = add_query_arg('wc-api', 'WC_Gateway_Rasedi', home_url('/'));
        $redirect_url = $this->get_return_url($order);

        // Fix for local development (Rasedi API requires public URL or valid TLD, rejecting localhost)
        // Also strip protocol because Rasedi seems to force HTTPS redirect, causing https://http// issue if sent with protocol
        if (strpos($callback_url, 'localhost') !== false) {
             $callback_url = str_replace(array('http://', 'https://'), '', $callback_url);
             $callback_url = str_replace('localhost', '127.0.0.1.nip.io', $callback_url);
        }
        if (strpos($redirect_url, 'localhost') !== false) {
             $redirect_url = str_replace(array('http://', 'https://'), '', $redirect_url);
             $redirect_url = str_replace('localhost', '127.0.0.1.nip.io', $redirect_url);
        }

        $gateways = $this->get_option('gateways');
        if (empty($gateways)) {
            $gateways = array('CREDIT_CARD');
        } elseif (!is_array($gateways)) {
             // Handle case where it might be stored as string if single select, though multiselect should be array
             $gateways = array($gateways);
        }

        $payload = array(
            'amount' => strval(intval($order->get_total())), // Ensure string integer representation if required, assuming IQD/lower denomination
             // NOTE: Adjust amount format based on Rasedi API requirements (e.g. cents vs units)
            'title' => $order->get_formatted_billing_full_name() . ' - Order #' . $order->get_id(),
            'description' => 'Payment for Order #' . $order->get_id() . ' by ' . $order->get_formatted_billing_full_name(),
            'gateways' => $gateways,
            'redirectUrl' => $redirect_url,
            'callbackUrl' => $callback_url,
            'collectFeeFromCustomer' => 'yes' === $this->get_option('collect_fee'),
            'collectCustomerEmail' => 'yes' === $this->get_option('collect_email'),
            'collectCustomerPhoneNumber' => 'yes' === $this->get_option('collect_phone'),
        );

        $body = json_encode($payload);
        $this->log('Rasedi: Payload: ' . $body);
        
        $signature = $this->make_signature('POST', $relative_url . $endpoint);
        $this->log('Rasedi: Signature: ' . $signature);

        $response = wp_remote_post($base_url . $endpoint, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-signature' => $signature,
                'x-id' => $this->get_option('key_id')
            ),
            'body' => $body,
            'timeout' => 45
        ));

        if (is_wp_error($response)) {
            $this->log('Rasedi: WP Error: ' . $response->get_error_message(), 'error');
            wc_add_notice(__('Connection error.', 'woocommerce'), 'error');
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        $this->log('Rasedi: Response Code: ' . $code);
        $this->log('Rasedi: Response Body: ' . print_r($response_body, true));

        if ($code >= 200 && $code <= 209 && isset($response_body['redirectUrl'])) {
            // Store reference code
            $order->update_meta_data('_rasedi_reference', $response_body['referenceCode']);
            $order->save();

            return array(
                'result' => 'success',
                'redirect' => $response_body['redirectUrl']
            );
        } else {
            wc_add_notice(__('Payment error: ', 'woocommerce') . ($response_body['message'] ?? 'Unknown error'), 'error');
            return;
        }
    }

    public function webhook() {
        // Handle callback/webhook
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $this->log('Rasedi Main Webhook Hit. Body: ' . $json);

        if (empty($data['referenceCode']) || empty($data['status'])) {
            $this->log('Rasedi Webhook: Missing ref or status', 'error');
            header("HTTP/1.1 400 Bad Request");
            exit;
        }

        $reference_code = sanitize_text_field($data['referenceCode']);
        $status = strtoupper(sanitize_text_field($data['status']));
        
        $this->log("Rasedi Webhook Processing: Ref: $reference_code, Status: $status");

        // Find order by reference
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_rasedi_reference',
            'meta_value' => $reference_code,
        ));

        if (empty($orders)) {
            $this->log('Rasedi Webhook: Order not found', 'error');
            header("HTTP/1.1 404 Not Found");
            exit;
        }

        $order = $orders[0];
        $this->log('Rasedi Webhook: Order Found OK: ' . $order->get_id());

        // Check status
        if ($status === 'SUCCESS' || $status === 'PAID' || $status === 'COMPLETED') {
            if (!$order->has_status('completed') && !$order->has_status('processing')) {
                $order->payment_complete($reference_code);
                $order->add_order_note(__('Payment Rasedi confirmed via webhook. Reference: ', 'rasedi-woocommerce') . $reference_code);
                $this->log('Rasedi Webhook: Order completed');
            }
        } elseif ($status === 'FAILED' || $status === 'CANCELLED') {
            $order->update_status('failed', __('Payment failed or cancelled via Rasedi.', 'rasedi-woocommerce'));
            $this->log('Rasedi Webhook: Order marked failed');
        }

        header("HTTP/1.1 200 OK");
        exit;
    }

    private function make_signature($method, $relativeUrl) {
        $key_id = $this->get_option('key_id');
        
        $raw_sign = $method . " || " . $key_id . " || " . $relativeUrl;
        
        $private_key_res = openssl_pkey_get_private($this->private_key);
        if (!$private_key_res) {
             $this->log('Rasedi: Invalid Private Key', 'error');
            return '';
        }

        $signature = '';
        $success = false;

        // Try OpenSSL with SHA256 (RSA/EC)
        try {
            $success = openssl_sign($raw_sign, $signature, $private_key_res, OPENSSL_ALGO_SHA256);
        } catch (Exception $e) {}

        if (!$success) {
            // Fallback: Try Sodium (Ed25519)
            $clean_key = str_replace(array('-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r", " "), '', $this->private_key);
            $der = base64_decode($clean_key);
            
            if (strlen($der) > 32) {
                // Last 32 bytes of PKCS8 Ed25519 private key is the seed
                $seed = substr($der, -32);
                
                if (function_exists('sodium_crypto_sign_detached') && function_exists('sodium_crypto_sign_seed_keypair')) {
                    $keypair = sodium_crypto_sign_seed_keypair($seed);
                    $secret_key_sodium = sodium_crypto_sign_secretkey($keypair);
                    $signature = sodium_crypto_sign_detached($raw_sign, $secret_key_sodium);
                    $success = true;
                }
            }
        }

        if (!$success) {
             $this->log('Rasedi: Signing failed via OpenSSL and Sodium', 'error');
             return '';
        }
        
        return base64_encode($signature);
    }
}
