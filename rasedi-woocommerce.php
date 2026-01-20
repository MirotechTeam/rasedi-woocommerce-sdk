<?php
/**
 * Plugin Name: Rasedi Payment Gateway
 * Plugin URI: https://rasedi.com
 * Description: Accept payments via Rasedi Payment Gateway for WooCommerce.
 * Version: 1.1.7
 * Author: MirotechTeam
 * Author URI: https://mirotech.com
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_rasedi_gateway_class');

function init_rasedi_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-rasedi.php';
}

add_filter('woocommerce_payment_gateways', 'add_rasedi_gateway_class');

function add_rasedi_gateway_class($methods) {
    $methods[] = 'WC_Gateway_Rasedi';
    return $methods;
}

// Force Classic Checkout (Shortcode) if Blocks are detected
add_filter('the_content', 'rasedi_force_classic_checkout');
add_filter('render_block', 'rasedi_force_classic_checkout_block', 10, 2);

function rasedi_force_classic_checkout_block($block_content, $block) {
    if ($block['blockName'] === 'woocommerce/checkout') {
        $logger = wc_get_logger();
        $logger->info('Detected WooCommerce Checkout Block via render_block. Forcing replacement.', array('source' => 'rasedi-payment-gateway'));
        return do_shortcode('[woocommerce_checkout]');
    }
    return $block_content;
}

function rasedi_force_classic_checkout($content) {
    if (is_checkout() && !is_wc_endpoint_url('order-received')) {
        // Logging for debug
        $logger = wc_get_logger();
        $context = array('source' => 'rasedi-payment-gateway');
        
        $logger->info('Checkout Filter running. Content length: ' . strlen($content), $context);
        
        // If content has block comments or is empty, use shortcode
        if (has_block('woocommerce/checkout') || empty($content) || strpos($content, '<!-- wp:woocommerce/checkout') !== false) {
            $logger->info('Detected WooCommerce Block. Forcing Classic Shortcode replacement.', $context);
            return '[woocommerce_checkout]';
        } else {
             $logger->info('No Block detected in content.', $context);
        }
    }
    return $content;
}
