<?php
/**
 * Multisite network credential overview (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVaultPro\Multisite_Network;

$report     = $report ?? Multisite_Network::site_report();
$blue_green = Multisite_Network::blue_green_network_report();

?>
<div class="card">
	<h2><?php esc_html_e( 'Subsites vs template credentials', 'credential-vault-pro' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Site', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Configured', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Missing', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'Broken decrypt', 'credential-vault-pro' ); ?></th>
				<th><?php esc_html_e( 'On previous slot', 'credential-vault-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report as $row ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( (string) $row['name'] ); ?></strong><br />
						<a href="<?php echo esc_url( (string) $row['url'] ); ?>"><?php echo esc_html( (string) $row['url'] ); ?></a>
					</td>
					<td><?php echo (int) $row['configured']; ?></td>
					<td><?php echo (int) $row['missing']; ?></td>
					<td><?php echo (int) $row['broken']; ?></td>
					<td><?php echo (int) count( (array) ( $row['on_previous'] ?? array() ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( ! empty( $blue_green ) ) : ?>
<div class="card">
	<h2><?php esc_html_e( 'Network blue/green — retire previous only after subsites catch up', 'credential-vault-pro' ); ?></h2>
	<ul>
		<?php foreach ( $blue_green as $item ) : ?>
			<li>
				<?php echo esc_html( (string) ( $item['label'] ?? $item['name'] ) ); ?>
				<?php if ( ! empty( $item['sites'] ) ) : ?>
					— <?php esc_html_e( 'Sites still on previous slot:', 'credential-vault-pro' ); ?>
					<?php echo esc_html( implode( ', ', array_map( 'strval', (array) $item['sites'] ) ) ); ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>
