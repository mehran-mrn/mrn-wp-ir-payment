<?php
/**
 * Payment provider contract.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Contracts;

use MRN\IranPayment\Domain\PaymentRequest;
use MRN\IranPayment\Domain\PaymentResult;

defined( 'ABSPATH' ) || exit;

interface GatewayInterface {
	public function request( PaymentRequest $request );

	public function verify( $authority, PaymentRequest $request, array $callback );
}
