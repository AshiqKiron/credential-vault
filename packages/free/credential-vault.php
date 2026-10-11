<?php
/**
 * Plugin bootstrap.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

/**
 * Plugin Name:       Credential Vault
 * Plugin URI:        https://asphaltthemes.com/credential-vault
 * Description:       Admin dashboard for the WordPress Secrets API — encrypted API keys, sudo re-auth, rotation, and audit log.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            ashiquzzaman
 * Author URI:        https://asphaltthemes.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       credvault
 * Domain Path:       /languages
 */

namespace CredentialVault;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'CREDENTIAL_VAULT_VERSION' ) ) {
	return;
}

$main_plugin_file = defined( 'CREDENTIAL_VAULT_MAIN_FILE' ) ? CREDENTIAL_VAULT_MAIN_FILE : __FILE__;

define( 'CREDENTIAL_VAULT_VERSION', '1.0.0' );
define( 'CREDENTIAL_VAULT_FILE', $main_plugin_file );
define( 'CREDENTIAL_VAULT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREDENTIAL_VAULT_URL', plugin_dir_url( __FILE__ ) );
define( 'CREDENTIAL_VAULT_BASENAME', plugin_basename( $main_plugin_file ) );

require_once CREDENTIAL_VAULT_DIR . 'includes/class-autoloader.php';

Autoloader::register();

require_once CREDENTIAL_VAULT_DIR . 'includes/functions-api.php';

register_activation_hook( CREDENTIAL_VAULT_FILE, array( Activator::class, 'activate' ) );
register_deactivation_hook( CREDENTIAL_VAULT_FILE, array( Deactivator::class, 'deactivate' ) );

/**
 * Begins plugin execution.
 */
function run_credential_vault(): void {
	Plugin::instance()->run();
}

run_credential_vault();
