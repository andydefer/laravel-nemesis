<?php

declare(strict_types=1);

namespace Kani\Nemesis\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Services\TokenMetadataService;

/**
 * Trait for models that can own Nemesis tokens.
 *
 * Provides full token management capabilities for any Eloquent model.
 * The using class should implement MustNemesis interface.
 *
 * @mixin Model
 */
trait HasNemesisTokens
{
    /**
     * Get all tokens for this model.
     *
     * @return MorphMany<NemesisToken, static>
     */
    public function nemesisTokens(): MorphMany
    {
        return $this->morphMany(NemesisToken::class, 'tokenable');
    }

    /**
     * Create a new token for the model.
     *
     * Generates a cryptographically secure random token, hashes it for storage,
     * and returns the plain text token (which should be shown to the user once).
     *
     * @param string|null $name Human-readable token name
     * @param string|null $source Token source/origin (web, mobile, api, cli)
     * @param array<int, string>|null $abilities List of permissions granted
     * @param array<string, mixed>|null $metadata Additional metadata
     * @return string The plain text token (store securely, cannot be retrieved again)
     *
     * @throws \Kani\Nemesis\Exceptions\MetadataValidationException When metadata is invalid
     */
    public function createNemesisToken(
        ?string $name = null,
        ?string $source = null,
        ?array $abilities = null,
        ?array $metadata = null
    ): string {
        $plainToken = Str::random(config('nemesis.token_length', 64));
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $plainToken);

        if ($metadata !== null) {
            TokenMetadataService::validate($metadata);
            $metadata = TokenMetadataService::sanitize($metadata);
        }

        $this->nemesisTokens()->create([
            'token_hash' => $hashedToken,
            'name' => $name,
            'source' => $source,
            'abilities' => $abilities,
            'metadata' => $metadata,
            'expires_at' => $this->getTokenExpiration(),
        ]);

        return $plainToken;
    }

    /**
     * Get token expiration date based on configuration.
     *
     * @return DateTimeInterface|null Expiration timestamp or null if never expires
     */
    protected function getTokenExpiration(): ?DateTimeInterface
    {
        $expiration = config('nemesis.expiration');

        if ($expiration === null) {
            return null;
        }

        return now()->addMinutes($expiration);
    }

    /**
     * Permanently delete all tokens for the model.
     *
     * Performs a force delete, removing tokens from the database completely.
     * Use revokeNemesisTokens() for soft delete with audit trail.
     *
     * @return int Number of tokens permanently deleted
     */
    public function deleteNemesisTokens(): int
    {
        return $this->nemesisTokens()->forceDelete();
    }

    /**
     * Revoke (soft delete) all tokens for the model.
     *
     * Performs a soft delete, setting the `deleted_at` timestamp.
     * Revoked tokens can be restored later with restoreNemesisTokens().
     *
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokens(): int
    {
        return $this->nemesisTokens()->delete();
    }

    /**
     * Revoke (soft delete) all tokens with a specific source.
     *
     * Useful for scenarios like "logout from all browsers" while keeping
     * mobile or API tokens active.
     *
     * @param string $source The source to filter by (e.g., "web", "mobile", "api")
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokensBySource(string $source, bool $force = false): int
    {
        $query = $this->nemesisTokens()->where('source', $source);

        return $force ? $query->forceDelete() : $query->delete();
    }

    /**
     * Revoke (soft delete) all tokens with a specific name.
     *
     * Useful for revoking specific token types across all sources.
     *
     * @param string $name The token name to filter by
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokensByName(string $name, bool $force = false): int
    {
        $query = $this->nemesisTokens()->where('name', $name);

        return $force ? $query->forceDelete() : $query->delete();
    }

    /**
     * Revoke (soft delete) all tokens matching specific source and name.
     *
     * Provides the most granular control for token revocation.
     *
     * @param string $source The source to filter by
     * @param string $name The token name to filter by
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     */
    public function revokeNemesisTokensBySourceAndName(string $source, string $name, bool $force = false): int
    {
        $query = $this->nemesisTokens()
            ->where('source', $source)
            ->where('name', $name);

        return $force ? $query->forceDelete() : $query->delete();
    }

    /**
     * Revoke (soft delete) all tokens except those matching specific criteria.
     *
     * Useful for keeping specific token types while revoking all others.
     *
     * @param string $source The source to keep (tokens with this source will NOT be revoked)
     * @param bool $force Whether to force delete instead of soft delete
     * @return int Number of tokens revoked
     */
    public function revokeAllNemesisTokensExceptSource(string $source, bool $force = false): int
    {
        $query = $this->nemesisTokens()->where('source', '!=', $source);

        return $force ? $query->forceDelete() : $query->delete();
    }

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
    public function revokeNemesisTokensWhere(array $criteria, bool $force = false): int
    {
        $query = $this->nemesisTokens();
        $query = $this->applyWhereConditions($query, $criteria);

        return $force ? $query->forceDelete() : $query->delete();
    }

    /**
     * Apply where conditions to a query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $criteria
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyWhereConditions($query, array $criteria): object
    {
        foreach ($criteria as $key => $value) {
            // Case 1: Format ['column', 'operator', 'value']
            if (is_array($value) && isset($value[0]) && isset($value[1]) && isset($value[2])) {
                $query->where($value[0], $value[1], $value[2]);
            }
            // Case 2: Format ['column' => ['operator', 'value']]
            elseif (is_array($value) && isset($value[0]) && isset($value[1]) && !is_numeric($key)) {
                $query->where($key, $value[0], $value[1]);
            }
            // Case 3: Format ['column' => 'value'] (simple equality)
            else {
                $query->where($key, $value);
            }
        }

        return $query;
    }

    /**
     * Permanently delete the current token (from the request).
     *
     * Performs a force delete on the token used in the current request.
     * The token cannot be recovered after this operation.
     *
     * @return bool True if the token was successfully deleted, false otherwise
     */
    public function deleteCurrentNemesisToken(): bool
    {
        $token = $this->currentNemesisToken();

        if ($token === null) {
            return false;
        }

        return (bool) $token->forceDelete();
    }

    /**
     * Revoke (soft delete) the current token (from the request).
     *
     * Performs a soft delete on the token used in the current request.
     * The token can be restored later with restoreNemesisTokens().
     *
     * @return bool True if the token was successfully revoked, false otherwise
     */
    public function revokeCurrentNemesisToken(): bool
    {
        $token = $this->currentNemesisToken();

        if ($token === null) {
            return false;
        }

        return (bool) $token->delete();
    }

    /**
     * Get the current access token from the request.
     *
     * Extracts the bearer token from the Authorization header and retrieves
     * the corresponding token model (excluding soft-deleted tokens by default).
     *
     * @return NemesisToken|null The token model or null if not found
     */
    public function currentNemesisToken(): ?NemesisToken
    {
        $bearerToken = request()->bearerToken();

        if ($bearerToken === null) {
            return null;
        }

        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $bearerToken);

        return $this->nemesisTokens()
            ->where('token_hash', $hashedToken)
            ->latest('id')
            ->first();
    }

    /**
     * Check if the model has any tokens.
     *
     * @param bool $withTrashed Whether to include soft-deleted (revoked) tokens
     * @return bool True if tokens exist, false otherwise
     */
    public function hasNemesisTokens(bool $withTrashed = false): bool
    {
        $query = $this->nemesisTokens();

        if ($withTrashed && $this->isUsingSoftDeletes()) {
            $query = $query->withTrashed();
        }

        return $query->exists();
    }

    /**
     * Get a token by its plain text value.
     *
     * Hashes the provided token and searches for a matching token_hash.
     *
     * @param string $plainToken The plain text token to search for
     * @param bool $withTrashed Whether to include soft-deleted (revoked) tokens
     * @return NemesisToken|null The token model or null if not found
     */
    public function getNemesisToken(string $plainToken, bool $withTrashed = false): ?NemesisToken
    {
        $hashedToken = hash(config('nemesis.hash_algorithm', 'sha256'), $plainToken);

        $query = $this->nemesisTokens()
            ->where('token_hash', $hashedToken)
            ->latest('id');

        if ($withTrashed && $this->isUsingSoftDeletes()) {
            $query = $query->withTrashed();
        }

        return $query->first();
    }

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
    public function validateNemesisToken(string $token, bool $includeRevoked = false): bool
    {
        $tokenModel = $this->getNemesisToken($token, $includeRevoked);

        if ($tokenModel === null) {
            return false;
        }

        if ($includeRevoked) {
            return !$tokenModel->isExpired();
        }

        return $tokenModel->isValid();
    }

    /**
     * Update the last used timestamp of a token.
     *
     * Updates the `last_used_at` field to the current timestamp.
     * Useful for tracking token usage and implementing inactivity timeouts.
     *
     * @param string $token The plain text token to touch
     * @return bool True if the token was found and updated, false otherwise
     */
    public function touchNemesisToken(string $token): bool
    {
        $tokenModel = $this->getNemesisToken($token);

        if ($tokenModel === null) {
            return false;
        }

        $tokenModel->updateLastUsed();

        return true;
    }

    /**
     * Get all tokens filtered by source.
     *
     * @param string $source The source to filter by (e.g., "web", "mobile", "api")
     * @param bool $withTrashed Whether to include soft-deleted (revoked) tokens
     * @return iterable<NemesisToken> Collection of tokens matching the source
     */
    public function getNemesisTokensBySource(string $source, bool $withTrashed = false): iterable
    {
        $query = $this->nemesisTokens()
            ->where('source', $source);

        if ($withTrashed && $this->isUsingSoftDeletes()) {
            $query = $query->withTrashed();
        }

        return $query->get();
    }

    /**
     * Revoke (soft delete) all expired tokens.
     *
     * Tokens with `expires_at` in the past will be soft-deleted.
     * This preserves audit history while removing expired tokens from active queries.
     *
     * @return int Number of expired tokens revoked
     */
    public function revokeExpiredNemesisTokens(): int
    {
        return $this->nemesisTokens()
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Permanently delete all expired tokens.
     *
     * Tokens with `expires_at` in the past will be force-deleted.
     * Use this for permanent cleanup without audit trail.
     *
     * @return int Number of expired tokens permanently deleted
     */
    public function forceDeleteExpiredNemesisTokens(): int
    {
        return $this->nemesisTokens()
            ->where('expires_at', '<', now())
            ->forceDelete();
    }

    /**
     * Restore all revoked (soft-deleted) tokens.
     *
     * Revokes the soft delete, making the tokens active again.
     * Only affects tokens that were soft-deleted via revoke operations.
     *
     * @return int Number of tokens restored
     */
    public function restoreNemesisTokens(): int
    {
        if (!$this->isUsingSoftDeletes()) {
            return 0;
        }

        $query = $this->nemesisTokens()->onlyTrashed();
        $count = $query->count();

        if ($count > 0) {
            $query->restore();
        }

        return $count;
    }

    /**
     * Check if the model uses the SoftDeletes trait.
     *
     * @return bool True if SoftDeletes is used, false otherwise
     */
    private function isUsingSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this), true);
    }
}
