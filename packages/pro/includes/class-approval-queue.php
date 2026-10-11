<?php
/**
 * Two-person approval queue for secret writes (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;
use WP_Error;

/**
 * Non-super-admins queue rotations; super admins approve.
 */
final class Approval_Queue {

	private const OPTION = 'credential_vault_pro_approval_queue';

	public static function register(): void {
		add_filter( 'credential_vault_before_set_secret', array( self::class, 'maybe_queue' ), 10, 3 );
		add_action( 'admin_post_credential_vault_pro_approve_change', array( self::class, 'handle_approve' ) );
		add_action( 'admin_post_credential_vault_pro_reject_change', array( self::class, 'handle_reject' ) );
		add_action( 'admin_post_credential_vault_pro_save_approval_settings', array( self::class, 'handle_save_settings' ) );
	}

	public static function handle_save_settings(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_approval_settings' );

		update_option( 'credential_vault_pro_approval_enabled', ! empty( $_POST['approval_enabled'] ), false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'approval_settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function enabled(): bool {
		return (bool) get_option( 'credential_vault_pro_approval_enabled', false );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function pending(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? array_values( $stored ) : array();
	}

	/**
	 * @return true|WP_Error
	 */
	public static function maybe_queue( $gate, string $name, bool $network ) {
		if ( is_wp_error( $gate ) || true !== $gate || ! self::enabled() ) {
			return $gate;
		}

		if ( current_user_can( 'manage_network' ) ) {
			return true;
		}

		$value = isset( $_POST['credential_value'] ) ? (string) wp_unslash( $_POST['credential_value'] ) : '';
		if ( '' === $value && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$request = rest_get_server()->get_raw_data();
			$decoded = json_decode( (string) $request, true );
			if ( is_array( $decoded ) && isset( $decoded['value'] ) ) {
				$value = (string) $decoded['value'];
			}
		}

		if ( '' === $value ) {
			return true;
		}

		$queue   = self::pending();
		$id      = wp_generate_uuid4();
		$queue[] = array(
			'id'         => $id,
			'secret'     => $name,
			'network'    => $network,
			'value_enc'  => self::encrypt_pending( $value ),
			'requested'  => time(),
			'requester'  => get_current_user_id(),
			'slug'       => basename( $name ),
		);

		update_option( self::OPTION, $queue, false );
		Secrets_Bridge::memzero( $value );

		Audit_Log::log_user_action(
			'change_pending',
			$name,
			array(
				'queue_id' => $id,
				'network'  => $network,
			)
		);

		return new WP_Error(
			'credential_vault_pending_approval',
			__( 'This change was queued for super admin approval.', 'credential-vault-pro' )
		);
	}

	public static function handle_approve(): void {
		if ( ! current_user_can( 'manage_network' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only super admins can approve queued changes.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_approve_change' );

		$id = isset( $_POST['queue_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['queue_id'] ) ) : '';

		$queue = self::pending();
		$match = null;
		foreach ( $queue as $index => $row ) {
			if ( is_array( $row ) && (string) ( $row['id'] ?? '' ) === $id ) {
				$match = $row;
				unset( $queue[ $index ] );
				break;
			}
		}

		if ( null === $match ) {
			wp_die( esc_html__( 'Queued change not found.', 'credential-vault-pro' ) );
		}

		update_option( self::OPTION, array_values( $queue ), false );

		$value = self::decrypt_pending( (string) ( $match['value_enc'] ?? '' ) );
		if ( '' === $value ) {
			wp_die( esc_html__( 'Could not decrypt queued value.', 'credential-vault-pro' ) );
		}

		remove_filter( 'credential_vault_before_set_secret', array( self::class, 'maybe_queue' ), 10 );

		$preview = Secrets_Bridge::masked_preview( $value );

		$result = Secrets_Bridge::set_secret( (string) $match['secret'], $value, ! empty( $match['network'] ) );
		Secrets_Bridge::memzero( $value );

		add_filter( 'credential_vault_before_set_secret', array( self::class, 'maybe_queue' ), 10, 3 );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		Credential_Meta::save(
			(string) $match['secret'],
			(string) ( $match['slug'] ?? basename( (string) $match['secret'] ) ),
			'custom',
			$preview
		);

		Audit_Log::log_user_action(
			'change_approved',
			(string) $match['secret'],
			array( 'queue_id' => $id )
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'change_approved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_reject(): void {
		if ( ! current_user_can( 'manage_network' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only super admins can reject queued changes.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_reject_change' );

		$id = isset( $_POST['queue_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['queue_id'] ) ) : '';

		$queue = array_values(
			array_filter(
				self::pending(),
				static function ( $row ) use ( $id ) {
					return ! is_array( $row ) || (string) ( $row['id'] ?? '' ) !== $id;
				}
			)
		);

		update_option( self::OPTION, $queue, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'change_rejected',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function encrypt_pending( string $value ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = random_bytes( 16 );
		$enc = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $enc ) {
			return '';
		}

		return base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private static function decrypt_pending( string $blob ): string {
		if ( '' === $blob ) {
			return '';
		}

		$raw = base64_decode( $blob, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) || strlen( $raw ) < 17 ) {
			return is_string( $raw ) ? $raw : '';
		}

		$iv  = substr( $raw, 0, 16 );
		$enc = substr( $raw, 16 );
		$key = hash( 'sha256', wp_salt( 'auth' ), true );

		$plain = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return is_string( $plain ) ? $plain : '';
	}
}
