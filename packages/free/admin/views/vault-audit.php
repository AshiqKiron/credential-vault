<?php
/**
 * Audit log tab.
 *
 * @package CredentialVault
 *
 * @var list<array<string, mixed>> $audit_entries
 * @var array<string, mixed>       $audit_filters
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$audit_filters = $audit_filters ?? array();

?>
<p class="description new-vault-audit-privacy">
	<?php esc_html_e( 'Privacy: the audit log stores timestamps, action names, user IDs, secret names, and cryptographic fingerprints — never plaintext secret values. Export only what you need for compliance reviews.', 'credvault' ); ?>
</p>
<div class="new-vault-audit-toolbar">
	<?php
	/**
	 * Audit filters and Pro search UI.
	 *
	 * @param array<string, mixed> $audit_filters
	 */
	do_action( 'credential_vault_audit_toolbar', $audit_filters );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-audit-export">
		<?php wp_nonce_field( 'credential_vault_export_audit' ); ?>
		<input type="hidden" name="action" value="credential_vault_export_audit" />
		<input type="hidden" name="audit_action" value="<?php echo esc_attr( (string) ( $audit_filters['action'] ?? '' ) ); ?>" />
		<input type="hidden" name="audit_secret" value="<?php echo esc_attr( (string) ( $audit_filters['secret'] ?? '' ) ); ?>" />
		<input type="hidden" name="audit_user" value="<?php echo esc_attr( (string) (int) ( $audit_filters['user_id'] ?? 0 ) ); ?>" />
		<?php submit_button( __( 'Export CSV', 'credvault' ), 'secondary', '', false ); ?>
	</form>
</div>
<table class="widefat striped new-vault-audit">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Time', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Action', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Credential', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'User', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Fingerprints', 'credvault' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $audit_entries ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No events recorded yet.', 'credvault' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $audit_entries as $entry ) : ?>
				<tr>
					<td>
						<?php
						echo esc_html(
							wp_date(
								get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
								(int) ( $entry['time'] ?? 0 )
							)
						);
						?>
					</td>
					<td><code><?php echo esc_html( (string) ( $entry['action'] ?? '' ) ); ?></code></td>
					<td><?php echo esc_html( (string) ( $entry['secret'] ?? '' ) ); ?></td>
					<td>
						<?php
						$user_id = (int) ( $entry['user_id'] ?? 0 );
						if ( $user_id > 0 ) {
							$user = get_userdata( $user_id );
							echo esc_html( $user ? $user->display_name : (string) $user_id );
						} else {
							esc_html_e( 'System', 'credvault' );
						}
						?>
					</td>
					<td class="new-vault-fingerprint">
						<?php
						$old = (string) ( $entry['old_fingerprint'] ?? '' );
						$new = (string) ( $entry['new_fingerprint'] ?? '' );
						if ( $old || $new ) {
							echo esc_html( ( $old ? substr( $old, 0, 12 ) . '…' : '' ) . ( $old && $new ? ' → ' : '' ) . ( $new ? substr( $new, 0, 12 ) . '…' : '' ) );
						} else {
							echo '—';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<?php
/**
 * Fires after the vault credential audit table (Pro may render WP Sudo action audit).
 */
do_action( 'credential_vault_after_audit_log' );
