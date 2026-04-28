<?php

declare(strict_types=1);

namespace Kani\Nemesis\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Kani\Nemesis\Models\NemesisToken;

/**
 * Contract for models that can own Nemesis tokens.
 *
 * Any Eloquent model (User, Admin, ApiClient, etc.) that needs to support
 * token-based authentication must implement this interface.
 *
 * The interface defines all operations for token management including creation,
 * revocation, deletion, restoration, and querying with support for soft deletes.
 *
 * @package Kani\Nemesis\Contracts
 */
interface MustNemesis extends CanBeFormatted
{
    /**
     * Get all tokens belonging to this model.
     *
     * Defines a polymorphic one-to-many relationship where the model is the owner of the tokens.
     *
     * @return MorphMany<NemesisToken, static>
     */
    public function nemesisTokens(): MorphMany;

    /**
     * Create a new token for the model.
     *
     * Generates a cryptographically secure random token, hashes it for storage,
     * and returns the plain text token (which should be shown to the user once).
     *
     * @param string|null $name Human-readable token name (e.g., "Mobile App", "API Key")
     * @param string|null $source Token source/origin (e.g., "web", "mobile", "api", "cli")
     * @param array<int, string>|null $abilities List of permissions/abilities granted
     * @param array<string, mixed>|null $metadata Additional metadata (validated and sanitized)
     * @return string The plain text token (store securely, cannot be retrieved again)
     *
     * @throws \Kani\Nemesis\Exceptions\MetadataValidationException When metadata is invalid
     */
    public function createNemesisToken(
        ?string $name = null,
        ?string $source = null,
        ?array $abilities = null,
        ?array $metadata = null
    ): string;

    /**
     * Permanently delete all tokens for the model.
     *
     * Performs a force delete, removing tokens from the database completely.
     * Use revokeNemesisTokens() for soft delete with audit trail.
     *
     * @return int Number of tokens permanently deleted
     */
    public function deleteNemesisTokens(): int;

    /**
     * Revoke (soft delete) all tokens for the model.
     *
     * Performs a soft delete, setting the `deleted_at` timestamp.
     * Revoked tokens can be restored later with restoreNemesisTokens().
     *
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokens(): int;

    /**
     * Revoke (soft delete) all tokens with a specific source.
     *
     * Useful for scenarios like "logout from all browsers" while keeping
     * mobile or API tokens active.
     *
     * @param string $source The source to filter by (e.g., "web", "mobile", "api")
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     *
     * @example
     * // Logout from all web sessions only
     * $user->revokeNemesisTokensBySource('web');
     *
     * @example
     * // Permanently delete all mobile tokens
     * $user->revokeNemesisTokensBySource('mobile', force: true);
     */
    public function revokeNemesisTokensBySource(string $source, bool $force = false): int;

    /**
     * Revoke (soft delete) all tokens with a specific name.
     *
     * Useful for revoking specific token types across all sources.
     *
     * @param string $name The token name to filter by
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     *
     * @example
     * // Revoke all temporary session tokens
     * $user->revokeNemesisTokensByName('temp_session');
     */
    public function revokeNemesisTokensByName(string $name, bool $force = false): int;

    /**
     * Revoke (soft delete) all tokens matching specific source and name.
     *
     * Provides the most granular control for token revocation.
     *
     * @param string $source The source to filter by
     * @param string $name The token name to filter by
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     *
     * @example
     * // Revoke only web session tokens, leave other web tokens intact
     * $user->revokeNemesisTokensBySourceAndName('web', 'web_session');
     */
    public function revokeNemesisTokensBySourceAndName(string $source, string $name, bool $force = false): int;

    /**
     * Revoke (soft delete) all tokens except those matching specific criteria.
     *
     * Useful for keeping specific token types while revoking all others.
     *
     * @param string $source The source to keep (tokens with this source will NOT be revoked)
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     *
     * @example
     * // Keep mobile tokens active, revoke everything else
     * $user->revokeAllNemesisTokensExceptSource('mobile');
     */
    public function revokeAllNemesisTokensExceptSource(string $source, bool $force = false): int;

    /**
     * Revoke (soft delete) tokens by custom criteria.
     *
     * Supports multiple condition formats:
     * - Simple equality: ['column' => 'value']
     * - With operator: ['column' => ['operator', 'value']]
     * - Array of conditions: [['column', 'operator', 'value']]
     *
     * @param array<string, mixed>|array<array{0: string, 1: string, 2: mixed}> $criteria Array of where conditions
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     *
     * @example
     * // Simple equality
     * $user->revokeNemesisTokensWhere(['source' => 'web']);
     *
     * @example
     * // With operator
     * $user->revokeNemesisTokensWhere(['created_at' => ['<', now()->subDays(30)]]);
     *
     * @example
     * // Multiple conditions
     * $user->revokeNemesisTokensWhere([
     *     ['source', '=', 'web'],
     *     ['created_at', '<', now()->subDays(30)]
     * ]);
     */
    public function revokeNemesisTokensWhere(array $criteria, bool $force = false): int;

    /**
     * Permanently delete the current token (from the request).
     *
     * Performs a force delete on the token used in the current request.
     * The token cannot be recovered after this operation.
     *
     * @return bool True if the token was successfully deleted, false otherwise
     */
    public function deleteCurrentNemesisToken(): bool;

    /**
     * Revoke (soft delete) the current token (from the request).
     *
     * Performs a soft delete on the token used in the current request.
     * The token can be restored later with restoreNemesisTokens().
     *
     * @return bool True if the token was successfully revoked, false otherwise
     */
    public function revokeCurrentNemesisToken(): bool;

    /**
     * Get the current access token from the request.
     *
     * Extracts the bearer token from the Authorization header and retrieves
     * the corresponding token model (excluding soft-deleted tokens by default).
     *
     * @return NemesisToken|null The token model or null if not found
     */
    public function currentNemesisToken(): ?NemesisToken;

    /**
     * Check if the model has any tokens.
     *
     * @param bool $withTrashed Whether to include soft-deleted (revoked) tokens
     * @return bool True if tokens exist, false otherwise
     */
    public function hasNemesisTokens(bool $withTrashed = false): bool;

    /**
     * Get a token by its plain text value.
     *
     * Hashes the provided token and searches for a matching token_hash.
     *
     * @param string $plainToken The plain text token to search for
     * @param bool $withTrashed Whether to include soft-deleted (revoked) tokens
     * @return NemesisToken|null The token model or null if not found
     */
    public function getNemesisToken(string $plainToken, bool $withTrashed = false): ?NemesisToken;

    /**
     * Validate if a token is valid for this model.
     *
     * A token is considered valid if:
     * - It exists and belongs to this model
     * - It is not expired (or expiration is null)
     * - It is not revoked (unless includeRevoked is true)
     *
     * @param string $token The plain text token to validate
     * @param bool $includeRevoked Whether to consider revoked tokens as valid
     * @return bool True if token is valid, false otherwise
     */
    public function validateNemesisToken(string $token, bool $includeRevoked = false): bool;

    /**
     * Update the last used timestamp of a token.
     *
     * Updates the `last_used_at` field to the current timestamp.
     * Useful for tracking token usage and implementing inactivity timeouts.
     *
     * @param string $token The plain text token to touch
     * @return bool True if the token was found and updated, false otherwise
     */
    public function touchNemesisToken(string $token): bool;

    /**
     * Get all tokens filtered by source.
     *
     * @param string $source The source to filter by (e.g., "web", "mobile", "api")
     * @param bool $withTrashed Whether to include soft-deleted (revoked) tokens
     * @return iterable<NemesisToken> Collection of tokens matching the source
     */
    public function getNemesisTokensBySource(string $source, bool $withTrashed = false): iterable;

    /**
     * Revoke (soft delete) all expired tokens.
     *
     * Tokens with `expires_at` in the past will be soft-deleted.
     * This preserves audit history while removing expired tokens from active queries.
     *
     * @return int Number of expired tokens revoked
     */
    public function revokeExpiredNemesisTokens(): int;

    /**
     * Permanently delete all expired tokens.
     *
     * Tokens with `expires_at` in the past will be force-deleted.
     * Use this for permanent cleanup without audit trail.
     *
     * @return int Number of expired tokens permanently deleted
     */
    public function forceDeleteExpiredNemesisTokens(): int;

    /**
     * Restore all revoked (soft-deleted) tokens.
     *
     * Revokes the soft delete, making the tokens active again.
     * Only affects tokens that were soft-deleted via revoke operations.
     *
     * @return int Number of tokens restored
     */
    public function restoreNemesisTokens(): int;
}
