<?php
/**
 * Per-secret ACLs (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Secrets_Bridge;

/**
 * Role/user grants per credential slug.
 */
final class Secret_Acl {

	private const OPTION = 'credential_vault_pro_secret_acls';

	public static function register(): void {
		add_filter( 'credential_vault_user_can', array( self::class, 'filter_user_can' ), 15, 4 );
		add_action( 'admin_post_credential_vault_pro_save_secret_acl', array( self::class, 'handle_save' ) );
	}

	/**
	 * @return array<string, array{roles: list<string>, users: list<int>, reveal: list<string>, rotate: list<string>, metadata: list<string>}>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @return array{roles: list<string>, users: list<int>, reveal: list<string>, rotate: list<string>, metadata: list<string>}|null
	 */
	public static function for_slug( string $slug ): ?array {
		$slug = sanitize_key( $slug );
		$row  = self::all()[ $slug ] ?? null;

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param bool   $allowed
	 * @param string $action
	 * @param string $secret_name
	 */
	public static function filter_user_can( $allowed, string $action, string $secret_name, bool $network ): bool {
		unset( $network );
		if ( ! $allowed || '' === $secret_name ) {
			return $allowed;
		}

		if ( current_user_can( 'manage_network' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$slug = basename( $secret_name );
		$acl  = self::for_slug( $slug );
		if ( null === $acl ) {
			return $allowed;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$subject_roles = array_values( array_intersect( (array) $user->roles, (array) ( $acl['roles'] ?? array() ) ) );
		$user_ids      = array_map( 'intval', (array) ( $acl['users'] ?? array() ) );
		$matched       = ! empty( $subject_roles ) || in_array( $user->ID, $user_ids, true );

		if ( ! $matched && ( ! empty( $acl['roles'] ) || ! empty( $acl['users'] ) ) ) {
			return false;
		}

		$need_list = self::action_list_key( $action );
		if ( null === $need_list ) {
			return $allowed;
		}

		$list = (array) ( $acl[ $need_list ] ?? array() );
		if ( empty( $list ) ) {
			return $allowed;
		}

		foreach ( $list as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return in_array( (string) $user->ID, $list, true );
	}

	private static function action_list_key( string $action ): ?string {
		switch ( $action ) {
			case 'reveal':
				return 'reveal';
			case 'rotate':
				return 'rotate';
			case 'metadata':
			case 'view':
				return 'metadata';
			default:
				return null;
		}
	}

	public static function handle_save(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_secret_acl' );

		$slug = isset( $_POST['acl_slug'] ) ? sanitize_key( (string) wp_unslash( $_POST['acl_slug'] ) ) : '';
		if ( '' === $slug ) {
			wp_die( esc_html__( 'Slug is required.', 'credential-vault-pro' ) );
		}

		$roles = isset( $_POST['acl_roles'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['acl_roles'] ) ) : '';
		$users = isset( $_POST['acl_users'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['acl_users'] ) ) : '';

		$row = array(
			'roles'    => array_values( array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $roles ) ) ) ) ),
			'users'    => array_values( array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $users ) ) ) ) ),
			'reveal'   => self::posted_list( 'acl_reveal' ),
			'rotate'   => self::posted_list( 'acl_rotate' ),
			'metadata' => self::posted_list( 'acl_metadata' ),
		);

		$all         = self::all();
		$all[ $slug ] = $row;
		update_option( self::OPTION, $all, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'secret_acl_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return list<string>
	 */
	private static function posted_list( string $field ): array {
		if ( ! isset( $_POST[ $field ] ) ) {
			return array();
		}

		$raw = sanitize_text_field( (string) wp_unslash( $_POST[ $field ] ) );

		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}
}
