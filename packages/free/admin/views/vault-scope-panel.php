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
 * @var bool $can_manage
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;
use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Vault_Settings;

$presets     = Credential_Meta::service_presets();
$is_network  = 'network' === $scope;
$scope_label = $is_network ? __( 'network', 'credvault' ) : __( 'site', 'credvault' );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_service = isset( $_GET['service'] ) ? sanitize_key( (string) wp_unslash( $_GET['service'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_slug    = isset( $_GET['slug'] ) ? sanitize_key( (string) wp_unslash( $_GET['slug'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_label   = isset( $_GET['label'] ) ? sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['label'] ) ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_rotate  = isset( $_GET['rotate'] ) && '1' === (string) wp_unslash( $_GET['rotate'] );

if ( '' !== $prefill_service && ! isset( $presets[ $prefill_service ] ) ) {
	$prefill_service = '';
}

$can_manage       = $can_manage ?? Secrets_Bridge::current_user_can_manage();
$can_edit_secrets = $api_available && $writable && $can_manage && ( $sudo_active || ! Vault_Settings::sudo_required_for_writes() );
$field_templates  = Credential_Meta::service_field_templates();

if ( $can_manage ) {
	include CREDENTIAL_VAULT_DIR . 'admin/views/vault-sudo-panel.php';
}
include CREDENTIAL_VAULT_DIR . 'admin/views/vault-rotation-guide.php';

if ( $is_network && $network_available ?? false ) :
	?>
	<p class="description"><?php esc_html_e( 'Network credentials apply to all sites unless overridden by a site-scoped secret with the same slug.', 'credvault' ); ?></p>
	<?php
elseif ( ! $is_network && ! empty( $network_credentials ?? array() ) ) :
	?>
	<p class="description new-vault-inherit-hint">
		<span class="new-vault-badge new-vault-badge--inherit"><?php esc_html_e( 'May inherit network', 'credvault' ); ?></span>
		<?php esc_html_e( 'Missing site slugs can fall back to network-scoped secrets with the same name.', 'credvault' ); ?>
	</p>
	<?php
endif;

if ( $can_edit_secrets ) :
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
					<th scope="row"><?php echo Field_Help::th( __( 'Service', 'credvault' ), Field_Help::credential_tip( 'service' ), 'credential_service' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td>
						<select name="credential_service" id="credential_service">
							<?php foreach ( $presets as $key => $preset_label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $prefill_service, $key ); ?>><?php echo esc_html( $preset_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Label', 'credvault' ), Field_Help::credential_tip( 'label' ), 'credential_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td><input name="credential_label" id="credential_label" type="text" class="regular-text" value="<?php echo esc_attr( $prefill_label ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Slug', 'credvault' ), Field_Help::credential_tip( 'slug' ), 'credential_slug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td>
						<code><?php echo esc_html( Secrets_Bridge::VAULT_NAMESPACE . '/' ); ?></code>
						<input name="credential_slug" id="credential_slug" type="text" class="regular-text" pattern="[a-z0-9-]+" required value="<?php echo esc_attr( $prefill_slug ); ?>" />
					</td>
				</tr>
				<?php foreach ( $field_templates as $service_key => $fields ) : ?>
					<?php foreach ( $fields as $field ) : ?>
						<?php
						$field_id = (string) ( $field['id'] ?? '' );
						if ( '' === $field_id ) {
							continue;
						}
						$input_name = 'credential_public_' . $field_id;
						?>
						<tr class="cv-service-field" data-cv-service="<?php echo esc_attr( $service_key ); ?>" hidden>
							<th scope="row"><?php echo Field_Help::th( (string) ( $field['label'] ?? $field_id ), Field_Help::credential_tip( $field_id ), $input_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
							<td><input name="<?php echo esc_attr( $input_name ); ?>" id="<?php echo esc_attr( $input_name ); ?>" type="text" class="large-text" autocomplete="off" /></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="credential_value" id="credential_value_label"><?php esc_html_e( 'Secret value', 'credvault' ); ?></label><?php echo Field_Help::icon( Field_Help::credential_tip( 'secret_value' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td><input name="credential_value" id="credential_value" type="password" class="large-text" autocomplete="off" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Environment', 'credvault' ), Field_Help::credential_tip( 'environment' ), 'credential_environment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td>
						<select name="credential_environment" id="credential_environment">
							<?php foreach ( Credential_Meta::environment_choices() as $env_key => $env_label ) : ?>
								<option value="<?php echo esc_attr( $env_key ); ?>"><?php echo esc_html( $env_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Rotate by', 'credvault' ), Field_Help::credential_tip( 'rotate_by' ), 'credential_rotate_by' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td>
						<input name="credential_rotate_by" id="credential_rotate_by" type="date" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Owner', 'credvault' ), Field_Help::credential_tip( 'owner' ), 'credential_owner' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td><input name="credential_owner" id="credential_owner" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. DevOps', 'credvault' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Runbook URL', 'credvault' ), Field_Help::credential_tip( 'runbook_url' ), 'credential_runbook_url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td><input name="credential_runbook_url" id="credential_runbook_url" type="url" class="large-text" placeholder="https://" /></td>
				</tr>
				<tr>
					<th scope="row"><?php echo Field_Help::th( __( 'Notes', 'credvault' ), Field_Help::credential_tip( 'notes' ), 'credential_notes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<td><textarea name="credential_notes" id="credential_notes" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'e.g. Used by WooCommerce Stripe', 'credvault' ); ?>"></textarea></td>
				</tr>
			</table>
			<?php
			submit_button(
				$prefill_rotate || '' !== $prefill_slug
					? __( 'Save rotated key', 'credvault' )
					: __( 'Save to vault', 'credvault' )
			);
			?>
		</form>
	</div>
	<?php
endif;
?>

<h2><?php esc_html_e( 'Stored credentials', 'credvault' ); ?></h2>
<?php
include CREDENTIAL_VAULT_DIR . 'admin/partials/credentials-toolbar.php';
include CREDENTIAL_VAULT_DIR . 'admin/partials/credentials-table.php';
?>
