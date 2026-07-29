<?php
/**
 * Provider catalog and factory.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Gateways;

use MRN\IranPayment\Core\Settings;
use MRN\IranPayment\Infrastructure\Logger;

defined( 'ABSPATH' ) || exit;

final class Registry {
	public static function definitions() {
		return apply_filters( 'mrn_ir_payment_provider_definitions', ProviderCatalog::definitions() );
	}

	public static function definition( $slug ) {
		$definitions = self::definitions();
		return isset( $definitions[ $slug ] ) ? $definitions[ $slug ] : array();
	}

	public static function is_configured( $slug, array $config ) {
		$definition = self::definition( $slug );
		if ( empty( $definition ) ) {
			return false;
		}
		foreach ( $definition['fields'] as $key => $field ) {
			if ( ! empty( $field['required'] ) && '' === trim( (string) ( isset( $config[ $key ] ) ? $config[ $key ] : '' ) ) ) {
				return false;
			}
		}
		return true;
	}

	public static function build( $slug, Settings $settings, Logger $logger ) {
		$config     = $settings->provider( $slug );
		$definition = self::definition( $slug );
		$object     = 'native' !== ( isset( $definition['implementation'] ) ? $definition['implementation'] : 'contract' )
			? new CustomRestGateway( $slug, $config, $logger )
			: new StandardGateway( $slug, $config, $logger );
		return apply_filters( 'mrn_ir_payment_gateway_instance', $object, $slug, $config, $logger );
	}
}
