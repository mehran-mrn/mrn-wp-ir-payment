<?php
/**
 * Preserve financial records unless the site owner explicitly opts in.
 *
 * @package MRN\IranPayment
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'MRN_IR_PAYMENT_REMOVE_DATA' ) || true !== MRN_IR_PAYMENT_REMOVE_DATA ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mrn_ir_payment_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mrn_ir_transactions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
delete_option( 'mrn_ir_payment_settings' );
delete_option( 'mrn_ir_payment_db_version' );
delete_option( 'woocommerce_mrn_ir_payment_settings' );
