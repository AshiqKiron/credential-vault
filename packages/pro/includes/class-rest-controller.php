<?php
/**
 * REST API for headless vault operations.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Credential_Meta;
use CredentialVault\Rotation_Workflow;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Sudo_Gate;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Routes under credential-vault/v1 (cookie + nonce or Application Password).
 */
final class Rest_Controller {

	private const NS = 'credential-vault/v1';

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		Rest_Extended::register_routes();
		register_rest_route(
			self::NS,
			'/secrets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'list_secrets' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => array(
						'scope' => array(
							'default' => 'site',
							'enum'    => array( 'site', 'network', 'vault', 'external' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/(?P<slug>[a-z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'rotate_secret' ),
					'permission_callback' => array( self::class, 'can_manage_with_sudo' ),
					'args'                => self::secret_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_secret' ),
					'permission_callback' => array( self::class, 'can_manage_with_sudo' ),
					'args'                => self::secret_args(),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/(?P<slug>[a-z0-9-]+)/reveal',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'reveal_secret' ),
				'permission_callback' => array( self::class, 'can_manage_with_sudo' ),
				'args'                => self::secret_args(),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/(?P<slug>[a-z0-9-]+)/retire-previous',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'retire_previous' ),
				'permission_callback' => array( self::class, 'can_manage_with_sudo' ),
				'args'                => self::secret_args(),
			)
		);

		register_rest_route(
			self::NS,
			'/sudo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'establish_sudo' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'password' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function secret_args(): array {
		return self::secret_args_public();
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function secret_args_public(): array {
		return array(
			'slug'           => array(
				'type'     => 'string',
				'required' => true,
			),
			'scope'          => array(
				'default' => 'site',
				'enum'    => array( 'site', 'network' ),
			),
			'sudo_password'  => array(
				'type' => 'string',
			),
			'value'          => array(
				'type' => 'string',
			),
			'auto_retire'      => array( 'type' => 'boolean' ),
			'rollback_on_fail' => array( 'type' => 'boolean' ),
		);
	}

	public static function can_manage(): bool {
		return Secrets_Bridge::user_can( 'manage' );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_manage_with_sudo( WP_REST_Request $request ) {
		if ( ! self::can_manage() ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permission.', 'credential-vault-pro' ), array( 'status' => 403 ) );
		}

		if ( Sudo_Gate::is_active() ) {
			return true;
		}

		$password = $request->get_param( 'sudo_password' );
		if ( is_string( $password ) && '' !== $password ) {
			$result = Sudo_Gate::establish( $password );
			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'credential_vault_sudo_required',
					$result->get_error_message(),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		return new WP_Error(
			'credential_vault_sudo_required',
			__( 'Active sudo session or sudo_password required.', 'credential-vault-pro' ),
			array( 'status' => 403 )
		);
	}

	public static function list_secrets( WP_REST_Request $request ): WP_REST_Response {
		$scope = $request->get_param( 'scope' );

		switch ( $scope ) {
			case 'network':
				$list = Secrets_Bridge::list_vault_secrets( true );
				break;
			case 'external':
				$list = Secrets_Bridge::list_external_secrets( false );
				break;
			case 'vault':
				$list = Secrets_Bridge::list_vault_secrets( false );
				break;
			default:
				$list = Secrets_Bridge::list_vault_secrets( false );
		}

		return new WP_REST_Response( $list );
	}

	public static function rotate_secret( WP_REST_Request $request ) {
		$value = $request->get_param( 'value' );
		if ( ! is_string( $value ) || '' === $value ) {
			return new WP_Error( 'rest_missing_callback_param', __( 'value is required.', 'credential-vault-pro' ), array( 'status' => 400 ) );
		}

		$network = 'network' === $request->get_param( 'scope' );
		$name    = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );

		if ( ! Secrets_Bridge::user_can( 'rotate', $name, $network ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permission.', 'credential-vault-pro' ), array( 'status' => 403 ) );
		}

		$preview = Secrets_Bridge::masked_preview( $value );
		$result  = Secrets_Bridge::set_secret( $name, $value, $network );
		Secrets_Bridge::memzero( $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Credential_Meta::save( $name, (string) $request->get_param( 'slug' ), 'custom', $preview );

		$test = Rotation_Workflow::run_post_save_test( $name, $network );
		if ( is_wp_error( $test ) ) {
			do_action( 'credential_vault_rotation_test_failed', $name, $network, $test );

			return new WP_REST_Response(
				array(
					'name'    => $name,
					'rotated' => true,
					'test'    => array(
						'ok'      => false,
						'message' => $test->get_error_message(),
					),
				),
				200
			);
		}

		return new WP_REST_Response( array( 'name' => $name, 'rotated' => true, 'test' => array( 'ok' => true ) ), 200 );
	}

	public static function reveal_secret( WP_REST_Request $request ) {
		$network = 'network' === $request->get_param( 'scope' );
		$name    = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );

		if ( ! Secrets_Bridge::user_can( 'reveal', $name, $network ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permission.', 'credential-vault-pro' ), array( 'status' => 403 ) );
		}

		$secret = $network ? Secrets_Bridge::get_network_secret( $name ) : Secrets_Bridge::get_secret( $name );

		if ( is_wp_error( $secret ) ) {
			return $secret;
		}
		if ( null === $secret ) {
			return new WP_Error( 'not_found', __( 'Secret not found.', 'credential-vault-pro' ), array( 'status' => 404 ) );
		}

		$plaintext = $secret->reveal();
		if ( is_wp_error( $plaintext ) ) {
			return $plaintext;
		}

		Audit_Log::log_user_action( 'copied', $name, array( 'via' => 'rest' ) );

		return new WP_REST_Response(
			array(
				'name'  => $name,
				'value' => $plaintext,
			)
		);
	}

	public static function delete_secret( WP_REST_Request $request ) {
		$network = 'network' === $request->get_param( 'scope' );
		$name    = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );

		$result = Secrets_Bridge::delete_secret( $name, $network );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Credential_Meta::delete( $name );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	public static function retire_previous( WP_REST_Request $request ) {
		$network = 'network' === $request->get_param( 'scope' );
		$name    = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );

		$result = Secrets_Bridge::retire_previous( $name, $network );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'retired' => true ) );
	}

	public static function establish_sudo( WP_REST_Request $request ) {
		$password = (string) $request->get_param( 'password' );
		$result   = Sudo_Gate::establish( $password );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'credential_vault_sudo_required',
				$result->get_error_message(),
				array( 'status' => 403 )
			);
		}

		Audit_Log::log_user_action( 'sudo_started', '', array( 'via' => 'rest' ) );

		return new WP_REST_Response(
			array(
				'sudo'               => true,
				'remaining_seconds'  => Sudo_Gate::remaining_seconds(),
			)
		);
	}
}
