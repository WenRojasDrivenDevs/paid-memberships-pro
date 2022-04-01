<?php
//include pmprogateway
require_once(dirname(__FILE__) . "/class.pmprogateway.php");
//load classes init method
add_action('init', array('PMProGateway_stripecheckout', 'init'));
class PMProGateway_stripecheckout extends PMProGateway
{

    /**
     * PMProGateway_stripecheckout constructor.
     *
     * @param null|string $gateway
     *
     * return string
     */
    function __construct($gateway = NULL)
    {

        
        $this->gateway = $gateway;
        $this->gateway_environment = pmpro_getOption("gateway_environment");
        return $this->gateway;
    }


    /**
     * Run on WP init
     *
     * @since 1.8
     */
    static function init()
    {

        //load Stripe library if it hasn't been loaded already (usually by another plugin using Stripe)
        if (!class_exists("Stripe\Stripe")) {
            require_once(PMPRO_DIR . "/includes/lib/Stripe/init.php");
        }
        //make sure Stripe Checkout is a gateway option
        add_filter('pmpro_gateways', array('PMProGateway_stripecheckout', 'pmpro_gateways'));
        //add fields to payment settings
        add_filter('pmpro_payment_options', array('PMProGateway_stripecheckout', 'pmpro_payment_options'));
        add_filter(
            'pmpro_payment_option_fields',
            array(
                'PMProGateway_stripecheckout',
                'pmpro_payment_option_fields'
            ),
            10,
            2
        );
        //code to add at checkout
        $gateway = pmpro_getGateway();
        if ($gateway == "stripecheckout") {
            add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_stripecheckout', 'pmpro_checkout_default_submit_button'));
            add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_stripecheckout', 'pmpro_checkout_before_change_membership_level'), 10, 2);
            add_filter('pmpro_required_billing_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_include_billing_address_fields', '__return_false');
        }
    }


    /**
     * Make sure this gateway is in the gateways list
     *
     * @param array $gateways - Array of recognized gateway identifiers
     *
     * @return array
     *
     * @since 1.8
     */
    static function pmpro_gateways($gateways)
    {
        if (empty($gateways['stripecheckout']))
            $gateways['stripecheckout'] = __('Stripe Checkout', 'paid-memberships-pro');
        return $gateways;
    }


    /**
     * Get a list of payment options that the Check gateway needs/supports.
     *       
     * @since 1.8
     */
    static function getGatewayOptions()
    {
        $options = array(
            'stripecheckout_apiusername',
        );

        return $options;
    }


    /**
     * Set payment options for payment settings page.
     *       
     * @since 1.8
     */
    static function pmpro_payment_options($options)
    {
        //get stripe checkout options
        $check_options = PMProGateway_stripecheckout::getGatewayOptions();

        //merge with others.
        $options = array_merge($check_options, $options);

        return $options;
    }


    /**
     * Display fields for this gateway's options.
     *
     * @since 1.8
     */
    static function pmpro_payment_option_fields($values, $gateway)
    {
?>
        <tr class="pmpro_settings_divider gateway gateway_stripecheckout" <?php if ($gateway != "stripecheckout") { ?>style="display: none;" <?php } ?>>
            <td colspan="2">
                <hr />
                <h2 class="title"><?php _e('Stripe Checkout', 'paid-memberships-pro'); ?></h2>
            </td>
        </tr>
        <tr class="gateway gateway_stripecheckout" <?php if ($gateway != "stripecheckout") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="stripecheckout_apiusername"><?php _e('API Username', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="stripecheckout_apiusername" name="stripecheckout_apiusername" value="<?php echo esc_attr($values['stripecheckout_apiusername']) ?>" class="regular-text code" />
                <p class="description"><?php esc_html_e('Go to Account &raquo; User Management in 2Checkout and create a user with API Access and API Updating.', 'paid-memberships-pro'); ?></p>
            </td>
        </tr>
    <?php
    }


    /**
     * Swap in our submit buttons.
     *
     * @param bool $show
     *
     * @return bool
     *
     * @since 1.8
     */
    static function pmpro_checkout_default_submit_button($show)
    {
        global $gateway, $pmpro_requirebilling;

        //show our submit buttons

    ?>
        <span id="pmpro_stripe_checkout" <?php if (($gateway != "stripecheckout") || !$pmpro_requirebilling) { ?>style="display: none;" <?php } ?>> <input type="hidden" name="submit-checkout" value="1" />
            <input type="image" id="pmpro_btn-submit-stripecheckout" style="border: 1px solid #635bff; border-radius: 15px;" style="border-radius: 15px;" class="<?php echo pmpro_get_element_class('pmpro_btn-submit-checkout'); ?>" value="<?php _e('Check Out with Stripe Checkout', 'paid-memberships-pro'); ?> &raquo;" src="<?php echo apply_filters("pmpro_stripecheckout_button_image", "https://cdn.brandfolder.io/KGT2DTA4/at/bskj2q8srfqx3cvfqvhk73pc/Stripe_wordmark_-_blurple_small.png?width=100&height=48"); ?>" />
        </span>

        <span id="pmpro_submit_span" <?php if (($gateway == "stripecheckout") && $pmpro_requirebilling) { ?>style="display: none;" <?php } ?>>
            <input type="hidden" name="submit-checkout" value="1" />
            <input type="submit" id="pmpro_btn-submit" class="<?php echo pmpro_get_element_class('pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout'); ?>" value="<?php if ($pmpro_requirebilling) {_e('Submit and Check Out', 'paid-memberships-pro');} else {_e('Submit and Confirm', 'paid-memberships-pro');} ?> &raquo;" />
        </span>
<?php

        //don't show the default
        return false;
    }


    /**
     * Instead of change membership levels, send users to Stripe to pay.
     *
     * @param int           $user_id
     * @param \MemberOrder  $morder
     *
     * @since 1.8
     */
    static function pmpro_checkout_before_change_membership_level($user_id, $morder)
    {
        //if no order, no need to pay
        if (empty($morder))
            return;

        $morder->user_id = $user_id;
        $morder->saveOrder();

        $morder->Gateway->sendToStripe($morder);
    }


    /**
     * Send the data/order to Stripe.com's server
     *
     * @param \MemberOrder $order
     */
    function sendToStripe($order)
    {
        //load Stripe library if it hasn't been loaded already (usually by another plugin using Stripe)
        if (!class_exists("Stripe\Stripe")) {
            require_once(PMPRO_DIR . "/stripe-php/init.php");
        }

        $dir = home_url();
        $stripe = new \Stripe\StripeClient('sk_test_51Kg6n8JxLtOkgj83AF1411YlBGGRqOdX7CoVQsEXL3aG9nYKWDQKEsiBwljGtVxaM7pek0JzetgSh9MYaYIGJN3V00gXDYG8Q0');
        $res = $stripe->checkout->sessions->create([
            'success_url' => $dir . '/membership-confirmation?id={CHECKOUT_SESSION_ID}&level=1',
            'cancel_url' => $dir . '/membership-cancel',
            'line_items' => [
                [
                    'price' => 'price_1KgCSkJxLtOkgj83I2k2yOPx',
                    'quantity' => 2,
                ],
            ],
            'mode' => 'subscription',
        ]);

        $order->payment_transaction_id = $res->id;
        $order->saveOrder();
        wp_redirect($res->url);
        exit;
    }


    /**
     * Process checkout.
     *
     * @param \MemberOrder $order
     *
     * @return bool
     */
    function process(&$order)
    {
        if (empty($order->code))
            $order->code = $order->getRandomCode();

        //clean up a couple values
        $order->payment_type = "Stripe Checkout";
        $order->status = "review";
        $order->saveOrder();

        return true;
    }


    /**
     * Process checkout.
     *
     * @param SessionIdStripe $order
     *
     * @return bool
     */
    static function test_req_checkout_session($id, $user_id)
    {
        //get last order
		$last_order  = new MemberOrder();
		$last_order->getLastMemberOrder( $user_id, "review" );

        $order = new MemberOrder();
        if (empty($order->code))
            $order->code = $order->getRandomCode();

        //clean up a couple values
        $order->payment_type = "Stripe Checkout";
        $order->user_id = $user_id;
        $order->payment_transaction_id = $last_order->payment_transaction_id;
        $order->status = "sucess";
        $order->membership_id = 1;
        $order->saveOrder();

        pmpro_changeMembershipLevel(1, $user_id = null,);
       
        return true;
    }

}
