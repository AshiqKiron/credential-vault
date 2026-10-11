<?php
/**
 * Template credentials pushed to new subsites (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

/**
 * Network-level expected secret templates for agency onboarding.
 */
final class Template_Credentials {

	private const OPTION = 'credential_vault_pro_template_credentials';

	public static function register(): void {
		add_action( 'wp_initialize_site', array( self::class, 'seed_new_site' ), 20, 1 );
		add_action( 'admin_post_credential_vault_pro_save_templates', array( self::class, 'handle_save' ) );
	}

	/**
	 * @return list<array{slug: string, label: string, service: string, environment: string}>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return self::defaults();
		}

		$out = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$out[] = array(
				'slug'        => $slug,
				'label'       => sanitize_text_field( (string) ( $row['label'] ?? $slug ) ),
				'service'     => sanitize_key( (string) ( $row['service'] ?? 'custom' ) ),
				'environment' => sanitize_key( (string) ( $row['environment'] ?? '' ) ),
			);
		}

		return $out;
	}

	/**
	 * @return list<string>
	 */
	public static function expected_slugs(): array {
		return array_values(
			array_map(
				static function ( array $row ): string {
					return (string) $row['slug'];
				},
				self::all()
			)
		);
	}

	/**
	 * @param \WP_Site $site
	 */
	public static function seed_new_site( $site ): void {
		if ( ! is_multisite() || ! Secrets_Bridge::is_available() ) {
			return;
		}

		$blog_id = (int) $site->blog_id;
		switch_to_blog( $blog_id );

		foreach ( self::all() as $template ) {
			$name = Secrets_Bridge::secret_name( $template['slug'] );
			$existing = Secrets_Bridge::get_secret( $name );
			if ( ! is_wp_error( $existing ) && null !== $existing ) {
				continue;
			}

			Credential_Meta::save(
				$name,
				$template['label'],
				$template['service'],
				'',
				__( 'Expected credential — add value in vault.', 'credential-vault-pro' ),
				$template['environment']
			);
		}

		restore_current_blog();
	}

	/**
	 * @return list<array{slug: string, label: string, service: string, environment: string}>
	 */
	private static function defaults(): array {
		return array(
			array(
				'slug'        => 'stripe-api-key',
				'label'       => 'Stripe',
				'service'     => 'stripe',
				'environment' => 'production',
			),
			array(
				'slug'        => 'smtp-password',
				'label'       => 'SMTP',
				'service'     => 'custom',
				'environment' => 'production',
			),
		);
	}

	public static function handle_save(): void {
		if ( ! Secrets_Bridge::current_user_can_manage( is_multisite() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_templates' );

		$slugs = isset( $_POST['template_slug'] ) && is_array( $_POST['template_slug'] )
			? wp_unslash( $_POST['template_slug'] )
			: array();
		$labels = isset( $_POST['template_label'] ) && is_array( $_POST['template_label'] )
			? wp_unslash( $_POST['template_label'] )
			: array();

		$rows = array();
		$max  = max( count( $slugs ), count( $labels ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$slug = sanitize_key( (string) ( $slugs[ $i ] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$rows[] = array(
				'slug'        => $slug,
				'label'       => sanitize_text_field( (string) ( $labels[ $i ] ?? $slug ) ),
				'service'     => 'custom',
				'environment' => 'production',
			);
		}

		update_option( self::OPTION, $rows, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'templates_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
