<?php
/**
 * Built-in Iranian online payment providers.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Gateways;

use MRN\IranPayment\Domain\PaymentRequest;
use MRN\IranPayment\Domain\PaymentResult;

defined( 'ABSPATH' ) || exit;

final class StandardGateway extends AbstractGateway {
	public function request( PaymentRequest $request ) {
		$this->logger->log( $request->record_id, 'gateway.request', $this->slug );

		switch ( $this->slug ) {
			case 'zarinpal':
				$this->check_amount( $request->amount_toman, 1100, 400000000 );
				$data      = $this->post(
					'https://api.zarinpal.com/pg/v4/payment/request.json',
					array(
						'merchant_id'  => $this->config['merchant_id'],
						'amount'       => $request->amount_toman,
						'currency'     => 'IRT',
						'description'  => $request->description,
						'callback_url' => $request->callback_url,
						'metadata'     => array_filter(
							array(
								'order_id' => (string) $request->order_id,
								'mobile'   => $this->iran_mobile( $request->mobile ),
								'email'    => $request->email,
							)
						),
					)
				);
				$authority = isset( $data['data']['authority'] ) ? $data['data']['authority'] : '';
				if ( ! $authority ) {
					throw new \RuntimeException( $this->message_or( $data, 'زرین‌پال توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $authority, 'https://www.zarinpal.com/pg/StartPay/' . rawurlencode( $authority ) . '/', $data );

			case 'zibal':
				$domain = ! empty( $this->config['non_iran_host'] ) ? 'io' : 'ir';
				$data   = $this->post(
					"https://gateway.zibal.{$domain}/v1/request",
					array_filter(
						array(
							'merchant'    => $this->config['merchant'],
							'callbackUrl' => $request->callback_url,
							'amount'      => $request->amount_toman * 10,
							'orderId'     => (string) $request->order_id,
							'description' => $request->description,
							'mobile'      => $this->iran_mobile( $request->mobile ),
						)
					)
				);
				if ( 100 !== (int) ( isset( $data['result'] ) ? $data['result'] : 0 ) || empty( $data['trackId'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'زیبال توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['trackId'], "https://gateway.zibal.{$domain}/start/" . rawurlencode( $data['trackId'] ), $data );

			case 'vandar':
				$body = array_filter(
					array(
						'api_key'       => $this->config['api_key'],
						'amount'        => $request->amount_toman * 10,
						'callback_url'  => $request->callback_url,
						'factorNumber'  => (string) $request->order_id,
						'description'   => $request->description,
						'mobile_number' => $this->iran_mobile( $request->mobile ),
						'port'          => isset( $this->config['port'] ) ? $this->config['port'] : '',
					)
				);
				$data = $this->post( 'https://ipg.vandar.io/api/v3/send', $body );
				if ( empty( $data['token'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'وندار توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['token'], 'https://ipg.vandar.io/v3/' . rawurlencode( $data['token'] ), $data );

			case 'payping':
				$this->check_amount( $request->amount_toman, 1000, 200000000 );
				$data = $this->post(
					'https://api.payping.ir/v3/pay',
					array_filter(
						array(
							'amount'        => $request->amount_toman,
							'description'   => $request->description,
							'returnUrl'     => $request->callback_url,
							'clientRefId'   => (string) $request->order_id,
							'payerIdentity' => $this->iran_mobile( $request->mobile ),
						)
					),
					array( 'Authorization' => 'Bearer ' . $this->config['token'] )
				);
				if ( empty( $data['paymentCode'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'پی‌پینگ کد پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['paymentCode'], 'https://api.payping.ir/v3/pay/start/' . rawurlencode( $data['paymentCode'] ), $data );

			case 'sepal':
				$domain = ! empty( $this->config['non_iran_host'] ) ? 'https://3pal.ir' : 'https://sepal.ir';
				$data   = $this->post(
					$domain . '/api/request.json',
					array_filter(
						array(
							'apiKey'        => $this->config['api_key'],
							'invoiceNumber' => (string) $request->order_id,
							'amount'        => $request->amount_toman * 10,
							'callbackUrl'   => $request->callback_url,
							'description'   => $request->description,
							'payerMobile'   => $this->iran_mobile( $request->mobile ),
						)
					)
				);
				if ( empty( $data['paymentNumber'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'سپال شماره پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['paymentNumber'], $domain . '/payment/' . rawurlencode( $data['paymentNumber'] ), $data );

			case 'aqayepardakht':
				$this->check_amount( $request->amount_toman, 1000, 100000000 );
				$data = $this->post(
					'https://panel.aqayepardakht.ir/api/v2/create',
					array_filter(
						array(
							'pin'         => $this->config['pin'],
							'amount'      => $request->amount_toman,
							'callback'    => $request->callback_url,
							'invoice_id'  => (string) $request->order_id,
							'description' => $request->description,
							'mobile'      => $this->iran_mobile( $request->mobile ),
						)
					),
					array(),
					false
				);
				if ( empty( $data['transid'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'آقای پرداخت شناسه تراکنش صادر نکرد.' ) );
				}
				$path = 'sandbox' === $this->config['pin'] ? 'startpay/sandbox/' : 'startpay/';
				return PaymentResult::requested( $data['transid'], 'https://panel.aqayepardakht.ir/' . $path . rawurlencode( $data['transid'] ), $data );
		}

		throw new \RuntimeException( 'درگاه پشتیبانی نمی‌شود.' );
	}

	public function verify( $authority, PaymentRequest $request, array $callback ) {
		$this->logger->log( $request->record_id, 'gateway.verify', $this->slug, array( 'callback' => $callback ) );

		switch ( $this->slug ) {
			case 'zarinpal':
				if ( 'OK' !== strtoupper( $this->callback_value( $callback, 'Status', 'OK' ) ) ) {
					return PaymentResult::failed( 'پرداخت توسط کاربر لغو شد.', 'cancelled' );
				}
				$data = $this->post(
					'https://api.zarinpal.com/pg/v4/payment/verify.json',
					array(
						'merchant_id' => $this->config['merchant_id'],
						'authority'   => $authority,
						'amount'      => $request->amount_toman,
					)
				);
				$code = (int) ( isset( $data['data']['code'] ) ? $data['data']['code'] : 0 );
				if ( in_array( $code, array( 100, 101 ), true ) ) {
					return PaymentResult::paid(
						isset( $data['data']['ref_id'] ) ? $data['data']['ref_id'] : $authority,
						isset( $data['data']['card_pan'] ) ? $data['data']['card_pan'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید زرین‌پال ناموفق بود.' ), $code, $data );

			case 'zibal':
				if ( '0' === $this->callback_value( $callback, 'success', '1' ) ) {
					return PaymentResult::failed( 'پرداخت توسط کاربر لغو شد.', 'cancelled' );
				}
				$domain = ! empty( $this->config['non_iran_host'] ) ? 'io' : 'ir';
				$data   = $this->post(
					"https://gateway.zibal.{$domain}/v1/verify",
					array(
						'merchant'           => $this->config['merchant'],
						'trackId'            => $authority,
						'dataOnDoubleVerify' => true,
					)
				);
				$code   = (int) ( isset( $data['result'] ) ? $data['result'] : 0 );
				if ( in_array( $code, array( 100, 201 ), true ) ) {
					return PaymentResult::paid(
						isset( $data['refNumber'] ) ? $data['refNumber'] : $authority,
						isset( $data['cardNumber'] ) ? $data['cardNumber'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید زیبال ناموفق بود.' ), $code, $data );

			case 'vandar':
				$data = $this->post(
					'https://ipg.vandar.io/api/v3/verify',
					array(
						'api_key' => $this->config['api_key'],
						'token'   => $authority,
					)
				);
				$code = (int) ( isset( $data['status'] ) ? $data['status'] : 0 );
				if ( in_array( $code, array( 1, 2 ), true ) ) {
					return PaymentResult::paid(
						isset( $data['transId'] ) ? $data['transId'] : $authority,
						isset( $data['cardNumber'] ) ? $data['cardNumber'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید وندار ناموفق بود.' ), $code, $data );

			case 'payping':
				$posted = $this->callback_value( $callback, 'data', '{}' );
				$posted = json_decode( stripslashes( $posted ), true );
				$ref    = is_array( $posted ) && ! empty( $posted['paymentRefId'] ) ? $posted['paymentRefId'] : $this->callback_value( $callback, 'paymentRefId' );
				if ( ! $ref ) {
					return PaymentResult::failed( 'پرداخت تکمیل نشده است.', 'cancelled' );
				}
				$data = $this->post(
					'https://api.payping.ir/v3/pay/verify',
					array(
						'PaymentRefId' => $ref,
						'PaymentCode'  => $authority,
						'Amount'       => $request->amount_toman,
					),
					array( 'Authorization' => 'Bearer ' . $this->config['token'] )
				);
				$card = isset( $data['cardNumber'] ) ? $data['cardNumber'] : '';
				return PaymentResult::paid( $ref, $card, 200, $data );

			case 'sepal':
				$domain = ! empty( $this->config['non_iran_host'] ) ? 'https://3pal.ir' : 'https://sepal.ir';
				$data   = $this->post(
					$domain . '/api/verify.json',
					array(
						'apiKey'        => $this->config['api_key'],
						'paymentNumber' => (string) $authority,
					)
				);
				$code   = (int) ( isset( $data['status'] ) ? $data['status'] : 0 );
				if ( 1 === $code ) {
					return PaymentResult::paid( $authority, isset( $data['cardNumber'] ) ? $data['cardNumber'] : '', $code, $data );
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید سپال ناموفق بود.' ), $code, $data );

			case 'aqayepardakht':
				$data = $this->post(
					'https://panel.aqayepardakht.ir/api/v2/verify',
					array(
						'pin'     => $this->config['pin'],
						'amount'  => $request->amount_toman,
						'transid' => $authority,
					),
					array(),
					false
				);
				$code = (int) ( isset( $data['code'] ) ? $data['code'] : 0 );
				if ( in_array( $code, array( 1, 2 ), true ) ) {
					return PaymentResult::paid(
						$this->callback_value( $callback, 'tracking_number', $authority ),
						$this->callback_value( $callback, 'cardnumber' ),
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید آقای پرداخت ناموفق بود.' ), $code, $data );
		}

		return PaymentResult::failed( 'درگاه پشتیبانی نمی‌شود.' );
	}
}
