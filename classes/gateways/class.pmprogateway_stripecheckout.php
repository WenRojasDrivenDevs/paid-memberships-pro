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
        add_action('admin_init', array('PMProGateway_stripecheckout', 'stripecheckout_connect_save_options'));
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
            'stripecheckout_secretkey',
            'stripecheckout_publishablekey',
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
        //TODO:
        // $environment = $livemode ? 'live' : 'sandbox';
        $environment = 'sandbox';
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
        <tr class="pmpro_settings_divider gateway gateway_stripecheckout" <?php if ($gateway != "stripecheckout") { ?>style="display: none;" <?php } ?>>
        <tr class="gateway pmpro_stripe_legacy_keys gateway_stripecheckout" <?php if ($gateway != "stripecheckout") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="stripecheckout_publishablekey"><?php _e('Publishable Key', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="stripecheckout_publishablekey" name="stripecheckout_publishablekey" value="<?php echo esc_attr($values['stripecheckout_publishablekey']) ?>" class="regular-text code" />
                <?php
                $public_key_prefix = substr($values['stripecheckout_publishablekey'], 0, 3);
                if (!empty($values['stripecheckout_publishablekey']) && $public_key_prefix != 'pk_') {
                ?>
                    <p class="pmpro_red"><strong><?php _e('Your Publishable Key appears incorrect.', 'paid-memberships-pro'); ?></strong></p>
                <?php
                }
                ?>
            </td>
        </tr>
        <tr class="gateway pmpro_stripe_legacy_keys gateway_stripecheckout" <?php if ($gateway != "stripecheckout") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="stripecheckout_secretkey"><?php _e('Secret Key', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="stripecheckout_secretkey" name="stripecheckout_secretkey" value="<?php echo esc_attr($values['stripecheckout_secretkey']) ?>" autocomplete="off" class="regular-text code pmpro-admin-secure-key" />
            </td>
        </tr>
        <input type='hidden' name='<?php echo $environment; ?>_stripe_connect_user_id' id='<?php echo $environment; ?>_stripe_connect_user_id' value='<?php echo esc_attr($values[$environment . '_stripe_connect_user_id']) ?>' />
        <input type='hidden' name='<?php echo $environment; ?>_stripe_connect_secretkey' id='<?php echo $environment; ?>_stripe_connect_secretkey' value='<?php echo esc_attr($values[$environment . '_stripe_connect_secretkey']) ?>' />
        <input type='hidden' name='<?php echo $environment; ?>_stripe_connect_publishablekey' id='<?php echo $environment; ?>_stripe_connect_publishablekey' value='<?php echo esc_attr($values[$environment . '_stripe_connect_publishablekey']) ?>' />
        <tr class="gateway pmpro_stripe_legacy_keys gateway_stripecheckout" <?php if ($gateway != "stripecheckout") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label><?php esc_html_e('Webhook', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <?php if (!empty($webhook) && is_array($webhook)) { ?>
                    <button type="button" id="pmpro_stripe_create_webhook" class="button button-secondary" style="display: none;"><span class="dashicons dashicons-update-alt"></span> <?php _e('Create Webhook', 'paid-memberships-pro'); ?></button>
                    <?php
                    if ('disabled' === $webhook['status']) {
                        // Check webhook status.
                    ?>
                        <div class="notice error inline">
                            <p id="pmpro_stripe_webhook_notice" class="pmpro_stripe_webhook_notice"><?php _e('A webhook is set up in Stripe, but it is disabled.', 'paid-memberships-pro'); ?> <a id="pmpro_stripe_rebuild_webhook" href="#">Rebuild Webhook</a></p>
                        </div>
                    <?php
                    } elseif ($webhook['api_version'] < PMPRO_STRIPE_API_VERSION) {
                        // Check webhook API version.
                    ?>
                        <div class="notice error inline">
                            <p id="pmpro_stripe_webhook_notice" class="pmpro_stripe_webhook_notice"><?php _e('A webhook is set up in Stripe, but it is using an old API version.', 'paid-memberships-pro'); ?> <a id="pmpro_stripe_rebuild_webhook" href="#"><?php _e('Rebuild Webhook', 'paid-memberships-pro'); ?></a></p>
                        </div>
                    <?php
                    } else {
                    ?>
                        <div class="notice notice-success inline">
                            <p id="pmpro_stripe_webhook_notice" class="pmpro_stripe_webhook_notice"><?php _e('Your webhook is enabled.', 'paid-memberships-pro'); ?> <a id="pmpro_stripe_delete_webhook" href="#"><?php _e('Disable Webhook', 'paid-memberships-pro'); ?></a></p>
                        </div>
                <?php
                    }
                } 
                ?>
                <p class="description"><?php esc_html_e('Webhook URL', 'paid-memberships-pro'); ?>:
                    <code><?php echo self::get_site_webhook_url(); ?></code>
                </p>
            </td>
        </tr>
    <?php
    }

    /**
     * Get current webhook URL for website to compare.
	 * 
     * @since 2.4
     * @deprecated 2.7.0. Only deprecated for public use, will be changed to private non-static in a future version.
     */
    public static function get_site_webhook_url()
    {
        // Show deprecation warning if called publically.
        pmpro_method_should_be_private('2.7.0');
        return admin_url('admin-ajax.php') . '?action=stripe_webhook';
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

        //set api key
        $stripe = new \Stripe\StripeClient(pmpro_getOption("stripecheckout_secretkey"));

        //List of subscriptions plan stripe IDs
        $products = $stripe->products->all(["active" => true]);

        // Get level selected for purchase
        $level_select = pmpro_getLevelAtCheckout();

        foreach ($products as $product) {
            if ($level_select->id == $product['metadata']->membership_id) {
                $stripe_plan = $product;
                break;
            }
        }

        $dir = home_url();
        $res = $stripe->checkout->sessions->create([
            'success_url' => get_permalink(get_option( 'pmpro_confirmation_page_id' )) . '/?id={CHECKOUT_SESSION_ID}&level=' . $level_select->id,
            'cancel_url' => get_permalink(get_option( 'pmpro_cancel_page_id' )),
            'line_items' => [
                [
                    'price' => $stripe_plan['metadata']->membership_price,
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
        ]);

        $order->payment_transaction_id = $res->payment_intent;
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

        global $current_user;
        $level_select = pmpro_getLevelAtCheckout();


        //clean up a couple values
        $order->payment_type = "Stripe Checkout";
        $order->status = "review";
        $order->user_id = $current_user->ID;
        $order->membership_id = $level_select->id;

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
    static function pmpro_checkout_after_stripecheckout_session($id, $user_id)
    {
        $stripe = new \Stripe\StripeClient(pmpro_getOption("stripecheckout_secretkey"));
        $session = $stripe->checkout->sessions->retrieve($id);

        $order = new MemberOrder();
        $order->getLastMemberOrder($user_id, "review");
        //clean up a couple values
        $order->payment_transaction_id = $session['payment_intent'];
        // Check if the payment was immediate
        if ($session['payment_status'] === "paid") {
            $order->status = "success";
            pmpro_changeMembershipLevel($order->membership_id, $user_id);
        }

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
    static function pmpro_stripecheckout_cancel_subscription($user_id)
    {
        $stripe = new \Stripe\StripeClient(
            pmpro_getOption("stripecheckout_secretkey")
        );
        // Get last order
        $order = new MemberOrder();
        $order->getLastMemberOrder();
        
        $stripe->subscriptions->cancel(
            $order->subscription_transaction_id,
            []
        );
        return true;
    }

    /**
     * This function is used to save the parameters returned after successfull connection of Stripe account.
     *
     * @return void
     */
    public static function stripecheckout_connect_save_options()
    {

        // Is user have permission to edit give setting.
        if (!current_user_can('manage_options')) {
            return;
        }
        if (empty($_REQUEST['pmpro_stripecheckout_secretkey']) || empty($_REQUEST['pmpro_stripecheckout_publishablekey'])) {
            if (pmpro_getOption("stripecheckout_secretkey") === null || pmpro_getOption("stripecheckout_publishablekey") === null) {
                global $pmpro_stripe_error;
                $pmpro_stripe_error = sprintf(
                    /* translators: %s Error Message */
                    __('<strong>Error:</strong> PMPro could not connect to the Stripe API. Publishable key and secret key need to be uploaded ', 'paid-memberships-pro'),
                );
            }
            return;
            delete_option('pmpro_stripecheckout_secretkey');
            delete_option('pmpro_stripecheckout_publishablekey');
        }

        // Change current gateway to Stripe
        pmpro_setOption('gateway', 'stripecheckout');

        pmpro_setOption('stripecheckout_secretkey', $_REQUEST['pmpro_stripecheckout_secretkey']);

        pmpro_setOption('stripecheckout_publishablekey', $_REQUEST['pmpro_stripecheckout_publishablekey']);

        wp_redirect(admin_url('admin.php?page=pmpro-paymentsettings'));
        exit;
    }
}
