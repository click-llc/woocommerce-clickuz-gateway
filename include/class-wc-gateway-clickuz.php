<?php
/**
 * Click.uz Payment Gateway.
 *
 * Provides a Click.uz Payment Gateway.
 *
 * @class        WC_Gateway_Clickuz
 * @extends        WC_Payment_Gateway
 * @version        1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Clickuz Class.
 */
class WC_Gateway_Clickuz extends WC_Payment_Gateway {

    /** @var bool Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'clickuz';
        $this->order_button_text  = __( 'Pay', 'clickuz' );
        $this->method_title       = 'CLICK';
        $this->method_description = __( 'Proceed payment with CLICK', 'clickuz' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title       = 'CLICK';
        $this->description = __( 'Pay with CLICK', 'clickuz' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options') );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'form' ) );
    }

    /**
     * Get gateway icon.
     *
     * @return string
     */
    public function get_icon() {
        $icon_html = '<img src="' . CLICK_LOGO . '" alt="CLICK" />';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log( $message, $level = 'info' ) {
        if ( self::$log_enabled ) {
            if ( empty( self::$log ) ) {
                self::$log = wc_get_logger();
            }
            self::$log->log( $level, $message, array( 'source' => 'paypal' ) );
        }
    }


    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = include( 'settings.php' );
    }

    /**
     * Process the payment and return the result.
     *
     * @param  int $order_id
     *
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = new WC_Order( $order_id );

        $order_number      = $order->get_order_number();
        $query_args = array(
            'merchant_id' => defined('CLICK_MERCHANT_ID') && ! empty(CLICK_MERCHANT_ID) ? CLICK_MERCHANT_ID : $this->get_option( 'merchant_id' ),
            'merchant_user_id' => defined('CLICK_MERCHANT_USER_ID') && ! empty(CLICK_MERCHANT_USER_ID) ? CLICK_MERCHANT_USER_ID :$this->get_option( 'merchant_user_id' ),
            'service_id' => defined('CLICK_SERVICE_ID') && ! empty(CLICK_SERVICE_ID) ? CLICK_SERVICE_ID :$this->get_option( 'merchant_service_id' ),
            'transaction_param' =>  $order_id != $order_number ? $order_number . CLICK_DELIMITER . $order_id : $order_id,
            'amount' =>  number_format( $order->get_total(), 0, '.', '' ),
            'return_url' =>  apply_filters( 'click_return_url', add_query_arg( array( 'click-return' => WC()->customer->get_id() ), $order->get_view_order_url() ) )
        );;

        return array(
            'result'   => 'success',
            'redirect' => add_query_arg(
                'order_pay',
                $order->get_id(),
                add_query_arg( $query_args, 'https://my.click.uz/services/pay' )
            )
        );
    }

    /**
     * @param  WC_Order $order
     *
     * @return bool
     */
    public function can_refund_order( $order ) {
        return $order && $order->get_transaction_id();
    }

    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
     *
     * @return bool was anything saved?
     */
    public function process_admin_options() {
        return parent::process_admin_options();
    }

    public function form( $order_id ) {

        $order = wc_get_order( $order_id );

        $order_number      = $order->get_order_number();
        $merchantID        = defined('CLICK_MERHCANT_ID') ? CLICK_MERCHANT_ID : $this->get_option( 'merchant_id' );
        $merchantUserID    = defined('CLICK_MERHCANT_USER_ID') ? CLICK_MERCHANT_USER_ID :$this->get_option( 'merchant_user_id' );
        $merchantServiceID = defined('CLICK_SERVICE_ID') ? CLICK_SERVICE_ID :$this->get_option( 'merchant_service_id' );
        $transID           = $order_id != $order_number ? $order_number . CLICK_DELIMITER . $order_id : $order_id;
        $transAmount       = number_format( $order->get_total(), 0, '.', '' );
        $returnURL         = apply_filters( 'click_return_url', add_query_arg( array( 'click-return' => WC()->customer->get_id() ), $order->get_view_order_url() ) );

        $button_title = $this->get_option( 'click_button_title' );

        if ( $this->get_option( 'click_button_type' ) == 'redirect' ) :

            ?>
            <form action="https://my.click.uz/services/pay" id="click-pay-form" method="get">
                <input type="hidden" name="amount" value="<?php echo $transAmount; ?>" />
                <input type="hidden" name="merchant_id" value="<?php echo $merchantID; ?>"/>
                <input type="hidden" name="merchant_user_id" value="<?php echo $merchantUserID; ?>"/>
                <input type="hidden" name="service_id" value="<?php echo $merchantServiceID; ?>"/>
                <input type="hidden" name="transaction_param" value="<?php echo $transID; ?>"/>
                <input type="hidden" name="return_url" value="<?php echo $returnURL; ?>"/>

                <button id="click-pay-button"><i></i><?php echo $button_title; ?></button>
            </form>

        <?php else: ?>
            <button id="click-pay-button"><i></i><?php echo $button_title; ?></button>

            <script src="//my.click.uz/pay/checkout.js"></script>
            <script>
                window.onload = function () {
                    var linkEl = document.querySelector("#click-pay-button");
                    linkEl.addEventListener("click", function () {
                        createPaymentRequest({
                            merchant_id: <?php echo $merchantID; ?>,
                            merchant_user_id: "<?php echo $merchantUserID; ?>",
                            service_id: <?php echo $merchantServiceID; ?>,
                            transaction_param: "<?php echo $transID; ?>",
                            amount: <?php echo $transAmount; ?>
                        }, function (data) {
                            if (data && (data.status === 2 || data.status === 0)) {

                                window.location.href = '<?php echo $returnURL; ?>';
                            }
                        });
                    });
                };
            </script>

            <?php
        endif;

        ?>

        <style>
            #click-pay-button {
                width: auto;
                border: 0;
                border-radius: 4px;
                background: #00a6ff;
                margin: 10px 0 0;
                padding: 0 15px;
                height: 49px;
                font: 17px/49px Microsoft Sans Serif, Arial, Helvetica, sans-serif;
                color: #fff;
            }

            #click-pay-button i {
                background: url(https://m.click.uz/static/img/logo.png) no-repeat center left;
                width: 30px;
                height: 49px;
                float: left;
            }

        </style>

        <?php
    }
}
