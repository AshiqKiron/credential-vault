<?php
/**
 * iCal export for rotate-by reminders.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Builds VCALENDAR payloads from credential metadata.
 */
final class Rotation_Calendar {

	public static function register(): void {
		add_action( 'admin_post_credential_vault_export_rotation_ical', array( self::class, 'handle_export' ) );
	}

	public static function handle_export(): void {
		if ( ! Secrets_Bridge::current_user_can_view() ) {
			wp_die( esc_html__( 'You do not have permission to export the rotation calendar.', 'credvault' ) );
		}

		check_admin_referer( 'credential_vault_export_rotation_ical' );

		$body = self::build_ical();
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=credential-vault-rotation.ics' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/calendar attachment.
		exit;
	}

	public static function build_ical(): string {
		$lines   = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Credential Vault//Rotation Reminders//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
		);
		$site    = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host    = is_string( $site ) ? $site : 'wordpress';

		if ( Secrets_Bridge::is_available() ) {
			foreach ( Secrets_Bridge::list_vault_secrets( false ) as $row ) {
				$name = (string) $row['name'];
				$meta = Credential_Meta::get( $name );
				if ( null === $meta ) {
					continue;
				}
				$rotate_by = (int) ( $meta['rotate_by'] ?? 0 );
				if ( $rotate_by <= 0 ) {
					continue;
				}

				$slug  = basename( $name );
				$label = (string) ( $meta['label'] ?? $slug );
				$uid   = 'credvault-' . $slug . '@' . $host;
				$start = gmdate( 'Ymd', $rotate_by );
				$lines = array_merge(
					$lines,
					array(
						'BEGIN:VEVENT',
						'UID:' . self::escape( $uid ),
						'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
						'DTSTART;VALUE=DATE:' . $start,
						'SUMMARY:' . self::escape( sprintf( '[Credential Vault] Rotate %s', $label ) ),
						'DESCRIPTION:' . self::escape(
							sprintf(
								'Rotate-by reminder for vault slug %s. Metadata only — open Credential Vault in wp-admin to rotate.',
								$slug
							)
						),
						'END:VEVENT',
					)
				);
			}
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	private static function escape( string $value ): string {
		return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\\;', '\\,', '\\n' ), $value );
	}
}
