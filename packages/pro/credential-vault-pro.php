<?php
/**
 * Pro plugin bootstrap.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

/**
 * Plugin Name:       Credential Vault Pro
 * Plugin URI:        https://example.com/credential-vault-pro
 * Description:       REST API, WP Sudo audit logging, and advanced vault automation for Credential Vault.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       credential-vault-pro
 */

namespace CredentialVaultPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CREDENTIAL_VAULT_PRO_VERSION', '1.1.0' );
define( 'CREDENTIAL_VAULT_PRO_FILE', __FILE__ );
define( 'CREDENTIAL_VAULT_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREDENTIAL_VAULT_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'CREDENTIAL_VAULT_PRO_BASENAME', plugin_basename( __FILE__ ) );

require_once CREDENTIAL_VAULT_PRO_DIR . 'includes/class-autoloader.php';

Autoloader::register();

register_activation_hook( CREDENTIAL_VAULT_PRO_FILE, array( Plugin::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );
