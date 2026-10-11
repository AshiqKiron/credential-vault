<?php
/**
 * IDE / PHPStan stubs for Credential Vault public API.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

/**
 * Whether encrypted credential storage is available.
 */
function credential_vault_available(): bool {}

/**
 * Retrieve a vault credential by slug (plaintext).
 *
 * @param array<string, mixed> $context Optional audit context (e.g. source => my-plugin/v1).
 */
function credential_vault_get( string $slug, bool $network = false, array $context = array() ): ?string {}

/**
 * Store or rotate a vault credential programmatically.
 *
 * @param array<string, mixed> $meta label, service, notes, environment, rotate_by, owner, runbook_url, public_fields.
 * @return true|\WP_Error
 */
function credential_vault_set( string $slug, string $value, bool $network = false, array $meta = array() ) {}

/**
 * Register default metadata for a slug before a secret value exists.
 *
 * @param array<string, mixed> $meta label, service, notes, owner, runbook_url, environment, public_fields.
 */
function credential_vault_register( string $slug, array $meta = array() ): void {}
