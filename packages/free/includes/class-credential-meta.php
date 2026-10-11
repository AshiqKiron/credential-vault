<?php
/**
 * Non-secret metadata (labels, service presets, display hints).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Maps secret names to friendly labels and cached masked previews.
 */
final class Credential_Meta {

	private const OPTION = 'credential_vault_credential_meta';

	/**
	 * Built-in service presets for the add-credential form.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function service_presets(): array {
		return array(
			'stripe'     => 'Stripe',
			'openai'     => 'OpenAI',
			'aws'        => 'AWS',
			'mailchimp'  => 'Mailchimp',
			'sendgrid'   => 'SendGrid',
			'recaptcha'  => 'Google reCAPTCHA',
			'custom'     => __( 'Custom', 'credvault' ),
		);
	}

	/**
	 * @return array<string, array{label: string, service: string, masked_preview: string}>
	 */
	private static function all(): array {
		$meta = get_option( self::OPTION, array() );

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * @return array{label: string, service: string, masked_preview: string}|null
	 */
	public static function get( string $secret_name ): ?array {
		$all = self::all();

		return $all[ $secret_name ] ?? null;
	}

	/**
	 * @param string $secret_name     Fully qualified secret name.
	 * @param string $label           Human label.
	 * @param string $service         Preset key.
	 * @param string $masked_preview  Display-only mask (computed at save time).
	 */
	public static function save(
		string $secret_name,
		string $label,
		string $service,
		string $masked_preview
	): void {
		$all = self::all();

		$all[ $secret_name ] = array(
			'label'           => sanitize_text_field( $label ),
			'service'         => sanitize_key( $service ),
			'masked_preview'  => sanitize_text_field( $masked_preview ),
		);

		update_option( self::OPTION, $all, false );
	}

	public static function delete( string $secret_name ): void {
		$all = self::all();

		unset( $all[ $secret_name ] );

		update_option( self::OPTION, $all, false );
	}
}
