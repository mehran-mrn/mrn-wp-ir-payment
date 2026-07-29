<?php
/**
 * Plugin composition root.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Core;

use MRN\IranPayment\Admin\Admin;
use MRN\IranPayment\Infrastructure\Repository;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;
	private $settings;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		load_plugin_textdomain( 'mrn-ir-payment', false, dirname( plugin_basename( MRN_IR_PAYMENT_FILE ) ) . '/languages' );
		Installer::maybe_upgrade();
		$this->settings = new Settings( new SecretVault() );

		if ( is_admin() ) {
			( new Admin( $this->settings, new Repository() ) )->register();
		}

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_assets' ) );
		add_action( 'mrn_ir_payment_daily_cleanup', array( $this, 'cleanup' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MRN_IR_PAYMENT_FILE ), array( $this, 'action_links' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
		}
	}

	public function register_gateway( array $gateways ) {
		$gateways[] = 'MRN\\IranPayment\\WooCommerce\\Gateway';
		return $gateways;
	}

	public function register_blocks() {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once MRN_IR_PAYMENT_DIR . 'src/WooCommerce/BlocksSupport.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new \MRN\IranPayment\WooCommerce\BlocksSupport() );
			}
		);
		add_action(
			'woocommerce_rest_checkout_process_payment_with_context',
			array( 'MRN\\IranPayment\\WooCommerce\\BlocksSupport', 'hydrate_provider' ),
			10,
			1
		);
	}

	public function checkout_assets() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			wp_enqueue_style(
				'mrn-ir-payment-checkout',
				MRN_IR_PAYMENT_URL . 'assets/css/checkout.css',
				array(),
				MRN_IR_PAYMENT_VERSION
			);
		}
	}

	public function cleanup() {
		$general = $this->settings->general();
		( new Repository() )->cleanup( $general['retention_days'] );
	}

	public function action_links( array $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mrn-ir-payment' ) ) . '">مرکز پرداخت</a>' );
		return $links;
	}

	public function woocommerce_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p><strong>MRN پرداخت ایران:</strong> برای پردازش سفارش‌ها، ووکامرس را نصب و فعال کنید.</p></div>';
		}
	}
}
