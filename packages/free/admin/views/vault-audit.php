<?php
/**
 * Audit log tab.
 *
 * @package CredentialVault
 *
 * @var list<array<string, mixed>> $audit_entries
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
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
