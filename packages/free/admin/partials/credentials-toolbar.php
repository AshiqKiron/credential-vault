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

use CredentialVault\Credential_Meta;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_search = isset( $_GET['cv_search'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['cv_search'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_env = isset( $_GET['cv_env'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_env'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_filter = isset( $_GET['cv_filter'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_filter'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cv_sort = isset( $_GET['cv_sort'] ) ? sanitize_key( (string) wp_unslash( $_GET['cv_sort'] ) ) : 'label';

$tab = 'network' === $scope ? 'network' : 'credentials';
$action_url = add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=credential-vault' ) );

?>
<form method="get" class="new-vault-cred-toolbar">
	<input type="hidden" name="page" value="credential-vault" />
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
	<label>
		<?php esc_html_e( 'Search', 'credvault' ); ?>
		<input type="search" name="cv_search" value="<?php echo esc_attr( $cv_search ); ?>" />
	</label>
	<label>
		<?php esc_html_e( 'Environment', 'credvault' ); ?>
		<select name="cv_env">
			<option value=""><?php esc_html_e( 'Any', 'credvault' ); ?></option>
			<?php foreach ( Credential_Meta::environment_choices() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cv_env, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<?php esc_html_e( 'Filter', 'credvault' ); ?>
		<select name="cv_filter">
			<option value=""><?php esc_html_e( 'All', 'credvault' ); ?></option>
			<option value="overdue" <?php selected( $cv_filter, 'overdue' ); ?>><?php esc_html_e( 'Overdue rotate-by', 'credvault' ); ?></option>
			<option value="needs_rotation" <?php selected( $cv_filter, 'needs_rotation' ); ?>><?php esc_html_e( 'Needs rotation flag', 'credvault' ); ?></option>
			<option value="has_previous" <?php selected( $cv_filter, 'has_previous' ); ?>><?php esc_html_e( 'Previous slot active', 'credvault' ); ?></option>
			<option value="env_mismatch" <?php selected( $cv_filter, 'env_mismatch' ); ?>><?php esc_html_e( 'Environment mismatch', 'credvault' ); ?></option>
		</select>
	</label>
	<label>
		<?php esc_html_e( 'Sort', 'credvault' ); ?>
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
	<h3><?php esc_html_e( 'Bulk update metadata', 'credvault' ); ?></h3>
	<?php wp_nonce_field( 'credential_vault_bulk_meta' ); ?>
	<input type="hidden" name="action" value="credential_vault_bulk_meta" />
	<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
	<p class="description"><?php esc_html_e( 'Select credentials in the table below, then set shared metadata (does not change secret values).', 'credvault' ); ?></p>
	<p>
		<label><?php esc_html_e( 'Environment', 'credvault' ); ?>
			<select name="bulk_environment">
				<option value=""><?php esc_html_e( '— No change —', 'credvault' ); ?></option>
				<?php foreach ( Credential_Meta::environment_choices() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</p>
	<p>
		<label><?php esc_html_e( 'Rotate by', 'credvault' ); ?>
			<input type="date" name="bulk_rotate_by" />
		</label>
		<label><?php esc_html_e( 'Owner', 'credvault' ); ?>
			<input type="text" name="bulk_owner" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank for no change', 'credvault' ); ?>" />
		</label>
	</p>
	<?php submit_button( __( 'Update selected', 'credvault' ), 'secondary', '', false ); ?>
</form>
<?php endif; ?>
