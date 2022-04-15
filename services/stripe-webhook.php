<?php
if (version_compare(PHP_VERSION, '5.3.29', '<')) {
    return;
}

// For compatibility with old library (Namespace Alias)
use Stripe\Invoice as Stripe_Invoice;
use Stripe\Event as Stripe_Event;
use Stripe\PaymentIntent as Stripe_PaymentIntent;
use Stripe\Charge as Stripe_Charge;

global $isapage;
$isapage = true;

global $logstr;
$logstr = "";

// Sets the PMPRO_DOING_WEBHOOK constant and fires the pmpro_doing_webhook action.
pmpro_doing_webhook('stripe', true);

//you can define a different # of seconds (define PMPRO_STRIPE_WEBHOOK_DELAY in your wp-config.php) if you need this webhook to delay more or less
if (!defined('PMPRO_STRIPE_WEBHOOK_DELAY'))
    define('PMPRO_STRIPE_WEBHOOK_DELAY', 2);

//in case the file is loaded directly
if (!defined("ABSPATH")) {
    define('WP_USE_THEMES', false);
    require_once(dirname(__FILE__) . '/../../../../wp-load.php');
}

if (!class_exists("Stripe\Stripe")) {
    require_once(PMPRO_DIR . "/includes/lib/Stripe/init.php");
}

// retrieve the request's body and parse it as JSON
if (empty($_REQUEST['event_id'])) {
    $body = @file_get_contents('php://input');
    $post_event = json_decode($body);

    //get the id
    if (!empty($post_event)) {
        $event_id = sanitize_text_field($post_event->id);
        $livemode = !empty($post_event->livemode);
    }
} else {

    $event_id = sanitize_text_field($_REQUEST['event_id']);
    $livemode = pmpro_getOption('gateway_environment') === 'live'; // User is testing, so use current environment.
}


if ($gateway === "stripe") {
    try {
        $secret_key = pmpro_getOption("stripe_secretkey");
        if (PMProGateway_stripe::using_legacy_keys()) {
            $secret_key = pmpro_getOption("stripe_secretkey");
        } elseif ($livemode) {
            $secret_key = pmpro_getOption('live_stripe_connect_secretkey');
        } else {
            $secret_key = pmpro_getOption('sandbox_stripe_connect_secretkey');
        }
        Stripe\Stripe::setApiKey($secret_key);
    } catch (Exception $e) {
        $logstr .= "Unable to set API key for Stripe gateway: " . $e->getMessage();
        pmpro_stripeWebhookExit();
    }
} else if ($gateway === "stripecheckout") {
    try {
        $secret_key = pmpro_getOption("stripecheckout_secretkey");
        Stripe\Stripe::setApiKey($secret_key);
    } catch (Exception $e) {
        $logstr .= "Unable to set API key for Stripe gateway: " . $e->getMessage();
        pmpro_stripeWebhookExit();
    }
}



//get the event through the API now
if (!empty($event_id)) {
    try {
        global $pmpro_stripe_event;
        $pmpro_stripe_event = Stripe_Event::retrieve($event_id);
    } catch (Exception $e) {
        $logstr .= "Could not find an event with ID #" . $event_id . ". " . $e->getMessage();
        // pmpro_stripeWebhookExit();
        $pmpro_stripe_event = $post_event;            //for testing you may want to assume that the passed in event is legit
    }
}

global $wpdb;

//real event?
if (!empty($pmpro_stripe_event->id)) {
    // Send a 200 HTTP response to Stripe to avoid timeout.
    pmpro_send_200_http_response();


    // Log that we have successfully received a webhook from Stripe.
    update_option('pmpro_stripe_last_webhook_received_' . ($livemode ? 'live' : 'sandbox'), date('Y-m-d H:i:s'));
    //check what kind of event it is
    if ($pmpro_stripe_event->type == "payment_intent.succeeded") {
        // do we have this order yet? (check status too)
        $order = getOrderFromInvoiceEvent($pmpro_stripe_event);
        //no? create it
        if ($order->status == 'review') {
            $morder = new MemberOrder($order->id);
            if ($pmpro_stripe_event->data->object->status == 'succeeded') {
                $morder->status = 'success';
                $morder->notes = "webhook";
                $morder->saveOrder();
                pmpro_changeMembershipLevel($order->membership_id, $order->user_id);
                $logstr .= "Order #" . $order->id . " is now set to success.";
            }
            pmpro_stripeWebhookExit();
        } else {
            $logstr .= "We've already processed this order with ID #" . $order->id . ". Event ID #" . $pmpro_stripe_event->id . ".";
            pmpro_stripeWebhookExit();
        }
    }
} else {
    if (!empty($event_id))
        $logstr .= "Could not find an event with ID #" . $event_id;
    else
        $logstr .= "No event ID given.";

    pmpro_unhandled_webhook();
    pmpro_stripeWebhookExit();
}

/**
 * @deprecated 2.7.0.
 */
function getUserFromInvoiceEvent($pmpro_stripe_event)
{
    _deprecated_function(__FUNCTION__, '2.7.0');
    //pause here to give PMPro a chance to finish checkout
    sleep(PMPRO_STRIPE_WEBHOOK_DELAY);

    global $wpdb;

    $customer_id = $pmpro_stripe_event->data->object->customer;

    //look up the order
    $user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->pmpro_membership_orders WHERE subscription_transaction_id = '" . esc_sql($customer_id) . "' LIMIT 1");

    if (!empty($user_id))
        return get_userdata($user_id);
    else
        return false;
}

/**
 * @deprecated 2.7.0.
 */
function getUserFromCustomerEvent($pmpro_stripe_event, $status = false, $checkplan = true)
{
    _deprecated_function(__FUNCTION__, '2.7.0');

    //pause here to give PMPro a chance to finish checkout
    sleep(PMPRO_STRIPE_WEBHOOK_DELAY);

    global $wpdb;

    $customer_id = $pmpro_stripe_event->data->object->customer;
    $subscription_id = $pmpro_stripe_event->data->object->id;
    $plan_id = $pmpro_stripe_event->data->object->plan->id;

    //look up the order
    $sqlQuery = "SELECT user_id FROM $wpdb->pmpro_membership_orders WHERE (subscription_transaction_id = '" . esc_sql($customer_id) . "' OR subscription_transaction_id = '"  . esc_sql($subscription_id) . "') ";
    if ($status)
        $sqlQuery .= " AND status='" . esc_sql($status) . "' ";
    if ($checkplan)
        $sqlQuery .= " AND code='" . esc_sql($plan_id) . "' ";
    $sqlQuery .= " LIMIT 1";

    $user_id = $wpdb->get_var($sqlQuery);

    if (!empty($user_id))
        return get_userdata($user_id);
    else
        return false;
}

// TODO Test this
// TODO docblock
function getOldOrderFromInvoiceEvent($pmpro_stripe_event)
{
    //pause here to give PMPro a chance to finish checkout
    sleep(PMPRO_STRIPE_WEBHOOK_DELAY);

    global $wpdb;

    if (!empty($pmpro_stripe_event->data->object->subscription)) {
        $subscription_id = $pmpro_stripe_event->data->object->subscription;
    } else {
        $subscription_id = $pmpro_stripe_event->data->object->id;
    }

    // Try to get the order ID from the subscription ID in the event.
    $old_order_id = $wpdb->get_var(
        $wpdb->prepare(
            "
					SELECT id
					FROM $wpdb->pmpro_membership_orders
					WHERE
						subscription_transaction_id = %s
						AND gateway = 'stripe'
					ORDER BY timestamp DESC
					LIMIT 1
				",
            $subscription_id
        )
    );

    if (empty($old_order_id)) {
        // Try to get the order ID from the invoice ID in the event.
        $invoice_id = $pmpro_stripe_event->data->object->invoice;

        try {

            $invoice = Stripe_Invoice::retrieve($invoice_id);
        } catch (Exception $e) {
            error_log("Unable to fetch Stripe Invoice object: " . $e->getMessage());
            $invoice = null;
        }

        if (isset($invoice->subscription)) {
            $subscription_id = $invoice->subscription;
            $old_order_id    = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE (subscription_transaction_id = '" . $subscription_id . "' OR subscription_transaction_id = '"  . esc_sql($subscription_id) . "') AND gateway = 'stripe' ORDER BY timestamp DESC LIMIT 1");
        }
    }

    // If we have an ID, get the associated MemberOrder.
    if (!empty($old_order_id)) {

        $old_order = new MemberOrder($old_order_id);

        if (isset($old_order->id) && !empty($old_order->id))
            return $old_order;
    }

    return false;
}

function getOrderFromInvoiceEvent($pmpro_stripe_event)
{
    //pause here to give PMPro a chance to finish checkout
    sleep(PMPRO_STRIPE_WEBHOOK_DELAY);

    $payment_id = $pmpro_stripe_event->data->object->id;

    $order = new MemberOrder();
    $order = $order->getMemberOrderByPaymentTransactionID($payment_id);

    if (!empty($order->id))
        return $order;
    else
        return false;
}

function pmpro_stripeWebhookExit()
{
    global $logstr;

    //for log
    if ($logstr) {
        $logstr = "Logged On: " . date_i18n("m/d/Y H:i:s") . "\n" . $logstr . "\n-------------\n";

        echo esc_html($logstr);

        //log in file or email?
        if (true) {
            //file
            $loghandle = fopen(dirname(__FILE__) . "/../logs/stripe-webhook.txt", "a+");
            fwrite($loghandle, $logstr);
            fclose($loghandle);
        } elseif (defined('PMPRO_STRIPE_WEBHOOK_DEBUG') && false !== PMPRO_STRIPE_WEBHOOK_DEBUG) {
            //email
            if (strpos(PMPRO_STRIPE_WEBHOOK_DEBUG, "@"))
                $log_email = PMPRO_STRIPE_WEBHOOK_DEBUG;    //constant defines a specific email address
            else
                $log_email = get_option("admin_email");

            wp_mail($log_email, get_option("blogname") . " Stripe Webhook Log", nl2br(esc_html($logstr)));
        }
    }

    exit;
}
