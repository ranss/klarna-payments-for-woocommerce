<?php
/**
 * Create invoice request class
 *
 * @package Payer_B2B/Classes/Put/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create invoice request class
 */
class KP_Create_Session extends KP_Requests {
	/**
	 * Makes the request.
	 *
	 * @return array
	 */
	public function request() {
		$request_url  = $this->environment . 'payments/v1/sessions';
		$request_args = apply_filters( 'payer_create_invoice_args', $this->get_request_args( $this->order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		$formated_response = $this->process_response( $response, $request_args, $request_url );

		return array();
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) ),
				'Content-Type'  => 'application/json',
			),
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - KP:' . WC_KLARNA_PAYMENTS_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil',
			'body'       => $this->get_request_body( $order_id ),
		);
	}

	/**
	 * Gets the request body for the API call.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return string
	 */
	public function get_request_body( $order_id ) {
		return wp_json_encode(
			array(
				'purchase_country'  => $this->klarna_country,
				'purchase_currency' => get_woocommerce_currency(),
				'locale'            => get_locale_for_klarna_country(),
				'order_amount'      => '',
				'order_tax_amount'  => '',
				'order_lines'       => '',
				'customer'          => get_klarna_customer( $this->kp_settings['customer_type'] ),
			)
		);
	}
}
