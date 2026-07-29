<?php
/**
 * Provider catalog and factory.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Gateways;

use MRN\IranPayment\Core\Settings;
use MRN\IranPayment\Infrastructure\Logger;

defined( 'ABSPATH' ) || exit;

final class Registry {
	public static function definitions() {
		$text     = array(
			'type'     => 'text',
			'required' => true,
			'secret'   => false,
		);
		$secret   = array(
			'type'     => 'password',
			'required' => true,
			'secret'   => true,
		);
		$checkbox = array(
			'type'     => 'checkbox',
			'required' => false,
			'secret'   => false,
		);

		$definitions = array(
			'zarinpal'      => array(
				'name'         => 'زرین‌پال',
				'description'  => 'درگاه مستقیم و مطمئن با واحد تومان',
				'mode'         => 'online',
				'accent'       => '#6f4bf2',
				'capabilities' => array( 'پرداخت آنلاین', 'تأیید مجدد', 'شناسه مرجع' ),
				'fields'       => array(
					'merchant_id' => array_merge( $secret, array( 'label' => 'Merchant ID' ) ),
				),
			),
			'zibal'         => array(
				'name'         => 'زیبال',
				'description'  => 'درگاه آنلاین با مسیر جایگزین برای هاست خارجی',
				'mode'         => 'online',
				'accent'       => '#ed164b',
				'capabilities' => array( 'پرداخت آنلاین', 'تأیید تکراری', 'شماره کارت ماسک‌شده' ),
				'fields'       => array(
					'merchant'      => array_merge( $secret, array( 'label' => 'کلید API' ) ),
					'non_iran_host' => array_merge( $checkbox, array( 'label' => 'هاست خارج از ایران' ) ),
				),
			),
			'vandar'        => array(
				'name'         => 'وندار',
				'description'  => 'درگاه پرداخت وندار با انتخاب پورت پذیرندگی',
				'mode'         => 'online',
				'accent'       => '#1976d2',
				'capabilities' => array( 'پرداخت آنلاین', 'پورت سامان', 'پورت به‌پرداخت' ),
				'fields'       => array(
					'api_key' => array_merge( $secret, array( 'label' => 'کلید وب‌سرویس' ) ),
					'port'    => array(
						'label'    => 'پورت',
						'type'     => 'select',
						'required' => false,
						'secret'   => false,
						'options'  => array(
							''            => 'پیش‌فرض',
							'SAMAN'       => 'سامان',
							'BEHPARDAKHT' => 'به‌پرداخت',
						),
					),
				),
			),
			'payping'       => array(
				'name'         => 'پی‌پینگ',
				'description'  => 'درگاه پرداخت مبتنی بر توکن Bearer',
				'mode'         => 'online',
				'accent'       => '#00a8a8',
				'capabilities' => array( 'پرداخت آنلاین', 'تأیید امن', 'اطلاعات کارت' ),
				'fields'       => array(
					'token' => array_merge( $secret, array( 'label' => 'توکن درگاه' ) ),
				),
			),
			'sepal'         => array(
				'name'         => 'سپال',
				'description'  => 'درگاه پرداخت سپال با دامنه جایگزین',
				'mode'         => 'online',
				'accent'       => '#ff7a00',
				'capabilities' => array( 'پرداخت آنلاین', 'مسیر هاست خارجی' ),
				'fields'       => array(
					'api_key'       => array_merge( $secret, array( 'label' => 'کلید وب‌سرویس' ) ),
					'non_iran_host' => array_merge( $checkbox, array( 'label' => 'هاست خارج از ایران' ) ),
				),
			),
			'aqayepardakht' => array(
				'name'         => 'آقای پرداخت',
				'description'  => 'درگاه واسط با محیط Sandbox داخلی',
				'mode'         => 'online',
				'accent'       => '#2b9348',
				'capabilities' => array( 'پرداخت آنلاین', 'Sandbox', 'تأیید مبلغ' ),
				'fields'       => array(
					'pin' => array_merge(
						$secret,
						array(
							'label' => 'PIN درگاه',
							'help'  => 'برای آزمایش می‌توانید sandbox وارد کنید.',
						)
					),
				),
			),
			'custom_online' => self::custom_definition(
				'درگاه سازمانی REST',
				'اتصال منعطف به PSP، بانک یا پرداخت‌یار دارای API مبتنی بر JSON',
				'online',
				'#334155'
			),
			'custom_bnpl'   => self::custom_definition(
				'پرداخت اقساطی / BNPL',
				'اتصال منعطف به اسنپ‌پی، دیجی‌پی، تارا، ازکی‌وام و قراردادهای سازمانی',
				'installment',
				'#b7791f'
			),
		);

		return apply_filters( 'mrn_ir_payment_provider_definitions', $definitions );
	}

	private static function custom_definition( $name, $description, $mode, $accent ) {
		return array(
			'name'         => $name,
			'description'  => $description,
			'mode'         => $mode,
			'accent'       => $accent,
			'capabilities' => 'installment' === $mode
				? array( 'BNPL', 'اقساط', 'قرارداد اختصاصی', 'JSON API' )
				: array( 'PSP سازمانی', 'JSON API', 'هدر اختصاصی' ),
			'fields'       => array(
				'request_url'      => array(
					'label'    => 'URL ایجاد پرداخت',
					'type'     => 'url',
					'required' => true,
					'secret'   => false,
				),
				'verify_url'       => array(
					'label'    => 'URL تأیید پرداخت',
					'type'     => 'url',
					'required' => true,
					'secret'   => false,
				),
				'redirect_url'     => array(
					'label'    => 'الگوی URL انتقال',
					'type'     => 'text',
					'required' => true,
					'secret'   => false,
					'help'     => 'از {{authority}} در محل توکن استفاده کنید.',
				),
				'auth_header'      => array(
					'label'    => 'نام هدر احراز هویت',
					'type'     => 'text',
					'required' => false,
					'secret'   => false,
				),
				'auth_token'       => array(
					'label'    => 'مقدار هدر / توکن',
					'type'     => 'password',
					'required' => false,
					'secret'   => true,
				),
				'request_template' => array(
					'label'    => 'قالب JSON درخواست',
					'type'     => 'textarea',
					'required' => true,
					'secret'   => false,
					'default'  => '{"amount": "{{amount_rial}}", "orderId": "{{order_id}}", "callbackUrl": "{{callback_url}}", "mobile": "{{mobile}}"}',
				),
				'authority_path'   => array(
					'label'    => 'مسیر توکن در پاسخ',
					'type'     => 'text',
					'required' => true,
					'secret'   => false,
					'default'  => 'data.token',
				),
				'verify_template'  => array(
					'label'    => 'قالب JSON تأیید',
					'type'     => 'textarea',
					'required' => true,
					'secret'   => false,
					'default'  => '{"token": "{{authority}}", "amount": "{{amount_rial}}"}',
				),
				'success_path'     => array(
					'label'    => 'مسیر وضعیت موفق',
					'type'     => 'text',
					'required' => true,
					'secret'   => false,
					'default'  => 'data.status',
				),
				'success_values'   => array(
					'label'    => 'مقادیر موفق (با ویرگول)',
					'type'     => 'text',
					'required' => true,
					'secret'   => false,
					'default'  => 'SUCCESS,PAID,100,1',
				),
				'reference_path'   => array(
					'label'    => 'مسیر کد مرجع',
					'type'     => 'text',
					'required' => true,
					'secret'   => false,
					'default'  => 'data.referenceId',
				),
				'card_path'        => array(
					'label'    => 'مسیر شماره کارت (اختیاری)',
					'type'     => 'text',
					'required' => false,
					'secret'   => false,
					'default'  => 'data.cardNumber',
				),
			),
		);
	}

	public static function definition( $slug ) {
		$definitions = self::definitions();
		return isset( $definitions[ $slug ] ) ? $definitions[ $slug ] : array();
	}

	public static function is_configured( $slug, array $config ) {
		$definition = self::definition( $slug );
		if ( empty( $definition ) ) {
			return false;
		}
		foreach ( $definition['fields'] as $key => $field ) {
			if ( ! empty( $field['required'] ) && '' === trim( (string) ( isset( $config[ $key ] ) ? $config[ $key ] : '' ) ) ) {
				return false;
			}
		}
		return true;
	}

	public static function build( $slug, Settings $settings, Logger $logger ) {
		$config = $settings->provider( $slug );
		$object = in_array( $slug, array( 'custom_online', 'custom_bnpl' ), true )
			? new CustomRestGateway( $slug, $config, $logger )
			: new StandardGateway( $slug, $config, $logger );
		return apply_filters( 'mrn_ir_payment_gateway_instance', $object, $slug, $config, $logger );
	}
}
