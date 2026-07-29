<?php
/**
 * Transaction persistence.
 *
 * @package MRN\IranPayment
 */

namespace MRN\IranPayment\Infrastructure;

defined( 'ABSPATH' ) || exit;

final class Repository {
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'mrn_ir_transactions';
	}

	public function create( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$this->table(),
			array(
				'public_id'  => wp_generate_uuid4(),
				'order_id'   => absint( $data['order_id'] ),
				'provider'   => sanitize_key( $data['provider'] ),
				'mode'       => sanitize_key( $data['mode'] ),
				'amount'     => absint( $data['amount'] ),
				'currency'   => sanitize_key( $data['currency'] ),
				'status'     => 'pending',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		return $this->find( (int) $wpdb->insert_id );
	}

	public function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', absint( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function find_public( $public_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE public_id = %s', $public_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function update( $id, array $data ) {
		global $wpdb;
		$allowed = array(
			'status',
			'authority',
			'reference_id',
			'card_pan',
			'gateway_status',
			'attempts',
			'paid_at',
		);
		$record  = array( 'updated_at' => current_time( 'mysql', true ) );
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$record[ $key ] = $data[ $key ];
			}
		}
		return false !== $wpdb->update( $this->table(), $record, array( 'id' => absint( $id ) ) );
	}

	public function mark_paid( $id, array $data ) {
		global $wpdb;
		$table = $this->table();
		$now   = current_time( 'mysql', true );
		$sql   = $wpdb->prepare(
			"UPDATE {$table}
			SET status = 'paid', reference_id = %s, card_pan = %s, gateway_status = %s,
				paid_at = %s, updated_at = %s
			WHERE id = %d AND status IN ('pending','unknown','failed')",
			(string) $data['reference_id'],
			(string) $data['card_pan'],
			(string) $data['gateway_status'],
			$now,
			$now,
			absint( $id )
		);
		return false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function list( $page = 1, $per_page = 20, $status = '', $provider = '' ) {
		global $wpdb;
		$table  = $this->table();
		$where  = array( '1=1' );
		$values = array();
		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $status );
		}
		if ( $provider ) {
			$where[]  = 'provider = %s';
			$values[] = sanitize_key( $provider );
		}
		$offset   = max( 0, ( absint( $page ) - 1 ) * absint( $per_page ) );
		$values[] = absint( $per_page );
		$values[] = $offset;
		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function count( $status = '', $provider = '' ) {
		global $wpdb;
		$table  = $this->table();
		$where  = array( '1=1' );
		$values = array();
		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $status );
		}
		if ( $provider ) {
			$where[]  = 'provider = %s';
			$values[] = sanitize_key( $provider );
		}
		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
		return (int) ( empty( $values ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function stats() {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
			SUM(status = 'paid') AS paid,
			SUM(status = 'pending') AS pending,
			SUM(status = 'unknown') AS unknown_count,
			COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS volume
			FROM {$table}",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return wp_parse_args(
			is_array( $row ) ? $row : array(),
			array(
				'total'         => 0,
				'paid'          => 0,
				'pending'       => 0,
				'unknown_count' => 0,
				'volume'        => 0,
			)
		);
	}

	public function cleanup( $days ) {
		global $wpdb;
		$table = $this->table();
		$logs  = $wpdb->prefix . 'mrn_ir_payment_logs';
		$cut   = gmdate( 'Y-m-d H:i:s', time() - ( absint( $days ) * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$logs} WHERE created_at < %s", $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s AND status != 'paid'", $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
