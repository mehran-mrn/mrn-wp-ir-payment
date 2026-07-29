<?php
/**
 * Encrypt provider credentials at rest.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Core;

defined( 'ABSPATH' ) || exit;

final class SecretVault {
	private function key() {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' )
			. ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' )
			. home_url( '/' );
		return hash( 'sha256', $material, true );
	}

	public function encrypt( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $value, $nonce, $this->key() );
			return 'sodium:' . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 12 );
			$tag = '';
			$out = openssl_encrypt( $value, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $out ) {
				return 'openssl:' . base64_encode( $iv . $tag . $out );
			}
		}

		return 'plain:' . base64_encode( $value );
	}

	public function decrypt( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw   = base64_decode( substr( $value, 7 ), true );
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$data  = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( $data, $nonce, $this->key() );
			return false === $plain ? '' : $plain;
		}

		if ( 0 === strpos( $value, 'openssl:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw   = base64_decode( substr( $value, 8 ), true );
			$iv    = substr( $raw, 0, 12 );
			$tag   = substr( $raw, 12, 16 );
			$data  = substr( $raw, 28 );
			$plain = openssl_decrypt( $data, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );
			return false === $plain ? '' : $plain;
		}

		if ( 0 === strpos( $value, 'plain:' ) ) {
			return (string) base64_decode( substr( $value, 6 ), true );
		}

		return $value;
	}
}
