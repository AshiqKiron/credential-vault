<?php
/**
 * Known plaintext option keys that can be migrated into the Secrets API.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Curated import map for common plugins (extend via filter).
 */
final class Import_Candidates {

	/**
	 * @return list<array{option: string, label: string, suggested_name: string, plugin: string}>
	 */
	public static function all(): array {
		$candidates = array(
			array(
				'option'         => 'woocommerce_stripe_settings',
				'label'          => __( 'WooCommerce Stripe (settings blob)', 'credvault' ),
				'suggested_name' => 'woocommerce/stripe-settings',
				'plugin'         => 'woocommerce',
			),
			array(
				'option'         => 'mailchimp_api_key',
				'label'          => __( 'Mailchimp API key', 'credvault' ),
				'suggested_name' => 'mailchimp/api-key',
				'plugin'         => 'mailchimp',
			),
			array(
				'option'         => 'openai_api_key',
				'label'          => __( 'OpenAI API key (generic option)', 'credvault' ),
				'suggested_name' => 'openai/api-key',
				'plugin'         => 'openai',
			),
			array(
				'option'         => 'recaptcha_secret_key',
				'label'          => __( 'reCAPTCHA secret key', 'credvault' ),
				'suggested_name' => 'google/recaptcha-secret',
				'plugin'         => 'recaptcha',
			),
			array(
				'option'         => 'smtp_password',
				'label'          => __( 'SMTP password (generic)', 'credvault' ),
				'suggested_name' => 'smtp/password',
				'plugin'         => 'smtp',
			),
		);

		/**
		 * Filter import candidates shown in the bulk import wizard.
		 *
		 * @param list<array{option: string, label: string, suggested_name: string, plugin: string}> $candidates
		 */
		return apply_filters( 'credential_vault_import_candidates', $candidates );
	}

	/**
	 * @return list<array{option: string, label: string, suggested_name: string, plugin: string, exists: bool, is_string: bool}>
	 */
	public static function detect(): array {
		$detected = array();

		foreach ( self::all() as $row ) {
			$option = $row['option'];
			$value  = get_option( $option, null );

			$row['exists']     = null !== $value;
			$row['is_string']  = is_string( $value );
			$detected[]        = $row;
		}

		return $detected;
	}
}
