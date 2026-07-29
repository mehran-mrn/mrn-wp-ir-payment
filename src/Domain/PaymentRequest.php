<?php
/**
 * Immutable payment request value object.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Domain;

defined( 'ABSPATH' ) || exit;

final class PaymentRequest {
	public $record_id;
	public $transaction_id;
	public $order_id;
	public $amount_toman;
	public $callback_url;
	public $description;
	public $mobile;
	public $email;

	public function __construct( array $data ) {
		$this->record_id      = absint( $data['record_id'] );
		$this->transaction_id = (string) $data['transaction_id'];
		$this->order_id       = absint( $data['order_id'] );
		$this->amount_toman   = absint( $data['amount_toman'] );
		$this->callback_url   = esc_url_raw( $data['callback_url'] );
		$this->description    = sanitize_text_field( $data['description'] );
		$this->mobile         = sanitize_text_field( isset( $data['mobile'] ) ? $data['mobile'] : '' );
		$this->email          = sanitize_email( isset( $data['email'] ) ? $data['email'] : '' );
	}
}
