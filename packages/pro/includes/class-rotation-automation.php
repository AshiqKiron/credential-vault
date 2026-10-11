<?php
/**
 * Automated provider rotation workflows (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Credential_Meta;
use CredentialVault\Rotation_Workflow;
use CredentialVault\Secrets_Bridge;
use WP_Error;

/**
 * Orchestrates generate → store slot B → test → optional retire via REST.
 */
final class Rotation_Automation {

	public static function register(): void {
		add_filter( 'credential_vault_rotation_test', array( self::class, 'provider_test' ), 5, 3 );
	}

	/**
	 * @param true|WP_Error|null $result
	 * @return true|WP_Error|null
	 */
	public static function provider_test( $result, string $secret_name, bool $network ) {
		if ( null !== $result ) {
			return $result;
		}

		$meta    = Credential_Meta::get( $secret_name );
		$service = is_array( $meta ) ? (string) ( $meta['service'] ?? '' ) : '';

		/**
		 * Provider-specific automated rotation test.
		 *
		 * @param true|WP_Error|null $result
		 * @param string             $secret_name
		 * @param bool               $network
		 * @param string             $service
		 */
		return apply_filters( 'credential_vault_pro_rotation_automation_test', null, $secret_name, $network, $service );
	}

	/**
	 * @param array<string, mixed> $params slug, value, scope, auto_retire.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function run_workflow( array $params ) {
		$slug    = sanitize_key( (string) ( $params['slug'] ?? '' ) );
		$value   = (string) ( $params['value'] ?? '' );
		$network = ! empty( $params['network'] );
		$retire  = ! empty( $params['auto_retire'] );

		if ( '' === $slug || '' === $value ) {
			return new WP_Error( 'invalid', __( 'Slug and value are required.', 'credential-vault-pro' ) );
		}

		$name = Secrets_Bridge::secret_name( $slug );

		$result = Secrets_Bridge::set_secret( $name, $value, $network );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$preview = Secrets_Bridge::masked_preview( $value );
		Secrets_Bridge::memzero( $value );

		Credential_Meta::save( $name, $slug, 'custom', $preview );

		$test = Rotation_Workflow::run_post_save_test( $name, $network );
		if ( is_wp_error( $test ) ) {
			do_action( 'credential_vault_rotation_test_failed', $name, $network, $test );

			return array(
				'slug'    => $slug,
				'tested'  => false,
				'error'   => $test->get_error_message(),
				'rolled_back' => self::maybe_rollback( $name, $network, ! empty( $params['rollback_on_fail'] ) ),
			);
		}

		$retired = false;
		if ( $retire ) {
			$ret = Secrets_Bridge::retire_previous( $name, $network );
			$retired = ! is_wp_error( $ret );
		}

		Audit_Log::log_user_action(
			'rotated',
			$name,
			array(
				'via'          => 'automation',
				'auto_retired' => $retired,
			)
		);

		return array(
			'slug'     => $slug,
			'tested'   => true,
			'retired'  => $retired,
		);
	}

	private static function maybe_rollback( string $name, bool $network, bool $enabled ): bool {
		if ( ! $enabled ) {
			return false;
		}

		$result = Secrets_Bridge::promote_previous( $name, $network );

		return ! is_wp_error( $result );
	}
}
