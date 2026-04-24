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
interface MustNemesis
{
    /**
     * Get all tokens belonging to this model.
     *
     * This defines a polymorphic one-to-many relationship where the model
     * is the owner of the tokens.
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
     * This performs a force delete, removing tokens from the database completely.
     * Use revokeNemesisTokens() for soft delete with audit trail.
     *
     * @return int Number of tokens permanently deleted
     */
    public function deleteNemesisTokens(): int;

    /**
     * Revoke (soft delete) all tokens for the model.
     *
     * This performs a soft delete, setting the `deleted_at` timestamp.
     * Revoked tokens can be restored later with restoreNemesisTokens().
     *
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokens(): int;

    /**
     * Permanently delete the current token (from the request).
     *
     * This performs a force delete on the token used in the current request.
     * The token cannot be recovered after this operation.
     */
    public function deleteCurrentNemesisToken(): void;

    /**
     * Revoke (soft delete) the current token (from the request).
     *
     * This performs a soft delete on the token used in the current request.
     * The token can be restored later with restoreNemesisTokens().
     */
    public function revokeCurrentNemesisToken(): void;

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
     */
    public function touchNemesisToken(string $token): void;

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
