<?php
/**
 * WP Sudo gated-action audit table (Pro).
 *
 * @package CredentialVaultPro
 *
 * @var list<array<string, mixed>> $entries
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h2><?php esc_html_e( 'Sudo-gated action audit', 'credential-vault-pro' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Detailed log of WP Sudo session events, re-authentication failures, and high-risk actions across the admin (including Credential Vault when gated).', 'credential-vault-pro' ); ?>
</p>
<table class="widefat striped new-vault-audit new-vault-sudo-audit">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Time', 'credential-vault-pro' ); ?></th>
			<th><?php esc_html_e( 'Event', 'credential-vault-pro' ); ?></th>
			<th><?php esc_html_e( 'Rule', 'credential-vault-pro' ); ?></th>
			<th><?php esc_html_e( 'Surface', 'credential-vault-pro' ); ?></th>
			<th><?php esc_html_e( 'User', 'credential-vault-pro' ); ?></th>
			<th><?php esc_html_e( 'Client', 'credential-vault-pro' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $entries ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No sudo events recorded yet. Install and configure the WP Sudo plugin to gate privileged actions.', 'credential-vault-pro' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $entries as $entry ) : ?>
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
					<td><code><?php echo esc_html( (string) ( $entry['event'] ?? '' ) ); ?></code></td>
					<td><?php echo esc_html( (string) ( $entry['rule_id'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( (string) ( $entry['surface'] ?? '' ) ); ?></td>
					<td>
						<?php
						$user_id = (int) ( $entry['user_id'] ?? 0 );
						if ( $user_id > 0 ) {
							$user = get_userdata( $user_id );
							echo esc_html( $user ? $user->display_name : (string) $user_id );
						} else {
							esc_html_e( 'System', 'credential-vault-pro' );
						}
						?>
					</td>
					<td class="new-vault-fingerprint">
						<?php
						$ip = (string) ( $entry['ip'] ?? '' );
						$ua = (string) ( $entry['ua'] ?? '' );
						if ( $ip || $ua ) {
							echo esc_html( $ip . ( $ua ? ' · ' . substr( $ua, 0, 40 ) . ( strlen( $ua ) > 40 ? '…' : '' ) : '' ) );
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
