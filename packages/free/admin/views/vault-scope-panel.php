<?php
/**
 * Sudo gate + add form + credentials table for a scope.
 *
 * @package CredentialVault
 *
 * @var string $scope site|network
 * @var list<array<string, mixed>> $credentials
 * @var bool $api_available
 * @var bool $sudo_active
 * @var int  $sudo_remaining
 * @var bool $writable
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

$presets     = Credential_Meta::service_presets();
$is_network  = 'network' === $scope;
$scope_label = $is_network ? __( 'network', 'credvault' ) : __( 'site', 'credvault' );

include CREDENTIAL_VAULT_DIR . 'admin/views/vault-sudo-panel.php';

if ( $api_available && $writable && $sudo_active ) :
	?>
	<div class="card new-vault-add">
		<h2>
			<?php
			printf(
				/* translators: %s: site or network */
				esc_html__( 'Add or rotate %s credential', 'credvault' ),
				esc_html( $scope_label )
			);
			?>
		</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'credential_vault_save' ); ?>
			<input type="hidden" name="action" value="credential_vault_save" />
			<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="credential_service"><?php esc_html_e( 'Service', 'credvault' ); ?></label></th>
					<td>
						<select name="credential_service" id="credential_service">
							<?php foreach ( $presets as $key => $preset_label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $preset_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="credential_label"><?php esc_html_e( 'Label', 'credvault' ); ?></label></th>
					<td><input name="credential_label" id="credential_label" type="text" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="credential_slug"><?php esc_html_e( 'Slug', 'credvault' ); ?></label></th>
					<td>
						<code><?php echo esc_html( Secrets_Bridge::VAULT_NAMESPACE . '/' ); ?></code>
						<input name="credential_slug" id="credential_slug" type="text" class="regular-text" pattern="[a-z0-9-]+" required />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="credential_value"><?php esc_html_e( 'API key', 'credvault' ); ?></label></th>
					<td><input name="credential_value" id="credential_value" type="password" class="large-text" autocomplete="off" required /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save to vault', 'credvault' ) ); ?>
		</form>
	</div>
	<?php
endif;
?>

<h2><?php esc_html_e( 'Stored credentials', 'credvault' ); ?></h2>
<?php include CREDENTIAL_VAULT_DIR . 'admin/partials/credentials-table.php'; ?>
