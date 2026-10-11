<?php
/**
 * Developer tab — usage map, manifest CI, plugin adapters.
 *
 * @package CredentialVault
 *
 * @var list<array<string, mixed>> $usage_map
 * @var string                     $manifest_path
 * @var array<string, mixed>|null  $manifest_result
 * @var list<array<string, mixed>> $plugin_adapters
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$import_base = admin_url( 'admin.php?page=credential-vault&tab=import' );

?>
<div class="card">
	<h2><?php esc_html_e( 'Secrets manifest (CI / Git)', 'credvault' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Commit a metadata-only manifest (no secret values). Validate in CI with:', 'credvault' ); ?>
		<code>wp credvault validate --manifest=<?php echo esc_html( $manifest_path ); ?></code>
	</p>
	<?php if ( is_readable( $manifest_path ) ) : ?>
		<p><span class="new-vault-badge new-vault-badge--active"><?php esc_html_e( 'Found credvault.manifest.json in site root', 'credvault' ); ?></span></p>
		<?php
		$parsed = \CredentialVault\Secrets_Manifest::parse_file( $manifest_path );
		if ( ! is_wp_error( $parsed ) ) {
			$live = \CredentialVault\Secrets_Manifest::validate( $parsed );
			if ( ! empty( $live['errors'] ) ) {
				echo '<div class="notice notice-error inline"><p>' . esc_html( implode( ' ', $live['errors'] ) ) . '</p></div>';
			} else {
				echo '<p class="description">' . esc_html__( 'Root manifest validates against the live vault.', 'credvault' ) . '</p>';
			}
		}
		?>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Validate manifest (upload or paste)', 'credvault' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'credential_vault_validate_manifest' ); ?>
		<input type="hidden" name="action" value="credential_vault_validate_manifest" />
		<p><input type="file" name="manifest_file" accept=".json,application/json" /></p>
		<p><textarea name="manifest_json" class="large-text code" rows="6" placeholder="{ &quot;secrets&quot;: [] }"></textarea></p>
		<?php submit_button( __( 'Validate', 'credvault' ), 'secondary', '', false ); ?>
	</form>
	<?php if ( ! empty( $manifest_result ) && is_array( $manifest_result ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: errors count, 2: missing count */
						__( 'Validation: %1$d error(s), %2$d missing slug(s).', 'credvault' ),
						count( (array) ( $manifest_result['errors'] ?? array() ) ),
						count( (array) ( $manifest_result['missing'] ?? array() ) )
					)
				);
				?>
			</p>
			<?php if ( ! empty( $manifest_result['errors'] ) ) : ?>
				<ul>
					<?php foreach ( (array) $manifest_result['errors'] as $err ) : ?>
						<li><?php echo esc_html( (string) $err ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Plugin adapter snippets', 'credvault' ); ?></h2>
	<?php foreach ( $plugin_adapters as $adapter ) : ?>
		<h3><?php echo esc_html( (string) ( $adapter['plugin'] ?? '' ) ); ?></h3>
		<p class="description"><?php echo esc_html( (string) ( $adapter['description'] ?? '' ) ); ?></p>
		<pre class="new-vault-code-sample"><code><?php echo esc_html( (string) ( $adapter['snippet'] ?? '' ) ); ?></code></pre>
	<?php endforeach; ?>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Where credentials live (migration map)', 'credvault' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Label', 'credvault' ); ?></th>
				<th><?php esc_html_e( 'Source', 'credvault' ); ?></th>
				<th><?php esc_html_e( 'Suggested slug', 'credvault' ); ?></th>
				<th><?php esc_html_e( 'In vault', 'credvault' ); ?></th>
				<th><?php esc_html_e( 'Import', 'credvault' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $usage_map ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No mapped sources detected.', 'credvault' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $usage_map as $row ) : ?>
					<?php
					$slug = (string) ( $row['suggested_slug'] ?? '' );
					$import_url = add_query_arg(
						array(
							'tab'   => 'credentials',
							'slug'  => $slug,
							'label' => rawurlencode( (string) ( $row['label'] ?? $slug ) ),
						),
						admin_url( 'admin.php?page=credential-vault' )
					);
					?>
					<tr>
						<td><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( $row['option'] ?? '' ) ); ?></code></td>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo ! empty( $row['in_vault'] ) ? esc_html__( 'Yes', 'credvault' ) : esc_html__( 'No', 'credvault' ); ?></td>
						<td>
							<?php if ( empty( $row['in_vault'] ) ) : ?>
								<a href="<?php echo esc_url( $import_url ); ?>"><?php esc_html_e( 'Pre-fill add form', 'credvault' ); ?></a>
								·
								<a href="<?php echo esc_url( $import_base ); ?>"><?php esc_html_e( 'Import tab', 'credvault' ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
