<?php
/**
 * Unified WooCommerce payment gateway.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\WooCommerce;

use MRN\IranPayment\Core\Settings;
use MRN\IranPayment\Domain\PaymentRequest;
use MRN\IranPayment\Gateways\Registry;
use MRN\IranPayment\Infrastructure\Logger;
use MRN\IranPayment\Infrastructure\Repository;

defined( 'ABSPATH' ) || exit;

class Gateway extends \WC_Payment_Gateway {
	private $settings_store;
	private $transactions;
	private $logger;

	public function __construct() {
		$this->id                 = 'mrn_ir_payment';
		$this->method_title       = 'MRN پرداخت ایران';
		$this->method_description = 'درگاه یکپارچه پرداخت آنلاین و اقساطی ایران';
		$this->has_fields         = true;
		$this->supports           = array( 'products' );
		$this->icon               = '';

		$this->settings_store = new Settings( new \MRN\IranPayment\Core\SecretVault() );
		$this->transactions   = new Repository();
		$this->logger         = new Logger();

		$this->init_form_fields();
		$this->init_settings();
		$general           = $this->settings_store->general();
		$this->title       = $general['title'];
		$this->description = $general['description'];
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => 'فعال‌سازی',
				'type'    => 'checkbox',
				'label'   => 'نمایش MRN پرداخت ایران در تسویه‌حساب',
				'default' => 'yes',
			),
			'manage'  => array(
				'title'       => 'مدیریت درگاه‌ها',
				'type'        => 'title',
				'description' => '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mrn-ir-payment&tab=gateways' ) ) . '">باز کردن مرکز پرداخت MRN</a>',
			),
		);
	}

	public function is_available() {
		if ( ! parent::is_available() || empty( $this->settings_store->enabled_providers() ) ) {
			return false;
		}
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return in_array( get_woocommerce_currency(), array( 'IRT', 'IRR', 'IRHR', 'IRHT' ), true );
		}
		return true;
	}

	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( $this->description ) );
		}
		$providers = $this->settings_store->enabled_providers();
		$selected  = $this->selected_provider( $providers );
		echo '<div class="mrn-ir-checkout-providers" role="radiogroup" aria-label="انتخاب سرویس پرداخت">';
		foreach ( $providers as $slug => $provider ) {
			$mode  = 'installment' === $provider['mode'] ? 'اقساطی' : 'آنلاین';
			$title = ! empty( $provider['config']['title'] ) ? $provider['config']['title'] : $provider['name'];
			printf(
				'<label class="mrn-ir-checkout-provider"><input type="radio" name="mrn_ir_provider" value="%1$s" %2$s><span class="mrn-ir-provider-dot" style="--mrn-provider:%3$s"></span><span><strong>%4$s</strong><small>%5$s · %6$s</small></span></label>',
				esc_attr( $slug ),
				checked( $selected, $slug, false ),
				esc_attr( $provider['accent'] ),
				esc_html( $title ),
				esc_html( $mode ),
				esc_html( $provider['description'] )
			);
		}
		echo '</div>';
	}

	public function validate_fields() {
		$providers = $this->settings_store->enabled_providers();
		$selected  = isset( $_POST['mrn_ir_provider'] ) ? sanitize_key( wp_unslash( $_POST['mrn_ir_provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $providers[ $selected ] ) ) {
			wc_add_notice( 'لطفاً یکی از سرویس‌های پرداخت را انتخاب کنید.', 'error' );
			return false;
		}
		if ( WC()->session ) {
			WC()->session->set( 'mrn_ir_provider', $selected );
		}
		return true;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( 'سفارش معتبر نیست.', 'error' );
			return array( 'result' => 'failure' );
		}

		$providers = $this->settings_store->enabled_providers();
		$slug      = $this->selected_provider( $providers );
		if ( ! isset( $providers[ $slug ] ) ) {
			wc_add_notice( 'سرویس پرداخت انتخاب‌شده فعال نیست.', 'error' );
			return array( 'result' => 'failure' );
		}

		try {
			$amount = $this->amount_in_toman( $order );
		} catch ( \Exception $exception ) {
			wc_add_notice( $exception->getMessage(), 'error' );
			return array( 'result' => 'failure' );
		}

		$transaction = $this->transactions->create(
			array(
				'order_id' => $order->get_id(),
				'provider' => $slug,
				'mode'     => $providers[ $slug ]['mode'],
				'amount'   => $amount,
				'currency' => 'IRT',
			)
		);
		if ( ! $transaction ) {
			wc_add_notice( 'ایجاد تراکنش با خطا روبه‌رو شد. لطفاً دوباره تلاش کنید.', 'error' );
			return array( 'result' => 'failure' );
		}

		$callback = add_query_arg(
			array(
				'tx'  => $transaction->public_id,
				'sig' => $this->signature( $transaction->public_id, $order->get_id() ),
			),
			WC()->api_request_url( $this->id )
		);
		$request  = $this->payment_request( $transaction, $order, $callback );

		try {
			$result = Registry::build( $slug, $this->settings_store, $this->logger )->request( $request );
			if ( ! $result->successful || ! $result->authority || ! $result->redirect_url ) {
				throw new \RuntimeException( 'درگاه پاسخ کامل برای شروع پرداخت برنگرداند.' );
			}
			$this->transactions->update( $transaction->id, array( 'authority' => $result->authority ) );
			$order->update_meta_data( '_mrn_ir_transaction_id', $transaction->id );
			$order->update_meta_data( '_mrn_ir_provider', $slug );
			$order->update_meta_data( '_mrn_ir_authority', $result->authority );
			$order->save();
			$order->add_order_note( sprintf( 'تراکنش MRN ایجاد شد؛ درگاه: %s، شناسه: %s', $providers[ $slug ]['name'], $result->authority ) );
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}
			return array(
				'result'   => 'success',
				'redirect' => $result->redirect_url,
			);
		} catch ( \Exception $exception ) {
			$this->transactions->update(
				$transaction->id,
				array(
					'status'         => 'failed',
					'gateway_status' => 'request_error',
				)
			);
			$this->logger->log( $transaction->id, 'request.failed', $exception->getMessage(), array(), 'error' );
			wc_add_notice( 'ارتباط با سرویس پرداخت برقرار نشد: ' . $exception->getMessage(), 'error' );
			return array( 'result' => 'failure' );
		}
	}

	public function handle_callback() {
		$public_id = isset( $_GET['tx'] ) ? sanitize_text_field( wp_unslash( $_GET['tx'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$signature = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tx        = $this->transactions->find_public( $public_id );
		if ( ! $tx || ! hash_equals( $this->signature( $public_id, $tx->order_id ), $signature ) ) {
			wp_die( esc_html__( 'نشانی بازگشت پرداخت معتبر نیست.', 'mrn-ir-payment' ), 403 );
		}

		$order = wc_get_order( $tx->order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'سفارش مرتبط با تراکنش پیدا نشد.', 'mrn-ir-payment' ), 404 );
		}
		if ( 'paid' === $tx->status || $order->is_paid() ) {
			wc_add_notice( 'این پرداخت قبلاً با موفقیت ثبت شده است.', 'notice' );
			$this->redirect_to_order( $order );
		}

		$request  = $this->payment_request( $tx, $order, $this->callback_url( $tx, $order ) );
		$callback = array_merge( wp_unslash( $_GET ), wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Gateway callbacks cannot carry a WP nonce; the URL HMAC is verified above.

		try {
			$result = Registry::build( $tx->provider, $this->settings_store, $this->logger )
				->verify( $tx->authority, $request, $callback );
		} catch ( \Exception $exception ) {
			$this->transactions->update(
				$tx->id,
				array(
					'status'         => 'unknown',
					'gateway_status' => 'verify_transport_error',
				)
			);
			$this->logger->log( $tx->id, 'verify.unknown', $exception->getMessage(), array(), 'error' );
			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$order->update_status( 'on-hold', 'وضعیت پرداخت نامشخص است و نیاز به استعلام دارد: ' . $exception->getMessage() );
			}
			wc_add_notice( 'پاسخ قطعی از درگاه دریافت نشد. سفارش شما محفوظ است و وضعیت آن بررسی می‌شود.', 'notice' );
			$this->redirect_to_order( $order );
		}

		if ( $result->successful ) {
			$this->transactions->mark_paid(
				$tx->id,
				array(
					'reference_id'   => $result->reference_id,
					'card_pan'       => $result->card_pan,
					'gateway_status' => $result->gateway_status,
				)
			);
			$order->payment_complete( $result->reference_id ? $result->reference_id : $tx->authority );
			$order->update_meta_data( '_mrn_ir_reference_id', $result->reference_id );
			$order->update_meta_data( '_mrn_ir_card_pan', $result->card_pan );
			$order->save();
			$order->add_order_note(
				sprintf(
					'پرداخت تأیید شد؛ سرویس: %1$s، مرجع: %2$s، کارت: %3$s',
					Registry::definition( $tx->provider )['name'],
					$result->reference_id,
					$result->card_pan ? $result->card_pan : '—'
				),
				true
			);
			do_action( 'mrn_ir_payment_paid', $order, $tx, $result );
			wc_add_notice( 'پرداخت با موفقیت انجام شد. سپاس از خرید شما.', 'success' );
		} else {
			$this->transactions->update(
				$tx->id,
				array(
					'status'         => $result->uncertain ? 'unknown' : 'failed',
					'gateway_status' => $result->gateway_status,
				)
			);
			$order->add_order_note( 'پرداخت تأیید نشد: ' . $result->message );
			do_action( 'mrn_ir_payment_failed', $order, $tx, $result );
			wc_add_notice( $result->message ? $result->message : 'پرداخت تکمیل نشد؛ می‌توانید دوباره تلاش کنید.', 'error' );
		}

		$this->redirect_to_order( $order );
	}

	private function selected_provider( array $providers ) {
		$selected = isset( $_POST['mrn_ir_provider'] ) ? sanitize_key( wp_unslash( $_POST['mrn_ir_provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $selected && WC()->session ) {
			$selected = sanitize_key( (string) WC()->session->get( 'mrn_ir_provider' ) );
		}
		$general = $this->settings_store->general();
		if ( ! isset( $providers[ $selected ] ) && isset( $providers[ $general['default_provider'] ] ) ) {
			$selected = $general['default_provider'];
		}
		if ( ! isset( $providers[ $selected ] ) ) {
			$selected = (string) key( $providers );
		}
		return $selected;
	}

	private function amount_in_toman( \WC_Order $order ) {
		$amount   = (float) $order->get_total();
		$currency = $order->get_currency();
		if ( 'IRR' === $currency ) {
			$amount /= 10;
		} elseif ( 'IRHR' === $currency ) {
			$amount *= 100;
		} elseif ( 'IRHT' === $currency ) {
			$amount *= 1000;
		} elseif ( 'IRT' !== $currency ) {
			throw new \RuntimeException( 'ارز سفارش توسط درگاه‌های ایرانی پشتیبانی نمی‌شود.' );
		}
		$amount = (int) round( $amount );
		if ( $amount < 1 ) {
			throw new \RuntimeException( 'مبلغ سفارش برای پرداخت معتبر نیست.' );
		}
		return $amount;
	}

	private function payment_request( $tx, \WC_Order $order, $callback ) {
		return new PaymentRequest(
			array(
				'record_id'      => $tx->id,
				'transaction_id' => $tx->public_id,
				'order_id'       => $order->get_id(),
				'amount_toman'   => $tx->amount,
				'callback_url'   => $callback,
				'description'    => sprintf( 'سفارش %d - %s', $order->get_id(), $order->get_formatted_billing_full_name() ),
				'mobile'         => $order->get_billing_phone(),
				'email'          => $order->get_billing_email(),
			)
		);
	}

	private function callback_url( $tx, \WC_Order $order ) {
		return add_query_arg(
			array(
				'tx'  => $tx->public_id,
				'sig' => $this->signature( $tx->public_id, $order->get_id() ),
			),
			WC()->api_request_url( $this->id )
		);
	}

	private function signature( $public_id, $order_id ) {
		return hash_hmac( 'sha256', $public_id . '|' . absint( $order_id ), wp_salt( 'auth' ) );
	}

	private function redirect_to_order( \WC_Order $order ) {
		wp_safe_redirect( $order->is_paid() ? $this->get_return_url( $order ) : $order->get_checkout_payment_url() );
		exit;
	}
}
