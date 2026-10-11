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
			'twilio'     => 'Twilio',
			'postmark'   => 'Postmark',
			'slack'      => 'Slack',
			'github'     => 'GitHub',
			'cloudflare' => 'Cloudflare',
			'googlemaps' => 'Google Maps',
			'oauth'      => 'OAuth',
			'webhook'    => __( 'Webhook', 'credvault' ),
			'custom'     => __( 'Custom', 'credvault' ),
		);
	}

	/**
	 * Extra form fields per service preset (non-secret values stored in meta).
	 *
	 * @return array<string, list<array{id: string, label: string, type: string}>>
	 */
	public static function service_field_templates(): array {
		$templates = array(
			'aws'     => array(
				array(
					'id'    => 'access_key_id',
					'label' => __( 'Access key ID', 'credvault' ),
					'type'  => 'public',
				),
			),
			'webhook' => array(
				array(
					'id'    => 'webhook_url',
					'label' => __( 'Webhook URL', 'credvault' ),
					'type'  => 'public',
				),
			),
			'oauth'   => array(
				array(
					'id'    => 'client_id',
					'label' => __( 'Client ID', 'credvault' ),
					'type'  => 'public',
				),
			),
		);

		/**
		 * Custom credential field templates keyed by service preset.
		 *
		 * @param array<string, list<array{id: string, label: string, type: string}>> $templates
		 */
		return apply_filters( 'credential_vault_service_field_templates', $templates );
	}

	/**
	 * Allowed environment tags for agency workflows.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function environment_choices(): array {
		return array(
			''            => __( 'Not set', 'credvault' ),
			'production'  => __( 'Production', 'credvault' ),
			'staging'     => __( 'Staging', 'credvault' ),
			'local'       => __( 'Local', 'credvault' ),
			'development' => __( 'Development', 'credvault' ),
		);
	}

	/**
	 * First-party integration keys shown on the centralized token dashboard.
	 *
	 * @return list<array{service: string, label: string, slug: string, description: string}>
	 */
	public static function token_integrations(): array {
		$integrations = array(
			array(
				'service'     => 'stripe',
				'label'       => 'Stripe',
				'slug'        => 'stripe-api-key',
				'description' => __( 'Secret or restricted API key for Stripe payments and billing.', 'credvault' ),
			),
			array(
				'service'     => 'openai',
				'label'       => 'OpenAI',
				'slug'        => 'openai-api-key',
				'description' => __( 'API key for OpenAI models and embeddings.', 'credvault' ),
			),
			array(
				'service'     => 'aws',
				'label'       => 'AWS',
				'slug'        => 'aws-secret-access-key',
				'description' => __( 'Secret access key (store alongside IAM access key ID in a separate credential if needed).', 'credvault' ),
			),
			array(
				'service'     => 'mailchimp',
				'label'       => 'Mailchimp',
				'slug'        => 'mailchimp-api-key',
				'description' => __( 'Mailchimp Marketing API key for lists and campaigns.', 'credvault' ),
			),
			array(
				'service'     => 'sendgrid',
				'label'       => 'SendGrid',
				'slug'        => 'sendgrid-api-key',
				'description' => __( 'Transactional email API keys.', 'credvault' ),
			),
			array(
				'service'     => 'recaptcha',
				'label'       => 'Google reCAPTCHA',
				'slug'        => 'recaptcha-secret-key',
				'description' => __( 'Secret keys for spam protection on forms.', 'credvault' ),
			),
			array(
				'service'     => 'twilio',
				'label'       => 'Twilio',
				'slug'        => 'twilio-auth-token',
				'description' => __( 'Auth token for SMS and voice APIs.', 'credvault' ),
			),
			array(
				'service'     => 'postmark',
				'label'       => 'Postmark',
				'slug'        => 'postmark-server-token',
				'description' => __( 'Server token for transactional email.', 'credvault' ),
			),
			array(
				'service'     => 'slack',
				'label'       => 'Slack',
				'slug'        => 'slack-signing-secret',
				'description' => __( 'Signing secret for incoming Slack webhooks and Events API.', 'credvault' ),
			),
			array(
				'service'     => 'github',
				'label'       => 'GitHub',
				'slug'        => 'github-pat',
				'description' => __( 'Personal access token for repos and Actions.', 'credvault' ),
			),
			array(
				'service'     => 'cloudflare',
				'label'       => 'Cloudflare',
				'slug'        => 'cloudflare-api-key',
				'description' => __( 'Global API key or token for DNS and CDN.', 'credvault' ),
			),
			array(
				'service'     => 'googlemaps',
				'label'       => 'Google Maps',
				'slug'        => 'google-maps-api-key',
				'description' => __( 'Browser or server key for Maps JavaScript / Geocoding.', 'credvault' ),
			),
			array(
				'service'     => 'oauth',
				'label'       => 'OAuth client',
				'slug'        => 'oauth-client-secret',
				'description' => __( 'Client secret half of an OAuth application pair.', 'credvault' ),
			),
		);

		/**
		 * Filter integrations on the token dashboard.
		 *
		 * @param list<array{service: string, label: string, slug: string, description: string}> $integrations
		 */
		return apply_filters( 'credential_vault_token_integrations', $integrations );
	}

	/**
	 * Resolve stored vault secret for a dashboard integration (default slug or matching service meta).
	 *
	 * @param list<array{name: string, fingerprint: string, created: int, has_previous: bool, needs_rotation: bool}> $credentials
	 *
	 * @return array{configured: bool, secret_name: string, label: string, masked_preview: string, needs_rotation: bool}|null
	 */
	public static function integration_status( array $integration, array $credentials ): ?array {
		$default_name = Secrets_Bridge::secret_name( $integration['slug'] );
		$match_name   = '';

		foreach ( $credentials as $row ) {
			$name = (string) $row['name'];
			if ( $name === $default_name ) {
				$match_name = $name;
				break;
			}

			$meta = self::get( $name );
			if ( is_array( $meta ) && ( $meta['service'] ?? '' ) === $integration['service'] ) {
				$match_name = $name;
				break;
			}
		}

		if ( '' === $match_name ) {
			return array(
				'configured'      => false,
				'secret_name'     => $default_name,
				'label'           => $integration['label'],
				'masked_preview'  => '',
				'needs_rotation'  => false,
			);
		}

		foreach ( $credentials as $row ) {
			if ( (string) $row['name'] !== $match_name ) {
				continue;
			}

			$meta = self::get( $match_name );

			return array(
				'configured'      => true,
				'secret_name'     => $match_name,
				'label'           => is_array( $meta ) ? (string) ( $meta['label'] ?? $integration['label'] ) : $integration['label'],
				'masked_preview'  => is_array( $meta ) ? (string) ( $meta['masked_preview'] ?? '' ) : '',
				'needs_rotation'  => ! empty( $row['needs_rotation'] ),
			);
		}

		return null;
	}

	/**
	 * @return array<string, array{label: string, service: string, masked_preview: string, notes: string, owner: string, runbook_url: string, public_fields: array<string, string>, environment: string, rotate_by: int}>
	 */
	private static function all(): array {
		$meta = get_option( self::OPTION, array() );

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * @return array{label: string, service: string, masked_preview: string, notes: string, owner: string, runbook_url: string, public_fields: array<string, string>, environment: string, rotate_by: int}|null
	 */
	public static function get( string $secret_name ): ?array {
		$all = self::all();
		$row = $all[ $secret_name ] ?? null;

		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::normalize_row( $row );
	}

	/**
	 * @param string $secret_name     Fully qualified secret name.
	 * @param string $label           Human label.
	 * @param string $service         Preset key.
	 * @param string $masked_preview  Display-only mask (computed at save time).
	 * @param string $notes           Operator notes (non-secret).
	 * @param string $environment     production|staging|local|development or empty.
	 * @param int    $rotate_by       Unix timestamp for rotation reminder, or 0.
	 */
	public static function save(
		string $secret_name,
		string $label,
		string $service,
		string $masked_preview,
		string $notes = '',
		string $environment = '',
		int $rotate_by = 0,
		string $owner = '',
		string $runbook_url = '',
		array $public_fields = array()
	): void {
		$all = self::all();

		$existing = is_array( $all[ $secret_name ] ?? null ) ? $all[ $secret_name ] : array();

		$all[ $secret_name ] = self::normalize_row(
			array_merge(
				$existing,
				array(
					'label'          => sanitize_text_field( $label ),
					'service'        => sanitize_key( $service ),
					'masked_preview' => sanitize_text_field( $masked_preview ),
					'notes'          => sanitize_textarea_field( $notes ),
					'owner'          => sanitize_text_field( $owner ),
					'runbook_url'    => esc_url_raw( $runbook_url ),
					'public_fields'  => self::sanitize_public_fields( $public_fields ),
					'environment'    => self::sanitize_environment( $environment ),
					'rotate_by'      => max( 0, $rotate_by ),
				)
			)
		);

		update_option( self::OPTION, $all, false );
	}

	/**
	 * Update non-secret metadata without rotating the credential value.
	 *
	 * @param string $secret_name Fully qualified secret name.
	 * @param string $label       Human label.
	 * @param string $notes       Operator notes.
	 * @param string $environment Environment slug.
	 * @param int    $rotate_by   Unix timestamp or 0.
	 */
	public static function update_details(
		string $secret_name,
		string $label,
		string $notes,
		string $environment,
		int $rotate_by,
		string $owner = '',
		string $runbook_url = '',
		array $public_fields = array()
	): void {
		$all = self::all();
		if ( ! isset( $all[ $secret_name ] ) || ! is_array( $all[ $secret_name ] ) ) {
			$all[ $secret_name ] = self::normalize_row(
				array(
					'label'          => '' !== $label ? $label : basename( $secret_name ),
					'service'        => 'custom',
					'masked_preview' => '',
				)
			);
		}

		$row = self::normalize_row( $all[ $secret_name ] );

		$row['label']         = sanitize_text_field( $label );
		$row['notes']         = sanitize_textarea_field( $notes );
		$row['owner']         = sanitize_text_field( $owner );
		$row['runbook_url']   = esc_url_raw( $runbook_url );
		$row['public_fields'] = self::sanitize_public_fields( $public_fields );
		$row['environment']   = self::sanitize_environment( $environment );
		$row['rotate_by']     = max( 0, $rotate_by );

		$all[ $secret_name ] = $row;
		update_option( self::OPTION, $all, false );
	}

	/**
	 * @param array<string, mixed> $fields Raw public field values.
	 * @return array<string, string>
	 */
	public static function sanitize_public_fields( array $fields ): array {
		$clean = array();
		foreach ( $fields as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			$clean[ $key ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}

	private static function sanitize_environment( string $environment ): string {
		$environment = sanitize_key( $environment );
		$choices     = self::environment_choices();

		return isset( $choices[ $environment ] ) ? $environment : '';
	}

	/**
	 * @param array<string, mixed> $row Raw meta row.
	 * @return array{label: string, service: string, masked_preview: string, notes: string, owner: string, runbook_url: string, public_fields: array<string, string>, environment: string, rotate_by: int}
	 */
	private static function normalize_row( array $row ): array {
		$public = $row['public_fields'] ?? array();

		return array(
			'label'          => (string) ( $row['label'] ?? '' ),
			'service'        => (string) ( $row['service'] ?? 'custom' ),
			'masked_preview' => (string) ( $row['masked_preview'] ?? '' ),
			'notes'          => (string) ( $row['notes'] ?? '' ),
			'owner'          => (string) ( $row['owner'] ?? '' ),
			'runbook_url'    => esc_url_raw( (string) ( $row['runbook_url'] ?? '' ) ),
			'public_fields'  => is_array( $public ) ? self::sanitize_public_fields( $public ) : array(),
			'environment'    => self::sanitize_environment( (string) ( $row['environment'] ?? '' ) ),
			'rotate_by'      => max( 0, (int) ( $row['rotate_by'] ?? 0 ) ),
		);
	}

	public static function environment_badge_class( string $environment ): string {
		$map = array(
			'production'  => 'new-vault-badge--env-production',
			'staging'     => 'new-vault-badge--env-staging',
			'local'       => 'new-vault-badge--env-local',
			'development' => 'new-vault-badge--env-development',
		);

		return $map[ $environment ] ?? '';
	}

	public static function delete( string $secret_name ): void {
		$all = self::all();

		unset( $all[ $secret_name ] );

		update_option( self::OPTION, $all, false );
	}
}
