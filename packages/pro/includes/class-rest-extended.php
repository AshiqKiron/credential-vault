<?php
/**
 * Extended REST routes (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Manifest;
use CredentialVault\Secrets_Bridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Metadata, bulk rotate, audit query, import jobs, rollback, compliance.
 */
final class Rest_Extended {

	private const NS = 'credential-vault/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NS,
			'/audit',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'query_audit' ),
				'permission_callback' => array( self::class, 'can_view_audit' ),
				'args'                => array(
					'action'  => array( 'type' => 'string' ),
					'secret'  => array( 'type' => 'string' ),
					'user_id' => array( 'type' => 'integer' ),
					'since'   => array( 'type' => 'integer' ),
					'until'   => array( 'type' => 'integer' ),
					'limit'   => array( 'type' => 'integer', 'default' => 100 ),
					'offset'  => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/bulk-rotate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'bulk_rotate' ),
				'permission_callback' => array( Rest_Controller::class, 'can_manage_with_sudo' ),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/(?P<slug>[a-z0-9-]+)/metadata',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_metadata' ),
					'permission_callback' => array( self::class, 'can_read_metadata' ),
					'args'                => Rest_Controller::secret_args_public(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_metadata' ),
					'permission_callback' => array( self::class, 'can_edit_metadata' ),
					'args'                => Rest_Controller::secret_args_public(),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/(?P<slug>[a-z0-9-]+)/promote-previous',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'promote_previous' ),
				'permission_callback' => array( Rest_Controller::class, 'can_manage_with_sudo' ),
				'args'                => Rest_Controller::secret_args_public(),
			)
		);

		register_rest_route(
			self::NS,
			'/secrets/(?P<slug>[a-z0-9-]+)/rotate-workflow',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rotate_workflow' ),
				'permission_callback' => array( Rest_Controller::class, 'can_manage_with_sudo' ),
				'args'                => Rest_Controller::secret_args_public(),
			)
		);

		register_rest_route(
			self::NS,
			'/import-jobs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'create_import_job' ),
				'permission_callback' => array( Rest_Controller::class, 'can_manage_with_sudo' ),
			)
		);

		register_rest_route(
			self::NS,
			'/import-jobs/(?P<id>[a-f0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_import_job' ),
				'permission_callback' => array( self::class, 'can_view_audit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/compliance/production-rotation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'compliance_production' ),
				'permission_callback' => array( self::class, 'can_view_audit' ),
				'args'                => array(
					'days' => array( 'type' => 'integer', 'default' => 90 ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/compliance/access',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'compliance_access' ),
				'permission_callback' => array( self::class, 'can_view_audit' ),
				'args'                => array(
					'secret' => array( 'type' => 'string' ),
					'days'   => array( 'type' => 'integer', 'default' => 7 ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/network/blue-green',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'network_blue_green' ),
				'permission_callback' => array( self::class, 'can_manage_network' ),
			)
		);

		register_rest_route(
			self::NS,
			'/manifest/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'validate_manifest' ),
				'permission_callback' => array( Rest_Controller::class, 'can_manage' ),
				'args'                => array(
					'manifest'         => array(
						'type'     => 'object',
						'required' => false,
					),
					'fail_on_overdue'  => array( 'type' => 'boolean' ),
					'apply_register'   => array( 'type' => 'boolean' ),
				),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_view_audit() {
		if ( Secrets_Bridge::user_can( 'view' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Insufficient permission.', 'credential-vault-pro' ), array( 'status' => 403 ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_manage_network() {
		if ( is_multisite() && current_user_can( 'manage_network' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Network admin required.', 'credential-vault-pro' ), array( 'status' => 403 ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_read_metadata( WP_REST_Request $request ) {
		$token_check = Api_Tokens::rest_require( $request, 'metadata', (string) $request->get_param( 'slug' ) );
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
		}

		$name    = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );
		$network = 'network' === $request->get_param( 'scope' );

		if ( ! Secrets_Bridge::user_can( 'metadata', $name, $network ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permission.', 'credential-vault-pro' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_edit_metadata( WP_REST_Request $request ) {
		return self::can_read_metadata( $request );
	}

	public static function query_audit( WP_REST_Request $request ): WP_REST_Response {
		$filters = array(
			'action'  => $request->get_param( 'action' ),
			'secret'  => $request->get_param( 'secret' ),
			'user_id' => $request->get_param( 'user_id' ),
			'since'   => $request->get_param( 'since' ),
			'until'   => $request->get_param( 'until' ),
		);

		$rows = Audit_Store::query(
			$filters,
			(int) $request->get_param( 'limit' ),
			(int) $request->get_param( 'offset' )
		);

		return new WP_REST_Response( $rows );
	}

	public static function get_metadata( WP_REST_Request $request ): WP_REST_Response {
		$name = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );
		$meta = Credential_Meta::get( $name );

		return new WP_REST_Response( $meta ?? array() );
	}

	public static function update_metadata( WP_REST_Request $request ) {
		$name = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );
		$row  = Credential_Meta::get( $name );

		Credential_Meta::update_details(
			$name,
			(string) ( $request->get_param( 'label' ) ?? ( $row['label'] ?? '' ) ),
			(string) ( $request->get_param( 'notes' ) ?? ( $row['notes'] ?? '' ) ),
			(string) ( $request->get_param( 'environment' ) ?? ( $row['environment'] ?? '' ) ),
			(int) ( $request->get_param( 'rotate_by' ) ?? ( $row['rotate_by'] ?? 0 ) ),
			(string) ( $request->get_param( 'owner' ) ?? ( $row['owner'] ?? '' ) ),
			(string) ( $request->get_param( 'runbook_url' ) ?? ( $row['runbook_url'] ?? '' ) ),
			is_array( $request->get_param( 'public_fields' ) ) ? $request->get_param( 'public_fields' ) : ( $row['public_fields'] ?? array() )
		);

		Audit_Log::log_user_action( 'meta_updated', $name, array( 'via' => 'rest' ) );

		return new WP_REST_Response( Credential_Meta::get( $name ) ?? array() );
	}

	public static function bulk_rotate( WP_REST_Request $request ) {
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'invalid', __( 'items array required.', 'credential-vault-pro' ), array( 'status' => 400 ) );
		}

		$results = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$sub = new WP_REST_Request( 'POST', '/credential-vault/v1/secrets/' . sanitize_key( (string) ( $item['slug'] ?? '' ) ) );
			$sub->set_param( 'slug', sanitize_key( (string) ( $item['slug'] ?? '' ) ) );
			$sub->set_param( 'value', (string) ( $item['value'] ?? '' ) );
			$sub->set_param( 'scope', (string) ( $item['scope'] ?? 'site' ) );
			$sub->set_param( 'sudo_password', $request->get_param( 'sudo_password' ) );

			$results[] = Rest_Controller::rotate_secret( $sub );
		}

		return new WP_REST_Response( array( 'results' => $results ) );
	}

	public static function promote_previous( WP_REST_Request $request ) {
		$network = 'network' === $request->get_param( 'scope' );
		$name    = Secrets_Bridge::secret_name( (string) $request->get_param( 'slug' ) );

		$result = Secrets_Bridge::promote_previous( $name, $network );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Audit_Log::log_user_action( 'rollback', $name, array( 'via' => 'rest' ) );

		return new WP_REST_Response( array( 'promoted' => true ) );
	}

	public static function rotate_workflow( WP_REST_Request $request ) {
		$result = Rotation_Automation::run_workflow(
			array(
				'slug'             => (string) $request->get_param( 'slug' ),
				'value'            => (string) $request->get_param( 'value' ),
				'network'          => 'network' === $request->get_param( 'scope' ),
				'auto_retire'      => (bool) $request->get_param( 'auto_retire' ),
				'rollback_on_fail' => (bool) $request->get_param( 'rollback_on_fail' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}

	public static function create_import_job( WP_REST_Request $request ) {
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'invalid', __( 'items array required.', 'credential-vault-pro' ), array( 'status' => 400 ) );
		}

		$id = Import_Jobs::create( $items );

		return new WP_REST_Response( array( 'id' => $id, 'status' => 'queued' ), 202 );
	}

	public static function get_import_job( WP_REST_Request $request ): WP_REST_Response {
		$job = Import_Jobs::get( (string) $request->get_param( 'id' ) );

		return new WP_REST_Response( $job ?? array( 'status' => 'not_found' ) );
	}

	public static function compliance_production( WP_REST_Request $request ): WP_REST_Response {
		$days = (int) $request->get_param( 'days' );

		return new WP_REST_Response(
			array(
				'days'   => $days,
				'rows'   => Compliance_Reports::production_rotated_within_days( $days ),
				'passed' => count(
					array_filter(
						Compliance_Reports::production_rotated_within_days( $days ),
						static function ( array $row ): bool {
							return ! empty( $row['ok'] );
						}
					)
				),
			)
		);
	}

	public static function compliance_access( WP_REST_Request $request ): WP_REST_Response {
		$secret = (string) $request->get_param( 'secret' );
		$days   = (int) $request->get_param( 'days' );

		return new WP_REST_Response(
			Compliance_Reports::secret_access_within_days( $secret, $days )
		);
	}

	public static function network_blue_green(): WP_REST_Response {
		return new WP_REST_Response( Multisite_Network::blue_green_network_report() );
	}

	public static function validate_manifest( WP_REST_Request $request ) {
		$manifest = $request->get_param( 'manifest' );
		if ( ! is_array( $manifest ) ) {
			return new WP_Error( 'invalid', __( 'manifest object required.', 'credential-vault-pro' ), array( 'status' => 400 ) );
		}

		if ( $request->get_param( 'apply_register' ) ) {
			Secrets_Manifest::apply_register( $manifest );
		}

		$result = Secrets_Manifest::validate(
			$manifest,
			array(
				'fail_on_overdue'       => (bool) $request->get_param( 'fail_on_overdue' ),
				'fail_on_undecryptable' => true,
			)
		);

		if ( ! $result['ok'] ) {
			do_action( 'credential_vault_manifest_validation_failed', $result, 'rest' );
		}

		return new WP_REST_Response( $result, $result['ok'] ? 200 : 422 );
	}
}
