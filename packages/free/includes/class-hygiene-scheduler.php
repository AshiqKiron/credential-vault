<?php
/**
 * Scheduled hygiene scans (plaintext + duplicate detection).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Weekly cron snapshot for admin notices and health dashboards.
 */
final class Hygiene_Scheduler {

	public const CRON_HOOK = 'credential_vault_hygiene_scan';

	private const OPTION = 'credential_vault_hygiene_snapshot';

	public static function register( Loader $loader ): void {
		$loader->add_action( self::CRON_HOOK, self::class, 'run_scan' );
		$loader->add_action( 'admin_notices', self::class, 'maybe_notice' );
	}

	public static function schedule(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
	}

	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public static function run_scan(): void {
		if ( ! Secrets_Bridge::is_available() ) {
			return;
		}

		$prev     = self::latest_snapshot();
		$prev_sig = (string) ( $prev['signature'] ?? '' );

		$scan_count  = count( Plaintext_Scanner::scan() );
		$dup_count   = count( Duplicate_Detector::find() );
		$block_count = count( Block_Scanner::scan( 100 ) );
		$signature   = wp_hash( $scan_count . '|' . $dup_count . '|' . $block_count );

		$new_since = 0;
		if ( '' !== $prev_sig && $signature !== $prev_sig ) {
			$new_since = max( 0, ( $scan_count + $dup_count + $block_count ) - (int) ( $prev['total'] ?? 0 ) );
		}

		update_option(
			self::OPTION,
			array(
				'last_run'            => time(),
				'plaintext_count'     => $scan_count,
				'duplicate_count'     => $dup_count,
				'block_content_count' => $block_count,
				'total'               => $scan_count + $dup_count + $block_count,
				'signature'           => $signature,
				'new_since_last'      => $new_since,
			),
			false
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function latest_snapshot(): array {
		$snap = get_option( self::OPTION, array() );

		return is_array( $snap ) ? $snap : array();
	}

	public static function maybe_notice(): void {
		if ( ! is_admin() || ! Secrets_Bridge::current_user_can_manage() ) {
			return;
		}

		$snap = self::latest_snapshot();
		$new  = (int) ( $snap['new_since_last'] ?? 0 );
		if ( $new <= 0 ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'credential-vault' === $page ) {
			return;
		}

		$url = admin_url( 'admin.php?page=credential-vault&tab=health' );
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of new hygiene findings */
					_n(
						'Credential Vault weekly scan found %d new hygiene item.',
						'Credential Vault weekly scan found %d new hygiene items.',
						$new,
						'credvault'
					),
					$new
				)
			),
			esc_url( $url ),
			esc_html__( 'Review vault health', 'credvault' )
		);
	}
}
