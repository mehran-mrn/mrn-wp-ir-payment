<?php
/**
 * Database installation and lifecycle tasks.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Core;

defined( 'ABSPATH' ) || exit;

final class Installer {
	const DB_VERSION = '1.0.0';

	public static function activate() {
		self::install_schema();
		update_option( 'mrn_ir_payment_db_version', self::DB_VERSION, false );

		if ( ! wp_next_scheduled( 'mrn_ir_payment_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mrn_ir_payment_daily_cleanup' );
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'mrn_ir_payment_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'mrn_ir_payment_daily_cleanup' );
		}
	}

	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( 'mrn_ir_payment_db_version' ) ) {
			self::activate();
		}
	}

	private static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset      = $wpdb->get_charset_collate();
		$transactions = $wpdb->prefix . 'mrn_ir_transactions';
		$logs         = $wpdb->prefix . 'mrn_ir_payment_logs';

		$sql_transactions = "CREATE TABLE {$transactions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(36) NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			provider varchar(64) NOT NULL,
			mode varchar(20) NOT NULL DEFAULT 'online',
			amount bigint(20) unsigned NOT NULL,
			currency varchar(8) NOT NULL DEFAULT 'IRT',
			status varchar(20) NOT NULL DEFAULT 'pending',
			authority varchar(191) NULL,
			reference_id varchar(191) NULL,
			card_pan varchar(32) NULL,
			gateway_status varchar(100) NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			paid_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY order_id (order_id),
			KEY authority (authority),
			KEY provider_status (provider,status),
			KEY created_at (created_at)
		) {$charset};";

		$sql_logs = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			transaction_id bigint(20) unsigned NULL,
			level varchar(12) NOT NULL DEFAULT 'info',
			event varchar(100) NOT NULL,
			message text NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY transaction_id (transaction_id),
			KEY event (event),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_transactions );
		dbDelta( $sql_logs );
	}
}
