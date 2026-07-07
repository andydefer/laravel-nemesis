<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Services;

use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Interface for the Nemesis token management service.
 *
 * Provides comprehensive token lifecycle management including creation,
 * validation, revocation, metadata handling, and capabilities management.
 * All tokens are associated with a tokenable model (User, ApiClient, etc.)
 * and support soft deletes, expiration, and origin restrictions.
 */
interface NemesisInterface
{
    // ============================================================================
    // Token Creation
    // ============================================================================

    /**
     * Create a new token for the given model.
     *
     * @param  NemesisTokenRecord  $record  The token record data
     * @param  Model  $tokenable  The model to associate the token with
     * @return NemesisToken The created token
     */
    public function create(NemesisTokenRecord $record, Model $tokenable): NemesisToken;

    /**
     * Create a new token and return it with the plain text token.
     *
     * @param  NemesisTokenRecord  $record  The token record data
     * @param  Model  $tokenable  The model to associate the token with
     * @return array{0: NemesisToken, 1: string} [token, plainToken]
     */
    public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array;

    // ============================================================================
    // CRUD Operations
    // ============================================================================

    /**
     * Update an existing token.
     *
     * @param  int  $tokenId  The token ID
     * @param  NemesisTokenRecord  $record  The updated token record data
     * @return NemesisToken The updated token
     */
    public function update(int $tokenId, NemesisTokenRecord $record): NemesisToken;

    /**
     * Soft delete a token.
     *
     * @param  int  $tokenId  The token ID
     * @return bool True if the token was deleted
     */
    public function delete(int $tokenId): bool;

    /**
     * Permanently delete a token.
     *
     * @param  int  $tokenId  The token ID
     * @return bool True if the token was permanently deleted
     */
    public function forceDelete(int $tokenId): bool;

    /**
     * Restore a soft-deleted token.
     *
     * @param  int  $tokenId  The token ID
     * @return bool True if the token was restored
     */
    public function restore(int $tokenId): bool;

    /**
     * Find a token by its ID.
     *
     * @param  int  $tokenId  The token ID
     * @return NemesisToken|null The token or null if not found
     */
    public function find(int $tokenId): ?NemesisToken;

    /**
     * Find a token by its ID, including soft-deleted ones.
     *
     * @param  int  $tokenId  The token ID
     * @return NemesisToken|null The token or null if not found
     */
    public function findWithTrashed(int $tokenId): ?NemesisToken;

    /**
     * Find a token by its hash.
     *
     * @param  string  $tokenHash  The hashed token
     * @return NemesisToken|null The token or null if not found
     */
    public function findByHash(string $tokenHash): ?NemesisToken;

    // ============================================================================
    // Bulk Delete Operations
    // ============================================================================

    /**
     * Bulk soft delete tokens matching the filters.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @return int The number of tokens deleted
     */
    public function deleteBulk(NemesisTokenFilterRecord $filters): int;

    /**
     * Bulk permanently delete tokens matching the filters.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @return int The number of tokens permanently deleted
     */
    public function forceDeleteBulk(NemesisTokenFilterRecord $filters): int;

    // ============================================================================
    // Tokenable Operations (Bulk)
    // ============================================================================

    /**
     * Get all tokens for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  bool  $withTrashed  Include soft-deleted tokens
     * @return Collection<int, NemesisToken> Collection of tokens
     */
    public function getTokensFor(Model $tokenable, bool $withTrashed = false): Collection;

    /**
     * Get tokens for a tokenable model filtered by source.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  string  $source  The token source
     * @param  bool  $withTrashed  Include soft-deleted tokens
     * @return Collection<int, NemesisToken> Collection of tokens
     */
    public function getTokensBySource(Model $tokenable, string $source, bool $withTrashed = false): Collection;

    /**
     * Get tokens for a tokenable model filtered by name.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  string  $name  The token name
     * @param  bool  $withTrashed  Include soft-deleted tokens
     * @return Collection<int, NemesisToken> Collection of tokens
     */
    public function getTokensByName(Model $tokenable, string $name, bool $withTrashed = false): Collection;

    /**
     * Check if a tokenable model has any tokens.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  bool  $withTrashed  Include soft-deleted tokens
     * @return bool True if the model has tokens
     */
    public function hasTokens(Model $tokenable, bool $withTrashed = false): bool;

    /**
     * Delete all tokens for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  bool  $force  Permanently delete
     * @return int The number of tokens deleted
     */
    public function deleteAllTokens(Model $tokenable, bool $force = false): int;

    /**
     * Revoke (soft delete) all tokens for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @return int The number of tokens revoked
     */
    public function revokeAllTokens(Model $tokenable): int;

    /**
     * Restore all soft-deleted tokens for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @return int The number of tokens restored
     */
    public function restoreAllTokens(Model $tokenable): int;

    /**
     * Revoke tokens by source.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  string  $source  The token source
     * @param  bool  $force  Permanently delete
     * @return int The number of tokens revoked
     */
    public function revokeTokensBySource(Model $tokenable, string $source, bool $force = false): int;

    /**
     * Revoke tokens by name.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  string  $name  The token name
     * @param  bool  $force  Permanently delete
     * @return int The number of tokens revoked
     */
    public function revokeTokensByName(Model $tokenable, string $name, bool $force = false): int;

    /**
     * Revoke tokens by source and name.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  string  $source  The token source
     * @param  string  $name  The token name
     * @param  bool  $force  Permanently delete
     * @return int The number of tokens revoked
     */
    public function revokeTokensBySourceAndName(Model $tokenable, string $source, string $name, bool $force = false): int;

    /**
     * Revoke all tokens except those with a specific source.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  string  $source  The source to keep
     * @param  bool  $force  Permanently delete
     * @return int The number of tokens revoked
     */
    public function revokeAllTokensExceptSource(Model $tokenable, string $source, bool $force = false): int;

    // ============================================================================
    // Current Token Operations
    // ============================================================================

    /**
     * Get the current token from the request for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  Request  $request  The HTTP request
     * @return NemesisToken|null The current token or null
     */
    public function getCurrentToken(Model $tokenable, Request $request): ?NemesisToken;

    /**
     * Revoke (soft delete) the current token.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  Request  $request  The HTTP request
     * @return bool True if the token was revoked
     */
    public function revokeCurrentToken(Model $tokenable, Request $request): bool;

    /**
     * Permanently delete the current token.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  Request  $request  The HTTP request
     * @return bool True if the token was deleted
     */
    public function deleteCurrentToken(Model $tokenable, Request $request): bool;

    // ============================================================================
    // Token Validation
    // ============================================================================

    /**
     * Validate a plain text token for a tokenable model.
     *
     * @param  string  $plainToken  The plain text token
     * @param  Model  $tokenable  The tokenable model
     * @param  bool  $includeRevoked  Check soft-deleted tokens as well
     * @return bool True if the token is valid
     */
    public function validateToken(string $plainToken, Model $tokenable, bool $includeRevoked = false): bool;

    /**
     * Get a token by its plain text value.
     *
     * @param  string  $plainToken  The plain text token
     * @param  Model  $tokenable  The tokenable model
     * @param  bool  $withTrashed  Include soft-deleted tokens
     * @return NemesisToken|null The token or null
     */
    public function getTokenByPlainText(string $plainToken, Model $tokenable, bool $withTrashed = false): ?NemesisToken;

    /**
     * Touch/update the last used timestamp of a token.
     *
     * @param  string  $plainToken  The plain text token
     * @param  Model  $tokenable  The tokenable model
     * @return bool True if the token was touched
     */
    public function touchToken(string $plainToken, Model $tokenable): bool;

    // ============================================================================
    // Count Operations
    // ============================================================================

    /**
     * Count tokens matching the filters.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @return int The number of tokens
     */
    public function count(NemesisTokenFilterRecord $filters): int;

    /**
     * Check if any tokens exist matching the filters.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @return bool True if tokens exist
     */
    public function exists(NemesisTokenFilterRecord $filters): bool;

    // ============================================================================
    // Expired Tokens Management
    // ============================================================================

    /**
     * Revoke (soft delete) expired tokens for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @return int The number of tokens revoked
     */
    public function revokeExpiredTokens(Model $tokenable): int;

    /**
     * Permanently delete expired tokens for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @return int The number of tokens deleted
     */
    public function forceDeleteExpiredTokens(Model $tokenable): int;

    // ============================================================================
    // Global Operations (without tokenable)
    // ============================================================================

    /**
     * Find all active tokens.
     *
     * @return Collection<int, NemesisToken> Collection of active tokens
     */
    public function findAllActive(): Collection;

    /**
     * Find all expired tokens.
     *
     * @return Collection<int, NemesisToken> Collection of expired tokens
     */
    public function findAllExpired(): Collection;

    /**
     * Find all revoked tokens.
     *
     * @return Collection<int, NemesisToken> Collection of revoked tokens
     */
    public function findAllRevoked(): Collection;

    /**
     * Revoke all expired tokens globally.
     *
     * @return int The number of tokens revoked
     */
    public function revokeAllExpiredTokensGlobally(): int;

    /**
     * Permanently delete all expired tokens globally.
     *
     * @return int The number of tokens deleted
     */
    public function forceDeleteAllExpiredTokensGlobally(): int;

    // ============================================================================
    // Token Capabilities
    // ============================================================================

    /**
     * Check if a token has a specific ability.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $ability  The ability to check
     * @return bool True if the token has the ability
     */
    public function can(NemesisToken $token, string $ability): bool;

    /**
     * Check if a token has all specified abilities.
     *
     * @param  NemesisToken  $token  The token
     * @param  array<string>  $abilities  The abilities to check
     * @return bool True if the token has all abilities
     */
    public function canAll(NemesisToken $token, array $abilities): bool;

    /**
     * Check if a token can be used from a specific origin.
     *
     * @param  NemesisToken  $token  The token
     * @param  string|null  $origin  The origin URL
     * @return bool True if the token can be used from the origin
     */
    public function canUseFromOrigin(NemesisToken $token, ?string $origin): bool;

    /**
     * Check if a token can be used from the current request's origin.
     *
     * @param  NemesisToken  $token  The token
     * @param  Request  $request  The HTTP request
     * @return bool True if the token can be used
     */
    public function canUseFromCurrentRequest(NemesisToken $token, Request $request): bool;

    // ============================================================================
    // Token Lifecycle Operations
    // ============================================================================

    /**
     * Update the last used timestamp of a token.
     *
     * @param  NemesisToken  $token  The token
     * @return NemesisToken The updated token
     */
    public function updateLastUsed(NemesisToken $token): NemesisToken;

    /**
     * Revoke (soft delete) a token.
     *
     * @param  NemesisToken  $token  The token
     * @return bool True if the token was revoked
     */
    public function revoke(NemesisToken $token): bool;

    /**
     * Restore a soft-deleted token.
     *
     * @param  NemesisToken  $token  The token
     * @return bool True if the token was restored
     */
    public function restoreToken(NemesisToken $token): bool;

    /**
     * Force a token to expire immediately.
     *
     * @param  NemesisToken  $token  The token
     * @return NemesisToken The updated token
     */
    public function forceExpire(NemesisToken $token): NemesisToken;

    /**
     * Force a token to expire after a specified number of minutes.
     *
     * @param  NemesisToken  $token  The token
     * @param  int  $minutes  Minutes to set in the past
     * @return NemesisToken The updated token
     */
    public function forceExpireByMinutes(NemesisToken $token, int $minutes): NemesisToken;

    // ============================================================================
    // Allowed Origins Management
    // ============================================================================

    /**
     * Add an allowed origin to a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $origin  The origin to add
     * @return NemesisToken The updated token
     */
    public function addAllowedOrigin(NemesisToken $token, string $origin): NemesisToken;

    /**
     * Remove an allowed origin from a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $origin  The origin to remove
     * @return NemesisToken The updated token
     */
    public function removeAllowedOrigin(NemesisToken $token, string $origin): NemesisToken;

    /**
     * Set the allowed origins for a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  array<string>|null  $origins  The origins to allow
     * @return NemesisToken The updated token
     */
    public function setAllowedOrigins(NemesisToken $token, ?array $origins): NemesisToken;

    // ============================================================================
    // Metadata Management
    // ============================================================================

    /**
     * Get a metadata value from a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $key  The metadata key
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The metadata value
     */
    public function getMetadata(NemesisToken $token, string $key, mixed $default = null): mixed;

    /**
     * Check if a token has a specific metadata key.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $key  The metadata key
     * @return bool True if the key exists
     */
    public function hasMetadata(NemesisToken $token, string $key): bool;

    /**
     * Set a metadata value on a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $key  The metadata key
     * @param  mixed  $value  The metadata value
     * @return NemesisToken The updated token
     */
    public function setMetadata(NemesisToken $token, string $key, mixed $value): NemesisToken;

    /**
     * Remove a metadata key from a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  string  $key  The metadata key to remove
     * @return NemesisToken The updated token
     */
    public function removeMetadata(NemesisToken $token, string $key): NemesisToken;

    /**
     * Get all metadata from a token.
     *
     * @param  NemesisToken  $token  The token
     * @return array|null The metadata array or null
     */
    public function getAllMetadata(NemesisToken $token): ?array;

    /**
     * Merge metadata into a token's existing metadata.
     *
     * @param  NemesisToken  $token  The token
     * @param  array<string, mixed>  $metadata  The metadata to merge
     * @return NemesisToken The updated token
     */
    public function mergeMetadata(NemesisToken $token, array $metadata): NemesisToken;

    /**
     * Set all metadata on a token.
     *
     * @param  NemesisToken  $token  The token
     * @param  array<string, mixed>|null  $metadata  The metadata to set
     * @return NemesisToken The updated token
     */
    public function setAllMetadata(NemesisToken $token, ?array $metadata): NemesisToken;

    /**
     * Clear all metadata from a token.
     *
     * @param  NemesisToken  $token  The token
     * @return NemesisToken The updated token
     */
    public function clearMetadata(NemesisToken $token): NemesisToken;

    // ============================================================================
    // Query Methods
    // ============================================================================

    /**
     * Find tokens matching the filters with optional sorting and limiting.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @param  int|null  $limit  Maximum number of tokens to return
     * @param  SortColumns|null  $sortBy  Sort columns
     * @param  array<string>  $columns  Columns to select
     * @return Collection<int, NemesisToken> Collection of tokens
     */
    public function findByFilters(
        NemesisTokenFilterRecord $filters,
        ?int $limit = null,
        ?SortColumns $sortBy = null,
        array $columns = ['*']
    ): Collection;
}
