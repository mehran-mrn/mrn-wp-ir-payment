<?php
/**
 * WooCommerce Checkout Blocks registration.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use MRN\IranPayment\Core\SecretVault;
use MRN\IranPayment\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class BlocksSupport extends AbstractPaymentMethodType {
	protected $name = 'mrn_ir_payment';
	private $store;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_mrn_ir_payment_settings', array() );
		$this->store    = new Settings( new SecretVault() );
	}

	public function is_active() {
		return 'yes' === ( isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes' )
			&& ! empty( $this->store->enabled_providers() );
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'mrn-ir-payment-blocks',
			MRN_IR_PAYMENT_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			MRN_IR_PAYMENT_VERSION,
			true
		);
		return array( 'mrn-ir-payment-blocks' );
	}

	public function get_payment_method_data() {
		$general   = $this->store->general();
		$providers = array();
		foreach ( $this->store->enabled_providers() as $slug => $provider ) {
			$providers[] = array(
				'slug'        => $slug,
				'name'        => ! empty( $provider['config']['title'] ) ? $provider['config']['title'] : $provider['name'],
				'mode'        => $provider['mode'],
				'description' => $provider['description'],
				'accent'      => $provider['accent'],
			);
		}
		return array(
			'title'       => $general['title'],
			'description' => $general['description'],
			'providers'   => $providers,
			'supports'    => array( 'products' ),
		);
	}

	public static function hydrate_provider( $context ) {
		if ( empty( $context->payment_method ) || 'mrn_ir_payment' !== $context->payment_method ) {
			return;
		}
		$data = isset( $context->payment_data ) && is_array( $context->payment_data ) ? $context->payment_data : array();
		if ( isset( $data['mrn_ir_provider'] ) && WC()->session ) {
			WC()->session->set( 'mrn_ir_provider', sanitize_key( $data['mrn_ir_provider'] ) );
			return;
		}
		foreach ( $data as $item ) {
			if ( isset( $item['key'], $item['value'] ) && 'mrn_ir_provider' === $item['key'] && WC()->session ) {
				WC()->session->set( 'mrn_ir_provider', sanitize_key( $item['value'] ) );
			}
		}
	}
}
