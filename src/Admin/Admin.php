<?php
/**
 * Refined RTL administration experience.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Admin;

use MRN\IranPayment\Core\Settings;
use MRN\IranPayment\Gateways\Registry;
use MRN\IranPayment\Infrastructure\Repository;

defined( 'ABSPATH' ) || exit;

final class Admin {
	private $settings;
	private $transactions;

	public function __construct( Settings $settings, Repository $transactions ) {
		$this->settings     = $settings;
		$this->transactions = $transactions;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_mrn_ir_save_general', array( $this, 'save_general' ) );
		add_action( 'admin_post_mrn_ir_save_provider', array( $this, 'save_provider' ) );
		add_action( 'admin_post_mrn_ir_export', array( $this, 'export' ) );
		add_action( 'wp_ajax_mrn_ir_check_provider', array( $this, 'check_provider' ) );
	}

	public function menu() {
		add_menu_page(
			'MRN پرداخت ایران',
			'MRN پرداخت',
			'manage_woocommerce',
			'mrn-ir-payment',
			array( $this, 'render' ),
			'dashicons-money-alt',
			56
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_mrn-ir-payment' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'mrn-ir-payment-admin', MRN_IR_PAYMENT_URL . 'assets/css/admin.css', array(), MRN_IR_PAYMENT_VERSION );
		wp_enqueue_script( 'mrn-ir-payment-admin', MRN_IR_PAYMENT_URL . 'assets/js/admin.js', array(), MRN_IR_PAYMENT_VERSION, true );
		wp_localize_script(
			'mrn-ir-payment-admin',
			'mrnIrPayment',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mrn_ir_check_provider' ),
				'texts'   => array(
					'checking' => 'در حال بررسی…',
					'error'    => 'بررسی پیکربندی انجام نشد.',
				),
			)
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'mrn-ir-payment' ) );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap mrn-ir-admin" dir="rtl">
			<header class="mrn-ir-hero">
				<div>
					<span class="mrn-ir-eyebrow">MRN · PAYMENT OPERATIONS</span>
					<h1>مرکز پرداخت ایران</h1>
					<p>کنترل شفاف پرداخت آنلاین و اقساطی؛ از اتصال درگاه تا پایش تراکنش.</p>
				</div>
				<div class="mrn-ir-hero-mark" aria-hidden="true"><span>م</span></div>
			</header>
			<nav class="mrn-ir-tabs" aria-label="بخش‌های مرکز پرداخت">
				<?php
				$tabs = array(
					'dashboard'    => array( 'داشبورد', 'chart-area' ),
					'gateways'     => array( 'درگاه‌ها', 'admin-links' ),
					'transactions' => array( 'تراکنش‌ها', 'list-view' ),
					'settings'     => array( 'تنظیمات', 'admin-generic' ),
					'tools'        => array( 'سلامت سیستم', 'shield-alt' ),
				);
				foreach ( $tabs as $slug => $item ) :
					$url = add_query_arg(
						array(
							'page' => 'mrn-ir-payment',
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="<?php echo $tab === $slug ? 'active' : ''; ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $item[1] ); ?>"></span><?php echo esc_html( $item[0] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php $this->notice(); ?>
			<main class="mrn-ir-content">
				<?php
				switch ( $tab ) {
					case 'gateways':
						$this->gateways();
						break;
					case 'transactions':
						$this->transactions();
						break;
					case 'settings':
						$this->general_settings();
						break;
					case 'tools':
						$this->tools();
						break;
					default:
						$this->dashboard();
				}
				?>
			</main>
		</div>
		<?php
	}

	private function dashboard() {
		$stats        = $this->transactions->stats();
		$enabled      = $this->settings->enabled_providers();
		$success_rate = $stats['total'] ? round( ( (int) $stats['paid'] / (int) $stats['total'] ) * 100, 1 ) : 0;
		?>
		<section class="mrn-ir-section-head">
			<div><h2>نمای عملیاتی</h2><p>تصویری سریع از وضعیت پرداخت‌های فروشگاه</p></div>
			<a class="mrn-ir-button primary" href="<?php echo esc_url( admin_url( 'admin.php?page=mrn-ir-payment&tab=gateways' ) ); ?>">مدیریت درگاه‌ها</a>
		</section>
		<div class="mrn-ir-stat-grid">
			<?php
			$this->stat_card( 'حجم پرداخت موفق', number_format_i18n( (int) $stats['volume'] ) . ' تومان', 'money-alt', 'green' );
			$this->stat_card( 'نرخ موفقیت', $success_rate . '٪', 'chart-line', 'violet' );
			$this->stat_card( 'تراکنش‌ها', number_format_i18n( (int) $stats['total'] ), 'tickets-alt', 'blue' );
			$this->stat_card( 'نیازمند بررسی', number_format_i18n( (int) $stats['unknown_count'] ), 'warning', 'amber' );
			?>
		</div>
		<div class="mrn-ir-dashboard-grid">
			<section class="mrn-ir-card">
				<div class="mrn-ir-card-head"><div><h3>اتصال‌های فعال</h3><p><?php echo esc_html( count( $enabled ) ); ?> سرویس آماده‌ی تسویه‌حساب</p></div><span class="mrn-ir-live"><i></i> زنده</span></div>
				<?php if ( $enabled ) : ?>
					<div class="mrn-ir-provider-list">
						<?php foreach ( $enabled as $slug => $provider ) : ?>
							<div><span class="mrn-ir-logo" style="--accent:<?php echo esc_attr( $provider['accent'] ); ?>"><?php echo esc_html( mb_substr( $provider['name'], 0, 1 ) ); ?></span><span><strong><?php echo esc_html( $provider['name'] ); ?></strong><small><?php echo 'installment' === $provider['mode'] ? 'اقساطی / BNPL' : 'پرداخت آنلاین'; ?></small></span><span class="mrn-ir-badge success">آماده</span></div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="mrn-ir-empty"><span class="dashicons dashicons-admin-links"></span><h4>هنوز درگاهی فعال نشده</h4><p>یک سرویس را پیکربندی کنید تا در تسویه‌حساب نمایش داده شود.</p></div>
				<?php endif; ?>
			</section>
			<section class="mrn-ir-card mrn-ir-insight">
				<span class="dashicons dashicons-shield"></span>
				<h3>پرداخت قابل اعتماد</h3>
				<p>بازگشت‌ها امضاشده‌اند، تأیید پرداخت تکرارپذیر است و خطای شبکه هرگز به‌اشتباه سفارش را ناموفق اعلام نمی‌کند.</p>
				<ul><li>رمزنگاری اطلاعات پذیرنده</li><li>ماسک خودکار داده حساس در لاگ</li><li>سازگار با HPOS و Checkout Blocks</li></ul>
			</section>
		</div>
		<?php
	}

	private function stat_card( $label, $value, $icon, $color ) {
		printf(
			'<section class="mrn-ir-stat %1$s"><span class="dashicons dashicons-%2$s"></span><div><small>%3$s</small><strong>%4$s</strong></div></section>',
			esc_attr( $color ),
			esc_attr( $icon ),
			esc_html( $label ),
			esc_html( $value )
		);
	}

	private function gateways() {
		$definitions = Registry::definitions();
		$native      = array_filter(
			$definitions,
			static function ( $definition ) {
				return 'native' === ( isset( $definition['implementation'] ) ? $definition['implementation'] : '' );
			}
		);
		?>
		<section class="mrn-ir-section-head"><div><h2>درگاه‌ها و سرویس‌های اعتباری</h2><p><?php echo esc_html( count( $definitions ) ); ?> اتصال در کاتالوگ؛ <?php echo esc_html( count( $native ) ); ?> درگاه با پروتکل داخلی و سایر سرویس‌ها با موتور قرارداد API</p></div></section>
		<div class="mrn-ir-gateway-toolbar">
			<label class="mrn-ir-search"><span class="dashicons dashicons-search"></span><input type="search" id="mrn-ir-gateway-search" placeholder="جست‌وجوی نام درگاه یا سرویس…"></label>
			<div class="mrn-ir-filter-chips" role="group" aria-label="فیلتر نوع درگاه">
				<button type="button" class="active" data-filter="all">همه <b><?php echo esc_html( count( $definitions ) ); ?></b></button>
				<button type="button" data-filter="online">پرداخت‌یار</button>
				<button type="button" data-filter="bank">بانکی</button>
				<button type="button" data-filter="installment">اقساطی</button>
				<button type="button" data-filter="card">کارت به کارت</button>
			</div>
		</div>
		<div class="mrn-ir-gateway-grid">
			<?php foreach ( $definitions as $slug => $definition ) : ?>
				<?php
				$config     = $this->settings->provider( $slug, false );
				$configured = Registry::is_configured( $slug, $this->settings->provider( $slug ) );
				$search     = $slug . ' ' . $definition['name'] . ' ' . $definition['description'];
				?>
				<article class="mrn-ir-gateway-card <?php echo ! empty( $config['enabled'] ) ? 'is-enabled' : ''; ?>" data-group="<?php echo esc_attr( isset( $definition['group'] ) ? $definition['group'] : $definition['mode'] ); ?>" data-search="<?php echo esc_attr( $search ); ?>" style="--accent:<?php echo esc_attr( $definition['accent'] ); ?>">
					<div class="mrn-ir-gateway-summary">
						<span class="mrn-ir-logo"><?php echo esc_html( mb_substr( $definition['name'], 0, 1 ) ); ?></span>
						<div class="mrn-ir-gateway-title"><div class="mrn-ir-gateway-labels"><span class="mrn-ir-badge <?php echo 'installment' === $definition['mode'] ? 'installment' : ''; ?>"><?php echo 'installment' === $definition['mode'] ? 'اقساطی' : 'آنلاین'; ?></span><span class="mrn-ir-badge protocol"><?php echo 'native' === $definition['implementation'] ? 'اتصال داخلی' : ( 'preset' === $definition['implementation'] ? 'API آماده' : 'قراردادی' ); ?></span></div><h3><?php echo esc_html( $definition['name'] ); ?></h3><p><?php echo esc_html( $definition['description'] ); ?></p></div>
						<button type="button" class="mrn-ir-expand" aria-expanded="false"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
					</div>
					<div class="mrn-ir-capabilities">
						<?php
						foreach ( $definition['capabilities'] as $capability ) :
							?>
							<span><?php echo esc_html( $capability ); ?></span><?php endforeach; ?>
					</div>
					<div class="mrn-ir-gateway-state">
						<span class="mrn-ir-badge <?php echo ! empty( $config['enabled'] ) && $configured ? 'success' : 'muted'; ?>"><?php echo ! empty( $config['enabled'] ) && $configured ? 'فعال و آماده' : ( $configured ? 'آماده، غیرفعال' : 'نیازمند پیکربندی' ); ?></span>
					</div>
					<form class="mrn-ir-provider-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="mrn_ir_save_provider">
						<input type="hidden" name="provider" value="<?php echo esc_attr( $slug ); ?>">
						<?php wp_nonce_field( 'mrn_ir_save_provider_' . $slug ); ?>
						<div class="mrn-ir-fields">
							<label class="mrn-ir-switch-row"><span><strong>فعال‌سازی در تسویه‌حساب</strong><small>پس از کامل‌شدن اطلاعات، این سرویس به مشتری نمایش داده می‌شود.</small></span><span class="mrn-ir-switch"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?>><i></i></span></label>
							<label><span>عنوان نمایشی</span><input type="text" name="title" value="<?php echo esc_attr( isset( $config['title'] ) ? $config['title'] : $definition['name'] ); ?>"></label>
							<?php foreach ( $definition['fields'] as $key => $field ) : ?>
								<?php $this->provider_field( $key, $field, $config ); ?>
							<?php endforeach; ?>
						</div>
						<div class="mrn-ir-form-actions">
							<button class="mrn-ir-button primary">ذخیره تنظیمات</button>
							<button type="button" class="mrn-ir-button ghost mrn-ir-check" data-provider="<?php echo esc_attr( $slug ); ?>"><span class="dashicons dashicons-yes-alt"></span> بررسی پیکربندی</button>
							<span class="mrn-ir-check-result" aria-live="polite"></span>
						</div>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
		<div class="mrn-ir-empty mrn-ir-gateway-empty" hidden><span class="dashicons dashicons-search"></span><h4>درگاهی با این فیلتر پیدا نشد</h4><p>عبارت جست‌وجو یا نوع سرویس را تغییر دهید.</p></div>
		<?php
	}

	private function provider_field( $key, array $field, array $config ) {
		$value = isset( $config[ $key ] ) ? $config[ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' );
		$type  = $field['type'];
		if ( 'checkbox' === $type ) {
			?>
			<label class="mrn-ir-switch-row compact"><span><?php echo esc_html( $field['label'] ); ?></span><span class="mrn-ir-switch"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $value ) ); ?>><i></i></span></label>
			<?php
			return;
		}
		?>
		<label>
			<span><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' *' : ''; ?></span>
			<?php if ( 'select' === $type ) : ?>
				<select name="<?php echo esc_attr( $key ); ?>">
				<?php
				foreach ( $field['options'] as $option => $label ) :
					?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<?php elseif ( 'textarea' === $type ) : ?>
				<textarea name="<?php echo esc_attr( $key ); ?>" rows="4" dir="ltr"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo ! empty( $field['secret'] ) ? '' : esc_attr( $value ); ?>" <?php echo ! empty( $field['secret'] ) && $value ? 'placeholder="ذخیره شده؛ برای حفظ مقدار خالی بگذارید"' : ''; ?> dir="<?php echo in_array( $type, array( 'password', 'url' ), true ) ? 'ltr' : 'auto'; ?>">
			<?php endif; ?>
			<?php
			if ( ! empty( $field['help'] ) ) :
				?>
				<small><?php echo esc_html( $field['help'] ); ?></small><?php endif; ?>
		</label>
		<?php
	}

	private function transactions() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page     = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows     = $this->transactions->list( $page, 25, $status, $provider );
		$total    = $this->transactions->count( $status, $provider );
		?>
		<section class="mrn-ir-section-head"><div><h2>دفتر تراکنش‌ها</h2><p>پیگیری وضعیت، مرجع بانکی و سفارش مرتبط</p></div><a class="mrn-ir-button ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mrn_ir_export' ), 'mrn_ir_export' ) ); ?>"><span class="dashicons dashicons-download"></span> خروجی CSV</a></section>
		<form class="mrn-ir-filters" method="get">
			<input type="hidden" name="page" value="mrn-ir-payment"><input type="hidden" name="tab" value="transactions">
			<select name="status"><option value="">همه وضعیت‌ها</option>
			<?php
			foreach ( $this->statuses() as $key => $label ) :
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<select name="provider"><option value="">همه سرویس‌ها</option>
			<?php
			foreach ( Registry::definitions() as $slug => $definition ) :
				?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $provider, $slug ); ?>><?php echo esc_html( $definition['name'] ); ?></option><?php endforeach; ?></select>
			<button class="mrn-ir-button dark">اعمال فیلتر</button>
		</form>
		<div class="mrn-ir-table-wrap"><table class="mrn-ir-table"><thead><tr><th>تراکنش</th><th>سفارش</th><th>سرویس</th><th>مبلغ</th><th>وضعیت</th><th>کد مرجع</th><th>تاریخ</th></tr></thead><tbody>
		<?php
		if ( $rows ) :
			foreach ( $rows as $row ) :
				$definition = Registry::definition( $row->provider );
				?>
			<tr><td><strong>#<?php echo esc_html( $row->id ); ?></strong><small><?php echo esc_html( substr( $row->public_id, 0, 8 ) ); ?></small></td><td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row->order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $row->order_id ); ?></a></td><td><?php echo esc_html( isset( $definition['name'] ) ? $definition['name'] : $row->provider ); ?></td><td><strong><?php echo esc_html( number_format_i18n( $row->amount ) ); ?></strong><small>تومان</small></td><td><span class="mrn-ir-badge <?php echo esc_attr( $this->status_class( $row->status ) ); ?>"><?php echo esc_html( isset( $this->statuses()[ $row->status ] ) ? $this->statuses()[ $row->status ] : $row->status ); ?></span></td><td dir="ltr"><?php echo esc_html( $row->reference_id ? $row->reference_id : '—' ); ?></td><td><span title="<?php echo esc_attr( get_date_from_gmt( $row->created_at ) ); ?>"><?php echo esc_html( human_time_diff( strtotime( $row->created_at . ' UTC' ), time() ) ); ?> پیش</span></td></tr>
					<?php
		endforeach; else :
			?>
						<tr><td colspan="7"><div class="mrn-ir-empty"><h4>تراکنشی پیدا نشد</h4><p>با اولین تلاش پرداخت، اطلاعات اینجا نمایش داده می‌شود.</p></div></td></tr><?php endif; ?>
		</tbody></table></div>
		<?php
		$pages = (int) ceil( $total / 25 );
		if ( $pages > 1 ) {
			echo '<div class="mrn-ir-pagination">' . wp_kses_post(
				paginate_links(
					array(
						'total'   => $pages,
						'current' => $page,
					)
				)
			) . '</div>';
		}
	}

	private function general_settings() {
		$general = $this->settings->general();
		?>
		<section class="mrn-ir-section-head"><div><h2>تنظیمات تجربه پرداخت</h2><p>متن‌ها، اولویت و نگه‌داری داده‌ها</p></div></section>
		<form class="mrn-ir-card mrn-ir-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mrn_ir_save_general"><?php wp_nonce_field( 'mrn_ir_save_general' ); ?>
			<div class="mrn-ir-fields two">
				<label><span>عنوان روش پرداخت</span><input type="text" name="title" value="<?php echo esc_attr( $general['title'] ); ?>"></label>
				<label><span>درگاه پیش‌فرض</span><select name="default_provider"><option value="">اولین درگاه فعال</option>
				<?php
				foreach ( Registry::definitions() as $slug => $definition ) :
					?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $general['default_provider'], $slug ); ?>><?php echo esc_html( $definition['name'] ); ?></option><?php endforeach; ?></select></label>
				<label class="full"><span>توضیح در تسویه‌حساب</span><textarea name="description" rows="3"><?php echo esc_textarea( $general['description'] ); ?></textarea></label>
				<label><span>نگه‌داری لاگ و تراکنش ناموفق</span><div class="mrn-ir-input-suffix"><input type="number" min="30" max="730" name="retention_days" value="<?php echo esc_attr( $general['retention_days'] ); ?>"><b>روز</b></div></label>
				<label class="mrn-ir-switch-row compact"><span><strong>لاگ تشخیصی</strong><small>فقط هنگام عیب‌یابی فعال شود.</small></span><span class="mrn-ir-switch"><input type="checkbox" name="debug" value="1" <?php checked( $general['debug'] ); ?>><i></i></span></label>
			</div>
			<div class="mrn-ir-form-actions"><button class="mrn-ir-button primary">ذخیره تنظیمات</button></div>
		</form>
		<?php
	}

	private function tools() {
		$checks = array(
			array( 'ووکامرس', class_exists( 'WooCommerce' ), class_exists( 'WooCommerce' ) ? WC_VERSION : 'فعال نیست' ),
			array( 'ارتباط امن سایت', is_ssl(), is_ssl() ? 'HTTPS فعال' : 'سایت با HTTP باز می‌شود' ),
			array( 'REST API', ! empty( get_option( 'permalink_structure' ) ), ! empty( get_option( 'permalink_structure' ) ) ? 'پیوند یکتا فعال' : 'پیوند یکتا ساده' ),
			array( 'رمزنگاری', function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' ), function_exists( 'sodium_crypto_secretbox' ) ? 'Sodium' : ( function_exists( 'openssl_encrypt' ) ? 'OpenSSL' : 'Fallback' ) ),
			array( 'پاک‌سازی زمان‌بندی‌شده', (bool) wp_next_scheduled( 'mrn_ir_payment_daily_cleanup' ), wp_next_scheduled( 'mrn_ir_payment_daily_cleanup' ) ? 'فعال' : 'زمان‌بندی نشده' ),
		);
		?>
		<section class="mrn-ir-section-head"><div><h2>سلامت سیستم</h2><p>پیش‌نیازهای اجرای پایدار و امن پرداخت</p></div></section>
		<section class="mrn-ir-card">
			<div class="mrn-ir-health">
				<?php
				foreach ( $checks as $check ) :
					?>
					<div><span class="dashicons dashicons-<?php echo $check[1] ? 'yes-alt' : 'warning'; ?>"></span><span><strong><?php echo esc_html( $check[0] ); ?></strong><small><?php echo esc_html( $check[2] ); ?></small></span><span class="mrn-ir-badge <?php echo $check[1] ? 'success' : 'warning'; ?>"><?php echo $check[1] ? 'مناسب' : 'نیازمند توجه'; ?></span></div><?php endforeach; ?>
			</div>
		</section>
		<section class="mrn-ir-card mrn-ir-doc-note"><span class="dashicons dashicons-editor-code"></span><div><h3>API توسعه‌پذیر</h3><p>با فیلترهای <code>mrn_ir_payment_provider_definitions</code> و <code>mrn_ir_payment_gateway_instance</code> می‌توانید درگاه اختصاصی را بدون ویرایش هسته ثبت کنید.</p></div></section>
		<?php
	}

	public function save_general() {
		$this->guard( 'mrn_ir_save_general' );
		$this->settings->save_general( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Guard verifies the form nonce immediately above.
		$this->redirect( 'settings', 'saved' );
	}

	public function save_provider() {
		$slug = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The provider selects the nonce action verified on the next line.
		$this->guard( 'mrn_ir_save_provider_' . $slug );
		$result = $this->settings->save_provider( $slug, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Guard verifies the form nonce immediately above.
		$this->redirect( 'gateways', is_wp_error( $result ) ? 'error' : 'saved' );
	}

	public function check_provider() {
		check_ajax_referer( 'mrn_ir_check_provider', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی کافی نیست.' ), 403 );
		}
		$slug       = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$config     = $this->settings->provider( $slug );
		$configured = Registry::is_configured( $slug, $config );
		if ( ! $configured ) {
			wp_send_json_error( array( 'message' => 'فیلدهای الزامی هنوز کامل نشده‌اند.' ) );
		}
		$definition = Registry::definition( $slug );
		if ( 'native' !== ( isset( $definition['implementation'] ) ? $definition['implementation'] : 'contract' ) ) {
			if ( ! is_array( json_decode( $config['request_template'], true ) ) || ! is_array( json_decode( $config['verify_template'], true ) ) ) {
				wp_send_json_error( array( 'message' => 'یکی از قالب‌های JSON معتبر نیست.' ) );
			}
			if ( ! empty( $config['headers_template'] ) && ! is_array( json_decode( $config['headers_template'], true ) ) ) {
				wp_send_json_error( array( 'message' => 'قالب JSON هدرها معتبر نیست.' ) );
			}
		}
		wp_send_json_success( array( 'message' => 'پیکربندی کامل و آماده‌ی استفاده است.' ) );
	}

	public function export() {
		$this->guard( 'mrn_ir_export' );
		$rows = $this->transactions->list( 1, 5000 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=mrn-ir-payment-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( 'ID', 'Order', 'Provider', 'Mode', 'Amount (IRT)', 'Status', 'Authority', 'Reference', 'Card', 'Created UTC' ) );
		foreach ( $rows as $row ) {
			fputcsv( $output, array( $row->id, $row->order_id, $row->provider, $row->mode, $row->amount, $row->status, $row->authority, $row->reference_id, $row->card_pan, $row->created_at ) );
		}
		fclose( $output );
		exit;
	}

	private function guard( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'mrn-ir-payment' ) );
		}
		check_admin_referer( $action );
	}

	private function redirect( $tab, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'mrn-ir-payment',
					'tab'        => $tab,
					'mrn_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function notice() {
		$notice = isset( $_GET['mrn_notice'] ) ? sanitize_key( wp_unslash( $_GET['mrn_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'saved' === $notice ) {
			echo '<div class="mrn-ir-toast success"><span class="dashicons dashicons-yes-alt"></span> تنظیمات با موفقیت ذخیره شد.</div>';
		} elseif ( 'error' === $notice ) {
			echo '<div class="mrn-ir-toast error"><span class="dashicons dashicons-warning"></span> ذخیره تنظیمات انجام نشد.</div>';
		}
	}

	private function statuses() {
		return array(
			'pending' => 'در انتظار',
			'paid'    => 'موفق',
			'failed'  => 'ناموفق',
			'unknown' => 'نامشخص',
		);
	}

	private function status_class( $status ) {
		return array(
			'paid'    => 'success',
			'failed'  => 'danger',
			'unknown' => 'warning',
			'pending' => 'muted',
		)[ $status ] ?? 'muted';
	}
}
