<?php
/**
 * Rotation workflow guidance (Secrets API current + previous slots).
 *
 * @package CredentialVault
 *
 * @var string $scope site|network
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Rotation_Workflow;

$steps = Rotation_Workflow::steps();

?>
<div class="card new-vault-rotation">
	<h2><?php esc_html_e( 'Key rotation workflow', 'credvault' ); ?></h2>
	<ol class="new-vault-rotation__steps">
		<?php foreach ( $steps as $step ) : ?>
			<li><?php echo esc_html( $step ); ?></li>
		<?php endforeach; ?>
	</ol>
	<p class="description">
		<?php esc_html_e( 'Use “Save to vault” with an existing slug to rotate a credential without downtime.', 'credvault' ); ?>
	</p>
	<h3><?php esc_html_e( 'Built-in rotation tests', 'credvault' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'After a rotated save, the free plugin runs smoke tests for Stripe (balance API) and OpenAI (models list) when the slug matches. Extend or override with the credential_vault_rotation_test filter.', 'credvault' ); ?>
	</p>
	<pre class="new-vault-code-sample">add_filter( 'credential_vault_rotation_test', function ( $result, $secret_name, $network ) {
    // Return true, WP_Error, or null to defer to other handlers.
    return $result;
}, 10, 3 );</pre>
</div>
