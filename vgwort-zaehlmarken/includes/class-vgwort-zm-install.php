<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aktivierung / Deaktivierung: legt die Datenbanktabelle für den Marken-Pool an
 * und setzt Standardeinstellungen.
 */
class VGWort_ZM_Install {

	public static function activate() {
		self::create_table();
		self::maybe_set_default_options();
	}

	public static function deactivate() {
		// Absichtlich keine Daten löschen. Aufräumen erfolgt optional in uninstall.php.
	}

	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . VGWORT_ZM_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			private_code VARCHAR(191) NOT NULL DEFAULT '',
			public_code VARCHAR(191) NOT NULL DEFAULT '',
			domain VARCHAR(191) NOT NULL DEFAULT '',
			source VARCHAR(20) NOT NULL DEFAULT 'import',
			status VARCHAR(20) NOT NULL DEFAULT 'unused',
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			reported_at DATETIME NULL,
			note VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			assigned_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY post_id (post_id),
			UNIQUE KEY public_code (public_code)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'vgwort_zm_db_version', VGWORT_ZM_VERSION );
	}

	private static function maybe_set_default_options() {
		if ( false === get_option( 'vgwort_zm_settings' ) ) {
			update_option( 'vgwort_zm_settings', VGWort_ZM_Settings::get_defaults() );
		}
	}
}
