<?php
/**
 * Local dev entry when the monorepo lives in wp-content/plugins/credential-vault.
 *
 * Production zips use packages/free/credential-vault.php as the plugin root.
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

define( 'CREDENTIAL_VAULT_MAIN_FILE', __FILE__ );

require __DIR__ . '/packages/free/credential-vault.php';
