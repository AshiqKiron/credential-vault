<?php
/**
 * Scoped REST API tokens (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Secrets_Bridge;
use WP_Error;
use WP_REST_Request;

/**
 * Plugin-issued bearer tokens with slug and capability scopes.
 */
final class Api_Tokens {

	private const OPTION = 'credential_vault_pro_api_tokens';

	public const CAP_METADATA = 'metadata';

	public const CAP_REVEAL = 'reveal';

	public const CAP_ROTATE = 'rotate';

	public const CAP_MANAGE = 'manage';

	/**
	 * @var array<string, mixed>|null Active token row for this request.
	 */
	private static ?array $active_token = null;

	public static function register(): void {
		add_filter( 'determine_current_user', array( self::class, 'authenticate_bearer' ), 20 );
		add_filter( 'credential_vault_user_can', array( self::class, 'filter_user_can' ), 10, 4 );
		add_action( 'admin_post_credential_vault_pro_create_api_token', array( self::class, 'handle_create' ) );
		add_action( 'admin_post_credential_vault_pro_revoke_api_token', array( self::class, 'handle_revoke' ) );
	}

	/**
	 * @return list<array{id: string, label: string, caps: list<string>, slugs: list<string>, created: int, last_used: int}>
	 */
	public static function list_tokens(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$out = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'id'        => (string) ( $row['id'] ?? '' ),
				'label'     => (string) ( $row['label'] ?? '' ),
				'caps'      => is_array( $row['caps'] ?? null ) ? array_values( array_map( 'strval', $row['caps'] ) ) : array( self::CAP_METADATA ),
				'slugs'     => is_array( $row['slugs'] ?? null ) ? array_values( array_map( 'sanitize_key', $row['slugs'] ) ) : array(),
				'created'   => (int) ( $row['created'] ?? 0 ),
				'last_used' => (int) ( $row['last_used'] ?? 0 ),
			);
		}

		return $out;
	}

	public static function active_token(): ?array {
		return self::$active_token;
	}

	/**
	 * @param int|false $user_id
	 * @return int|false
	 */
	public static function authenticate_bearer( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		$header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';
		if ( '' === $header && function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $key => $value ) {
					if ( 'authorization' === strtolower( (string) $key ) ) {
						$header = (string) $value;
						break;
					}
				}
			}
		}

		if ( ! preg_match( '/^\s*Bearer\s+(.+)\s*$/i', $header, $matches ) ) {
			return $user_id;
		}

		$plain = trim( $matches[1] );
		if ( '' === $plain ) {
			return $user_id;
		}

		$row = self::resolve_plaintext_token( $plain );
		if ( null === $row ) {
			return $user_id;
		}

		self::$active_token = $row;
		self::touch_last_used( (string) $row['id'] );

		$service_user = (int) get_option( 'credential_vault_pro_api_service_user', 0 );
		if ( $service_user > 0 ) {
			return $service_user;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);

		if ( ! empty( $admins[0]->ID ) ) {
			return (int) $admins[0]->ID;
		}

		return $user_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function resolve_plaintext_token( string $plain ): ?array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return null;
		}

		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) || empty( $row['hash'] ) ) {
				continue;
			}
			if ( hash_equals( (string) $row['hash'], hash_hmac( 'sha256', $plain, wp_salt( 'auth' ) ) ) ) {
				return $row;
			}
		}

		return null;
	}

	private static function touch_last_used( string $id ): void {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return;
		}

		foreach ( $stored as &$row ) {
			if ( is_array( $row ) && (string) ( $row['id'] ?? '' ) === $id ) {
				$row['last_used'] = time();
				break;
			}
		}
		unset( $row );

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * @param bool   $allowed
	 * @param string $action
	 * @param string $secret_name
	 */
	public static function filter_user_can( $allowed, string $action, string $secret_name, bool $network ): bool {
		unset( $network );
		if ( null === self::$active_token ) {
			return $allowed;
		}

		$token = self::$active_token;
		$caps  = is_array( $token['caps'] ?? null ) ? $token['caps'] : array( self::CAP_METADATA );
		$slugs = is_array( $token['slugs'] ?? null ) ? $token['slugs'] : array();

		if ( ! empty( $slugs ) && '' !== $secret_name ) {
			$slug = basename( $secret_name );
			if ( ! in_array( $slug, $slugs, true ) ) {
				return false;
			}
		}

		$map = array(
			'view'     => self::CAP_METADATA,
			'metadata' => self::CAP_METADATA,
			'reveal'   => self::CAP_REVEAL,
			'rotate'   => self::CAP_ROTATE,
			'delete'   => self::CAP_MANAGE,
			'manage'   => self::CAP_MANAGE,
		);

		$need = $map[ $action ] ?? self::CAP_MANAGE;

		if ( in_array( self::CAP_MANAGE, $caps, true ) ) {
			return true;
		}

		return in_array( $need, $caps, true );
	}

	public static function handle_create(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_create_api_token' );

		$label = isset( $_POST['token_label'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['token_label'] ) ) : '';
		$slugs_raw = isset( $_POST['token_slugs'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['token_slugs'] ) ) : '';
		$caps = isset( $_POST['token_caps'] ) && is_array( $_POST['token_caps'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['token_caps'] ) )
			: array( self::CAP_METADATA );

		$slugs = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $slugs_raw ) ) ) );

		$plain = wp_generate_password( 48, false, false );
		$id    = wp_generate_uuid4();

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored[] = array(
			'id'        => $id,
			'label'     => $label,
			'hash'      => hash_hmac( 'sha256', $plain, wp_salt( 'auth' ) ),
			'caps'      => array_values( array_unique( $caps ) ),
			'slugs'     => array_values( $slugs ),
			'created'   => time(),
			'last_used' => 0,
		);

		update_option( self::OPTION, $stored, false );

		set_transient( 'credential_vault_pro_new_token_' . get_current_user_id(), $plain, 300 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'api_token_created',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_revoke(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_revoke_api_token' );

		$id = isset( $_POST['token_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['token_id'] ) ) : '';

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored = array_values(
			array_filter(
				$stored,
				static function ( $row ) use ( $id ) {
					return is_array( $row ) && (string) ( $row['id'] ?? '' ) !== $id;
				}
			)
		);

		update_option( self::OPTION, $stored, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'api_token_revoked',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * REST permission helper.
	 *
	 * @return true|WP_Error
	 */
	public static function rest_require( WP_REST_Request $request, string $action, string $slug = '' ) {
		$name = '' !== $slug ? Secrets_Bridge::secret_name( $slug ) : '';

		if ( ! Secrets_Bridge::user_can( $action, $name, 'network' === $request->get_param( 'scope' ) ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permission.', 'credential-vault-pro' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
