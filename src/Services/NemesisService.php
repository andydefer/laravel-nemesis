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

final class NemesisService implements NemesisInterface
{
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
    public function update(int|string $tokenId, NemesisTokenRecord $record): NemesisToken
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
    public function delete(int|string $tokenId): bool
    {
        return $this->repository->delete($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function forceDelete(int|string $tokenId): bool
    {
        return $this->repository->forceDelete($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function restore(int|string $tokenId): bool
    {
        return $this->repository->restore($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function find(int|string $tokenId): ?NemesisToken
    {
        return $this->repository->find($tokenId);
    }

    /**
     * {@inheritDoc}
     */
    public function findWithTrashed(int|string $tokenId): ?NemesisToken
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

        return $this->repository->findBy($findByRecord)->first();
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
    // Tokenable Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function getTokensFor(Model $tokenable, bool $withTrashed = false): Collection
    {
        return $this->findTokensWithFilter($this->createTokenableFilters($tokenable), $withTrashed);
    }

    /**
     * {@inheritDoc}
     */
    public function getTokensBySource(Model $tokenable, string $source, bool $withTrashed = false): Collection
    {
        return $this->findTokensWithFilter(
            $this->createTokenableFilters($tokenable, ['source' => $source]),
            $withTrashed
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTokensByName(Model $tokenable, string $name, bool $withTrashed = false): Collection
    {
        return $this->findTokensWithFilter(
            $this->createTokenableFilters($tokenable, ['name' => $name]),
            $withTrashed
        );
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
        return $this->repository->deleteBulk($this->createTokenableFilters($tokenable));
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
        $revokedCount = 0;

        foreach ($this->getTokensFor($tokenable) as $token) {
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

        return $this->repository
            ->findBy(new FindByRecord(filters: $filters, limit: 1))
            ->first();
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
        $filters = NemesisTokenFilterRecord::from([
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $this->hashToken($plainToken),
        ]);

        return $this->findTokensWithFilter($filters, $withTrashed)->first();
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
        return $this->repository->deleteBulk(
            $this->createTokenableFilters($tokenable, ['is_expired' => true])
        );
    }

    /**
     * {@inheritDoc}
     */
    public function forceDeleteExpiredTokens(Model $tokenable): int
    {
        return $this->repository->forceDeleteBulk(
            $this->createTokenableFilters($tokenable, ['is_expired' => true])
        );
    }

    // ============================================================================
    // Global Operations
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function findAllActive(): Collection
    {
        return $this->repository->findBy(new FindByRecord(
            filters: NemesisTokenFilterRecord::from([
                'is_expired' => false,
                'is_revoked' => false,
            ])
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function findAllExpired(): Collection
    {
        return $this->repository->findBy(new FindByRecord(
            filters: NemesisTokenFilterRecord::from(['is_expired' => true])
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function findAllRevoked(): Collection
    {
        return $this->repository->findBy(new FindByRecord(
            filters: NemesisTokenFilterRecord::from(['is_revoked' => true])
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllExpiredTokensGlobally(): int
    {
        return $this->repository->deleteBulk(
            NemesisTokenFilterRecord::from(['is_expired' => true])
        );
    }

    /**
     * {@inheritDoc}
     */
    public function forceDeleteAllExpiredTokensGlobally(): int
    {
        return $this->repository->forceDeleteBulk(
            NemesisTokenFilterRecord::from(['is_expired' => true])
        );
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
            $decoded = json_decode($token->abilities, true);

            return is_array($decoded) && in_array($ability, $decoded, true);
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
        return $this->canUseFromOrigin($token, $request->headers->get('Origin'));
    }

    // ============================================================================
    // Token Lifecycle
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function updateLastUsed(NemesisToken $token): NemesisToken
    {
        return $this->repository->update($token->id, NemesisTokenRecord::from([
            'last_used_at' => new DateTimeVO(now()->toIso8601String()),
        ]));
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
        return $this->repository->update($token->id, NemesisTokenRecord::from([
            'expires_at' => new DateTimeVO(now()->subSecond()->toIso8601String()),
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function forceExpireByMinutes(NemesisToken $token, int $minutes): NemesisToken
    {
        return $this->repository->update($token->id, NemesisTokenRecord::from([
            'expires_at' => new DateTimeVO(now()->subMinutes($minutes)->toIso8601String()),
        ]));
    }

    // ============================================================================
    // Allowed Origins
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function addAllowedOrigin(NemesisToken $token, string $origin): NemesisToken
    {
        $origins = $token->allowed_origins ?? [];

        if (in_array($origin, $origins, true)) {
            return $token;
        }

        $origins[] = $origin;

        return $this->repository->update($token->id, NemesisTokenRecord::from([
            'allowed_origins' => $origins,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function removeAllowedOrigin(NemesisToken $token, string $origin): NemesisToken
    {
        $origins = $token->allowed_origins ?? [];

        $key = array_search($origin, $origins, true);

        if ($key === false) {
            return $token;
        }

        unset($origins[$key]);

        return $this->repository->update($token->id, NemesisTokenRecord::from([
            'allowed_origins' => array_values($origins),
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function setAllowedOrigins(NemesisToken $token, ?array $origins): NemesisToken
    {
        return $this->repository->update($token->id, NemesisTokenRecord::from([
            'allowed_origins' => $origins,
        ]));
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

        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $this->wrapMetadata($this->validateMetadataArray($metadata)),
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function removeMetadata(NemesisToken $token, string $key): NemesisToken
    {
        $metadata = $token->metadata ?? [];

        if (! array_key_exists($key, $metadata)) {
            return $token;
        }

        unset($metadata[$key]);

        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $this->wrapMetadata($this->validateMetadataArray($metadata)),
        ]));
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
        $merged = array_merge($token->metadata ?? [], $metadata);

        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $this->wrapMetadata($this->validateMetadataArray($merged)),
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function setAllMetadata(NemesisToken $token, ?array $metadata): NemesisToken
    {
        return $this->update($token->id, NemesisTokenRecord::from([
            'metadata' => $this->wrapMetadata($this->validateMetadataArray($metadata)),
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
        return $this->repository->findBy(new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: $sortBy,
            columns: new SelectColumns($columns)
        ));
    }

    // ============================================================================
    // Private Helpers
    // ============================================================================

    private function wrapMetadata(?array $metadata): ?StrictDataObject
    {
        return $metadata !== null ? new StrictDataObject($metadata) : null;
    }

    private function validateMetadata(?StrictDataObject $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return $this->metadataValidator->process($metadata->toArray());
    }

    private function validateMetadataArray(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        return $this->metadataValidator->process($metadata);
    }

    private function validateMetadataValue(array $metadata): void
    {
        if (! $this->metadataValidator->isValid($metadata)) {
            $this->metadataValidator->validate($metadata);
        }
    }

    private function createTokenableFilters(Model $tokenable, array $extraFilters = []): NemesisTokenFilterRecord
    {
        return NemesisTokenFilterRecord::from(
            array_merge([
                'tokenable_type' => $tokenable->getMorphClass(),
                'tokenable_id' => $tokenable->getKey(),
            ], $extraFilters)
        );
    }

    private function findTokensWithFilter(NemesisTokenFilterRecord $filters, bool $withTrashed): Collection
    {
        if ($withTrashed) {
            return $this->repository->findWithTrashedByFilters($filters);
        }

        return $this->repository->findBy(new FindByRecord(filters: $filters));
    }

    private function deleteToken(NemesisToken $token, bool $force): void
    {
        if ($force) {
            $this->repository->forceDelete($token->id);
        } else {
            $this->repository->delete($token->id);
        }
    }

    private function hashToken(string $plainToken): string
    {
        return hash($this->config->tokenConfig()->hash_algorithm, $plainToken);
    }

    private function normalizeUrl(string $url): string
    {
        return rtrim($url, '/');
    }

    private function isWildcardMatch(string $origin, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return false;
        }

        $escaped = preg_quote($pattern, '/');
        $regex = '/^'.str_replace('\\*', '.*', $escaped).'$/i';

        return preg_match($regex, $origin) === 1;
    }
}
