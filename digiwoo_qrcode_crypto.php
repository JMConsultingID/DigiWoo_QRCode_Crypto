<?php
/**
 * Plugin Name: DigiWoo QRCode Crypto for WooCommerce
 * Description: Integrates LetKnow Crypto Payment Gateway with WooCommerce.
 * Version: 1.0
 * Author: Ardika JM-Consulting
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('plugins_loaded', 'digiwoo_init_payment_gateway', 0);

    function digiwoo_init_payment_gateway() {
        if (!class_exists('WC_Payment_Gateway')) {
            return; // Exit if WooCommerce is not loaded
        }
    /**
     * LetKnow Crypto Payment Gateway class
     */
    class WC_Gateway_LetKnow_Crypto extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'letknow_crypto';
            $this->icon               = ''; // URL to the icon
            $this->has_fields         = false;
            $this->method_title       = 'LetKnow Crypto Payment';
            $this->method_description = 'Allows payments with LetKnow Crypto Payment Gateway.';

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            $this->enabled  = $this->get_option('enabled');
            $this->title    = $this->get_option('title');
            $this->shop_id  = $this->get_option('shop_id');
            $this->shop_key = $this->get_option('shop_key');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Enable LetKnow Crypto Payment Gateway',
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'LetKnow Crypto Payment',
                    'desc_tip'    => true,
                ),
                'shop_id' => array(
                    'title'       => 'Shop ID',
                    'type'        => 'text',
                    'description' => 'Enter your LetKnow Shop ID.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'shop_key' => array(
                    'title'       => 'Shop Key',
                    'type'        => 'text',
                    'description' => 'Enter your LetKnow Shop Key.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        public function process_payment($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);

            // Do the LetKnow API call here
            $payment_data = $this->get_payment_data($order);

            if ($payment_data['result'] === 'success') {
                // Set order note with the QR Code info
                $order->add_order_note("QR Code: " . $payment_data['qr_code']);

                // Reduce stock levels
                wc_reduce_stock_levels($order_id);

                // Remove cart
                $woocommerce->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                wc_add_notice('Payment error:', 'error');
                return;
            }
        }

        private function get_payment_data($order) {
            $nonce = str_replace('.', '', microtime(true));

            $signature = hash_hmac('sha256', "{$nonce}|{$this->shop_id}|{$this->shop_key}", $this->shop_key);

            $requestHeader = [
                "C-Request-Nonce: {$nonce}",
                "C-Request-Signature: {$signature}",
                "C-Shop-Id: {$this->shop_id}",
                "Content-Type: application/json"
            ];

            $request = [
                'currency'         => 'BTC',
                'currency_receive' => 'BTC',
                'reference_id'     => 'refid_' . $order->get_id(),
                'client'           => [
                    'id'        => 'client_' . $order->get_billing_email(),
                    'first_name'=> $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email'     => $order->get_billing_email(),
                    'address'   => $order->get_billing_address_1(),
                ],
            ];

            $response = wp_remote_post('https://pay.letknow.com/api/2/get_deposit_address', array(
                'method'      => 'POST',
                'headers'     => $requestHeader,
                'body'        => json_encode($request),
                'timeout'     => 60,
                'sslverify'   => false,
            ));

            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                return json_decode($response['body'], true);
            }

            return array('result' => 'failure');
        }
    }

    /**
     * Add the gateway to WooCommerce
     */
    function add_letknow_gateway($methods) {
        $methods[] = 'WC_Gateway_LetKnow_Crypto';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_letknow_gateway');
}
}
?>
