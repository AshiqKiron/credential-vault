<?php
/**
 * Core plugin orchestrator.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Singleton that wires admin and public hooks.
 */
final class Plugin {

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @var Loader
	 */
	private Loader $loader;

	private function __construct() {
		$this->loader = new Loader();
		$this->set_locale();
		Site_Health::register( $this->loader );
		Rotation_Reminders::register( $this->loader );
		Rotation_Recipes::register( $this->loader );
		Vault_Capabilities::register( $this->loader );
		$this->define_admin_hooks();
		$this->define_cli_hooks();
		$this->define_public_hooks();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks with WordPress.
	 */
	public function run(): void {
		$this->loader->run();

		/**
		 * Fires after the free plugin has registered its hooks.
		 *
		 * @param self $plugin Plugin instance.
		 */
		do_action( 'credential_vault_loaded', $this );
	}

	private function set_locale(): void {
		$i18n = new I18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		Audit_Log::register_hooks( $this->loader );
		Sudo_Integration::register( $this->loader );
		Admin\Vault_Actions::register( $this->loader );

		$admin = new Admin\Admin();
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->loader->add_action( 'admin_notices', $admin, 'vault_admin_notices' );
	}

	private function define_public_hooks(): void {
		$public = new Public\Public_Facing();
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );
	}

	private function define_cli_hooks(): void {
		$this->loader->add_action( 'cli_init', CLI::class, 'register' );
	}
}
