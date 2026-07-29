<?php
/**
 * Redacted payment event logger.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Infrastructure;

defined( 'ABSPATH' ) || exit;

final class Logger {
	private $sensitive = array( 'token', 'api_key', 'merchant_id', 'merchant', 'pin', 'authorization', 'password', 'auth_token' );

	public function log( $transaction_id, $event, $message = '', array $context = array(), $level = 'info' ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'mrn_ir_payment_logs',
			array(
				'transaction_id' => $transaction_id ? absint( $transaction_id ) : null,
				'level'          => sanitize_key( $level ),
				'event'          => sanitize_key( str_replace( '.', '_', $event ) ),
				'message'        => sanitize_text_field( $message ),
				'context'        => wp_json_encode( $this->redact( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'created_at'     => current_time( 'mysql', true ),
			)
		);
	}

	public function redact( array $context ) {
		foreach ( $context as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $this->sensitive, true ) ) {
				$context[ $key ] = '••••••••';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = $this->redact( $value );
			} elseif ( false !== stripos( (string) $key, 'card' ) && is_string( $value ) ) {
				$context[ $key ] = $this->mask_card( $value );
			}
		}
		return $context;
	}

	private function mask_card( $card ) {
		$digits = preg_replace( '/\D+/', '', $card );
		return strlen( $digits ) >= 10
			? substr( $digits, 0, 6 ) . '******' . substr( $digits, -4 )
			: '****';
	}
}
