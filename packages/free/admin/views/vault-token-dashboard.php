<?php
/**
 * Centralized token dashboard for common third-party integrations.
 *
 * @package CredentialVault
 *
 * @var bool   $api_available
 * @var bool   $sudo_active
 * @var int    $sudo_remaining
 * @var bool   $writable
 * @var list<array<string, mixed>> $credentials
 * @var list<array<string, mixed>> $audit_entries
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Credential_Meta;

$integrations = Credential_Meta::token_integrations();
$groups       = Credential_Meta::integration_groups();
$base_url     = admin_url( 'admin.php?page=credential-vault' );
$manage_url   = add_query_arg( 'tab', 'credentials', $base_url );
$grouped       = array();
$rotation_rows = array();
$counts        = array(
	'all'        => 0,
	'configured' => 0,
	'attention'  => 0,
	'available'  => 0,
);

foreach ( array_keys( $groups ) as $group_key ) {
	$grouped[ $group_key ] = array();
}

foreach ( $integrations as $integration ) {
	$status = Credential_Meta::integration_status( $integration, $credentials );
	if ( null === $status ) {
		continue;
	}

	$group = isset( $integration['group'] ) ? sanitize_key( (string) $integration['group'] ) : 'other';
	if ( ! isset( $groups[ $group ] ) ) {
		$label = isset( $integration['group_label'] ) ? (string) $integration['group_label'] : ucfirst( $group );
		$groups[ $group ] = $label;
		$grouped[ $group ] = array();
	}

	$bucket = 'available';
	if ( $status['configured'] && ! empty( $status['needs_rotation'] ) ) {
		$bucket = 'attention';
	} elseif ( $status['configured'] ) {
		$bucket = 'ready';
	}

	++$counts['all'];
	if ( $status['configured'] ) {
		++$counts['configured'];
	}
	if ( 'attention' === $bucket ) {
		++$counts['attention'];
	}
	if ( 'available' === $bucket ) {
		++$counts['available'];
	}

	$service_label = (string) $integration['label'];
	$parts         = preg_split( '/\s+/', $service_label );
	if ( ! is_array( $parts ) || array() === $parts ) {
		$parts = array( $service_label );
	}
	$monogram = strtoupper( substr( (string) $parts[0], 0, 1 ) );
	if ( isset( $parts[1] ) && '' !== (string) $parts[1] ) {
		$monogram .= strtoupper( substr( (string) $parts[1], 0, 1 ) );
	}

	$configure_url = add_query_arg(
		array(
			'tab'     => 'credentials',
			'service' => $integration['service'],
			'slug'    => $integration['slug'],
			'label'   => rawurlencode( $service_label ),
		),
		$base_url
	);

	$row = array(
		'integration' => $integration,
		'status'      => $status,
		'bucket'      => $bucket,
		'url'         => $configure_url,
		'monogram'    => $monogram,
		'tone'        => (int) ( hexdec( substr( md5( (string) $integration['service'] ), 0, 2 ) ) % 6 ),
		'search'      => strtolower( $service_label . ' ' . (string) $integration['description'] . ' ' . (string) $groups[ $group ] ),
	);

	$grouped[ $group ][] = $row;
	if ( 'attention' === $bucket ) {
		$rotation_rows[] = $row;
	}
}

include CREDENTIAL_VAULT_DIR . 'admin/views/vault-sudo-panel.php';

if ( ! empty( $demo_can_install ) && empty( $credentials ) ) {
	$redirect_tab = 'dashboard';
	include CREDENTIAL_VAULT_DIR . 'admin/partials/vault-demo-install-card.php';
}

include CREDENTIAL_VAULT_DIR . 'admin/views/vault-onboarding.php';

if ( ! empty( $vault_health ) && is_array( $vault_health ) ) :
	$attention = (int) ( $vault_health['overdue_rotate_by'] ?? 0 )
		+ (int) ( $vault_health['plaintext_candidates'] ?? 0 )
		+ (int) ( $vault_health['undecryptable'] ?? 0 );
	if ( $attention > 0 ) :
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				esc_html_e( 'Vault health items need attention.', 'credvault' );
				echo ' ';
				printf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=credential-vault&tab=health' ) ),
					esc_html__( 'View health summary', 'credvault' )
				);
				?>
			</p>
		</div>
		<?php
	endif;
endif;

?>
<p class="description new-vault-token-intro">
	<?php esc_html_e( 'API keys for common services, grouped by what they are for. Unlock the vault to preview a stored key.', 'credvault' ); ?>
</p>

<?php if ( ! $api_available && empty( $storage_available ) ) : ?>
	<p><?php esc_html_e( 'Secrets API required before tokens can be stored.', 'credvault' ); ?></p>
<?php endif; ?>
<?php if ( ! empty( $storage_available ) ) : ?>
	<div class="new-vault-token-board" id="cv-token-board">
		<div class="new-vault-token-toolbar">
			<label class="screen-reader-text" for="cv-token-search"><?php esc_html_e( 'Search services', 'credvault' ); ?></label>
			<input type="search" id="cv-token-search" class="new-vault-token-search" placeholder="<?php esc_attr_e( 'Search services', 'credvault' ); ?>" autocomplete="off" />
			<a class="button new-vault-token-manage" href="<?php echo esc_url( $manage_url ); ?>"><?php esc_html_e( 'All credentials', 'credvault' ); ?></a>
		</div>

		<div class="new-vault-token-stats" role="group" aria-label="<?php esc_attr_e( 'Filter services', 'credvault' ); ?>">
			<button type="button" class="new-vault-token-stat is-active" data-token-filter="all" aria-pressed="true">
				<strong><?php echo esc_html( (string) $counts['all'] ); ?></strong>
				<span><?php esc_html_e( 'All services', 'credvault' ); ?></span>
			</button>
			<button type="button" class="new-vault-token-stat" data-token-filter="configured" aria-pressed="false">
				<strong><?php echo esc_html( (string) $counts['configured'] ); ?></strong>
				<span><?php esc_html_e( 'Configured', 'credvault' ); ?></span>
			</button>
			<button type="button" class="new-vault-token-stat<?php echo $counts['attention'] > 0 ? ' new-vault-token-stat--warn' : ''; ?>" data-token-filter="attention" aria-pressed="false">
				<strong><?php echo esc_html( (string) $counts['attention'] ); ?></strong>
				<span><?php esc_html_e( 'Need rotation', 'credvault' ); ?></span>
			</button>
			<button type="button" class="new-vault-token-stat" data-token-filter="available" aria-pressed="false">
				<strong><?php echo esc_html( (string) $counts['available'] ); ?></strong>
				<span><?php esc_html_e( 'Not set up', 'credvault' ); ?></span>
			</button>
		</div>

		<?php if ( array() !== $rotation_rows ) : ?>
			<div class="notice notice-warning inline new-vault-token-alert" data-token-alert="attention">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of keys that should be rotated. */
							_n( '%d key should be rotated:', '%d keys should be rotated:', $counts['attention'], 'credvault' ),
							$counts['attention']
						)
					);
					echo ' ';
					$rotation_links = array();
					foreach ( $rotation_rows as $rotation_row ) {
						$rotation_links[] = sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( $rotation_row['url'] ),
							esc_html( (string) $rotation_row['integration']['label'] )
						);
					}
					echo wp_kses_post( implode( ', ', $rotation_links ) );
					?>
				</p>
			</div>
		<?php endif; ?>

		<p class="new-vault-token-empty description" hidden><?php esc_html_e( 'No services match that filter.', 'credvault' ); ?></p>

		<?php foreach ( $grouped as $group_key => $rows ) : ?>
			<?php
			if ( array() === $rows ) {
				continue;
			}
			$ordered = array();
			foreach ( array( 'attention', 'ready', 'available' ) as $bucket_name ) {
				foreach ( $rows as $row ) {
					if ( $bucket_name === $row['bucket'] ) {
						$ordered[] = $row;
					}
				}
			}
			?>
			<section class="new-vault-token-group">
				<h2 class="new-vault-token-group__title">
					<?php echo esc_html( (string) $groups[ $group_key ] ); ?>
					<span class="new-vault-token-group__count"><?php echo esc_html( (string) count( $ordered ) ); ?></span>
				</h2>
				<div class="new-vault-token-list">
					<?php foreach ( $ordered as $row ) : ?>
						<?php
						$status      = $row['status'];
						$integration = $row['integration'];
						$row_class   = 'new-vault-token-row';
						if ( 'attention' === $row['bucket'] ) {
							$row_class .= ' new-vault-token-row--attention';
						} elseif ( 'ready' === $row['bucket'] ) {
							$row_class .= ' new-vault-token-row--ready';
						}
						?>
						<article
							class="<?php echo esc_attr( $row_class ); ?>"
							data-token-status="<?php echo esc_attr( $row['bucket'] ); ?>"
							data-token-configured="<?php echo $status['configured'] ? '1' : '0'; ?>"
							data-token-search="<?php echo esc_attr( $row['search'] ); ?>"
						>
							<div class="new-vault-token-row__identity">
								<span class="new-vault-token-mark new-vault-token-mark--<?php echo esc_attr( (string) $row['tone'] ); ?>" aria-hidden="true"><?php echo esc_html( $row['monogram'] ); ?></span>
								<div>
									<h3><?php echo esc_html( (string) $integration['label'] ); ?></h3>
									<p class="description"><?php echo esc_html( (string) $integration['description'] ); ?></p>
									<?php if ( $status['configured'] && $sudo_active && '' !== $status['masked_preview'] ) : ?>
										<code class="new-vault-preview new-vault-token-row__preview"><?php echo esc_html( $status['masked_preview'] ); ?></code>
									<?php elseif ( $status['configured'] ) : ?>
										<p class="description"><?php esc_html_e( 'Unlock the vault to view a masked preview.', 'credvault' ); ?></p>
									<?php endif; ?>
								</div>
							</div>
							<div class="new-vault-token-row__status">
								<?php if ( $status['configured'] ) : ?>
									<span class="new-vault-badge new-vault-badge--active"><?php esc_html_e( 'Configured', 'credvault' ); ?></span>
								<?php else : ?>
									<span class="new-vault-badge"><?php esc_html_e( 'Not set up', 'credvault' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $status['needs_rotation'] ) ) : ?>
									<span class="new-vault-badge new-vault-badge--warn"><?php esc_html_e( 'Rotate recommended', 'credvault' ); ?></span>
								<?php endif; ?>
							</div>
							<p class="new-vault-token-row__actions">
								<?php if ( $writable ) : ?>
									<a class="button<?php echo 'attention' === $row['bucket'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $row['url'] ); ?>">
										<?php
										if ( 'attention' === $row['bucket'] ) {
											esc_html_e( 'Rotate key', 'credvault' );
										} elseif ( $status['configured'] ) {
											esc_html_e( 'Update key', 'credvault' );
										} else {
											esc_html_e( 'Add key', 'credvault' );
										}
										?>
									</a>
								<?php endif; ?>
							</p>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Recent vault activity', 'credvault' ); ?></h2>
	<p class="description">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'audit', $base_url ) ); ?>"><?php esc_html_e( 'View full audit log', 'credvault' ); ?></a>
	</p>
	<?php
	$audit_entries = array_slice( $audit_entries, 0, 5 );
	include CREDENTIAL_VAULT_DIR . 'admin/views/vault-audit.php';
	?>
<?php endif; ?>
