<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Repositories\NemesisTokenRepositoryInterface;
use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service for managing Nemesis authentication tokens.
 *
 * Provides comprehensive token lifecycle management including creation,
 * validation, revocation, metadata handling, and capabilities management.
 * All tokens are associated with a tokenable model (User, ApiClient, etc.)
 * and support soft deletes, expiration, and origin restrictions.
 */
final class NemesisService implements NemesisInterface
{
    /**
     * Create a new NemesisService instance.
     *
     * @param  NemesisTokenRepositoryInterface  $repository  Repository for token persistence
     * @param  NemesisConfigInterface  $config  Configuration for token generation
     * @param  Str  $str  String helper for random token generation
     * @param  MetadataValidatorInterface  $metadataValidator  Validator for token metadata
     */
    public function __construct(
        private readonly NemesisTokenRepositoryInterface $repository,
        private readonly NemesisConfigInterface $config,
        private readonly Str $str,
        private readonly MetadataValidatorInterface $metadataValidator,
    ) {}

    // ============================================================================
    // Token Creation
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function create(NemesisTokenRecord $record, Model $tokenable): NemesisToken
    {
        $validatedMetadata = $this->validateMetadata($record->metadata);

        $fullRecord = NemesisTokenRecord::from([
            ...$record->toArrayWithoutNulls(),
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
        ]);

        return $this->repository->create($fullRecord);
    }

    /**
     * {@inheritDoc}
     */
    public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array
    {
        $tokenConfig = $this->config->tokenConfig();

        $plainToken = $this->str->random($tokenConfig->token_length);
        $hashedToken = hash($tokenConfig->hash_algorithm, $plainToken);

        $validatedMetadata = $this->validateMetadata($record->metadata);

        $fullRecord = NemesisTokenRecord::from([
            ...$record->toArrayWithoutNulls(),
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
            'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
        ]);

        $token = $this->repository->create($fullRecord);

        return [$token, $plainToken];
    }

    // ============================================================================
    // CRUD Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function update(int $tokenId, NemesisTokenRecord $record): NemesisToken
    {
        $validatedMetadata = $this->validateMetadata($record->metadata);

        $validatedRecord = NemesisTokenRecord::from([
            ...$record->toArrayWithoutNulls(),
            'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
        ]);

        return $this->repository->update($tokenId, $validatedRecord);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $tokenId): bool
    {
        return $this->repository->delete($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function forceDelete(int $tokenId): bool
    {
        return $this->repository->forceDelete($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function restore(int $tokenId): bool
    {
        return $this->repository->restore($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $tokenId): ?NemesisToken
    {
        return $this->repository->find($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function findWithTrashed(int $tokenId): ?NemesisToken
    {
        return $this->repository->findWithTrashed($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function findByHash(string $tokenHash): ?NemesisToken
    {
        $filters = NemesisTokenFilterRecord::from([
            'token_hash' => $tokenHash,
        ]);

        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1
        );

        $collection = $this->repository->findBy($findByRecord);

        return $collection->first();
    }

    // ============================================================================
    // Bulk Delete Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function deleteBulk(NemesisTokenFilterRecord $filters): int
    {
        return $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function forceDeleteBulk(NemesisTokenFilterRecord $filters): int
    {
        return $this->repository->forceDeleteBulk($filters);
    }

    // ============================================================================
    // Tokenable Operations (Bulk)
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function getTokensFor(Model $tokenable, bool $withTrashed = false): Collection
    {
        $filters = $this->createTokenableFilters($tokenable);

        return $this->findTokensWithFilter($filters, $withTrashed);
    }

    /**
     * {@inheritDoc}
     */
    public function getTokensBySource(Model $tokenable, string $source, bool $withTrashed = false): Collection
    {
        $filters = $this->createTokenableFilters($tokenable, ['source' => $source]);

        return $this->findTokensWithFilter($filters, $withTrashed);
    }

    /**
     * {@inheritDoc}
     */
    public function getTokensByName(Model $tokenable, string $name, bool $withTrashed = false): Collection
    {
        $filters = $this->createTokenableFilters($tokenable, ['name' => $name]);

        return $this->findTokensWithFilter($filters, $withTrashed);
    }

    /**
     * {@inheritDoc}
     */
    public function hasTokens(Model $tokenable, bool $withTrashed = false): bool
    {
        $filters = $this->createTokenableFilters($tokenable);

        return $withTrashed
            ? $this->repository->existsWithTrashed($filters)
            : $this->repository->exists($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteAllTokens(Model $tokenable, bool $force = false): int
    {
        $filters = $this->createTokenableFilters($tokenable);

        return $force
            ? $this->repository->forceDeleteBulk($filters)
            : $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllTokens(Model $tokenable): int
    {
        $filters = $this->createTokenableFilters($tokenable);

        return $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function restoreAllTokens(Model $tokenable): int
    {
        return $this->repository->restoreBulkForTokenable(
            $tokenable->getMorphClass(),
            $tokenable->getKey()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function revokeTokensBySource(Model $tokenable, string $source, bool $force = false): int
    {
        $filters = $this->createTokenableFilters($tokenable, ['source' => $source]);

        return $force
            ? $this->repository->forceDeleteBulk($filters)
            : $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function revokeTokensByName(Model $tokenable, string $name, bool $force = false): int
    {
        $filters = $this->createTokenableFilters($tokenable, ['name' => $name]);

        return $force
            ? $this->repository->forceDeleteBulk($filters)
            : $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function revokeTokensBySourceAndName(Model $tokenable, string $source, string $name, bool $force = false): int
    {
        $filters = $this->createTokenableFilters($tokenable, [
            'source' => $source,
            'name' => $name,
        ]);

        return $force
            ? $this->repository->forceDeleteBulk($filters)
            : $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllTokensExceptSource(Model $tokenable, string $source, bool $force = false): int
    {
        $allTokens = $this->getTokensFor($tokenable);
        $revokedCount = 0;

        foreach ($allTokens as $token) {
            if ($token->source !== $source) {
                $this->deleteToken($token, $force);
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    // ============================================================================
    // Current Token Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function getCurrentToken(Model $tokenable, Request $request): ?NemesisToken
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return null;
        }

        $hashedToken = $this->hashToken($bearerToken);

        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
        ]);

        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1
        );

        $collection = $this->repository->findBy($findByRecord);

        return $collection->first();
    }

    /**
     * {@inheritDoc}
     */
    public function revokeCurrentToken(Model $tokenable, Request $request): bool
    {
        $token = $this->getCurrentToken($tokenable, $request);

        return $token !== null && $this->repository->delete($token->id);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteCurrentToken(Model $tokenable, Request $request): bool
    {
        $token = $this->getCurrentToken($tokenable, $request);

        return $token !== null && $this->repository->forceDelete($token->id);
    }

    // ============================================================================
    // Token Validation
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function validateToken(string $plainToken, Model $tokenable, bool $includeRevoked = false): bool
    {
        $token = $this->getTokenByPlainText($plainToken, $tokenable, $includeRevoked);

        if ($token === null) {
            return false;
        }

        return $includeRevoked
            ? ! $token->isExpired()
            : $token->isValid();
    }

    /**
     * {@inheritDoc}
     */
    public function getTokenByPlainText(string $plainToken, Model $tokenable, bool $withTrashed = false): ?NemesisToken
    {
        $hashedToken = $this->hashToken($plainToken);

        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
        ]);

        $collection = $this->findTokensWithFilter($filters, $withTrashed);

        return $collection->first();
    }

    /**
     * {@inheritDoc}
     */
    public function touchToken(string $plainToken, Model $tokenable): bool
    {
        $token = $this->getTokenByPlainText($plainToken, $tokenable);

        if ($token === null) {
            return false;
        }

        $this->updateLastUsed($token);

        return true;
    }

    // ============================================================================
    // Count Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function count(NemesisTokenFilterRecord $filters): int
    {
        return $this->repository->count($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(NemesisTokenFilterRecord $filters): bool
    {
        return $this->repository->exists($filters);
    }

    // ============================================================================
    // Expired Tokens Management
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function revokeExpiredTokens(Model $tokenable): int
    {
        $filters = $this->createTokenableFilters($tokenable, ['is_expired' => true]);

        return $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function forceDeleteExpiredTokens(Model $tokenable): int
    {
        $filters = $this->createTokenableFilters($tokenable, ['is_expired' => true]);

        return $this->repository->forceDeleteBulk($filters);
    }

    // ============================================================================
    // Global Operations (without tokenable)
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function findAllActive(): Collection
    {
        $filters = NemesisTokenFilterRecord::from([
            'is_expired' => false,
            'is_revoked' => false,
        ]);

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    /**
     * {@inheritDoc}
     */
    public function findAllExpired(): Collection
    {
        $filters = NemesisTokenFilterRecord::from([
            'is_expired' => true,
        ]);

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    /**
     * {@inheritDoc}
     */
    public function findAllRevoked(): Collection
    {
        $filters = NemesisTokenFilterRecord::from([
            'is_revoked' => true,
        ]);

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllExpiredTokensGlobally(): int
    {
        $filters = NemesisTokenFilterRecord::from([
            'is_expired' => true,
        ]);

        return $this->repository->deleteBulk($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function forceDeleteAllExpiredTokensGlobally(): int
    {
        $filters = NemesisTokenFilterRecord::from([
            'is_expired' => true,
        ]);

        return $this->repository->forceDeleteBulk($filters);
    }

    // ============================================================================
    // Token Capabilities
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function can(NemesisToken $token, string $ability): bool
    {
        if ($token->abilities === null) {
            return true;
        }

        if (is_array($token->abilities)) {
            return in_array($ability, $token->abilities, true);
        }

        if ($token->abilities instanceof StringTypedCollection) {
            return $token->abilities->contains($ability);
        }

        if (is_string($token->abilities)) {
            $decodedAbilities = json_decode($token->abilities, true);

            if (is_array($decodedAbilities)) {
                return in_array($ability, $decodedAbilities, true);
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function canAll(NemesisToken $token, array $abilities): bool
    {
        if ($token->abilities === null) {
            return true;
        }

        foreach ($abilities as $ability) {
            if (! $this->can($token, $ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function canUseFromOrigin(NemesisToken $token, ?string $origin): bool
    {
        if ($origin === null) {
            return true;
        }

        if ($token->allowed_origins === null || empty($token->allowed_origins)) {
            return true;
        }

        $normalizedOrigin = $this->normalizeUrl($origin);

        foreach ($token->allowed_origins as $allowedOrigin) {
            $normalizedAllowed = $this->normalizeUrl($allowedOrigin);

            if ($this->isWildcardMatch($normalizedOrigin, $normalizedAllowed)) {
                return true;
            }

            if (strcasecmp($normalizedOrigin, $normalizedAllowed) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function canUseFromCurrentRequest(NemesisToken $token, Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        return $this->canUseFromOrigin($token, $origin);
    }

    // ============================================================================
    // Token Lifecycle Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function updateLastUsed(NemesisToken $token): NemesisToken
    {
        $record = NemesisTokenRecord::from([
            'last_used_at' => new DateTimeVO(now()->toIso8601String()),
        ]);

        return $this->repository->update($token->id, $record);
    }

    /**
     * {@inheritDoc}
     */
    public function revoke(NemesisToken $token): bool
    {
        return $this->repository->delete($token->id);
    }

    /**
     * {@inheritDoc}
     */
    public function restoreToken(NemesisToken $token): bool
    {
        return $this->repository->restore($token->id);
    }

    /**
     * {@inheritDoc}
     */
    public function forceExpire(NemesisToken $token): NemesisToken
    {
        $record = NemesisTokenRecord::from([
            'expires_at' => new DateTimeVO(now()->subSecond()->toIso8601String()),
        ]);

        return $this->repository->update($token->id, $record);
    }

    /**
     * {@inheritDoc}
     */
    public function forceExpireByMinutes(NemesisToken $token, int $minutes): NemesisToken
    {
        $record = NemesisTokenRecord::from([
            'expires_at' => new DateTimeVO(now()->subMinutes($minutes)->toIso8601String()),
        ]);

        return $this->repository->update($token->id, $record);
    }

    // ============================================================================
    // Allowed Origins Management
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function addAllowedOrigin(NemesisToken $token, string $origin): NemesisToken
    {
        $origins = $token->allowed_origins ?? [];

        if (! in_array($origin, $origins, true)) {
            $origins[] = $origin;

            $record = NemesisTokenRecord::from([
                'allowed_origins' => $origins,
            ]);

            return $this->repository->update($token->id, $record);
        }

        return $token;
    }

    /**
     * {@inheritDoc}
     */
    public function removeAllowedOrigin(NemesisToken $token, string $origin): NemesisToken
    {
        $origins = $token->allowed_origins ?? [];

        $key = array_search($origin, $origins, true);

        if ($key !== false) {
            unset($origins[$key]);

            $record = NemesisTokenRecord::from([
                'allowed_origins' => array_values($origins),
            ]);

            return $this->repository->update($token->id, $record);
        }

        return $token;
    }

    /**
     * {@inheritDoc}
     */
    public function setAllowedOrigins(NemesisToken $token, ?array $origins): NemesisToken
    {
        $record = NemesisTokenRecord::from([
            'allowed_origins' => $origins,
        ]);

        return $this->repository->update($token->id, $record);
    }

    // ============================================================================
    // Metadata Management
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function getMetadata(NemesisToken $token, string $key, mixed $default = null): mixed
    {
        return $token->metadata[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function hasMetadata(NemesisToken $token, string $key): bool
    {
        return is_array($token->metadata) && array_key_exists($key, $token->metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function setMetadata(NemesisToken $token, string $key, mixed $value): NemesisToken
    {
        $this->validateMetadataValue([$key => $value]);

        $metadata = $token->metadata ?? [];
        $metadata[$key] = $value;

        $validatedMetadata = $this->validateMetadataArray($metadata);

        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function removeMetadata(NemesisToken $token, string $key): NemesisToken
    {
        $metadata = $token->metadata ?? [];

        if (array_key_exists($key, $metadata)) {
            unset($metadata[$key]);

            $validatedMetadata = $this->validateMetadataArray($metadata);

            return $this->update($token->id, NemesisTokenRecord::from([
                'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
            ]));
        }

        return $token;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllMetadata(NemesisToken $token): ?array
    {
        return $token->metadata;
    }

    /**
     * {@inheritDoc}
     */
    public function mergeMetadata(NemesisToken $token, array $metadata): NemesisToken
    {
        $existing = $token->metadata ?? [];
        $merged = array_merge($existing, $metadata);

        $validatedMetadata = $this->validateMetadataArray($merged);

        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function setAllMetadata(NemesisToken $token, ?array $metadata): NemesisToken
    {
        $validatedMetadata = $this->validateMetadataArray($metadata);

        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $validatedMetadata !== null ? new StrictDataObject($validatedMetadata) : null,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function clearMetadata(NemesisToken $token): NemesisToken
    {
        return $this->repository->updateRaw($token->id, ['metadata' => null]);
    }

    // ============================================================================
    // Query Methods
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function findByFilters(
        NemesisTokenFilterRecord $filters,
        ?int $limit = null,
        ?SortColumns $sortBy = null,
        array $columns = ['*']
    ): Collection {
        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: $sortBy,
            columns: new SelectColumns($columns)
        );

        return $this->repository->findBy($findByRecord);
    }

    // ============================================================================
    // Private Helpers
    // ============================================================================

    /**
     * Validate and sanitize metadata from a StrictDataObject.
     *
     * @param  StrictDataObject|null  $metadata  The metadata to validate
     * @return array|null The validated metadata as array, or null if empty
     */
    private function validateMetadata(?StrictDataObject $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return $this->metadataValidator->process($metadata->toArray());
    }

    /**
     * Validate and sanitize metadata from an array.
     *
     * @param  array|null  $metadata  The metadata to validate
     * @return array|null The validated metadata as array, or null if empty
     */
    private function validateMetadataArray(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        return $this->metadataValidator->process($metadata);
    }

    /**
     * Validate a metadata value.
     *
     * @param  array<string, mixed>  $metadata  The metadata to validate
     *
     * @throws \InvalidArgumentException If the metadata is invalid
     */
    private function validateMetadataValue(array $metadata): void
    {
        if (! $this->metadataValidator->isValid($metadata)) {
            $this->metadataValidator->validate($metadata);
        }
    }

    /**
     * Create filter record for a tokenable model.
     *
     * @param  Model  $tokenable  The tokenable model
     * @param  array<string, mixed>  $extraFilters  Additional filters to apply
     * @return NemesisTokenFilterRecord The filter record
     */
    private function createTokenableFilters(Model $tokenable, array $extraFilters = []): NemesisTokenFilterRecord
    {
        $filters = [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
        ];

        return NemesisTokenFilterRecord::from(
            array_merge($filters, $extraFilters)
        );
    }

    /**
     * Find tokens with a filter, optionally including soft-deleted ones.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @param  bool  $withTrashed  Whether to include soft-deleted tokens
     * @return Collection<int, NemesisToken> Collection of tokens
     */
    private function findTokensWithFilter(NemesisTokenFilterRecord $filters, bool $withTrashed): Collection
    {
        if ($withTrashed) {
            return $this->repository->findWithTrashedByFilters($filters);
        }

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    /**
     * Delete a single token (soft or hard delete).
     *
     * @param  NemesisToken  $token  The token to delete
     * @param  bool  $force  Whether to permanently delete
     */
    private function deleteToken(NemesisToken $token, bool $force): void
    {
        if ($force) {
            $this->repository->forceDelete($token->id);
        } else {
            $this->repository->delete($token->id);
        }
    }

    /**
     * Hash a plain text token.
     *
     * @param  string  $plainToken  The plain text token
     * @return string The hashed token
     */
    private function hashToken(string $plainToken): string
    {
        $tokenConfig = $this->config->tokenConfig();

        return hash($tokenConfig->hash_algorithm, $plainToken);
    }

    /**
     * Normalize a URL by removing trailing slash.
     *
     * @param  string  $url  The URL to normalize
     * @return string The normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    /**
     * Check if an origin matches a wildcard pattern.
     *
     * Supports wildcard pattern matching where '*' can match any subdomain.
     * Example: "*.example.com" matches "api.example.com" and "app.example.com".
     *
     * @param  string  $origin  The origin to test
     * @param  string  $pattern  The pattern to test against
     * @return bool True if the origin matches the pattern
     */
    private function isWildcardMatch(string $origin, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return false;
        }

        $escapedPattern = preg_quote($pattern, '/');
        $regexPattern = str_replace('\\*', '.*', $escapedPattern);
        $regex = '/^'.$regexPattern.'$/i';

        return preg_match($regex, $origin) === 1;
    }
}
