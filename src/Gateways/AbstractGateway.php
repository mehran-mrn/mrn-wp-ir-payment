<?php
/**
 * Shared gateway HTTP client.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Gateways;

use MRN\IranPayment\Contracts\GatewayInterface;
use MRN\IranPayment\Infrastructure\Logger;

defined( 'ABSPATH' ) || exit;

abstract class AbstractGateway implements GatewayInterface {
	protected $slug;
	protected $config;
	protected $logger;

	public function __construct( $slug, array $config, Logger $logger ) {
		$this->slug   = sanitize_key( $slug );
		$this->config = $config;
		$this->logger = $logger;
	}

	protected function post( $url, array $body, array $headers = array(), $json = true ) {
		$args = array(
			'timeout'     => 15,
			'redirection' => 2,
			'headers'     => $headers,
			'body'        => $json ? wp_json_encode( $body ) : $body,
			'data_format' => 'body',
		);
		if ( $json ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['headers']['Accept']       = 'application/json';
		}

		$response = wp_safe_remote_post( esc_url_raw( $url ), $args );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( sanitize_text_field( $response->get_error_message() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exceptions are not output here.
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = '' === trim( $raw ) ? array() : json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( sprintf( 'پاسخ نامعتبر از درگاه دریافت شد (HTTP %d).', $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Integer-only formatted exception.
		}
		if ( $code < 200 || $code >= 300 ) {
			$message = $this->message_from_response( $data );
			throw new \RuntimeException( $message ? $message : sprintf( 'درگاه با کد HTTP %d پاسخ داد.', $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message was sanitized above.
		}
		return $data;
	}

	protected function message_from_response( array $response ) {
		foreach ( array( 'message', 'error_message', 'error', 'description' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_scalar( $response[ $key ] ) ) {
				return sanitize_text_field( (string) $response[ $key ] );
			}
		}
		if ( isset( $response['errors']['message'] ) ) {
			return sanitize_text_field( (string) $response['errors']['message'] );
		}
		return '';
	}

	protected function message_or( array $response, $fallback ) {
		$message = $this->message_from_response( $response );
		return $message ? $message : $fallback;
	}

	protected function callback_value( array $callback, $key, $fallback = '' ) {
		foreach ( $callback as $candidate => $value ) {
			if ( 0 === strcasecmp( (string) $candidate, (string) $key ) && is_scalar( $value ) ) {
				return sanitize_text_field( wp_unslash( (string) $value ) );
			}
		}
		return $fallback;
	}

	protected function iran_mobile( $mobile ) {
		$mobile = preg_replace( '/\D+/', '', (string) $mobile );
		if ( 0 === strpos( $mobile, '98' ) ) {
			$mobile = '0' . substr( $mobile, 2 );
		}
		return $mobile;
	}

	protected function check_amount( $amount, $minimum, $maximum ) {
		$amount = absint( $amount );
		if ( $amount < $minimum || $amount > $maximum ) {
			throw new \RuntimeException(
				sprintf(
					'مبلغ پرداخت باید بین %1$s و %2$s تومان باشد.',
					number_format_i18n( $minimum ),
					number_format_i18n( $maximum )
				)
			);
		}
	}
}
