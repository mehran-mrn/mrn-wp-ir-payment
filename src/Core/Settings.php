<?php
/**
 * Plugin settings repository.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Core;

use MRN\IranPayment\Gateways\Registry;

defined( 'ABSPATH' ) || exit;

final class Settings {
	const OPTION = 'mrn_ir_payment_settings';

	private $vault;

	public function __construct( SecretVault $vault ) {
		$this->vault = $vault;
	}

	public function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'title'            => 'پرداخت امن آنلاین یا اقساطی',
				'description'      => 'روش پرداخت دلخواه خود را انتخاب کنید.',
				'debug'            => false,
				'retention_days'   => 180,
				'default_provider' => '',
				'provider_order'   => array(),
				'providers'        => array(),
			)
		);
	}

	public function general() {
		$all = $this->all();
		unset( $all['providers'] );
		return $all;
	}

	public function save_general( array $input ) {
		$all                     = $this->all();
		$all['title']            = sanitize_text_field( isset( $input['title'] ) ? $input['title'] : '' );
		$all['description']      = sanitize_textarea_field( isset( $input['description'] ) ? $input['description'] : '' );
		$all['debug']            = ! empty( $input['debug'] );
		$all['retention_days']   = max( 30, min( 730, absint( isset( $input['retention_days'] ) ? $input['retention_days'] : 180 ) ) );
		$all['default_provider'] = sanitize_key( isset( $input['default_provider'] ) ? $input['default_provider'] : '' );
		$order                   = isset( $input['provider_order'] ) ? explode( ',', (string) $input['provider_order'] ) : array();
		$all['provider_order']   = array_values( array_filter( array_map( 'sanitize_key', $order ) ) );
		update_option( self::OPTION, $all, false );
	}

	public function provider( $slug, $decrypt = true ) {
		$all    = $this->all();
		$config = isset( $all['providers'][ $slug ] ) && is_array( $all['providers'][ $slug ] )
			? $all['providers'][ $slug ]
			: array();

		if ( $decrypt ) {
			$definition = Registry::definition( $slug );
			foreach ( isset( $definition['fields'] ) ? $definition['fields'] : array() as $key => $field ) {
				if ( ! empty( $field['secret'] ) && ! empty( $config[ $key ] ) ) {
					$config[ $key ] = $this->vault->decrypt( $config[ $key ] );
				}
			}
		}

		return $config;
	}

	public function save_provider( $slug, array $input ) {
		$definition = Registry::definition( $slug );
		if ( empty( $definition ) ) {
			return new \WP_Error( 'unknown_provider', 'درگاه انتخاب‌شده معتبر نیست.' );
		}

		$all      = $this->all();
		$existing = $this->provider( $slug, false );
		$config   = array(
			'enabled' => ! empty( $input['enabled'] ),
			'title'   => sanitize_text_field( isset( $input['title'] ) ? $input['title'] : $definition['name'] ),
		);

		foreach ( $definition['fields'] as $key => $field ) {
			$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
			if ( 'checkbox' === $field['type'] ) {
				$config[ $key ] = ! empty( $value );
			} elseif ( 'url' === $field['type'] ) {
				$config[ $key ] = esc_url_raw( $value );
			} elseif ( 'textarea' === $field['type'] ) {
				$config[ $key ] = sanitize_textarea_field( $value );
			} else {
				$config[ $key ] = sanitize_text_field( $value );
			}

			if ( ! empty( $field['secret'] ) ) {
				if ( '' === trim( (string) $value ) && isset( $existing[ $key ] ) ) {
					$config[ $key ] = $existing[ $key ];
				} else {
					$config[ $key ] = $this->vault->encrypt( $config[ $key ] );
				}
			}
		}

		$all['providers'][ $slug ] = $config;
		update_option( self::OPTION, $all, false );
		return true;
	}

	public function enabled_providers() {
		$all     = $this->all();
		$enabled = array();
		foreach ( Registry::definitions() as $slug => $definition ) {
			$config = $this->provider( $slug );
			if ( ! empty( $config['enabled'] ) && Registry::is_configured( $slug, $config ) ) {
				$enabled[ $slug ] = array_merge( $definition, array( 'config' => $config ) );
			}
		}

		$order = isset( $all['provider_order'] ) ? $all['provider_order'] : array();
		uksort(
			$enabled,
			static function ( $a, $b ) use ( $order ) {
				$ai = array_search( $a, $order, true );
				$bi = array_search( $b, $order, true );
				$ai = false === $ai ? 999 : $ai;
				$bi = false === $bi ? 999 : $bi;
				return $ai - $bi;
			}
		);
		return $enabled;
	}
}
