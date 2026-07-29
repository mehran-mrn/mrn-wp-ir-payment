<?php
/**
 * Complete Iranian provider catalog.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Gateways;

defined( 'ABSPATH' ) || exit;

final class ProviderCatalog {
	public static function definitions() {
		$catalog = array(
			'aqayepardakht' => array( 'آقای پرداخت', 'درگاه پرداخت‌یاری با محیط آزمایشی داخلی', 'online', '#24945d', 'native' ),
			'asanpardakht'  => array( 'آسان پرداخت', 'درگاه مستقیم آپ با تأیید، تسویه و تطبیق موبایل', 'bank', '#ef3f52', 'native' ),
			'azkivam'       => array( 'ازکی‌وام', 'خرید اعتباری و اقساطی ازکی‌وام', 'installment', '#5a46e8', 'contract' ),
			'bitpay'        => array( 'بیت‌پی', 'درگاه پرداخت واسط بیت‌پی', 'online', '#289dc0', 'native' ),
			'card_to_card'  => array( 'کارت به کارت', 'ثبت رسید و تأیید دستی یا سازمانی کارت به کارت', 'card', '#64748b', 'contract' ),
			'daracard'      => array( 'داراکارت', 'پرداخت اعتباری داراکارت', 'installment', '#f59e0b', 'contract' ),
			'digipay'       => array( 'دیجی‌پی', 'درگاه اعتباری و پرداخت اقساطی دیجی‌پی', 'installment', '#00a7c4', 'contract' ),
			'directpay'     => array( 'دایرکت‌پی', 'درگاه پرداخت مستقیم مبتنی بر امضای HMAC', 'online', '#3154d5', 'native' ),
			'eghtesadnovin' => array( 'اقتصاد نوین', 'پرداخت نوین آرین؛ درگاه مستقیم بانک اقتصاد نوین', 'bank', '#7c3aed', 'contract' ),
			'gateland_test' => array( 'درگاه آزمایشی', 'پروفایل کنترل کیفیت برای محیط staging و قراردادهای تست', 'test', '#475569', 'contract' ),
			'idpay'         => array( 'آیدی‌پی', 'درگاه پرداخت و لینک پرداخت IDPay', 'online', '#2563eb', 'preset' ),
			'irandargah'    => array( 'ایران درگاه', 'درگاه پرداخت ایران‌درگاه', 'online', '#10b981', 'contract' ),
			'irankish'      => array( 'ایران‌کیش', 'درگاه مستقیم ایران‌کیش با کلید عمومی پذیرنده', 'bank', '#dc2626', 'contract' ),
			'jibit'         => array( 'جیبیت', 'درگاه پرداخت جیبیت با توکن Bearer و تطبیق موبایل', 'online', '#9333ea', 'native' ),
			'mellat'        => array( 'بانک ملت', 'به‌پرداخت ملت؛ درگاه مستقیم بانکی', 'bank', '#e11d48', 'contract' ),
			'nabikpay'      => array( 'نابیک‌پی', 'اتصال به هاب پرداخت سازگار با پروتکل نابیک‌پی', 'online', '#4338ca', 'native' ),
			'neogate'       => array( 'نئوگیت', 'تأیید خودکار کارت به کارت نئوگیت', 'card', '#0f766e', 'native' ),
			'nextpay'       => array( 'نکست‌پی', 'درگاه پرداخت نکست‌پی', 'online', '#0ea5e9', 'preset' ),
			'nopay'         => array( 'نوپی', 'پرداخت اعتباری نوپی', 'installment', '#111827', 'contract' ),
			'novinopay'     => array( 'نوینو', 'درگاه پرداخت نوینوپی', 'online', '#8b5cf6', 'contract' ),
			'novinpal'      => array( 'نوین‌پال', 'درگاه پرداخت نوین‌پال', 'online', '#7c3aed', 'contract' ),
			'omidpay'       => array( 'امیدپی', 'سایان‌کارت و بانک سپه', 'bank', '#0369a1', 'contract' ),
			'panapal'       => array( 'پاناپال', 'درگاه پرداخت پاناپال', 'online', '#ea580c', 'contract' ),
			'parsian'       => array( 'بانک پارسیان', 'تجارت الکترونیک پارسیان؛ درگاه مستقیم بانکی', 'bank', '#dc2626', 'contract' ),
			'parspal'       => array( 'پارس‌پال', 'درگاه پرداخت پارس‌پال', 'online', '#2563eb', 'contract' ),
			'pasargad'      => array( 'بانک پاسارگاد', 'پرداخت الکترونیک پاسارگاد با API جدید PEPG', 'bank', '#f97316', 'native' ),
			'payfa'         => array( 'پی‌فا', 'درگاه پرداخت پی‌فا', 'online', '#06b6d4', 'contract' ),
			'payping'       => array( 'پی‌پینگ', 'درگاه پرداخت مبتنی بر توکن Bearer', 'online', '#00a8a8', 'native' ),
			'paystar'       => array( 'پی‌استار', 'درگاه پرداخت با امضای HMAC-SHA512', 'online', '#2563eb', 'native' ),
			'refah'         => array( 'بانک رفاه', 'درگاه مستقیم بانک رفاه کارگران', 'bank', '#0f766e', 'contract' ),
			'resalat'       => array( 'بانک رسالت', 'درگاه مستقیم بانک قرض‌الحسنه رسالت', 'bank', '#166534', 'contract' ),
			'sadad'         => array( 'بانک ملی (سداد)', 'درگاه پرداخت سداد بانک ملی', 'bank', '#dc2626', 'contract' ),
			'saderat'       => array( 'بانک صادرات', 'پرداخت الکترونیک سپهر؛ درگاه بانک صادرات', 'bank', '#1d4ed8', 'contract' ),
			'saman'         => array( 'بانک سامان', 'پرداخت الکترونیک سامان و سرویس سریع بلوپی', 'bank', '#0284c7', 'native' ),
			'sepal'         => array( 'سپال', 'درگاه پرداخت سپال با دامنه جایگزین', 'online', '#ff7a00', 'native' ),
			'sepordeh'      => array( 'سپرده', 'درگاه پرداخت سپرده', 'online', '#16a34a', 'contract' ),
			'shepa'         => array( 'شپا', 'درگاه پرداخت شپا با وب‌سرویس JSON', 'online', '#4f46e5', 'native' ),
			'sizpay'        => array( 'سیزپی', 'درگاه پرداخت سیزپی', 'online', '#0891b2', 'native' ),
			'snapppay'      => array( 'اسنپ‌پی', 'پرداخت اعتباری و اقساطی اسنپ‌پی', 'installment', '#00d170', 'contract' ),
			'tara'          => array( 'تارا', 'اعتبار خرید و پرداخت اقساطی تارا', 'installment', '#ec4899', 'contract' ),
			'torobpay'      => array( 'ترب‌پی', 'پرداخت اعتباری ترب‌پی', 'installment', '#ef4444', 'contract' ),
			'vanda'         => array( 'وندا پرداخت', 'درگاه پرداخت وندا', 'online', '#0d9488', 'contract' ),
			'vandar'        => array( 'وندار', 'درگاه پرداخت وندار با انتخاب پورت پذیرندگی', 'online', '#1976d2', 'native' ),
			'yektapay'      => array( 'یکتاپی', 'درگاه پرداخت یکتاپی', 'online', '#7c3aed', 'contract' ),
			'zarinpal'      => array( 'زرین‌پال', 'درگاه پرداخت زرین‌پال با API نسل چهار', 'online', '#6f4bf2', 'native' ),
			'zarinplus'     => array( 'زرین‌پلاس', 'پرداخت اعتباری زرین‌پلاس', 'installment', '#f4b000', 'contract' ),
			'zibal'         => array( 'زیبال', 'درگاه پرداخت زیبال با مسیر جایگزین هاست خارجی', 'online', '#ed164b', 'native' ),
		);

		$definitions = array();
		foreach ( $catalog as $slug => $item ) {
			$definitions[ $slug ] = array(
				'name'           => $item[0],
				'description'    => $item[1],
				'mode'           => in_array( $item[2], array( 'installment', 'card' ), true ) ? 'installment' : 'online',
				'group'          => $item[2],
				'accent'         => $item[3],
				'implementation' => $item[4],
				'capabilities'   => self::capabilities( $item[2], $item[4] ),
				'fields'         => self::fields( $slug ),
			);
		}

		$definitions['custom_online'] = self::custom_definition(
			'درگاه سازمانی REST',
			'اتصال منعطف به PSP، بانک یا پرداخت‌یار دارای API مبتنی بر JSON یا فرم',
			'online',
			'#334155'
		);
		$definitions['custom_bnpl']   = self::custom_definition(
			'پرداخت اقساطی سفارشی',
			'اتصال منعطف به قرارداد BNPL یا اعتبار سازمانی',
			'installment',
			'#b7791f'
		);

		return $definitions;
	}

	private static function capabilities( $group, $implementation ) {
		if ( 'installment' === $group ) {
			return array( 'BNPL', 'اقساط', 'قرارداد پذیرندگی', 'تأیید سمت سرور' );
		}
		if ( 'card' === $group ) {
			return array( 'کارت به کارت', 'استعلام', 'تأیید رسید' );
		}
		if ( 'bank' === $group ) {
			return array( 'درگاه مستقیم', 'شاپرک', 'تأیید بانکی', 'ضدتکرار' );
		}
		if ( 'test' === $group ) {
			return array( 'Staging', 'تست قرارداد', 'غیرفعال در تولید' );
		}
		return 'native' === $implementation
			? array( 'پرداخت آنلاین', 'تأیید امن', 'ضدتکرار', 'کد مرجع' )
			: array( 'پرداخت آنلاین', 'قرارداد API', 'تأیید سمت سرور' );
	}

	private static function fields( $slug ) {
		$secret   = static function ( $label, $help = '' ) {
			return array_filter(
				array(
					'label'    => $label,
					'type'     => 'password',
					'required' => true,
					'secret'   => true,
					'help'     => $help,
				)
			);
		};
		$text     = static function ( $label, $required = true, $help = '', $default_value = '' ) {
			return array_filter(
				array(
					'label'    => $label,
					'type'     => 'text',
					'required' => $required,
					'secret'   => false,
					'help'     => $help,
					'default'  => $default_value,
				)
			);
		};
		$checkbox = static function ( $label ) {
			return array(
				'label'    => $label,
				'type'     => 'checkbox',
				'required' => false,
				'secret'   => false,
			);
		};

		$native = array(
			'aqayepardakht' => array( 'pin' => $secret( 'PIN درگاه', 'برای آزمایش می‌توانید sandbox وارد کنید.' ) ),
			'asanpardakht'  => array(
				'username'               => $secret( 'نام کاربری وب‌سرویس' ),
				'password'               => $secret( 'رمز عبور وب‌سرویس' ),
				'merchant'               => $text( 'کد پیکربندی پذیرنده' ),
				'match_card_with_mobile' => $checkbox( 'تطبیق مالک موبایل و کارت' ),
			),
			'bitpay'        => array( 'api' => $secret( 'کلید API' ) ),
			'directpay'     => array(
				'gateway_id'     => $secret( 'شناسه درگاه' ),
				'encryption_key' => $secret( 'کلید رمزنگاری HMAC' ),
				'non_iran_host'  => $checkbox( 'هاست خارج از ایران' ),
			),
			'jibit'         => array(
				'api_key'                => $secret( 'کلید API' ),
				'secret_key'             => $secret( 'کلید Secret' ),
				'match_card_with_mobile' => $checkbox( 'تطبیق مالک موبایل و کارت' ),
			),
			'nabikpay'      => array(
				'base_url' => array(
					'label'    => 'آدرس پایه هاب پرداخت',
					'type'     => 'url',
					'required' => true,
					'secret'   => false,
				),
				'merchant' => $secret( 'کلید پذیرنده' ),
			),
			'neogate'       => array(
				'merchant'        => $secret( 'مرچنت' ),
				'expiration_time' => $text( 'مهلت پرداخت (دقیقه)', false ),
			),
			'pasargad'      => array(
				'username'     => $secret( 'نام کاربری' ),
				'password'     => $secret( 'کلمه عبور' ),
				'terminal'     => $text( 'شماره ترمینال' ),
				'merchant_id'  => $text( 'شماره فروشگاه (Merchant ID)' ),
				'base_url'     => array(
					'label'    => 'آدرس پایه API',
					'type'     => 'url',
					'required' => true,
					'secret'   => false,
					'default'  => 'https://pep.shaparak.ir/pepg',
					'help'     => 'پیش‌فرض نسخه جدید pepg است؛ فقط مطابق قرارداد پذیرندگی تغییر دهید.',
				),
				'token_path'   => $text( 'مسیر دریافت توکن', true, '', '/token/getToken' ),
				'request_path' => $text( 'مسیر ایجاد پرداخت', true, '', '/api/payment/purchase' ),
				'verify_path'  => $text( 'مسیر تأیید پرداخت', true, '', '/api/payment/verify-transactions' ),
			),
			'payping'       => array( 'token' => $secret( 'توکن درگاه' ) ),
			'paystar'       => array(
				'gateway_id'     => $secret( 'شناسه درگاه' ),
				'encryption_key' => $secret( 'کلید رمزنگاری HMAC' ),
				'non_iran_host'  => $checkbox( 'هاست خارج از ایران' ),
			),
			'saman'         => array(
				'terminal_id' => $text( 'شماره ترمینال (MID)' ),
				'blupay'      => $checkbox( 'فعال‌سازی پرداخت سریع بلوپی' ),
			),
			'sepal'         => array(
				'api_key'       => $secret( 'کلید وب‌سرویس' ),
				'non_iran_host' => $checkbox( 'هاست خارج از ایران' ),
			),
			'shepa'         => array( 'api' => $secret( 'کلید API شپا' ) ),
			'sizpay'        => array( 'key' => $secret( 'کلید اصلی سیزپی' ) ),
			'vandar'        => array(
				'api_key' => $secret( 'کلید وب‌سرویس' ),
				'port'    => array(
					'label'    => 'پورت پذیرندگی',
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
			'zarinpal'      => array( 'merchant_id' => $secret( 'Merchant ID' ) ),
			'zibal'         => array(
				'merchant'      => $secret( 'کلید API / Merchant' ),
				'non_iran_host' => $checkbox( 'هاست خارج از ایران' ),
			),
		);

		if ( isset( $native[ $slug ] ) ) {
			return $native[ $slug ];
		}

		$credential_maps = array(
			'eghtesadnovin' => array(
				'terminal' => 'شماره ترمینال',
				'username' => 'نام کاربری',
				'password' => 'کلمه عبور',
			),
			'idpay'         => array( 'api_key' => 'کلید وب‌سرویس' ),
			'irandargah'    => array( 'merchant' => 'Merchant Code' ),
			'irankish'      => array(
				'terminal_id' => 'شناسه ترمینال',
				'password'    => 'رمز عبور',
				'acceptor_id' => 'شناسه پذیرنده',
				'public_key'  => 'کلید عمومی',
			),
			'mellat'        => array(
				'terminal_id' => 'شناسه ترمینال',
				'username'    => 'نام کاربری',
				'password'    => 'کلمه عبور',
			),
			'nextpay'       => array( 'api_key' => 'کلید وب‌سرویس' ),
			'novinopay'     => array( 'merchant_id' => 'کد درگاه' ),
			'novinpal'      => array( 'api_key' => 'کلید API' ),
			'omidpay'       => array(
				'username' => 'نام کاربری',
				'password' => 'کلمه عبور',
				'mid'      => 'MID',
			),
			'panapal'       => array( 'merchant_id' => 'Merchant ID' ),
			'parsian'       => array( 'login_account' => 'Login Account' ),
			'parspal'       => array( 'api_key' => 'کلید وب‌سرویس' ),
			'payfa'         => array( 'api_key' => 'کلید وب‌سرویس' ),
			'refah'         => array( 'terminal_id' => 'شناسه ترمینال' ),
			'resalat'       => array( 'terminal_id' => 'شناسه ترمینال' ),
			'sadad'         => array(
				'merchant_id'      => 'شناسه پذیرنده',
				'terminal_id'      => 'شناسه ترمینال',
				'key'              => 'کلید رمزنگاری',
				'payment_identity' => 'شناسه پرداخت',
			),
			'saderat'       => array( 'terminal_id' => 'شناسه ترمینال' ),
			'sepordeh'      => array( 'merchant' => 'مرچنت' ),
			'vanda'         => array( 'merchant_code' => 'کد پذیرنده' ),
		);
		$credentials     = array();
		foreach ( isset( $credential_maps[ $slug ] ) ? $credential_maps[ $slug ] : array( 'contract_key' => 'کلید قرارداد / API' ) as $key => $label ) {
			$credentials[ $key ] = $secret( $label );
		}

		return array_merge( $credentials, self::adapter_fields( self::preset( $slug ) ) );
	}

	private static function preset( $slug ) {
		$presets = array(
			'idpay'   => array(
				'request_url'      => 'https://api.idpay.ir/v1.1/payment',
				'verify_url'       => 'https://api.idpay.ir/v1.1/payment/verify',
				'redirect_url'     => 'https://idpay.ir/p/ws/{{authority}}',
				'headers_template' => '{"X-API-KEY":"{{config.api_key}}","X-SANDBOX":"0"}',
				'request_template' => '{"order_id":"{{transaction_id}}","amount":"{{amount_rial}}","name":"{{description}}","phone":"{{mobile}}","mail":"{{email}}","desc":"{{description}}","callback":"{{callback_url}}"}',
				'authority_path'   => 'id',
				'verify_template'  => '{"id":"{{authority}}","order_id":"{{transaction_id}}"}',
				'success_path'     => 'status',
				'success_values'   => '100,101,200',
				'reference_path'   => 'track_id',
				'card_path'        => 'payment.card_no',
			),
			'nextpay' => array(
				'request_url'      => 'https://nextpay.org/nx/gateway/token',
				'verify_url'       => 'https://nextpay.org/nx/gateway/verify',
				'redirect_url'     => 'https://nextpay.org/nx/gateway/payment/{{authority}}',
				'request_format'   => 'form',
				'request_template' => '{"api_key":"{{config.api_key}}","order_id":"{{transaction_id}}","amount":"{{amount_toman}}","callback_uri":"{{callback_url}}"}',
				'authority_path'   => 'trans_id',
				'verify_template'  => '{"api_key":"{{config.api_key}}","order_id":"{{transaction_id}}","amount":"{{amount_toman}}","trans_id":"{{authority}}"}',
				'success_path'     => 'code',
				'success_values'   => '-1,0',
				'reference_path'   => 'Shaparak_Ref_Id',
			),
		);
		return isset( $presets[ $slug ] ) ? $presets[ $slug ] : array();
	}

	public static function custom_definition( $name, $description, $mode, $accent ) {
		return array(
			'name'           => $name,
			'description'    => $description,
			'mode'           => $mode,
			'group'          => 'installment' === $mode ? 'installment' : 'online',
			'accent'         => $accent,
			'implementation' => 'contract',
			'capabilities'   => 'installment' === $mode
				? array( 'BNPL', 'اقساط', 'قرارداد اختصاصی', 'JSON / Form API' )
				: array( 'PSP سازمانی', 'JSON / Form API', 'هدر اختصاصی' ),
			'fields'         => self::adapter_fields(),
		);
	}

	private static function adapter_fields( array $defaults = array() ) {
		$field = static function ( $label, $type, $required, $default_value = '', $help = '', $options = array() ) {
			return array_filter(
				array(
					'label'    => $label,
					'type'     => $type,
					'required' => $required,
					'secret'   => false,
					'default'  => $default_value,
					'help'     => $help,
					'options'  => $options,
				),
				static function ( $value ) {
					return '' !== $value && array() !== $value;
				}
			);
		};
		$get   = static function ( $key, $fallback ) use ( $defaults ) {
			return isset( $defaults[ $key ] ) ? $defaults[ $key ] : $fallback;
		};

		return array(
			'request_url'      => $field( 'URL ایجاد پرداخت', 'url', true, $get( 'request_url', '' ) ),
			'verify_url'       => $field( 'URL تأیید پرداخت', 'url', true, $get( 'verify_url', '' ) ),
			'redirect_url'     => $field( 'الگوی URL انتقال', 'text', true, $get( 'redirect_url', '' ), 'از {{authority}} برای توکن استفاده کنید.' ),
			'request_format'   => $field(
				'قالب ارسال',
				'select',
				false,
				$get( 'request_format', 'json' ),
				'',
				array(
					'json' => 'JSON',
					'form' => 'Form URL Encoded',
				)
			),
			'headers_template' => $field( 'هدرهای درخواست (JSON)', 'textarea', false, $get( 'headers_template', '{}' ), 'مقادیر محرمانه را با {{config.key}} ارجاع دهید.' ),
			'request_template' => $field( 'قالب درخواست (JSON)', 'textarea', true, $get( 'request_template', '{"amount":"{{amount_rial}}","orderId":"{{order_id}}","callbackUrl":"{{callback_url}}","mobile":"{{mobile}}"}' ) ),
			'authority_path'   => $field( 'مسیر توکن در پاسخ', 'text', true, $get( 'authority_path', 'data.token' ) ),
			'verify_template'  => $field( 'قالب تأیید (JSON)', 'textarea', true, $get( 'verify_template', '{"token":"{{authority}}","amount":"{{amount_rial}}"}' ) ),
			'success_path'     => $field( 'مسیر وضعیت موفق', 'text', true, $get( 'success_path', 'data.status' ) ),
			'success_values'   => $field( 'مقادیر موفق (با ویرگول)', 'text', true, $get( 'success_values', 'SUCCESS,PAID,100,1' ) ),
			'reference_path'   => $field( 'مسیر کد مرجع', 'text', true, $get( 'reference_path', 'data.referenceId' ) ),
			'card_path'        => $field( 'مسیر شماره کارت (اختیاری)', 'text', false, $get( 'card_path', 'data.cardNumber' ) ),
		);
	}
}
