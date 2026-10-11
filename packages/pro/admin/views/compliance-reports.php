<?php
/**
 * Compliance reports (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$production_rotated = $production_rotated ?? array();
$access_report      = $access_report ?? array();

?>
<div class="card">
	<h2><?php esc_html_e( 'Production keys rotated in the last 90 days', 'credential-vault-pro' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Credential', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Rotate by', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Compliant', 'credential-vault-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $production_rotated as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) ( $row['label'] ?? $row['name'] ) ); ?></td>
					<td>
						<?php
						$rb = (int) ( $row['rotate_by'] ?? 0 );
						echo $rb > 0 ? esc_html( gmdate( 'Y-m-d', $rb ) ) : '—';
						?>
					</td>
					<td><?php echo ! empty( $row['ok'] ) ? esc_html__( 'Yes', 'credential-vault-pro' ) : esc_html__( 'No', 'credential-vault-pro' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Vault access in the last 7 days', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_export_jsonl' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_export_jsonl" />
		<?php submit_button( __( 'Export audit (JSON Lines)', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time (UTC)', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Action', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'User', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Secret', 'credential-vault-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_slice( $access_report, 0, 50 ) as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( gmdate( 'c', (int) ( $entry['time'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( (string) ( $entry['action'] ?? '' ) ); ?></td>
					<td><?php echo (int) ( $entry['user_id'] ?? 0 ); ?></td>
					<td><?php echo esc_html( (string) ( $entry['secret'] ?? '' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
