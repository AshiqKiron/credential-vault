<?php
/**
 * Vault health summary tab.
 *
 * @package CredentialVault
 *
 * @var array<string, int|bool|string> $health_summary
 * @var list<array<string, mixed>>     $env_mismatches
 * @var list<array{file: string, name: string, kind: string}> $config_constants
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base = admin_url( 'admin.php?page=credential-vault' );

?>
<h2><?php esc_html_e( 'Vault health', 'credvault' ); ?></h2>
<p class="description"><?php esc_html_e( 'Operational snapshot — no secret values are shown.', 'credvault' ); ?></p>

<div class="new-vault-health-grid">
	<div class="card">
		<h3><?php esc_html_e( 'Storage', 'credvault' ); ?></h3>
		<ul>
			<li><?php echo esc_html( sprintf( __( 'Secrets API: %s', 'credvault' ), ! empty( $health_summary['api_available'] ) ? __( 'yes', 'credvault' ) : __( 'no', 'credvault' ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Provider: %s', 'credvault' ), (string) ( $health_summary['provider'] ?? '' ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Writable: %s', 'credvault' ), ! empty( $health_summary['writable'] ) ? __( 'yes', 'credvault' ) : __( 'no', 'credvault' ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Site / network credentials: %1$d / %2$d', 'credvault' ), (int) $health_summary['site_secrets'], (int) $health_summary['network_secrets'] ) ); ?></li>
		</ul>
	</div>
	<div class="card">
		<h3><?php esc_html_e( 'Risk indicators', 'credvault' ); ?></h3>
		<ul>
			<li><a href="<?php echo esc_url( add_query_arg( 'tab', 'credentials', $base ) ); ?>"><?php echo esc_html( sprintf( __( 'Overdue rotate-by: %d', 'credvault' ), (int) $health_summary['overdue_rotate_by'] ) ); ?></a></li>
			<li><a href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base ) ); ?>"><?php echo esc_html( sprintf( __( 'Plaintext candidates: %d', 'credvault' ), (int) $health_summary['plaintext_candidates'] ) ); ?></a></li>
			<li><?php echo esc_html( sprintf( __( 'Duplicate hygiene issues: %d', 'credvault' ), (int) $health_summary['duplicate_hygiene'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Undecryptable secrets: %d', 'credvault' ), (int) $health_summary['undecryptable'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Environment mismatches: %d', 'credvault' ), (int) $health_summary['environment_mismatches'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Config constant names detected: %d', 'credvault' ), (int) $health_summary['config_constant_hits'] ) ); ?></li>
		</ul>
	</div>
	<div class="card">
		<h3><?php esc_html_e( 'Hygiene schedule', 'credvault' ); ?></h3>
		<?php
		$last = (int) ( $health_summary['hygiene_last_run'] ?? 0 );
		if ( $last > 0 ) {
			echo '<p>' . esc_html( sprintf( __( 'Last weekly scan: %s', 'credvault' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) ) ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Weekly scan has not run yet.', 'credvault' ) . '</p>';
		}
		?>
		<p><?php echo esc_html( sprintf( __( 'New findings since previous scan: %d', 'credvault' ), (int) $health_summary['hygiene_new_findings'] ) ); ?></p>
	</div>
</div>

<?php if ( ! empty( $env_mismatches ) ) : ?>
	<h3><?php esc_html_e( 'Environment mismatches', 'credvault' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Secret', 'credvault' ); ?></th><th><?php esc_html_e( 'Issue', 'credvault' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $env_mismatches as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( (string) $row['secret'] ); ?></code></td>
					<td><?php echo esc_html( (string) $row['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $config_constants ) ) : ?>
	<h3><?php esc_html_e( 'Constants / env var names (values not read)', 'credvault' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'File', 'credvault' ); ?></th><th><?php esc_html_e( 'Name', 'credvault' ); ?></th><th><?php esc_html_e( 'Kind', 'credvault' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $config_constants as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( (string) $row['file'] ); ?></code></td>
					<td><code><?php echo esc_html( (string) $row['name'] ); ?></code></td>
					<td><?php echo esc_html( (string) $row['kind'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
