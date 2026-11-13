<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Datenzugriff auf den lokalen Zählmarken-Pool (wp_vgwort_marks).
 */
class VGWort_ZM_Pool {

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . VGWORT_ZM_TABLE;
	}

	/**
	 * Fügt eine oder mehrere Zählmarken in den Pool ein.
	 *
	 * @param array  $pixels Liste von ['public_code' => ..., 'private_code' => ...].
	 * @param string $domain Zähldomäne (ohne Schema), die für diese Marken gilt.
	 * @param string $source order|import|internal.
	 * @return int Anzahl der eingefügten Datensätze.
	 */
	public static function add_pixels( array $pixels, $domain, $source = 'import' ) {
		global $wpdb;

		$table   = self::table();
		$domain  = preg_replace( '#^https?://#', '', (string) $domain );
		$inserted = 0;

		foreach ( $pixels as $pixel ) {
			$public_code  = isset( $pixel['public_code'] ) ? sanitize_text_field( $pixel['public_code'] ) : '';
			$private_code = isset( $pixel['private_code'] ) ? sanitize_text_field( $pixel['private_code'] ) : '';

			if ( '' === $public_code ) {
				continue;
			}

			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (public_code, private_code, domain, source, status, created_at) VALUES (%s, %s, %s, %s, 'unused', %s)",
					$public_code,
					$private_code,
					$domain,
					$source,
					current_time( 'mysql' )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $result ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	public static function get_stats() {
		global $wpdb;

		$table = self::table();

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS amount FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$stats = array(
			'unused'   => 0,
			'assigned' => 0,
			'reported' => 0,
			'total'    => 0,
		);

		foreach ( (array) $rows as $row ) {
			$stats[ $row['status'] ] = (int) $row['amount'];
			$stats['total']         += (int) $row['amount'];
		}

		return $stats;
	}

	public static function get_next_unused() {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'unused' ORDER BY id ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_id( $id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function assign_to_post( $mark_id, $post_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->update(
			$table,
			array(
				'status'      => 'assigned',
				'post_id'     => $post_id,
				'assigned_at' => current_time( 'mysql' ),
			),
			array( 'id' => $mark_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function unassign( $mark_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->update(
			$table,
			array(
				'status'      => 'unused',
				'post_id'     => 0,
				'assigned_at' => null,
			),
			array( 'id' => $mark_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function mark_reported( $mark_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->update(
			$table,
			array(
				'status'      => 'reported',
				'reported_at' => current_time( 'mysql' ),
			),
			array( 'id' => $mark_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete_unused( $mark_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->delete(
			$table,
			array(
				'id'     => $mark_id,
				'status' => 'unused',
			),
			array( '%d', '%s' )
		);
	}

	public static function get_for_post( $post_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Listet Marken für die Admin-Übersicht mit optionalem Statusfilter.
	 */
	public static function query( $status = '', $paged = 1, $per_page = 20 ) {
		global $wpdb;

		$table  = self::table();
		$offset = max( 0, ( $paged - 1 ) * $per_page );

		if ( $status ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
					$status,
					$per_page,
					$offset
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Parst hochgeladenen/eingefügten CSV-Text im VG-Wort-Format
	 * "Öffentlicher Identifikationscode;Privater Identifikationscode".
	 *
	 * @return array Liste von ['public_code' => ..., 'private_code' => ...].
	 */
	public static function parse_csv( $raw_text ) {
		$pixels = array();
		$lines  = preg_split( '/\r\n|\r|\n/', trim( (string) $raw_text ) );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = preg_split( '/[;,\t]/', $line );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$public  = trim( $parts[0] );
			$private = trim( $parts[1] );

			// Kopfzeile überspringen.
			if ( 0 === strcasecmp( $public, 'Öffentlicher Identifikationscode' ) ) {
				continue;
			}

			if ( '' === $public ) {
				continue;
			}

			$pixels[] = array(
				'public_code'  => $public,
				'private_code' => $private,
			);
		}

		return $pixels;
	}
}
