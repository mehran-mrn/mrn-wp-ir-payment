<?php
/**
 * Configurable JSON adapter for enterprise PSP and BNPL services.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Gateways;

use MRN\IranPayment\Domain\PaymentRequest;
use MRN\IranPayment\Domain\PaymentResult;

defined( 'ABSPATH' ) || exit;

final class CustomRestGateway extends AbstractGateway {
	public function request( PaymentRequest $request ) {
		$variables = $this->variables( $request );
		$body      = $this->template( $this->config['request_template'], $variables );
		$data      = $this->post(
			$this->replace( $this->config['request_url'], $variables ),
			$body,
			$this->headers( $variables ),
			'form' !== ( isset( $this->config['request_format'] ) ? $this->config['request_format'] : 'json' )
		);
		$authority = $this->path( $data, $this->config['authority_path'] );
		if ( '' === (string) $authority ) {
			$message = $this->message_from_response( $data );
			throw new \RuntimeException( $message ? $message : 'سرویس شناسه پرداخت صادر نکرد.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider message is sanitized.
		}
		$variables['authority'] = rawurlencode( (string) $authority );
		$redirect               = $this->replace( $this->config['redirect_url'], $variables );
		return PaymentResult::requested( $authority, $redirect, $data );
	}

	public function verify( $authority, PaymentRequest $request, array $callback ) {
		$variables              = $this->variables( $request );
		$variables['authority'] = (string) $authority;
		foreach ( $callback as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$variables[ 'callback.' . sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}
		}
		$body    = $this->template( $this->config['verify_template'], $variables );
		$data    = $this->post(
			$this->replace( $this->config['verify_url'], $variables ),
			$body,
			$this->headers( $variables ),
			'form' !== ( isset( $this->config['request_format'] ) ? $this->config['request_format'] : 'json' )
		);
		$status  = (string) $this->path( $data, $this->config['success_path'] );
		$success = array_map( 'trim', explode( ',', $this->config['success_values'] ) );
		if ( in_array( $status, $success, true ) ) {
			$ref  = $this->path( $data, $this->config['reference_path'] );
			$card = $this->path( $data, isset( $this->config['card_path'] ) ? $this->config['card_path'] : '' );
			return PaymentResult::paid( $ref ? $ref : $authority, $card, $status, $data );
		}
		$message = $this->message_from_response( $data );
		return PaymentResult::failed( $message ? $message : 'پرداخت توسط سرویس تأیید نشد.', $status, $data );
	}

	private function headers( array $variables ) {
		$headers = array();
		if ( ! empty( $this->config['headers_template'] ) ) {
			$template = $this->replace( $this->config['headers_template'], $variables );
			$decoded  = json_decode( $template, true );
			if ( ! is_array( $decoded ) ) {
				throw new \RuntimeException( 'قالب JSON هدرهای درگاه معتبر نیست.' );
			}
			foreach ( $decoded as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$headers[ sanitize_text_field( $key ) ] = sanitize_text_field( (string) $value );
				}
			}
		}
		if ( ! empty( $this->config['auth_header'] ) && ! empty( $this->config['auth_token'] ) ) {
			$headers[ $this->config['auth_header'] ] = $this->config['auth_token'];
		}
		return $headers;
	}

	private function variables( PaymentRequest $request ) {
		$variables = array(
			'transaction_id' => $request->transaction_id,
			'order_id'       => (string) $request->order_id,
			'amount_toman'   => (string) $request->amount_toman,
			'amount_rial'    => (string) ( $request->amount_toman * 10 ),
			'callback_url'   => $request->callback_url,
			'description'    => $request->description,
			'mobile'         => $request->mobile,
			'email'          => $request->email,
		);
		foreach ( $this->config as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$variables[ 'config.' . sanitize_key( $key ) ] = (string) $value;
			}
		}
		return $variables;
	}

	private function template( $json, array $variables ) {
		$decoded = json_decode( (string) $json, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( 'قالب JSON درگاه معتبر نیست.' );
		}
		return $this->replace_recursive( $decoded, $variables );
	}

	private function replace_recursive( $value, array $variables ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->replace_recursive( $item, $variables );
			}
			return $value;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( preg_match( '/^\{\{([^}]+)\}\}$/', $value, $match ) && isset( $variables[ $match[1] ] ) && is_numeric( $variables[ $match[1] ] ) ) {
			return (int) $variables[ $match[1] ];
		}
		return $this->replace( $value, $variables );
	}

	private function replace( $value, array $variables ) {
		foreach ( $variables as $key => $replacement ) {
			$value = str_replace( '{{' . $key . '}}', (string) $replacement, (string) $value );
		}
		return $value;
	}

	private function path( array $data, $path ) {
		if ( '' === trim( (string) $path ) ) {
			return '';
		}
		$value = $data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return '';
			}
			$value = $value[ $segment ];
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
