<?php

/**
 * Created by PhpStorm.
 * User: User
 * Date: 28.05.2018
 * Time: 16:45
 */
class WC_ClickAPI {

	private $secret;

	private $after_payment_status = 'processing';

	private $table;

	function __construct( ) {
		global $wpdb;

		$this->table = $wpdb->prefix . 'wc_click_transactions';

		// Add query vars.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Register API endpoints.
		add_action( 'init', array( $this, 'add_endpoint' ), 0 );

		add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );

	}

	public function add_query_vars( $vars ) {
		$vars[] = 'click-api';

		return $vars;
	}

	public function add_endpoint() {
		add_rewrite_endpoint( 'click-api', EP_ALL );
	}

	public function handle_api_requests() {
		global $wp;

		$gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $gateways['clickuz'] ) ) {
			return;
		}

		if ( ! empty( $_GET['click-api'] ) ) { // WPCS: input var okay, CSRF ok.
			$wp->query_vars['click-api'] = sanitize_key( wp_unslash( $_GET['click-api'] ) ); // WPCS: input var okay, CSRF ok.
		}

		if ( ! empty( $wp->query_vars['click-api'] ) ) {

			// Clean the API request.
			$api_request = strtolower( wc_clean( $wp->query_vars['click-api'] ) );

            $this->secret = defined('CLICK_SECRET_KEY') ? CLICK_SECRET_KEY : $gateways['clickuz']->get_option( 'secret_key' );

            $this->after_payment_status = $gateways['clickuz']->get_option( 'after_payment_status' );

			$response = array();

			switch ( $api_request ) {
				case 'prepare':
					$response = $this->prepare();
					break;

				case 'complete':
					$response = $this->complete();
					break;
			}
			wp_send_json( $response );
		}
	}

	function get_order( $merchant_trans_id ) {
        $order_id = $merchant_trans_id;

        if( strpos($merchant_trans_id, CLICK_DELIMITER) !== FALSE ) {
            $parts = explode(CLICK_DELIMITER, $merchant_trans_id);
            $order_id = $parts[1];
        }

        return wc_get_order($order_id);
    }

	function prepare() {
		global $wpdb;

		if( ! isset(
			$_POST['click_trans_id'],
			$_POST['service_id'],
			$_POST['merchant_trans_id'],
			$_POST['amount'],
			$_POST['action'],
			$_POST['sign_time']) ) {
			return array(
				'error'      => '-8',
				'error_note' => __( 'Error in request from click', 'clickuz' )
			);
		}


		$signString = $_POST['click_trans_id'] .
		              $_POST['service_id'] .
		              $this->secret .
		              $_POST['merchant_trans_id'] .
		              $_POST['amount'] .
		              $_POST['action'] .
		              $_POST['sign_time'];

		$signString = md5( $signString );



		if ( $signString !== $_POST['sign_string'] ) {
			return array(
				'error'      => '-1',
				'error_note' => __( 'Sign check error', 'clickuz' )
			);
		}

		$order = $this->get_order($_POST['merchant_trans_id']);

		if( ! $order ) {
			return array(
				'error'      => '-5',
				'error_note' => __( 'User does not exist', 'clickuz' )
			);
		}

		if( $order->is_paid() ) {
			return array(
				'error'      => '-4',
				'error_note' => __( 'Already paid', 'clickuz' )
			);
		}

		if( abs($order->get_total() - (float)$_POST['amount']) > 0.01 ) {
			return array(
				'error'      => '-2',
				'error_note' => __( 'Incorrect parameter amount', 'clickuz' )
			);
		}

		try {

			$prepare_id = $wpdb->get_var("Select ID From {$this->table} Where merchant_trans_id = " . $order->get_id() );

			if( ! $prepare_id ) {
				$wpdb->insert( $this->table, array(
					'click_trans_id'    => $_POST['click_trans_id'],
					'service_id'        => $_POST['service_id'],
					'click_paydoc_id'   => $_POST['click_paydoc_id'],
					'merchant_trans_id' => $order->get_id(),
					'amount'            => $_POST['amount'],
					'error'             => $_POST['error'],
					'error_note'        => $_POST['error_note'],
					'status'            => 'prepare'
				) );

				$prepare_id = $wpdb->insert_id;
			} else {
				$wpdb->update( $this->table, array(
					'click_trans_id'    => $_POST['click_trans_id'],
					'service_id'        => $_POST['service_id'],
					'click_paydoc_id'   => $_POST['click_paydoc_id'],
					'merchant_trans_id' => $order->get_id(),
					'amount'            => $_POST['amount'],
					'error'             => $_POST['error'],
					'error_note'        => $_POST['error_note'],
					'status'            => 'prepare'
				), array('ID' => $prepare_id ) );
			}

			$order->set_transaction_id($_POST['click_trans_id']);

			$order->set_status('on-hold', __('Click Prepare requested, reserving products.', 'clickuz') );

			return array(
				'click_trans_id' => $_POST['click_trans_id'],
				'merchant_trans_id' => $_POST['merchant_trans_id'],
				'merchant_prepare_id' => $prepare_id,
				'error'      => '0',
				'error_note' => __( 'Success', 'clickuz' )
			);

		} catch (Exception $ex) {
			return array(
				'error'      => '-7',
				'error_note' => __( 'Failed to update user', 'clickuz')
			);
		}
	}

	function complete() {
		global $wpdb;

        if( ! isset(
            $_POST['click_trans_id'],
            $_POST['service_id'],
            $_POST['merchant_trans_id'],
            $_POST['merchant_prepare_id'],
            $_POST['amount'],
            $_POST['action'],
            $_POST['sign_time']) ) {
            return array(
                'error'      => '-8',
                'error_note' => __( 'Error in request from click', 'clickuz' )
            );
        }

		$signString = $_POST['click_trans_id'] .
		              $_POST['service_id'] .
		              $this->secret .
		              $_POST['merchant_trans_id'] .
		              $_POST['merchant_prepare_id'] .
		              $_POST['amount'] .
		              $_POST['action'] .
		              $_POST['sign_time'];

		$signString = md5( $signString );

		if ( $signString !== $_POST['sign_string'] ) {
			return array(
				'error'      => '-1',
				'error_note' => __( 'Sign check error', 'clickuz' )
			);
		}

		$order = $this->get_order($_POST['merchant_trans_id']);

		if( ! $order ) {
			return array(
				'error'      => '-5',
				'error_note' => __( 'User does not exist', 'clickuz' )
			);
		}

		if( ! $wpdb->get_var("Select count(ID) from {$wpdb->prefix}wc_click_transactions Where ID = " . $_POST['merchant_prepare_id']) ) {
			return array(
				'error'      => '-6',
				'error_note' => __( 'Transaction does not exist', 'clickuz' )
			);
		}

		if( abs($order->get_total() - (float)$_POST['amount']) > 0.01 ) {
			return array(
				'error'      => '-2',
				'error_note' => __( 'Incorrect parameter amount', 'clickuz' )
			);
		}

		if( $order->has_status('failed') ) {
			return array(
				'error'      => '-9',
				'error_note' => __( 'Transaction cancelled', 'clickuz' )
			);
		}

        if( $order->is_paid() ) {
            return array(
                'error'      => '-4',
                'error_note' => __( 'Already paid', 'clickuz' )
            );
        }

		if( $_POST['error'] < 0 ) {

			$wpdb->update( $this->table, array(
				'click_trans_id'    => $_POST['click_trans_id'],
				'service_id'        => $_POST['service_id'],
				'click_paydoc_id'   => $_POST['click_paydoc_id'],
				'merchant_trans_id' => $order->get_id(),
				'amount'            => $_POST['amount'],
				'error'             => $_POST['error'],
				'error_note'        => $_POST['error_note'],
				'status'            => '',
			), array( 'ID' => $_POST['merchant_prepare_id'] ) );

			$order->set_transaction_id('');

			$order->update_status( 'failed', $_POST['error_note']);

			return array(
				'click_trans_id' => $_POST['click_trans_id'],
				'merchant_trans_id' => $_POST['merchant_trans_id'],
				'merchant_confirm_id' => $_POST['merchant_prepare_id'],
				'error'      => '-9',
				'error_note' => __( 'Transaction cancelled', 'clickuz' )
			);

		}

		try {
			$wpdb->update( $this->table, array(
				'click_trans_id'    => $_POST['click_trans_id'],
				'service_id'        => $_POST['service_id'],
				'click_paydoc_id'   => $_POST['click_paydoc_id'],
				'merchant_trans_id' => $order->get_id(),
				'amount'            => $_POST['amount'],
				'error'             => $_POST['error'],
				'error_note'        => $_POST['error_note'],
				'status'            => 'complete',
			), array( 'ID' => $_POST['merchant_prepare_id'] ) );

			$order->set_transaction_id($_POST['click_trans_id']);

			$order->payment_complete( $_POST['click_trans_id'] );

			if( ! $order->has_status( $this->after_payment_status ) ) {
                $order->update_status( $this->after_payment_status );
                $order->save();
            }

			return array(
				'click_trans_id' => $_POST['click_trans_id'],
				'merchant_trans_id' => $_POST['merchant_trans_id'],
				'merchant_confirm_id' => $_POST['merchant_prepare_id'],
				'error'      => '0',
				'error_note' => __( 'Success', 'clickuz' )
			);

		} catch (Exception $ex) {
			return array(
				'error'      => '-7',
				'error_note' => __( 'Failed to update user', 'clickuz')
			);
		}
	}
}