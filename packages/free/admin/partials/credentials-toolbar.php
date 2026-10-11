<?php
/**
 * Search, filter, and bulk metadata toolbar for credentials lists.
 *
 * @package CredentialVault
 *
 * @var string $scope site|network
 * @var bool   $can_manage
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;
use CredentialVault\Credential_Meta;
use CredentialVault\Secret_Collections;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_search = isset( $_GET['cv_search'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['cv_search'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_env = isset( $_GET['cv_env'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_env'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_filter = isset( $_GET['cv_filter'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_filter'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_sort = isset( $_GET['cv_sort'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_sort'] ) ) : 'label';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_collection = isset( $_GET['cv_collection'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_collection'] ) ) : '';

$tab = 'network' === $scope ? 'network' : 'credentials';
$action_url = add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=credential-vault' ) );

?>
<form method="get" class="new-vault-cred-toolbar">
	<input type="hidden" name="page" value="credential-vault" />
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
	<label>
		<span class="new-vault-cred-toolbar__name"><?php echo Field_Help::inline( __( 'Search', 'credvault' ), Field_Help::credential_tip( 'search' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<input type="search" name="cv_search" value="<?php echo esc_attr( $cv_search ); ?>" />
	</label>
	<label>
		<span class="new-vault-cred-toolbar__name"><?php echo Field_Help::inline( __( 'Environment', 'credvault' ), Field_Help::credential_tip( 'environment' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<select name="cv_env">
			<option value=""><?php esc_html_e( 'Any', 'credvault' ); ?></option>
			<?php foreach ( Credential_Meta::environment_choices() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cv_env, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<span class="new-vault-cred-toolbar__name"><?php echo Field_Help::inline( __( 'Collection', 'credvault' ), Field_Help::credential_tip( 'collection' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<select name="cv_collection">
			<?php foreach ( Secret_Collections::choices() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cv_collection, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<span class="new-vault-cred-toolbar__name"><?php echo Field_Help::inline( __( 'Filter', 'credvault' ), Field_Help::credential_tip( 'filter' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<select name="cv_filter">
			<option value=""><?php esc_html_e( 'All', 'credvault' ); ?></option>
			<option value="overdue" <?php selected( $cv_filter, 'overdue' ); ?>><?php esc_html_e( 'Overdue rotate-by', 'credvault' ); ?></option>
			<option value="needs_rotation" <?php selected( $cv_filter, 'needs_rotation' ); ?>><?php esc_html_e( 'Needs rotation flag', 'credvault' ); ?></option>
			<option value="has_previous" <?php selected( $cv_filter, 'has_previous' ); ?>><?php esc_html_e( 'Previous slot active', 'credvault' ); ?></option>
			<option value="env_mismatch" <?php selected( $cv_filter, 'env_mismatch' ); ?>><?php esc_html_e( 'Environment mismatch', 'credvault' ); ?></option>
		</select>
	</label>
	<label>
		<span class="new-vault-cred-toolbar__name"><?php echo Field_Help::inline( __( 'Sort', 'credvault' ), Field_Help::credential_tip( 'sort' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<select name="cv_sort">
			<option value="label" <?php selected( $cv_sort, 'label' ); ?>><?php esc_html_e( 'Label', 'credvault' ); ?></option>
			<option value="slug" <?php selected( $cv_sort, 'slug' ); ?>><?php esc_html_e( 'Slug', 'credvault' ); ?></option>
			<option value="rotate_by" <?php selected( $cv_sort, 'rotate_by' ); ?>><?php esc_html_e( 'Rotate by', 'credvault' ); ?></option>
			<option value="activity" <?php selected( $cv_sort, 'activity' ); ?>><?php esc_html_e( 'Last activity', 'credvault' ); ?></option>
		</select>
	</label>
	<?php submit_button( __( 'Apply', 'credvault' ), 'secondary', '', false ); ?>
</form>

<?php if ( $can_manage ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-bulk-meta card" id="cv-bulk-meta-form">
	<h3 class="new-vault-heading-tip">
		<?php
		echo Field_Help::heading(
			__( 'Bulk update metadata', 'credvault' ),
			__( 'Select credentials in the table below, then apply shared metadata. Secret values and rotation slots are not changed.', 'credvault' )
		);
		?>
	</h3>
	<?php wp_nonce_field( 'credential_vault_bulk_meta' ); ?>
	<input type="hidden" name="action" value="credential_vault_bulk_meta" />
	<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
	<div class="new-vault-bulk-meta__grid">
		<label class="new-vault-bulk-meta__field new-vault-bulk-meta__field--wide">
			<?php echo Field_Help::inline( __( 'Environment', 'credvault' ), Field_Help::credential_tip( 'environment' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<select name="bulk_environment">
				<option value=""><?php esc_html_e( '— No change —', 'credvault' ); ?></option>
				<?php foreach ( Credential_Meta::environment_choices() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="new-vault-bulk-meta__field">
			<?php echo Field_Help::inline( __( 'Rotate by', 'credvault' ), Field_Help::credential_tip( 'rotate_by' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="date" name="bulk_rotate_by" />
		</label>
		<label class="new-vault-bulk-meta__field">
			<?php echo Field_Help::inline( __( 'Owner', 'credvault' ), Field_Help::credential_tip( 'owner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="text" name="bulk_owner" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank for no change', 'credvault' ); ?>" />
		</label>
		<label class="new-vault-bulk-meta__field">
			<?php echo Field_Help::inline( __( 'Collection', 'credvault' ), Field_Help::credential_tip( 'collection' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<select name="bulk_collection">
				<option value=""><?php esc_html_e( '— No change —', 'credvault' ); ?></option>
				<?php foreach ( Secret_Collections::choices() as $key => $label ) : ?>
					<?php if ( '' === $key ) { continue; } ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
	<?php submit_button( __( 'Update selected', 'credvault' ), 'secondary', '', false ); ?>
</form>
<?php endif; ?>
