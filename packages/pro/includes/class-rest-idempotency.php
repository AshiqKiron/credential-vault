<?php
/**
 * REST idempotency keys for mutating vault routes (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Replays cached responses for duplicate Idempotency-Key headers.
 */
final class Rest_Idempotency {

	private const TRANSIENT_PREFIX = 'credvault_idem_';

	public static function register(): void {
		add_filter( 'rest_pre_dispatch', array( self::class, 'maybe_replay' ), 5, 3 );
		add_filter( 'rest_post_dispatch', array( self::class, 'maybe_store' ), 10, 3 );
	}

	/**
	 * @param mixed            $result
	 * @param WP_REST_Server   $server
	 * @return mixed
	 */
	public static function maybe_replay( $result, $server, WP_REST_Request $request ) {
		unset( $server );
		if ( ! self::applies( $request ) ) {
			return $result;
		}

		$key = self::header_key( $request );
		if ( '' === $key ) {
			return $result;
		}

		$cached = get_transient( self::TRANSIENT_PREFIX . $key );
		if ( ! is_array( $cached ) || ! isset( $cached['body'] ) ) {
			return $result;
		}

		return new WP_REST_Response( $cached['body'], (int) ( $cached['status'] ?? 200 ) );
	}

	/**
	 * @param WP_REST_Response|WP_Error $response
	 * @return WP_REST_Response|WP_Error
	 */
	public static function maybe_store( $response, $server, WP_REST_Request $request ) {
		unset( $server );
		if ( ! self::applies( $request ) ) {
			return $response;
		}

		$key = self::header_key( $request );
		if ( '' === $key || is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof WP_REST_Response ) {
			set_transient(
				self::TRANSIENT_PREFIX . $key,
				array(
					'status' => $response->get_status(),
					'body'   => $response->get_data(),
				),
				HOUR_IN_SECONDS
			);
		}

		return $response;
	}

	private static function applies( WP_REST_Request $request ): bool {
		if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return false;
		}

		$route = (string) $request->get_route();

		return str_starts_with( $route, '/credential-vault/v1/' );
	}

	private static function header_key( WP_REST_Request $request ): string {
		$key = $request->get_header( 'idempotency-key' );
		if ( ! is_string( $key ) || '' === trim( $key ) ) {
			return '';
		}

		return hash( 'sha256', trim( $key ) . '|' . (string) $request->get_route() );
	}
}
