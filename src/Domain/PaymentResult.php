<?php
/**
 * Gateway operation result.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Domain;

defined( 'ABSPATH' ) || exit;

final class PaymentResult {
	public $successful;
	public $authority;
	public $redirect_url;
	public $reference_id;
	public $card_pan;
	public $gateway_status;
	public $message;
	public $uncertain;
	public $raw;

	private function __construct() {}

	public static function requested( $authority, $redirect_url, array $raw = array() ) {
		$result               = new self();
		$result->successful   = true;
		$result->authority    = (string) $authority;
		$result->redirect_url = esc_url_raw( $redirect_url );
		$result->raw          = $raw;
		$result->uncertain    = false;
		return $result;
	}

	public static function paid( $reference_id, $card_pan = '', $gateway_status = '', array $raw = array() ) {
		$result                 = new self();
		$result->successful     = true;
		$result->reference_id   = sanitize_text_field( (string) $reference_id );
		$result->card_pan       = sanitize_text_field( (string) $card_pan );
		$result->gateway_status = sanitize_text_field( (string) $gateway_status );
		$result->raw            = $raw;
		$result->uncertain      = false;
		return $result;
	}

	public static function failed( $message, $gateway_status = '', array $raw = array() ) {
		$result                 = new self();
		$result->successful     = false;
		$result->message        = sanitize_text_field( $message );
		$result->gateway_status = sanitize_text_field( (string) $gateway_status );
		$result->raw            = $raw;
		$result->uncertain      = false;
		return $result;
	}

	public static function uncertain( $message ) {
		$result            = self::failed( $message );
		$result->uncertain = true;
		return $result;
	}
}
