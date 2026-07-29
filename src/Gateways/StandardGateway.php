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
			case 'asanpardakht':
				$headers = $this->asan_headers();
				$token   = $this->post_scalar(
					'https://ipgrest.asanpardakht.ir/v1/Token',
					array(
						'merchantConfigurationId' => $this->config['merchant'],
						'serviceTypeId'           => 1,
						'localInvoiceId'          => $request->record_id,
						'amountInRials'           => $request->amount_toman * 10,
						'localDate'               => current_time( 'Ymd His' ),
						'additionalData'          => wp_json_encode(
							array_filter(
								array(
									'MatchCardNumberMobileNumber' => ! empty( $this->config['match_card_with_mobile'] ),
								)
							)
						),
						'callbackURL'             => $request->callback_url,
						'paymentId'               => 0,
						'settlementPortions'      => array(),
						'mobileNumber'            => $this->iran_mobile( $request->mobile ),
					),
					$headers,
					true
				);
				if ( ! $token ) {
					throw new \RuntimeException( 'آسان پرداخت توکن پرداخت صادر نکرد.' );
				}
				$relay = add_query_arg(
					array(
						'mrn_relay' => 'asanpardakht',
						'token'     => $token,
					),
					$request->callback_url
				);
				return PaymentResult::requested( $token, $relay );

			case 'bitpay':
				$authority = $this->post_scalar(
					'https://bitpay.ir/payment/gateway-send',
					array(
						'api'         => $this->config['api'],
						'amount'      => $request->amount_toman * 10,
						'redirect'    => $request->callback_url,
						'description' => $request->description,
						'factorId'    => $request->transaction_id,
					)
				);
				if ( (int) $authority < 1 ) {
					throw new \RuntimeException( 'بیت‌پی شناسه پرداخت معتبر صادر نکرد.' );
				}
				return PaymentResult::requested( $authority, 'https://bitpay.ir/payment/gateway-' . rawurlencode( $authority ) . '-get' );

			case 'directpay':
			case 'paystar':
				$amount = $request->amount_toman * 10;
				$sign   = hash_hmac(
					'sha512',
					$amount . '#' . $request->transaction_id . '#' . $request->callback_url,
					$this->config['encryption_key']
				);
				$host   = $this->signed_gateway_host();
				$data   = $this->post(
					$host . '/api/pardakht/create',
					array_filter(
						array(
							'amount'          => $amount,
							'order_id'        => $request->transaction_id,
							'callback'        => $request->callback_url,
							'sign'            => $sign,
							'description'     => $request->description,
							'callback_method' => 1,
							'referer_id'      => 'jkn5y',
							'phone'           => $this->iran_mobile( $request->mobile ),
						)
					),
					array( 'Authorization' => 'Bearer ' . $this->config['gateway_id'] )
				);
				if ( 1 !== (int) ( isset( $data['status'] ) ? $data['status'] : 0 ) || empty( $data['data']['token'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'سرویس توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested(
					$data['data']['token'],
					$host . '/api/pardakht/payment/?token=' . rawurlencode( $data['data']['token'] ),
					$data
				);

			case 'jibit':
				$token = $this->jibit_token();
				$data  = $this->post(
					'https://napi.jibit.ir/ppg/v3/purchases',
					array_filter(
						array(
							'amount'                 => $request->amount_toman * 10,
							'currency'               => 'IRR',
							'clientReferenceNumber'  => $request->transaction_id,
							'description'            => $request->description,
							'callbackUrl'            => $request->callback_url,
							'payerMobileNumber'      => $this->iran_mobile( $request->mobile ),
							'checkPayerMobileNumber' => ! empty( $this->config['match_card_with_mobile'] ),
						)
					),
					array( 'Authorization' => 'Bearer ' . $token )
				);
				if ( empty( $data['purchaseIdStr'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'جیبیت شناسه خرید صادر نکرد.' ) );
				}
				return PaymentResult::requested(
					$data['purchaseIdStr'],
					'https://napi.jibit.ir/ppg/v3/purchases/' . rawurlencode( $data['purchaseIdStr'] ) . '/payments',
					$data
				);

			case 'nabikpay':
				$base = untrailingslashit( $this->config['base_url'] );
				$data = $this->post(
					$base . '/request',
					array_filter(
						array(
							'amount'      => $request->amount_toman,
							'currency'    => 'IRT',
							'merchant'    => $this->config['merchant'],
							'order_id'    => $request->transaction_id,
							'callback'    => $request->callback_url,
							'description' => $request->description,
							'mobile'      => $this->iran_mobile( $request->mobile ),
						)
					),
					array(),
					false
				);
				if ( empty( $data['success'] ) || empty( $data['data']['authority'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'نابیک‌پی شناسه پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['data']['authority'], $base . '/pay/' . rawurlencode( $data['data']['authority'] ), $data );

			case 'neogate':
				$data = $this->post(
					'https://app.neogate.ir/api/v1/payment/request',
					array_filter(
						array(
							'amount'          => $request->amount_toman * 10,
							'merchant'        => $this->config['merchant'],
							'order_id'        => $request->transaction_id,
							'mobile'          => $this->iran_mobile( $request->mobile ),
							'callback'        => $request->callback_url,
							'description'     => $request->description,
							'expiration_time' => isset( $this->config['expiration_time'] ) ? $this->config['expiration_time'] : '',
						)
					),
					array(),
					false
				);
				if ( empty( $data['data']['token'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'نئوگیت توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['data']['token'], 'https://app.neogate.ir/pay/' . rawurlencode( $data['data']['token'] ), $data );

			case 'pasargad':
				$base  = untrailingslashit( isset( $this->config['base_url'] ) ? $this->config['base_url'] : 'https://pep.shaparak.ir/pepg' );
				$token = $this->pasargad_token( $base );
				$data  = $this->post(
					$base . $this->pasargad_path( 'request_path', '/api/payment/purchase' ),
					array_filter(
						array(
							'invoice'        => $request->transaction_id,
							'invoiceDate'    => current_time( 'Y-m-d H:i:s' ),
							'serviceCode'    => 8,
							'serviceType'    => 'PURCHASE',
							'amount'         => $request->amount_toman * 10,
							'payerMail'      => $request->email,
							'payerName'      => $request->description,
							'mobileNumber'   => $this->iran_mobile( $request->mobile ),
							'callbackApi'    => $request->callback_url,
							'terminalNumber' => $this->config['terminal'],
							'merchantCode'   => $this->config['merchant_id'],
							'paymentCode'    => '0',
						)
					),
					array( 'Authorization' => 'Bearer ' . $token )
				);
				if ( 0 !== (int) ( isset( $data['resultCode'] ) ? $data['resultCode'] : -1 ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'پاسارگاد لینک پرداخت صادر نکرد.' ) );
				}
				$authority = isset( $data['data']['urlId'] ) ? $data['data']['urlId'] : ( isset( $data['data']['url'] ) ? basename( $data['data']['url'] ) : '' );
				$redirect  = isset( $data['data']['url'] ) ? $data['data']['url'] : '';
				if ( ! $authority || ! filter_var( $redirect, FILTER_VALIDATE_URL ) ) {
					throw new \RuntimeException( 'پاسخ ایجاد پرداخت پاسارگاد کامل نیست.' );
				}
				return PaymentResult::requested( $authority, $redirect, $data );

			case 'saman':
				$data = $this->post(
					'https://sep.shaparak.ir/OnlinePG/OnlinePG',
					array_filter(
						array(
							'Action'           => 'token',
							'TerminalId'       => $this->config['terminal_id'],
							'Amount'           => $request->amount_toman * 10,
							'RedirectUrl'      => $request->callback_url,
							'ResNum'           => $request->transaction_id,
							'TokenExpiryInMin' => 60,
							'CellNumber'       => preg_replace( '/^0/', '', $this->iran_mobile( $request->mobile ) ),
						)
					),
					array()
				);
				if ( 1 !== (int) ( isset( $data['status'] ) ? $data['status'] : 0 ) || empty( $data['token'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'سامان توکن پرداخت صادر نکرد.' ) );
				}
				if ( ! empty( $this->config['blupay'] ) ) {
					return PaymentResult::requested( $data['token'], 'https://neo-pg.sep.ir/transaction/init?token=' . rawurlencode( $data['token'] ), $data );
				}
				$relay = add_query_arg(
					array(
						'mrn_relay' => 'saman',
						'token'     => $data['token'],
					),
					$request->callback_url
				);
				return PaymentResult::requested( $data['token'], $relay, $data );

			case 'shepa':
				$data = $this->post(
					'https://merchant.shepa.com/api/v1/token',
					array_filter(
						array(
							'api'         => $this->config['api'],
							'amount'      => $request->amount_toman * 10,
							'callback'    => $request->callback_url,
							'description' => $request->description,
							'mobile'      => $this->iran_mobile( $request->mobile ),
							'email'       => $request->email,
						)
					)
				);
				if ( empty( $data['success'] ) || empty( $data['result']['token'] ) || empty( $data['result']['url'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'شپا توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['result']['token'], $data['result']['url'], $data );

			case 'sizpay':
				$this->check_amount( $request->amount_toman, 1000, 50000000 );
				$data = $this->post(
					'https://rt.sizpay.ir/api/PaymentSimple/GetTokenSimple',
					array(
						'sizPayKey' => $this->config['key'],
						'Amount'    => $request->amount_toman * 10,
						'ReturnURL' => $request->callback_url,
						'OrderID'   => $request->transaction_id,
						'InvoiceNo' => $request->transaction_id,
						'ExtraInf'  => array_filter(
							array(
								'Descr'       => $request->description,
								'PayerMobile' => $this->iran_mobile( $request->mobile ),
							)
						),
					)
				);
				if ( ! in_array( (string) ( isset( $data['ResCod'] ) ? $data['ResCod'] : '' ), array( '0', '00' ), true ) || empty( $data['Token'] ) ) {
					throw new \RuntimeException( $this->message_or( $data, 'سیزپی توکن پرداخت صادر نکرد.' ) );
				}
				return PaymentResult::requested( $data['Token'], 'https://rt.sizpay.ir/Route/Payment/?token=' . rawurlencode( $data['Token'] ), $data );

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
			case 'asanpardakht':
				$headers = $this->asan_headers();
				$data    = $this->get(
					add_query_arg(
						array(
							'merchantConfigurationId' => $this->config['merchant'],
							'LocalInvoiceId'          => $request->record_id,
						),
						'https://ipgrest.asanpardakht.ir/v1/TranResult'
					),
					$headers
				);
				if ( empty( $data['payGateTranID'] ) ) {
					return PaymentResult::failed( $this->message_or( $data, 'تراکنش آسان پرداخت پیدا نشد.' ), 'not_found', $data );
				}
				$verify = array(
					'merchantConfigurationId' => $this->config['merchant'],
					'payGateTranId'           => $data['payGateTranID'],
				);
				$this->post( 'https://ipgrest.asanpardakht.ir/v1/Verify', $verify, $headers );
				try {
					$this->post( 'https://ipgrest.asanpardakht.ir/v1/Settlement', $verify, $headers );
				} catch ( \Exception $exception ) {
					$this->logger->log( $request->record_id, 'gateway.settlement.deferred', $exception->getMessage(), array(), 'warning' );
				}
				return PaymentResult::paid(
					isset( $data['rrn'] ) ? $data['rrn'] : $authority,
					isset( $data['cardNumber'] ) ? $data['cardNumber'] : '',
					isset( $data['serviceStatusCode'] ) ? $data['serviceStatusCode'] : 0,
					$data
				);

			case 'bitpay':
				$trans_id = $this->callback_value( $callback, 'trans_id' );
				if ( ! $trans_id ) {
					return PaymentResult::failed( 'پرداخت در بیت‌پی تکمیل نشد.', 'cancelled' );
				}
				$data = $this->post(
					'https://bitpay.ir/payment/gateway-result-second',
					array(
						'trans_id' => $trans_id,
						'id_get'   => $authority,
						'api'      => $this->config['api'],
						'json'     => 1,
					),
					array(),
					false
				);
				$code = (int) ( isset( $data['status'] ) ? $data['status'] : 0 );
				if ( in_array( $code, array( 1, 11 ), true ) ) {
					return PaymentResult::paid( $trans_id, isset( $data['cardNum'] ) ? $data['cardNum'] : '', $code, $data );
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید بیت‌پی ناموفق بود.' ), $code, $data );

			case 'directpay':
			case 'paystar':
				$ref  = $this->callback_value( $callback, 'ref_num', $authority );
				$data = $this->post(
					$this->signed_gateway_host() . '/api/pardakht/verify',
					array(
						'ref_num' => $ref,
						'amount'  => $request->amount_toman * 10,
					),
					array( 'Authorization' => 'Bearer ' . $this->config['gateway_id'] )
				);
				$code = (int) ( isset( $data['status'] ) ? $data['status'] : 0 );
				if ( in_array( $code, array( 1, -6 ), true ) ) {
					return PaymentResult::paid( $ref, isset( $data['data']['card_number'] ) ? $data['data']['card_number'] : '', $code, $data );
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید تراکنش ناموفق بود.' ), $code, $data );

			case 'jibit':
				$data = $this->post(
					'https://napi.jibit.ir/ppg/v3/purchases/' . rawurlencode( $authority ) . '/verify',
					array(),
					array( 'Authorization' => 'Bearer ' . $this->jibit_token() )
				);
				$code = isset( $data['status'] ) ? $data['status'] : '';
				if ( in_array( $code, array( 'SUCCESSFUL', 'ALREADY_VERIFIED' ), true ) ) {
					return PaymentResult::paid(
						isset( $data['referenceNumber'] ) ? $data['referenceNumber'] : $authority,
						isset( $data['payerCardNumber'] ) ? $data['payerCardNumber'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید جیبیت ناموفق بود.' ), $code, $data );

			case 'nabikpay':
				$data = $this->post(
					untrailingslashit( $this->config['base_url'] ) . '/verify',
					array(
						'merchant'  => $this->config['merchant'],
						'authority' => $authority,
					),
					array(),
					false
				);
				if ( ! empty( $data['success'] ) && 'paid' === ( isset( $data['data']['status'] ) ? $data['data']['status'] : '' ) ) {
					return PaymentResult::paid(
						isset( $data['data']['trans_id'] ) ? $data['data']['trans_id'] : $authority,
						isset( $data['data']['card_number'] ) ? $data['data']['card_number'] : '',
						isset( $data['data']['gateway_status'] ) ? $data['data']['gateway_status'] : 'paid',
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید نابیک‌پی ناموفق بود.' ), isset( $data['data']['status'] ) ? $data['data']['status'] : '', $data );

			case 'neogate':
				$data = $this->post(
					'https://app.neogate.ir/api/v1/payment/verify',
					array(
						'merchant' => $this->config['merchant'],
						'token'    => $authority,
					),
					array(),
					false
				);
				$code = isset( $data['data']['status'] ) ? $data['data']['status'] : '';
				if ( 'paid' === $code ) {
					return PaymentResult::paid(
						isset( $data['data']['ref_number'] ) ? $data['data']['ref_number'] : $authority,
						isset( $data['data']['card_number'] ) ? $data['data']['card_number'] : '',
						$code,
						$data
					);
				}
				return 'pending' === $code
					? PaymentResult::uncertain( 'رسید کارت به کارت هنوز تعیین وضعیت نشده است.' )
					: PaymentResult::failed( $this->message_or( $data, 'رسید کارت به کارت تأیید نشد.' ), $code, $data );

			case 'pasargad':
				$base = untrailingslashit( isset( $this->config['base_url'] ) ? $this->config['base_url'] : 'https://pep.shaparak.ir/pepg' );
				$data = $this->post(
					$base . $this->pasargad_path( 'verify_path', '/api/payment/verify-transactions' ),
					array(
						'invoice' => $request->transaction_id,
						'urlId'   => $authority,
					),
					array( 'Authorization' => 'Bearer ' . $this->pasargad_token( $base ) )
				);
				$code = (int) ( isset( $data['resultCode'] ) ? $data['resultCode'] : -1 );
				if ( in_array( $code, array( 0, 13029, 13046 ), true ) ) {
					return PaymentResult::paid(
						isset( $data['data']['referenceNumber'] ) ? $data['data']['referenceNumber'] : $authority,
						isset( $data['data']['maskedCardNumber'] ) ? $data['data']['maskedCardNumber'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید بانک پاسارگاد ناموفق بود.' ), $code, $data );

			case 'saman':
				$ref = $this->callback_value( $callback, 'RefNum' );
				if ( ! $ref ) {
					return PaymentResult::failed( 'پرداخت در درگاه سامان تکمیل نشد.', 'cancelled' );
				}
				$data = $this->post(
					'https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTranscation',
					array(
						'RefNum'             => $ref,
						'TerminalNumber'     => $this->config['terminal_id'],
						'CellNumber'         => $this->iran_mobile( $request->mobile ),
						'NationalCode'       => '',
						'IgnoreNationalcode' => true,
					)
				);
				$code = (int) ( isset( $data['ResultCode'] ) ? $data['ResultCode'] : -1 );
				if ( isset( $data['TransactionDetail']['OrginalAmount'] ) && (int) $data['TransactionDetail']['OrginalAmount'] !== $request->amount_toman * 10 ) {
					return PaymentResult::failed( 'مبلغ پرداخت سامان با سفارش مطابقت ندارد.', 'amount_mismatch', $data );
				}
				if ( in_array( $code, array( 0, 2 ), true ) ) {
					return PaymentResult::paid(
						isset( $data['TransactionDetail']['RRN'] ) ? $data['TransactionDetail']['RRN'] : $ref,
						isset( $data['TransactionDetail']['MaskedPan'] ) ? $data['TransactionDetail']['MaskedPan'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید بانک سامان ناموفق بود.' ), $code, $data );

			case 'shepa':
				$data = $this->post(
					'https://merchant.shepa.com/api/v1/verify',
					array(
						'api'    => $this->config['api'],
						'amount' => $request->amount_toman * 10,
						'token'  => $authority,
					)
				);
				if ( ! empty( $data['success'] ) ) {
					return PaymentResult::paid(
						isset( $data['result']['transaction_id'] ) ? $data['result']['transaction_id'] : $authority,
						isset( $data['result']['card_pan'] ) ? $data['result']['card_pan'] : '',
						'success',
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید شپا ناموفق بود.' ), isset( $data['status'] ) ? $data['status'] : '', $data );

			case 'sizpay':
				$data = $this->post(
					'https://rt.sizpay.ir/api/PaymentSimple/ConfirmSimple',
					array(
						'sizPayKey' => $this->config['key'],
						'Token'     => $authority,
					)
				);
				$code = (string) ( isset( $data['ResCod'] ) ? $data['ResCod'] : '' );
				if ( in_array( $code, array( '0', '00' ), true ) ) {
					return PaymentResult::paid(
						isset( $data['RefNo'] ) ? $data['RefNo'] : $authority,
						isset( $data['CardNo'] ) ? $data['CardNo'] : '',
						$code,
						$data
					);
				}
				return PaymentResult::failed( $this->message_or( $data, 'تأیید سیزپی ناموفق بود.' ), $code, $data );

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

	private function signed_gateway_host() {
		if ( 'paystar' === $this->slug ) {
			$tld = ! empty( $this->config['non_iran_host'] ) ? 'click' : 'ir';
			return 'https://core.paystar.' . $tld;
		}
		$tld = ! empty( $this->config['non_iran_host'] ) ? 'click' : 'finance';
		return 'https://api.directpay.' . $tld;
	}

	private function jibit_token() {
		$data = $this->post(
			'https://napi.jibit.ir/ppg/v3/tokens',
			array(
				'username' => $this->config['api_key'],
				'password' => $this->config['secret_key'],
			)
		);
		if ( empty( $data['accessToken'] ) ) {
			throw new \RuntimeException( $this->message_or( $data, 'جیبیت توکن دسترسی صادر نکرد.' ) );
		}
		return $data['accessToken'];
	}

	private function pasargad_token( $base ) {
		$data = $this->post(
			$base . $this->pasargad_path( 'token_path', '/token/getToken' ),
			array(
				'username' => $this->config['username'],
				'password' => $this->config['password'],
			)
		);
		if ( empty( $data['token'] ) ) {
			throw new \RuntimeException( $this->message_or( $data, 'پاسارگاد توکن دسترسی صادر نکرد.' ) );
		}
		return $data['token'];
	}

	private function pasargad_path( $key, $fallback ) {
		$path = isset( $this->config[ $key ] ) && '' !== trim( (string) $this->config[ $key ] )
			? $this->config[ $key ]
			: $fallback;
		return '/' . ltrim( $path, '/' );
	}

	private function asan_headers() {
		return array(
			'Accept'       => 'application/json',
			'usr'          => $this->config['username'],
			'pwd'          => $this->config['password'],
			'Content-Type' => 'application/json',
		);
	}
}
