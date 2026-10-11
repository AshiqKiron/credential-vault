<?php
/**
 * Pro activation: database tables and defaults.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Creates long-retention audit storage on activate.
 */
final class Activator {

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::create_tables();
		Audit_Store::maybe_schedule_purge();
	}

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Audit_Store::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			recorded_at int(11) unsigned NOT NULL DEFAULT 0,
			action varchar(64) NOT NULL DEFAULT '',
			secret varchar(255) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			network tinyint(1) NOT NULL DEFAULT 0,
			payload longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY recorded_at (recorded_at),
			KEY action (action),
			KEY secret (secret(191)),
			KEY user_id (user_id)
		) {$charset};";

		dbDelta( $sql );
	}
}
