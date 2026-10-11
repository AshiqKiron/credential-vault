<?php
/**
 * Read-from-vault shims for popular plugins (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Overrides option reads with vault values after migration.
 */
final class Plugin_Adapters {

	private const OPTION = 'credential_vault_pro_adapter_map';

	public static function register(): void {
		add_filter( 'pre_option_stripe_api_key', array( self::class, 'stripe_key' ), 10, 1 );
		add_filter( 'pre_option_stripe_secret_key', array( self::class, 'stripe_key' ), 10, 1 );
		add_filter( 'pre_option_smtp_pass', array( self::class, 'smtp_password' ), 10, 1 );
		add_filter( 'pre_option_phpmailer_smtp_password', array( self::class, 'smtp_password' ), 10, 1 );
		add_filter( 'pre_option_openai_api_key', array( self::class, 'openai_key' ), 10, 1 );
		add_filter( 'pre_option_mailchimp_api_key', array( self::class, 'mailchimp_key' ), 10, 1 );
		add_filter( 'pre_option_aws_secret_access_key', array( self::class, 'aws_secret' ), 10, 1 );
		add_filter( 'credential_vault_usage_map', array( self::class, 'inject_usage_rows' ), 10, 1 );
		add_action( 'admin_post_credential_vault_pro_save_adapters', array( self::class, 'handle_save' ) );
	}

	/**
	 * @return array<string, string> option_name => vault slug
	 */
	public static function map(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return self::default_map();
		}

		$out = array();
		foreach ( $stored as $option => $slug ) {
			$out[ sanitize_key( (string) $option ) ] = sanitize_key( (string) $slug );
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private static function default_map(): array {
		return array(
			'stripe_api_key'        => 'stripe-api-key',
			'stripe_secret_key'       => 'stripe-api-key',
			'smtp_pass'               => 'smtp-password',
			'phpmailer_smtp_password' => 'smtp-password',
			'openai_api_key'          => 'openai-api-key',
			'mailchimp_api_key'       => 'mailchimp-api-key',
			'aws_secret_access_key'   => 'aws-secret-access-key',
		);
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function stripe_key( $pre ) {
		return self::filter_option( $pre, 'stripe_secret_key' );
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function smtp_password( $pre ) {
		return self::filter_option( $pre, 'smtp_pass' );
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function openai_key( $pre ) {
		return self::filter_option( $pre, 'openai_api_key' );
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function mailchimp_key( $pre ) {
		return self::filter_option( $pre, 'mailchimp_api_key' );
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function aws_secret( $pre ) {
		return self::filter_option( $pre, 'aws_secret_access_key' );
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	private static function filter_option( $pre, string $option_name ) {
		if ( false !== $pre ) {
			return $pre;
		}

		$map  = self::map();
		$slug = $map[ $option_name ] ?? '';

		if ( '' === $slug ) {
			return $pre;
		}

		return self::vault_value( $slug );
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	public static function inject_usage_rows( array $rows ): array {
		foreach ( self::map() as $option => $slug ) {
			$rows[] = array(
				'source'         => 'adapter',
				'option'         => 'option:' . $option,
				'suggested_slug' => $slug,
				'vault_name'     => \CredentialVault\Secrets_Bridge::secret_name( $slug ),
				'in_vault'       => false,
				'adapter'        => 'pre_option_' . $option,
				'label'          => sprintf( 'Adapter: %s', $option ),
			);
		}

		return $rows;
	}

	/**
	 * @return string|false
	 */
	private static function vault_value( string $slug ) {
		if ( ! function_exists( 'credential_vault_get' ) ) {
			return false;
		}

		$value = credential_vault_get( $slug, false, array( 'source' => 'plugin_adapter' ) );

		return is_string( $value ) && '' !== $value ? $value : false;
	}

	public static function handle_save(): void {
		if ( ! \CredentialVault\Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_adapters' );

		$raw = isset( $_POST['adapter_map'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['adapter_map'] ) ) : '';
		$map = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $option, $slug ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$map[ sanitize_key( $option ) ] = sanitize_key( $slug );
		}

		update_option( self::OPTION, $map, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'adapters_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
