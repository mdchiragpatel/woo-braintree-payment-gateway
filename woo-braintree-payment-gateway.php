<?php
/**
 * Plugin Name:       Woo Braintree Payment Gateway
 * Plugin URI:        http://www.multidots.com/
 * Description:       Woo Braintree Payment Gateway authorizes credit card payments and processes them securely with your merchant account.
 * Version:           1.0.0
 * Author:            Multidots
 * Author URI:        http://www.multidots.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-braintree-payment-gateway
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


/**
 * Begins execution of the plugin.
 */
add_action('plugins_loaded', 'init_woo_braintree_payment_gateway');

function init_woo_braintree_payment_gateway() {

    /**
     * Tell WooCommerce that Braintree class exists 
     */
    function add_woo_braintree_payment_gateway($methods) {
        $methods[] = 'Woo_Braintree_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_woo_braintree_payment_gateway');

    if (!class_exists('WC_Payment_Gateway'))
        return;

    /**
     * Braintree gateway class
     */
    class Woo_Braintree_Payment_Gateway extends WC_Payment_Gateway {

        /**
         * Constructor
         */
        public function __construct() {
            $this->id = 'woo_braintree_payment_gateway';
            $this->icon = apply_filters('woocommerce_braintree_icon', plugins_url('images/cards.png', __FILE__));
            $this->has_fields = true;
            $this->method_title = ' Woo Braintree Payment Gateway';
            $this->method_description = 'Woo Braintree Payment Gateway authorizes credit card payments and processes them securely with your merchant account.';
            $this->supports = array('products', 'refunds');
            // Load the form fields
            $this->init_form_fields();
            // Load the settings
            $this->init_settings();
            // Get setting values
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->sandbox = $this->get_option('sandbox');
            $this->environment = $this->sandbox == 'no' ? 'production' : 'sandbox';
            $this->merchant_id = $this->sandbox == 'no' ? $this->get_option('merchant_id') : $this->get_option('sandbox_merchant_id');
            $this->private_key = $this->sandbox == 'no' ? $this->get_option('private_key') : $this->get_option('sandbox_private_key');
            $this->public_key = $this->sandbox == 'no' ? $this->get_option('public_key') : $this->get_option('sandbox_public_key');
            $this->cse_key = $this->sandbox == 'no' ? $this->get_option('cse_key') : $this->get_option('sandbox_cse_key');
            $this->debug = isset($this->settings['debug']) ? $this->settings['debug'] : 'no';
            // Hooks

            register_activation_hook(__FILE__, array($this, 'activate_woo_braintree_payment_gateway'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate_woo_braintree_payment_gateway'));

            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
            add_action('admin_notices', array($this, 'checks'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Admin Panel Options
         */
        public function admin_options() {
            ?>
            <h3><?php _e('Woo Braintree Payment Gateway', 'woo-braintree-payment-gateway'); ?></h3>
            <p><?php _e('Woo Braintree Payment Gateway authorizes credit card payments and processes them securely with your merchant account.', 'woo-braintree-payment-gateway'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
            }

            /**
             * Check if SSL is enabled and notify the user
             */
            public function checks() {
                if ($this->enabled == 'no') {
                    return;
                }

                // PHP Version
                if (version_compare(phpversion(), '5.2.1', '<')) {
                    echo '<div class="error"><p>' . sprintf(__('Woo Braintree Payment Gateway Error: Braintree requires PHP 5.2.1 and above. You are using version %s.', 'woo-braintree-payment-gateway'), phpversion()) . '</p></div>';
                }

                // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
                elseif ('no' == get_option('woocommerce_force_ssl_checkout') && !class_exists('WordPressHTTPS')) {
                    echo '<div class="error"><p>' . sprintf(__('Woo Braintree Payment Gateway is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure!', 'woo-braintree-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
                }
            }

            /**
             * Check if this gateway is enabled
             */
            public function is_available() {
                if ('yes' != $this->enabled) {
                    return false;
                }

                if (!is_ssl() && 'yes' != $this->sandbox) {
                    //	return false;
                }

                return true;
            }

            /**
             * Initialise Gateway Settings Form Fields
             */
            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woo-braintree-payment-gateway'),
                        'label' => __('Enable Woo Braintree Payment Gateway', 'woo-braintree-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woo-braintree-payment-gateway'),
                        'default' => __('Credit card', 'woo-braintree-payment-gateway'),
                        'desc_tip' => true
                    ),
                    'description' => array(
                        'title' => __('Description', 'woo-braintree-payment-gateway'),
                        'type' => 'textarea',
                        'description' => __('This controls the description which the user sees during checkout.', 'woo-braintree-payment-gateway'),
                        'default' => 'Pay securely with your credit card.',
                        'desc_tip' => true
                    ),
                    'sandbox' => array(
                        'title' => __('Sandbox', 'woo-braintree-payment-gateway'),
                        'label' => __('Enable Sandbox Mode', 'woo-braintree-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woo-braintree-payment-gateway'),
                        'default' => 'yes'
                    ),
                    'sandbox_merchant_id' => array(
                        'title' => __('Sandbox Merchant ID', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'sandbox_public_key' => array(
                        'title' => __('Sandbox Public Key', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'sandbox_private_key' => array(
                        'title' => __('Sandbox Private Key', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'sandbox_cse_key' => array(
                        'title' => __('Sandbox CSE Key', 'woo-braintree-payment-gateway'),
                        'type' => 'textarea',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'merchant_id' => array(
                        'title' => __('Production Merchant ID', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'public_key' => array(
                        'title' => __('Production Public Key', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'private_key' => array(
                        'title' => __('Production Private Key', 'woo-braintree-payment-gateway'),
                        'type' => 'text',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'cse_key' => array(
                        'title' => __('Production CSE Key', 'woo-braintree-payment-gateway'),
                        'type' => 'textarea',
                        'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'debug' => array(
                        'title' => __('Debug', 'woo-braintree-payment-gateway'),
                        'type' => 'checkbox',
                        'label' => __('Enable logging <code>/wp-content/uploads/wc-logs/woo-braintree-payment-gateway-{tag}.log</code>', 'woo-braintree-payment-gateway'),
                        'default' => 'no'
                    ),
                );
            }

            /**
             * Initialise Credit Card Payment Form Fields
             */
            public function payment_fields() {
                ?>
            <fieldset id="braintree-cc-form">
                <p class="form-row form-row-wide">
                    <label for="braintree-card-number"><?php echo __('Card Number', 'woo-braintree-payment-gateway') ?> <span class="required">*</span></label>
                    <input type="text" data-encrypted-name="braintree-card-number" placeholder="" autocomplete="off" maxlength="20" class="input-text wc-credit-card-form-card-number" id="woo-braintree-payment-gateway-card-number" name='woo-braintree-payment-gateway-card-number'>
                </p>

                <p class="form-row form-row-first braintree-card-expiry">
                    <label for="braintree-card-expiry-month"><?php echo __('Expiry', 'woo-braintree-payment-gateway') ?> <span class="required">*</span></label>
                    <select name="woo-braintree-payment-gateway-card-expiry-month" id="woo-braintree-payment-gateway-card-expiry-month" class="input-text">
                        <option value=""><?php _e('Month', 'woo-braintree-payment-gateway') ?></option>
                        <option value='01'>01</option>
                        <option value='02'>02</option>
                        <option value='03'>03</option>
                        <option value='04'>04</option>
                        <option value='05'>05</option>
                        <option value='06'>06</option>
                        <option value='07'>07</option>
                        <option value='08'>08</option>
                        <option value='09'>09</option>
                        <option value='10'>10</option>
                        <option value='11'>11</option>
                        <option value='12'>12</option>  
                    </select>

                    <select name="woo-braintree-payment-gateway-card-expiry-year" id="woo-braintree-payment-gateway-card-expiry-year" class="input-text">
                        <option value=""><?php _e('Year', 'woo-braintree-payment-gateway') ?></option><?php
            for ($iYear = date('Y'); $iYear < date('Y') + 21; $iYear++) {
                echo '<option value="' . $iYear . '">' . $iYear . '</option>';
            }
            ?>
                    </select>
                </p>

                <p class="form-row form-row-last">
                    <label for="braintree-card-cvc"><?php echo __('Card Code', 'woo-braintree-payment-gateway') ?> <span class="required">*</span></label>
                    <input type="text" data-encrypted-name="braintree-card-cvc" placeholder="CVC" autocomplete="off" class="input-text wc-credit-card-form-card-cvc" name ='woo-braintree-payment-gateway-card-cvc' id="woo-braintree-payment-gateway-card-cvc">
                </p>
            </fieldset>
            <?php
        }

        /**
         * Outputs style used for Woo Braintree Payment Gateway Payment fields
         * Outputs scripts used for Woo Braintree Payment Gateway
         */
        public function payment_scripts() {
            if (!is_checkout() || !$this->is_available()) {
                return;
            }
        }

        /**
         * Process the payment
         */
        public function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $order_id = $order->id;
            require_once( 'includes/Braintree.php' );

            Braintree_Configuration::environment($this->environment);
            Braintree_Configuration::merchantId($this->merchant_id);
            Braintree_Configuration::publicKey($this->public_key);
            Braintree_Configuration::privateKey($this->private_key);


            $result = Braintree_Transaction::sale(array(
                        "amount" => $order->order_total,
                        'orderId' => $order_id,
                        "creditCard" => array(
                            "number" => $_POST['woo-braintree-payment-gateway-card-number'],
                            "cvv" => $_POST['woo-braintree-payment-gateway-card-cvc'],
                            "expirationMonth" => $_POST['woo-braintree-payment-gateway-card-expiry-month'],
                            "expirationYear" => $_POST['woo-braintree-payment-gateway-card-expiry-year']
                        ),
                        'customer' => array(
                            'firstName' => $_POST['billing_first_name'],
                            'lastName' => $_POST['billing_last_name'],
                            'company' => $_POST['billing_company'],
                            'phone' => $_POST['billing_phone'],
                            'email' => $_POST['billing_email']
                        ),
                        'billing' => array(
                            'firstName' => $_POST['billing_first_name'],
                            'lastName' => $_POST['billing_last_name'],
                            'company' => $_POST['billing_company'],
                            'streetAddress' => $_POST['billing_address_1'],
                            'extendedAddress' => $_POST['billing_address_2'],
                            'locality' => $_POST['billing_city'],
                            'region' => $_POST['billing_state'],
                            'postalCode' => $_POST['billing_postcode'],
                            'countryCodeAlpha2' => $_POST['billing_country']
                        ),
                        'shipping' => array(
                            'firstName' => isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : '',
                            'lastName' => isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : '',
                            'company' => isset($_POST['billing_company']) ? $_POST['billing_company'] : '',
                            'streetAddress' => isset(WC()->customer->shipping_address_1) ? WC()->customer->shipping_address_1 : '',
                            'extendedAddress' => isset(WC()->customer->shipping_address_2) ? WC()->customer->shipping_address_2 : '',
                            'locality' => isset(WC()->customer->shipping_city) ? WC()->customer->shipping_city : '',
                            'region' => isset(WC()->customer->shipping_state) ? WC()->customer->shipping_state : '',
                            'postalCode' => isset(WC()->customer->shipping_postcode) ? WC()->customer->shipping_postcode : '',
                            'countryCodeAlpha2' => isset(WC()->customer->shipping_country) ? WC()->customer->shipping_country : ''
                        ),
                        "options" => array(
                            "submitForSettlement" => true
                        )
                    ));

            if ($result->success) {

                // Payment complete
                $order->payment_complete($result->transaction->id);

                // Add order note
                $order->add_order_note(sprintf(__('%s payment approved! Trnsaction ID: %s', 'woo-braintree-payment-gateway'), $this->title, $result->transaction->id));

                $checkout_note = array(
                    'ID' => $order_id,
                    'post_excerpt' => isset($_POST['order_comments']) ? $_POST['order_comments'] : '',
                );
                wp_update_post($checkout_note);
                update_post_meta($order_id, 'wc_braintree_gateway_transaction_id', $result->transaction->id);

                if (is_user_logged_in()) {
                    $userLogined = wp_get_current_user();
                    update_post_meta($order_id, '_billing_email', $userLogined->user_email);
                    update_post_meta($order_id, '_customer_user', $userLogined->ID);
                } else {
                    update_post_meta($order_id, '_billing_email', $this->get_session('payeremail'));
                }
                $fullname = $result->transaction->billing['firstName'] . ' ' . $result->transaction->billing['lastName'];

                update_post_meta($order_id, '_billing_first_name', isset($result->transaction->billing['firstName']) ? $result->transaction->billing['firstName'] : '');
                update_post_meta($order_id, '_billing_last_name', isset($result->transaction->billing['lastName']) ? $result->transaction->billing['lastName'] : '');
                update_post_meta($order_id, '_billing_full_name', isset($fullname) ? $fullname : '');
                update_post_meta($order_id, '_billing_company', isset($result->transaction->billing['company']) ? $result->transaction->billing['company'] : '');
                update_post_meta($order_id, '_billing_phone', isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '');
                update_post_meta($order_id, '_billing_address_1', isset($result->transaction->billing['streetAddress']) ? $result->transaction->billing['streetAddress'] : '');
                update_post_meta($order_id, '_billing_address_2', isset($result->transaction->billing['extendedAddress']) ? $result->transaction->billing['extendedAddress'] : '');
                update_post_meta($order_id, '_billing_city', isset($result->transaction->billing['locality']) ? $result->transaction->billing['locality'] : '');
                update_post_meta($order_id, '_billing_postcode', isset($result->transaction->billing['postalCode']) ? $result->transaction->billing['postalCode'] : '');
                update_post_meta($order_id, '_billing_country', isset($result->transaction->billing['countryCodeAlpha2']) ? $result->transaction->billing['countryCodeAlpha2'] : '');
                update_post_meta($order_id, '_billing_state', isset($result->transaction->billing['region']) ? $result->transaction->billing['region'] : '');
                update_post_meta($order_id, '_customer_user', get_current_user_id());

                update_post_meta($order_id, '_shipping_first_name', isset($result->transaction->shipping['firstName']) ? $result->transaction->shipping['firstName'] : '');
                update_post_meta($order_id, '_shipping_last_name', isset($result->transaction->shipping['lastName']) ? $result->transaction->shipping['lastName'] : '');
                update_post_meta($order_id, '_shipping_full_name', isset($fullname) ? $fullname : '');
                update_post_meta($order_id, '_shipping_company', isset($result->transaction->shipping['company']) ? $result->transaction->shipping['company'] : '');
                update_post_meta($order_id, '_billing_phone', isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '');
                update_post_meta($order_id, '_shipping_address_1', isset($result->transaction->shipping['streetAddress']) ? $result->transaction->shipping['streetAddress'] : '');
                update_post_meta($order_id, '_shipping_address_2', isset($result->transaction->shipping['extendedAddress']) ? $result->transaction->shipping['extendedAddress'] : '');
                update_post_meta($order_id, '_shipping_city', isset($result->transaction->shipping['locality']) ? $result->transaction->shipping['locality'] : '');
                update_post_meta($order_id, '_shipping_postcode', isset($result->transaction->shipping['postalCode']) ? $result->transaction->shipping['postalCode'] : '');
                update_post_meta($order_id, '_shipping_country', isset($result->transaction->shipping['countryCodeAlpha2']) ? $result->transaction->shipping['countryCodeAlpha2'] : '');
                update_post_meta($order_id, '_shipping_state', isset($result->transaction->shipping['region']) ? $result->transaction->shipping['region'] : '');

                if (is_user_logged_in()) {
                    $userLogined = wp_get_current_user();
                    $customer_id = $userLogined->ID;
                    update_user_meta($customer_id, 'billing_first_name', isset($result->transaction->billing['firstName']) ? $result->transaction->billing['firstName'] : '');
                    update_user_meta($customer_id, 'billing_last_name', isset($result->transaction->billing['lastName']) ? $result->transaction->billing['lastName'] : '');
                    update_user_meta($customer_id, 'billing_full_name', isset($fullname) ? $fullname : '');
                    update_user_meta($customer_id, 'billing_company', isset($result->transaction->billing['company']) ? $result->transaction->billing['company'] : '');
                    update_user_meta($customer_id, 'billing_phone', isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '');
                    update_user_meta($customer_id, 'billing_address_1', isset($result->transaction->billing['streetAddress']) ? $result->transaction->billing['streetAddress'] : '');
                    update_user_meta($customer_id, 'billing_address_2', isset($result->transaction->billing['extendedAddress']) ? $result->transaction->billing['extendedAddress'] : '');
                    update_user_meta($customer_id, 'billing_city', isset($result->transaction->billing['locality']) ? $result->transaction->billing['locality'] : '');
                    update_user_meta($customer_id, 'billing_postcode', isset($result->transaction->billing['postalCode']) ? $result->transaction->billing['postalCode'] : '');
                    update_user_meta($customer_id, 'billing_country', isset($result->transaction->billing['countryCodeAlpha2']) ? $result->transaction->billing['countryCodeAlpha2'] : '');
                    update_user_meta($customer_id, 'billing_state', isset($result->transaction->billing['region']) ? $result->transaction->billing['region'] : '');
                    update_user_meta($customer_id, 'customer_user', get_current_user_id());

                    update_user_meta($customer_id, 'shipping_first_name', isset($result->transaction->shipping['firstName']) ? $result->transaction->shipping['firstName'] : '');
                    update_user_meta($customer_id, 'shipping_last_name', isset($result->transaction->shipping['lastName']) ? $result->transaction->shipping['lastName'] : '');
                    update_user_meta($customer_id, 'shipping_full_name', isset($fullname) ? $fullname : '');
                    update_user_meta($customer_id, 'shipping_company', isset($result->transaction->shipping['company']) ? $result->transaction->shipping['company'] : '');
                    update_user_meta($customer_id, 'billing_phone', isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '');
                    update_user_meta($customer_id, 'shipping_address_1', isset($result->transaction->shipping['streetAddress']) ? $result->transaction->shipping['streetAddress'] : '');
                    update_user_meta($customer_id, 'shipping_address_2', isset($result->transaction->shipping['extendedAddress']) ? $result->transaction->shipping['extendedAddress'] : '');
                    update_user_meta($customer_id, 'shipping_city', isset($result->transaction->shipping['locality']) ? $result->transaction->shipping['locality'] : '');
                    update_user_meta($customer_id, 'shipping_postcode', isset($result->transaction->shipping['postalCode']) ? $result->transaction->shipping['postalCode'] : '');
                    update_user_meta($customer_id, 'shipping_country', isset($result->transaction->shipping['countryCodeAlpha2']) ? $result->transaction->shipping['countryCodeAlpha2'] : '');
                    update_user_meta($customer_id, 'shipping_state', isset($result->transaction->shipping['region']) ? $result->transaction->shipping['region'] : '');
                }

                $this->add_log(print_r($result, true));
                // Remove cart
                WC()->cart->empty_cart();
                // Return thank you page redirect

                if (is_ajax()) {
                    $result = array(
                        'redirect' => $this->get_return_url($order),
                        'result' => 'success'
                    );
                    echo json_encode($result);
                    exit;
                } else {
                    exit;
                }
            } else if ($result->transaction) {
                $order->add_order_note(sprintf(__('%s payment declined.<br />Error: %s<br />Code: %s', 'woo-braintree-payment-gateway'), $this->title, $result->message, $result->transaction->processorResponseCode));
                $this->add_log(print_r($result, true));
            } else {
                foreach (($result->errors->deepAll()) as $error) {
                    wc_add_notice("Validation error - " . $error->message, 'error');
                }
                return array(
                    'result' => 'fail',
                    'redirect' => ''
                );
                $this->add_log($error->message);
            }
        }

        //  Process a refund if supported
        public function process_refund($order_id, $amount = null, $reason = '') {
            $order = new WC_Order($order_id);
            require_once( 'includes/Braintree.php' );

            Braintree_Configuration::environment($this->environment);
            Braintree_Configuration::merchantId($this->merchant_id);
            Braintree_Configuration::publicKey($this->public_key);
            Braintree_Configuration::privateKey($this->private_key);
            $transation_id = get_post_meta($order_id, 'wc_braintree_gateway_transaction_id', true);

            $result = Braintree_Transaction::refund($transation_id, $amount);

            if ($result->success) {
                
                $this->add_log(print_r($result, true));
                $max_remaining_refund = wc_format_decimal($order->get_total() - $amount);
                if (!$max_remaining_refund > 0) {
                    $order->update_status('refunded');
                }

                if (ob_get_length())
                    ob_end_clean();
                return true;
            }else {
                $wc_message = apply_filters('wc_braintree_refund_message', $result->message, $result);
                $this->add_log(print_r($result, true));
                return new WP_Error('wc_braintree_gateway_refund-error', $wc_message);
            }
        }

        /**
         * Use WooCommerce logger if debug is enabled.
         */
        function add_log($message) {
            if ($this->debug == 'yes') {
                if (empty($this->log))
                    $this->log = new WC_Logger();
                $this->log->add('woo_braintree_payment_gateway', $message);
            }
        }

        /**
         * Run when plugin is activated
         */
        function activate_woo_braintree_payment_gateway() {
            
        }

        /**
         * Run when plugin is deactivate
         */
        function deactivate_woo_braintree_payment_gateway() {
            
        }

    }

}