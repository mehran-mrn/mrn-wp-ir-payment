<?php
/**
 * Plugin Name:       MRN پرداخت ایران
 * Plugin URI:        https://github.com/mehran-mrn/mrn-wp-ir-payment
 * Description:       زیرساخت حرفه‌ای پرداخت آنلاین و اقساطی ایران برای ووکامرس، با مسیریابی هوشمند و گزارش تراکنش.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   10.1
 * Author:            MRN
 * Author URI:        https://github.com/mehran-mrn
 * Text Domain:       mrn-ir-payment
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package MRN\IranPayment
 */

defined( 'ABSPATH' ) || exit;

define( 'MRN_IR_PAYMENT_VERSION', '1.1.0' );
define( 'MRN_IR_PAYMENT_FILE', __FILE__ );
define( 'MRN_IR_PAYMENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MRN_IR_PAYMENT_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'MRN\\IranPayment\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = MRN_IR_PAYMENT_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'MRN\\IranPayment\\Core\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MRN\\IranPayment\\Core\\Installer', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\MRN\IranPayment\Core\Plugin::instance()->boot();
	},
	20
);
