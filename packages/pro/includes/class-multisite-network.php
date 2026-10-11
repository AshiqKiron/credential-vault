<?php
/**
 * Multisite network dashboard and blue/green reporting (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

/**
 * Agency network visibility across subsites.
 */
final class Multisite_Network {

	public static function register(): void {
		add_filter( 'credential_vault_admin_tabs', array( self::class, 'register_tab' ) );
		add_action( 'credential_vault_render_admin_tab', array( self::class, 'render_tab' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $tabs
	 * @return array<string, string>
	 */
	public static function register_tab( array $tabs ): array {
		if ( is_multisite() && current_user_can( 'manage_network' ) ) {
			$tabs['network-pro'] = __( 'Network overview', 'credential-vault-pro' );
		}

		return $tabs;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function render_tab( string $tab, array $context ): void {
		unset( $context );
		if ( 'network-pro' !== $tab ) {
			return;
		}

		$report = self::site_report();
		include CREDENTIAL_VAULT_PRO_DIR . 'admin/views/network-overview.php';
	}

	/**
	 * @return list<array{blog_id: int, name: string, url: string, configured: int, missing: int, broken: int, on_previous: list<string>}>
	 */
	public static function site_report(): array {
		if ( ! is_multisite() ) {
			return array();
		}

		$templates = Template_Credentials::expected_slugs();
		$sites     = get_sites( array( 'number' => 500 ) );
		$out       = array();

		foreach ( $sites as $site ) {
			$blog_id = (int) $site->blog_id;
			switch_to_blog( $blog_id );

			$configured = 0;
			$missing    = 0;
			$broken     = count( Secrets_Bridge::find_undecryptable( false ) );
			$on_prev    = array();

			foreach ( Secrets_Bridge::list_vault_secrets( false ) as $row ) {
				if ( ! empty( $row['has_previous'] ) ) {
					$on_prev[] = (string) $row['name'];
				}
			}

			foreach ( $templates as $slug ) {
				$name = Secrets_Bridge::secret_name( $slug );
				$sec  = Secrets_Bridge::get_secret( $name );
				if ( is_wp_error( $sec ) ) {
					continue;
				}
				if ( null === $sec ) {
					++$missing;
				} else {
					++$configured;
				}
			}

			$out[] = array(
				'blog_id'     => $blog_id,
				'name'        => get_bloginfo( 'name' ),
				'url'         => get_home_url( $blog_id, '/' ),
				'configured'  => $configured,
				'missing'     => $missing,
				'broken'      => $broken,
				'on_previous' => $on_prev,
			);

			restore_current_blog();
		}

		return $out;
	}

	/**
	 * Network secrets still serving previous slot before global retire.
	 *
	 * @return list<array{name: string, label: string, sites: list<int>}>
	 */
	public static function blue_green_network_report(): array {
		if ( ! Secrets_Bridge::supports_network() ) {
			return array();
		}

		$rows = Secrets_Bridge::list_vault_secrets( true );
		$out  = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['has_previous'] ) ) {
				continue;
			}

			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );

			$out[] = array(
				'name'  => $name,
				'label' => is_array( $meta ) ? (string) ( $meta['label'] ?? $name ) : $name,
				'sites' => self::sites_still_on_previous_slot( $name ),
			);
		}

		return $out;
	}

	/**
	 * @return list<int>
	 */
	private static function sites_still_on_previous_slot( string $network_secret_name ): array {
		if ( ! is_multisite() ) {
			return array();
		}

		$sites = get_sites( array( 'number' => 500 ) );
		$ids   = array();

		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			$local = Secrets_Bridge::get_secret( $network_secret_name );
			if ( ! is_wp_error( $local ) && null !== $local && ! empty( Secrets_Bridge::list_vault_secrets( false ) ) ) {
				foreach ( Secrets_Bridge::list_vault_secrets( false ) as $row ) {
					if ( (string) $row['name'] === $network_secret_name && ! empty( $row['has_previous'] ) ) {
						$ids[] = (int) $site->blog_id;
					}
				}
			}
			restore_current_blog();
		}

		return array_values( array_unique( $ids ) );
	}
}
