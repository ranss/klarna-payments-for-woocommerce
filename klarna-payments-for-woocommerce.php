<?php // phpcs:ignore
/**
 * Plugin Name: Klarna Payments for WooCommerce
 * Plugin URI: https://krokedil.com/klarna-payments/
 * Description: Provides Klarna Payments as payment method to WooCommerce.
 * Author: krokedil, klarna, automattic
 * Author URI: https://krokedil.com/
 * Version: 2.0.1
 * Text Domain: klarna-payments-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 3.3.0
 * WC tested up to: 3.7.0
 *
 * Copyright (c) 2017-2019 Krokedil
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package WC_Klarna_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_KLARNA_PAYMENTS_VERSION', '2.0.1' );
define( 'WC_KLARNA_PAYMENTS_MIN_PHP_VER', '5.6.0' );
define( 'WC_KLARNA_PAYMENTS_MIN_WC_VER', '3.3.0' );
define( 'WC_KLARNA_PAYMENTS_MAIN_FILE', __FILE__ );
define( 'WC_KLARNA_PAYMENTS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_KLARNA_PAYMENTS_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! class_exists( 'WC_Klarna_Payments' ) ) {

	/**
	 * Class WC_Klarna_Payments
	 */
	class WC_Klarna_Payments {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
		}

		/**
		 * Notices (array)
		 *
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_action( 'admin_notices', array( $this, 'order_management_check' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_filter( 'woocommerce_checkout_posted_data', array( $this, 'filter_payment_method_id' ) );
			add_filter( 'woocommerce_process_checkout_field_billing_phone', array( $this, 'maybe_filter_billing_phone' ) );

			// Load text domain.
			load_plugin_textdomain( 'klarna-payments-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Init the gateway itself.
			$this->include_files();
		}

		/**
		 * Changes payment_method in posted data to klarna_payments when one of KP methods is selected.
		 *
		 * @param array $data Posted data.
		 *
		 * @return mixed
		 */
		public function filter_payment_method_id( $data ) {
			if ( strpos( $data['payment_method'], 'klarna_payments_' ) !== false ) {
				$data['payment_method'] = 'klarna_payments';
			}

			return $data;
		}

		/**
		 * Show admin notice if Order Management plugin is not active.
		 */
		public function order_management_check() {
			if ( ! class_exists( 'WC_Klarna_Order_Management' ) ) {
				$current_screen = get_current_screen();
				if ( 'shop_order' === $current_screen->id || 'plugins' === $current_screen->id || 'woocommerce_page_wc-settings' === $current_screen->id ) {
					?>
					<div class="notice notice-warning">
						<p><?php echo esc_html( 'Klarna Order Management is not active. Please activate it so you can capture, cancel, update and refund Klarna orders.', 'woocommerce-klarna-payments' ); ?></p>
					</div>
					<?php
				}
			}
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'klarna-payments-for-woocommerce' ) . '</a>',
				'<a href="https://docs.woocommerce.com/document/klarna-payments/">' . __( 'Docs', 'klarna-payments-for-woocommerce' ) . '</a>',
				'<a href="https://krokedil.com/support/">' . __( 'Support', 'klarna-payments-for-woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'klarna_payments' : strtolower( 'WC_Gateway_Klarna_Payments' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Includes the files needed for the plugin
		 *
		 * @since 2.0.0
		 */
		public function include_files() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Classes.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-wc-gateway-klarna-payments.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-gdpr.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-ajax.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-logger.php';

			// Requests.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/class-kp-requests.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-create-session.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-update-session.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-place-order.php';

			// Request helpers.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/helpers/class-kp-order-lines.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/helpers/class-kp-customer-data.php';

			// Includes.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/kp-functions.php';

			if ( is_admin() ) {
				include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/admin/class-klarna-for-woocommerce-addons.php';
				include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-banners.php';
			}
		}

		/**
		 * Maybe filter posted billing phone number.
		 *
		 * Has to be done here, because we can't hook into this filter from gateway class.
		 * Klarna Payments phone validation is not the same as WooCommerce phone validation, so in case Klarna Payments
		 * says a phone is OK that would not be validated by WooCommerce we need to filter it here.
		 *
		 * @param string $phone_value Billing phone value.
		 *
		 * @return mixed
		 */
		public function maybe_filter_billing_phone( $phone_value ) {
			// Get rid of everything that's not what WC_Validation::is_phone requires.
			if ( 'klarna_payments' === $_POST['payment_method'] ) { // phpcs:ignore
				if ( trim( preg_replace( '/[^\s\#0-9_\-\+\/\(\)]/', '', $phone_value ) ) !== $phone_value ) {
					$phone_value = trim( preg_replace( '/[^\s\#0-9_\-\+\/\(\)]/', '', $phone_value ) );
				}
			}

			return $phone_value;
		}
	}
	WC_Klarna_Payments::get_instance();

	/**
	 * Main instance WC_Klarna_Payments.
	 *
	 * Returns the main instance of WC_Klarna_Payments.
	 *
	 * @return WC_Klarna_Payments
	 */
	function KP_WC() { // phpcs:ignore
		return WC_Klarna_Payments::get_instance();
	}
}
