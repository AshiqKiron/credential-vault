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

use CredentialVault\Admin\Field_Help;
use CredentialVault\Rotation_Workflow;

$steps = Rotation_Workflow::steps();

?>
<details class="card new-vault-rotation">
	<summary class="new-vault-heading-tip">
		<?php esc_html_e( 'Key rotation workflow', 'credvault' ); ?>
		<?php
		echo Field_Help::icon(
			__( 'Save with an existing slug to rotate without downtime. Stripe and OpenAI slugs get optional smoke tests after save.', 'credvault' )
		);
		?>
	</summary>
	<div class="new-vault-rotation__body">
		<ol class="new-vault-rotation__steps">
			<?php foreach ( $steps as $step ) : ?>
				<li><?php echo esc_html( $step ); ?></li>
			<?php endforeach; ?>
		</ol>
		<details class="new-vault-meta-details">
			<summary><?php esc_html_e( 'Extend rotation tests', 'credvault' ); ?></summary>
			<pre class="new-vault-code-sample">add_filter( 'credential_vault_rotation_test', function ( $result, $secret_name, $network ) {
    // Return true, WP_Error, or null to defer to other handlers.
    return $result;
}, 10, 3 );</pre>
		</details>
	</div>
</details>
