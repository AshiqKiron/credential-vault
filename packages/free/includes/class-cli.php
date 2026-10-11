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
		unset( $args );
		$this->require_manage();

		$format    = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$available = credential_vault_available();
		$writable  = $available && function_exists( 'wp_secrets_provider_is_writable' ) && wp_secrets_provider_is_writable();

		$summary = array(
			'api_available'         => $available,
			'provider'              => $available ? Secrets_Bridge::provider_label() : '',
			'writable'              => $writable,
			'site_secrets'          => $available ? count( Secrets_Bridge::list_vault_secrets( false ) ) : 0,
			'undecryptable_site'    => $available ? count( Secrets_Bridge::find_undecryptable( false ) ) : 0,
			'plaintext_candidates'  => $available ? count( Plaintext_Scanner::scan() ) : 0,
			'overdue_rotate_by'     => $available ? count( Rotation_Reminders::overdue_secret_names() ) : 0,
			'duplicate_hygiene'     => $available ? count( Duplicate_Detector::find() ) : 0,
			'dev_profile'           => Dev_Profile::is_active(),
			'test_mode'             => Test_Storage::enabled(),
		);

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $summary ) );
		} else {
			foreach ( $summary as $key => $value ) {
				if ( is_bool( $value ) ) {
					$value = $value ? 'yes' : 'no';
				}
				\WP_CLI::line( $key . ': ' . $value );
			}
		}

		$fail = (int) ( $assoc_args['fail-threshold'] ?? 0 );
		if ( $fail > 0 && ( $summary['undecryptable_site'] > 0 || $summary['overdue_rotate_by'] >= $fail ) ) {
			\WP_CLI::halt( 1 );
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

	/**
	 * Validate a credvault.manifest.json against the live vault.
	 *
	 * ## OPTIONS
	 *
	 * [--manifest=<path>]
	 * : Path to manifest (default: ABSPATH/credvault.manifest.json)
	 *
	 * [--fail-on-overdue]
	 * : Treat overdue rotate-by as errors.
	 *
	 * [--apply-register]
	 * : Register metadata from manifest before validating.
	 */
	public function validate( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$path = isset( $assoc_args['manifest'] ) ? (string) $assoc_args['manifest'] : ABSPATH . 'credvault.manifest.json';
		$data = Secrets_Manifest::parse_file( $path );
		if ( is_wp_error( $data ) ) {
			\WP_CLI::error( $data->get_error_message() );
		}

		if ( isset( $assoc_args['apply-register'] ) ) {
			$applied = Secrets_Manifest::apply_register( $data );
			\WP_CLI::log( 'Registered metadata for ' . (int) $applied['registered'] . ' slug(s).' );
		}

		$result = Secrets_Manifest::validate(
			$data,
			array(
				'fail_on_overdue'       => isset( $assoc_args['fail-on-overdue'] ),
				'fail_on_undecryptable' => true,
			)
		);

		foreach ( $result['warnings'] as $warning ) {
			\WP_CLI::warning( $warning );
		}

		if ( ! $result['ok'] ) {
			foreach ( $result['errors'] as $error ) {
				\WP_CLI::line( 'Error: ' . $error );
			}
			do_action( 'credential_vault_manifest_validation_failed', $result, $path );
			\WP_CLI::halt( 1 );
		}

		\WP_CLI::success( 'Manifest validation passed.' );
	}

	/**
	 * Store a credential (stdin or env — avoids shell history).
	 *
	 * ## OPTIONS
	 *
	 * [--from-stdin]
	 * : Read plaintext from STDIN.
	 *
	 * [--from-env=<NAME>]
	 * : Read plaintext from an environment variable.
	 *
	 * [--network]
	 * : Network scope on multisite.
	 *
	 * [<slug>]
	 * : Vault slug.
	 */
	public function set( array $args, array $assoc_args ): void {
		$this->require_manage();

		$slug = isset( $args[0] ) ? sanitize_key( (string) $args[0] ) : '';
		if ( '' === $slug ) {
			\WP_CLI::error( 'Provide a vault slug.' );
		}

		$value = '';
		if ( isset( $assoc_args['from-env'] ) ) {
			$key = (string) $assoc_args['from-env'];
			$value = getenv( $key );
			$value = is_string( $value ) ? $value : '';
		} elseif ( isset( $assoc_args['from-stdin'] ) ) {
			$value = stream_get_contents( STDIN );
			$value = is_string( $value ) ? trim( $value ) : '';
		} else {
			\WP_CLI::error( 'Use --from-stdin or --from-env=NAME.' );
		}

		if ( '' === $value ) {
			\WP_CLI::error( 'Empty secret value.' );
		}

		$network = isset( $assoc_args['network'] );
		$result  = credential_vault_set( $slug, $value, $network, array( 'source' => 'cli_set' ) );
		Secrets_Bridge::memzero( $value );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success( 'Credential saved.' );
	}

	/**
	 * Export an encrypted vault bundle (metadata with optional values).
	 *
	 * ## OPTIONS
	 *
	 * --password=<password>
	 * : Encryption password.
	 *
	 * [--include-values]
	 * : Include plaintext values (audited; use carefully).
	 *
	 * [--network]
	 * : Export network scope.
	 *
	 * [--file=<path>]
	 * : Write bundle to file instead of STDOUT.
	 */
	public function bundle_export( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$password = isset( $assoc_args['password'] ) ? (string) $assoc_args['password'] : '';
		$bundle   = Vault_Bundle::export(
			$password,
			array(
				'include_values' => isset( $assoc_args['include-values'] ),
				'network'        => isset( $assoc_args['network'] ),
			)
		);

		if ( is_wp_error( $bundle ) ) {
			\WP_CLI::error( $bundle->get_error_message() );
		}

		if ( isset( $assoc_args['file'] ) ) {
			$written = file_put_contents( (string) $assoc_args['file'], $bundle );
			if ( false === $written ) {
				\WP_CLI::error( 'Could not write bundle file.' );
			}
			\WP_CLI::success( 'Bundle written.' );
			return;
		}

		\WP_CLI::line( $bundle );
	}

	/**
	 * Import an encrypted vault bundle.
	 *
	 * ## OPTIONS
	 *
	 * --password=<password>
	 * : Decryption password.
	 *
	 * [--file=<path>]
	 * : Bundle file (required unless passed as first arg).
	 *
	 * [--skip-existing]
	 * : Skip slugs that already exist.
	 */
	public function bundle_import( array $args, array $assoc_args ): void {
		$this->require_manage();

		$file = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : ( isset( $args[0] ) ? (string) $args[0] : '' );
		if ( '' === $file || ! is_readable( $file ) ) {
			\WP_CLI::error( 'Provide a readable --file path.' );
		}

		$password = isset( $assoc_args['password'] ) ? (string) $assoc_args['password'] : '';
		$payload  = file_get_contents( $file );
		if ( ! is_string( $payload ) ) {
			\WP_CLI::error( 'Could not read bundle.' );
		}

		$result = Vault_Bundle::import(
			trim( $payload ),
			$password,
			array( 'skip_existing' => isset( $assoc_args['skip-existing'] ) )
		);

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success(
			sprintf(
				'Imported %d item(s), skipped %d.',
				(int) $result['imported'],
				(int) $result['skipped']
			)
		);
	}

	/**
	 * List duplicate-slug merge suggestions (plan only).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json
	 */
	public function dedupe( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$plan   = Duplicate_Detector::merge_plan();

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $plan ) );
			return;
		}

		$rows = array();
		foreach ( $plan as $row ) {
			$rows[] = array(
				'service'    => (string) $row['service'],
				'slugs'      => implode( ', ', (array) $row['slugs'] ),
				'suggestion' => (string) $row['suggestion'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'service', 'slugs', 'suggestion' ) );
	}

	/**
	 * Rotation workflow helper (save, test, optional retire previous).
	 *
	 * ## OPTIONS
	 *
	 * [--from-stdin]
	 * : New secret value from STDIN.
	 *
	 * [--from-env=<NAME>]
	 * : New secret value from environment variable.
	 *
	 * [--retire-previous]
	 * : Retire previous slot after successful save/test.
	 *
	 * [--network]
	 * : Network scope.
	 *
	 * [<slug>]
	 * : Vault slug.
	 */
	public function rotate( array $args, array $assoc_args ): void {
		$this->require_manage();

		$slug = isset( $args[0] ) ? sanitize_key( (string) $args[0] ) : '';
		if ( '' === $slug ) {
			\WP_CLI::error( 'Provide a vault slug.' );
		}

		$value = '';
		if ( isset( $assoc_args['from-env'] ) ) {
			$env_val = getenv( (string) $assoc_args['from-env'] );
			$value   = is_string( $env_val ) ? $env_val : '';
		} elseif ( isset( $assoc_args['from-stdin'] ) ) {
			$value = stream_get_contents( STDIN );
			$value = is_string( $value ) ? trim( $value ) : '';
		} else {
			\WP_CLI::error( 'Provide --from-stdin or --from-env for the new value.' );
		}

		$network = isset( $assoc_args['network'] );
		$result  = credential_vault_set( $slug, $value, $network, array( 'source' => 'cli_rotate' ) );
		Secrets_Bridge::memzero( $value );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		$name = Secrets_Bridge::secret_name( $slug );
		$test = Rotation_Workflow::run_post_save_test( $name, $network );
		if ( is_wp_error( $test ) ) {
			\WP_CLI::warning( $test->get_error_message() );
			\WP_CLI::log( implode( ' ', Rotation_Workflow::steps() ) );
			\WP_CLI::halt( 1 );
		}

		if ( isset( $assoc_args['retire-previous'] ) ) {
			$retire = Secrets_Bridge::retire_previous( $name, $network );
			if ( is_wp_error( $retire ) ) {
				\WP_CLI::error( $retire->get_error_message() );
			}
			\WP_CLI::success( 'Rotated and retired previous slot.' );
			return;
		}

		\WP_CLI::success( 'Credential rotated. Previous slot kept until you run with --retire-previous.' );
		\WP_CLI::log( '- ' . implode( "\n- ", Rotation_Workflow::steps() ) );
	}

	/**
	 * Install or remove demo fixtures.
	 *
	 * ## OPTIONS
	 *
	 * [--remove]
	 * : Remove tracked demo credentials.
	 */
	public function demo( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		if ( isset( $assoc_args['remove'] ) ) {
			$result = Demo_Data::remove();
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			\WP_CLI::success( 'Removed ' . (int) $result['removed'] . ' demo credential(s).' );
			return;
		}

		$result = Demo_Data::install();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success(
			sprintf(
				'Demo data: %d installed, %d skipped.',
				(int) $result['installed'],
				(int) $result['skipped']
			)
		);
	}

	/**
	 * Show option → slug usage map.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv
	 */
	public function usage( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$rows   = Usage_Map::build();

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'label', 'option', 'suggested_slug', 'in_vault', 'source' )
		);
	}

	/**
	 * Queue a background import job (Pro).
	 *
	 * ## OPTIONS
	 *
	 * [--run]
	 * : Import ready options immediately (sync, free tier).
	 *
	 * [--async]
	 * : Queue Pro import job instead of synchronous import.
	 */
	public function import( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		if ( isset( $assoc_args['async'] ) && class_exists( '\\CredentialVaultPro\\Import_Jobs' ) ) {
			$items = array();
			foreach ( Import_Candidates::detect() as $row ) {
				if ( empty( $row['exists'] ) || empty( $row['is_string'] ) ) {
					continue;
				}
				$items[] = array(
					'import_key'  => Plaintext_Scanner::option_import_key( $row['option'] ),
					'secret_name' => $row['suggested_name'],
				);
			}

			$id = \CredentialVaultPro\Import_Jobs::create( $items );
			\WP_CLI::success( 'Import job queued: ' . $id );
			return;
		}

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
	 * Hygiene summary (scanner + duplicates + config constants).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json
	 */
	public function hygiene( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_manage();

		$summary = array(
			'plaintext_candidates' => Secrets_Bridge::is_available() ? count( Plaintext_Scanner::scan() ) : 0,
			'duplicate_issues'     => Secrets_Bridge::is_available() ? count( Duplicate_Detector::find() ) : 0,
			'config_constants'       => count( Config_Constant_Scanner::scan() ),
			'env_mismatches'         => Secrets_Bridge::is_available() ? count( Environment_Guard::find_mismatches() ) : 0,
		);

		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			\WP_CLI::line( wp_json_encode( $summary ) );
			return;
		}

		foreach ( $summary as $key => $value ) {
			\WP_CLI::line( $key . ': ' . $value );
		}
	}

	/**
	 * Tail audit log (metadata only).
	 *
	 * ## OPTIONS
	 *
	 * [--since=<days>]
	 * : Limit to entries in the last N days.
	 * ---
	 * default: 7
	 * ---
	 *
	 * [--limit=<n>]
	 * : Max rows.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json
	 */
	public function audit( array $args, array $assoc_args ): void {
		unset( $args );
		$this->require_view();

		$days  = max( 1, (int) ( $assoc_args['since'] ?? 7 ) );
		$limit = max( 1, (int) ( $assoc_args['limit'] ?? 50 ) );
		$since = time() - ( $days * DAY_IN_SECONDS );

		$rows = Audit_Log::entries(
			$limit,
			array(
				'since' => $since,
			)
		);

		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			\WP_CLI::line( wp_json_encode( $rows ) );
			return;
		}

		$out = array();
		foreach ( $rows as $entry ) {
			$out[] = array(
				'time'   => gmdate( 'c', (int) ( $entry['time'] ?? 0 ) ),
				'action' => (string) ( $entry['action'] ?? '' ),
				'secret' => (string) ( $entry['secret'] ?? '' ),
				'user'   => (string) ( $entry['user_id'] ?? '' ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $out, array( 'time', 'action', 'secret', 'user' ) );
	}

	private function require_manage(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			\WP_CLI::error( 'You do not have permission to manage the credential vault.' );
		}
	}

	private function require_view(): void {
		if ( ! Secrets_Bridge::current_user_can_view() ) {
			\WP_CLI::error( 'You do not have permission to view the credential vault.' );
		}
	}
}
