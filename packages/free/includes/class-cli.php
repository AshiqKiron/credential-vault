<?php
/**
 * WP-CLI commands (list, status, scan — no reveal).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Agency-friendly CLI for inventory and hygiene checks.
 */
final class CLI {

	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'credvault', self::class );
	}

	/**
	 * List vault credentials (metadata only).
	 *
	 * ## OPTIONS
	 *
	 * [--scope=<scope>]
	 * : site or network
	 * ---
	 * default: site
	 * ---
	 *
	 * @param list<string>           $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		if ( ! Secrets_Bridge::is_available() ) {
			\WP_CLI::error( 'Secrets API is not available.' );
		}

		$scope   = isset( $assoc_args['scope'] ) ? sanitize_key( (string) $assoc_args['scope'] ) : 'site';
		$network = 'network' === $scope;

		if ( $network && ! Secrets_Bridge::supports_network() ) {
			\WP_CLI::error( 'Network scope is not available.' );
		}

		$rows = Secrets_Bridge::list_vault_secrets( $network );
		$out  = array();

		foreach ( $rows as $row ) {
			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );
			$out[] = array(
				'name'            => $name,
				'label'           => $meta['label'] ?? '',
				'environment'     => $meta['environment'] ?? '',
				'rotate_by'       => ! empty( $meta['rotate_by'] ) ? gmdate( 'Y-m-d', (int) $meta['rotate_by'] ) : '',
				'needs_rotation'  => ! empty( $row['needs_rotation'] ) ? 'yes' : 'no',
				'has_previous'    => ! empty( $row['has_previous'] ) ? 'yes' : 'no',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $out, array( 'name', 'label', 'environment', 'rotate_by', 'needs_rotation', 'has_previous' ) );
	}

	/**
	 * Show vault health summary.
	 */
	public function status( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		$this->require_manage();

		$available = Secrets_Bridge::is_available();
		$writable  = $available && function_exists( 'wp_secrets_provider_is_writable' ) && wp_secrets_provider_is_writable();

		\WP_CLI::line( 'API available: ' . ( $available ? 'yes' : 'no' ) );
		\WP_CLI::line( 'Provider: ' . ( $available ? Secrets_Bridge::provider_label() : 'n/a' ) );
		\WP_CLI::line( 'Writable: ' . ( $writable ? 'yes' : 'no' ) );

		if ( $available ) {
			$broken = Secrets_Bridge::find_undecryptable( false );
			\WP_CLI::line( 'Site secrets: ' . count( Secrets_Bridge::list_vault_secrets( false ) ) );
			\WP_CLI::line( 'Undecryptable (site): ' . count( $broken ) );
			$scan = Plaintext_Scanner::scan();
			\WP_CLI::line( 'Plaintext candidates: ' . count( $scan ) );
			\WP_CLI::line( 'Overdue rotate-by: ' . count( Rotation_Reminders::overdue_secret_names() ) );
			\WP_CLI::line( 'Duplicate hygiene issues: ' . count( Duplicate_Detector::find() ) );
		}
	}

	/**
	 * Scan wp_options for likely API keys (no values printed).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv
	 */
	public function scan( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$rows   = Plaintext_Scanner::scan();

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'import_key', 'source_type', 'option', 'suggested_name', 'reason', 'preview_hint' )
		);
	}

	/**
	 * Preview bulk import from curated options (no secrets written).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only list import candidates (default).
	 */
	public function import( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$dry_run = isset( $assoc_args['dry-run'] ) || ! isset( $assoc_args['run'] );
		if ( $dry_run ) {
			$rows = Import_Candidates::detect();
			$out  = array();
			foreach ( $rows as $row ) {
				$out[] = array(
					'option'  => $row['option'],
					'secret'  => $row['suggested_name'],
					'ready'   => ( ! empty( $row['exists'] ) && ! empty( $row['is_string'] ) ) ? 'yes' : 'no',
				);
			}
			\WP_CLI\Utils\format_items( 'table', $out, array( 'option', 'secret', 'ready' ) );
			\WP_CLI::line( 'Dry run only. Pass --run to import ready options (requires writable provider).' );
			return;
		}

		if ( ! Secrets_Bridge::is_available() || ! wp_secrets_provider_is_writable() ) {
			\WP_CLI::error( 'Import requires a writable Secrets API provider.' );
		}

		$imported = 0;
		foreach ( Import_Candidates::detect() as $row ) {
			if ( empty( $row['exists'] ) || empty( $row['is_string'] ) ) {
				continue;
			}
			$key    = Plaintext_Scanner::option_import_key( $row['option'] );
			$result = Secrets_Bridge::import_from_source( $key, $row['suggested_name'] );
			if ( ! is_wp_error( $result ) ) {
				++$imported;
			}
		}

		\WP_CLI::success( 'Imported ' . $imported . ' option(s).' );
	}

	/**
	 * Fetch a credential slug (metadata by default).
	 *
	 * ## OPTIONS
	 *
	 * [--reveal]
	 * : Output plaintext (requires manage capability; audited).
	 *
	 * [--network]
	 * : Use network scope on multisite.
	 *
	 * [<slug>]
	 * : Vault slug (not full secret name).
	 */
	public function get( array $args, array $assoc_args ): void {
		$slug = isset( $args[0] ) ? sanitize_key( (string) $args[0] ) : '';
		if ( '' === $slug ) {
			\WP_CLI::error( 'Provide a vault slug.' );
		}

		$network = isset( $assoc_args['network'] );
		$reveal  = isset( $assoc_args['reveal'] );

		if ( $reveal ) {
			$this->require_manage();
			$value = credential_vault_get( $slug, $network );
			if ( null === $value ) {
				\WP_CLI::error( 'Credential not found or unavailable.' );
			}
			\WP_CLI::line( $value );
			return;
		}

		$this->require_manage();

		$name = Secrets_Bridge::secret_name( $slug );
		$meta = Credential_Meta::get( $name );
		$row  = null;
		foreach ( Secrets_Bridge::list_vault_secrets( $network ) as $candidate ) {
			if ( (string) $candidate['name'] === $name ) {
				$row = $candidate;
				break;
			}
		}

		\WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'name'           => $name,
					'label'          => $meta['label'] ?? '',
					'environment'    => $meta['environment'] ?? '',
					'configured'     => null !== $row ? 'yes' : 'no',
					'needs_rotation' => ( null !== $row && ! empty( $row['needs_rotation'] ) ) ? 'yes' : 'no',
				),
			),
			array( 'name', 'label', 'environment', 'configured', 'needs_rotation' )
		);
	}

	private function require_manage(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			\WP_CLI::error( 'You do not have permission to manage the credential vault.' );
		}
	}
}
