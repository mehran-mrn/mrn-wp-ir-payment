<?php
/**
 * Runtime smoke checks executed through WP-CLI on staging.
 *
 * @package MRN\IranPayment
 */

use MRN\IranPayment\Core\SecretVault;
use MRN\IranPayment\Gateways\Registry;
use MRN\IranPayment\Infrastructure\Repository;

global $wpdb;

$gateway_objects = WC()->payment_gateways()->payment_gateways();
$vault           = new SecretVault();
$secret          = 'mrn-smoke-' . wp_generate_password( 18, false );
$cipher          = $vault->encrypt( $secret );
$checks          = array(
	'plugin_version'        => defined( 'MRN_IR_PAYMENT_VERSION' ) && '1.0.0' === MRN_IR_PAYMENT_VERSION,
	'woocommerce_gateway'   => isset( $gateway_objects['mrn_ir_payment'] ) && $gateway_objects['mrn_ir_payment'] instanceof \MRN\IranPayment\WooCommerce\Gateway,
	'callback_registered'   => (bool) has_action( 'woocommerce_api_mrn_ir_payment' ),
	'provider_catalog'      => 8 === count( Registry::definitions() ),
	'transaction_table'     => $wpdb->prefix . 'mrn_ir_transactions' === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'mrn_ir_transactions' ) ),
	'log_table'             => $wpdb->prefix . 'mrn_ir_payment_logs' === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'mrn_ir_payment_logs' ) ),
	'repository_query'      => is_array( ( new Repository() )->stats() ),
	'credential_encryption' => $secret === $vault->decrypt( $cipher ) && $secret !== $cipher,
	'daily_cleanup'         => (bool) wp_next_scheduled( 'mrn_ir_payment_daily_cleanup' ),
);

echo wp_json_encode(
	array(
		'ok'       => ! in_array( false, $checks, true ),
		'checks'   => $checks,
		'currency' => get_woocommerce_currency(),
		'checkout' => wc_get_checkout_url(),
	),
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ( in_array( false, $checks, true ) ) {
	exit( 1 );
}
