<?php

// src/Services/NemesisService.php

declare(strict_types=1);

namespace Kani\Nemesis\Services;

use AndyDefer\DataValidator\Services\MetadataValidator;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\NemesisTokenFilterRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;
use Kani\Nemesis\Repositories\NemesisTokenRepository;

class NemesisService
{
    public function __construct(
        private readonly NemesisTokenRepository $repository,
        private readonly NemesisConfigInterface $config,
        private readonly Str $str,
        private readonly MetadataValidator $metadataValidator,
        private readonly HydrationService $hydration,
    ) {}

    // ============================================================================
    // Token Creation
    // ============================================================================

    public function create(NemesisTokenRecord $record, Model $tokenable): NemesisToken
    {
        $validatedMetadata = $this->validateMetadata($record->metadata);

        $fullRecord = $this->hydration->hydrate(NemesisTokenRecord::class, [
            ...$record->toArrayWithoutNulls(),
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'metadata' => $validatedMetadata,
        ]);

        return $this->repository->create($fullRecord);
    }

    public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array
    {
        $tokenConfig = $this->config->tokenConfig();
        $plainToken = $this->str->random($tokenConfig->token_length);
        $hashedToken = hash($tokenConfig->hash_algorithm, $plainToken);

        $validatedMetadata = $this->validateMetadata($record->metadata);

        $fullRecord = $this->hydration->hydrate(NemesisTokenRecord::class, [
            ...$record->toArrayWithoutNulls(),
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
            'metadata' => $validatedMetadata,
        ]);

        $token = $this->repository->create($fullRecord);

        return [$token, $plainToken];
    }

    // ============================================================================
    // CRUD Operations
    // ============================================================================

    public function update(int $tokenId, NemesisTokenRecord $record): NemesisToken
    {
        $validatedMetadata = $this->validateMetadata($record->metadata);

        $validatedRecord = $this->hydration->hydrate(NemesisTokenRecord::class, [
            ...$record->toArrayWithoutNulls(),
            'metadata' => $validatedMetadata,
        ]);

        return $this->repository->update($tokenId, $validatedRecord);
    }

    public function delete(int $tokenId): bool
    {
        return $this->repository->delete($tokenId);
    }

    public function forceDelete(int $tokenId): bool
    {
        return $this->repository->forceDelete($tokenId);
    }

    public function restore(int $tokenId): bool
    {
        return $this->repository->restore($tokenId);
    }

    public function find(int $tokenId): ?NemesisToken
    {
        return $this->repository->find($tokenId);
    }

    public function findWithTrashed(int $tokenId): ?NemesisToken
    {
        return $this->repository->findWithTrashed($tokenId);
    }

    public function findByHash(string $tokenHash): ?NemesisToken
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'token_hash' => $tokenHash,
        ]);
        $findByRecord = new FindByRecord(filters: $filters, limit: 1);
        $collection = $this->repository->findBy($findByRecord);

        return $collection->first();
    }

    // ============================================================================
    // Bulk Delete Operations
    // ============================================================================

    public function deleteBulk(NemesisTokenFilterRecord $filters): int
    {
        return $this->repository->deleteBulk($filters);
    }

    public function forceDeleteBulk(NemesisTokenFilterRecord $filters): int
    {
        return $this->repository->forceDeleteBulk($filters);
    }

    // ============================================================================
    // Tokenable Operations (Bulk)
    // ============================================================================

    public function getTokensFor(Model $tokenable, bool $withTrashed = false): Collection
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
        ]);

        if ($withTrashed) {
            return $this->repository->findWithTrashedByFilters($filters);
        }

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    public function getTokensBySource(Model $tokenable, string $source, bool $withTrashed = false): Collection
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'source' => $source,
        ]);

        if ($withTrashed) {
            return $this->repository->findWithTrashedByFilters($filters);
        }

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    public function getTokensByName(Model $tokenable, string $name, bool $withTrashed = false): Collection
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'name' => $name,
        ]);

        if ($withTrashed) {
            return $this->repository->findWithTrashedByFilters($filters);
        }

        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    public function hasTokens(Model $tokenable, bool $withTrashed = false): bool
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
        ]);

        if ($withTrashed) {
            return $this->repository->existsWithTrashed($filters);
        }

        return $this->repository->exists($filters);
    }

    public function deleteAllTokens(Model $tokenable, bool $force = false): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
        ]);

        if ($force) {
            return $this->repository->forceDeleteBulk($filters);
        }

        return $this->repository->deleteBulk($filters);
    }

    public function revokeAllTokens(Model $tokenable): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
        ]);

        return $this->repository->deleteBulk($filters);
    }

    public function restoreAllTokens(Model $tokenable): int
    {
        return $this->repository->restoreBulkForTokenable(
            $tokenable->getMorphClass(),
            $tokenable->getKey()
        );
    }

    public function revokeTokensBySource(Model $tokenable, string $source, bool $force = false): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'source' => $source,
        ]);

        if ($force) {
            return $this->repository->forceDeleteBulk($filters);
        }

        return $this->repository->deleteBulk($filters);
    }

    public function revokeTokensByName(Model $tokenable, string $name, bool $force = false): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'name' => $name,
        ]);

        if ($force) {
            return $this->repository->forceDeleteBulk($filters);
        }

        return $this->repository->deleteBulk($filters);
    }

    public function revokeTokensBySourceAndName(Model $tokenable, string $source, string $name, bool $force = false): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'source' => $source,
            'name' => $name,
        ]);

        if ($force) {
            return $this->repository->forceDeleteBulk($filters);
        }

        return $this->repository->deleteBulk($filters);
    }

    public function revokeAllTokensExceptSource(Model $tokenable, string $source, bool $force = false): int
    {
        $allTokens = $this->getTokensFor($tokenable);
        $count = 0;

        foreach ($allTokens as $token) {
            if ($token->source !== $source) {
                if ($force) {
                    $this->repository->forceDelete($token->id);
                } else {
                    $this->repository->delete($token->id);
                }
                $count++;
            }
        }

        return $count;
    }

    // ============================================================================
    // Current Token Operations
    // ============================================================================

    public function getCurrentToken(Model $tokenable, Request $request): ?NemesisToken
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return null;
        }

        $tokenConfig = $this->config->tokenConfig();
        $hashedToken = hash($tokenConfig->hash_algorithm, $bearerToken);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
        ]);

        $findByRecord = new FindByRecord(filters: $filters, limit: 1);
        $collection = $this->repository->findBy($findByRecord);

        return $collection->first();
    }

    public function revokeCurrentToken(Model $tokenable, Request $request): bool
    {
        $token = $this->getCurrentToken($tokenable, $request);

        if ($token === null) {
            return false;
        }

        return $this->repository->delete($token->id);
    }

    public function deleteCurrentToken(Model $tokenable, Request $request): bool
    {
        $token = $this->getCurrentToken($tokenable, $request);

        if ($token === null) {
            return false;
        }

        return $this->repository->forceDelete($token->id);
    }

    // ============================================================================
    // Token Validation
    // ============================================================================

    public function validateToken(string $plainToken, Model $tokenable, bool $includeRevoked = false): bool
    {
        $tokenConfig = $this->config->tokenConfig();
        $hashedToken = hash($tokenConfig->hash_algorithm, $plainToken);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
        ]);

        if ($includeRevoked) {
            $tokens = $this->repository->findWithTrashedByFilters($filters);
        } else {
            $findByRecord = new FindByRecord(filters: $filters, limit: 1);
            $tokens = $this->repository->findBy($findByRecord);
        }

        $token = $tokens->first();

        if ($token === null) {
            return false;
        }

        if ($includeRevoked) {
            return !$token->isExpired();
        }

        return $token->isValid();
    }

    public function getTokenByPlainText(string $plainToken, Model $tokenable, bool $withTrashed = false): ?NemesisToken
    {
        $tokenConfig = $this->config->tokenConfig();
        $hashedToken = hash($tokenConfig->hash_algorithm, $plainToken);

        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'token_hash' => $hashedToken,
        ]);

        if ($withTrashed) {
            $tokens = $this->repository->findWithTrashedByFilters($filters);
        } else {
            $findByRecord = new FindByRecord(filters: $filters, limit: 1);
            $tokens = $this->repository->findBy($findByRecord);
        }

        return $tokens->first();
    }

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

    public function count(NemesisTokenFilterRecord $filters): int
    {
        return $this->repository->count($filters);
    }

    public function exists(NemesisTokenFilterRecord $filters): bool
    {
        return $this->repository->exists($filters);
    }

    // ============================================================================
    // Expired Tokens Management
    // ============================================================================

    public function revokeExpiredTokens(Model $tokenable): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'is_expired' => true,
        ]);

        return $this->repository->deleteBulk($filters);
    }

    public function forceDeleteExpiredTokens(Model $tokenable): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'is_expired' => true,
        ]);

        return $this->repository->forceDeleteBulk($filters);
    }

    // ============================================================================
    // Global Operations (without tokenable)
    // ============================================================================

    public function findAllActive(): Collection
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => false,
            'is_revoked' => false,
        ]);
        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    public function findAllExpired(): Collection
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => true,
        ]);
        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    public function findAllRevoked(): Collection
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_revoked' => true,
        ]);
        $findByRecord = new FindByRecord(filters: $filters);

        return $this->repository->findBy($findByRecord);
    }

    public function revokeAllExpiredTokensGlobally(): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => true,
        ]);

        return $this->repository->deleteBulk($filters);
    }

    public function forceDeleteAllExpiredTokensGlobally(): int
    {
        $filters = $this->hydration->hydrate(NemesisTokenFilterRecord::class, [
            'is_expired' => true,
        ]);

        return $this->repository->forceDeleteBulk($filters);
    }

    // ============================================================================
    // Token Capabilities
    // ============================================================================

    public function can(NemesisToken $token, string $ability): bool
    {
        if ($token->abilities === null) {
            return true;
        }

        if (is_array($token->abilities)) {
            return in_array($ability, $token->abilities);
        }

        if (is_object($token->abilities) && $token->abilities instanceof StringTypedCollection) {
            return $token->abilities->contains($ability);
        }

        if (is_string($token->abilities)) {
            $decoded = json_decode($token->abilities, true);
            if (is_array($decoded)) {
                return in_array($ability, $decoded);
            }
        }

        return false;
    }

    public function canAll(NemesisToken $token, array $abilities): bool
    {
        if ($token->abilities === null) {
            return true;
        }

        foreach ($abilities as $ability) {
            if (!$this->can($token, $ability)) {
                return false;
            }
        }

        return true;
    }

    public function canUseFromOrigin(NemesisToken $token, ?string $origin): bool
    {
        if ($origin === null) {
            return true;
        }

        if ($token->allowed_origins === null || empty($token->allowed_origins)) {
            return true;
        }

        $normalizedOrigin = rtrim($origin, '/');

        foreach ($token->allowed_origins as $allowedOrigin) {
            $normalizedAllowed = rtrim($allowedOrigin, '/');

            if ($this->isWildcardMatch($normalizedOrigin, $normalizedAllowed)) {
                return true;
            }

            if (strcasecmp($normalizedOrigin, $normalizedAllowed) === 0) {
                return true;
            }
        }

        return false;
    }

    public function canUseFromCurrentRequest(NemesisToken $token, Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        return $this->canUseFromOrigin($token, $origin);
    }

    // ============================================================================
    // Token Lifecycle Operations
    // ============================================================================

    public function updateLastUsed(NemesisToken $token): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'last_used_at' => new DateTimeVO(now()->toIso8601String()),
        ]);

        return $this->repository->update($token->id, $record);
    }

    public function revoke(NemesisToken $token): bool
    {
        return $this->repository->delete($token->id);
    }

    public function restoreToken(NemesisToken $token): bool
    {
        return $this->repository->restore($token->id);
    }

    public function forceExpire(NemesisToken $token): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'expires_at' => new DateTimeVO(now()->subSecond()->toIso8601String()),
        ]);

        return $this->repository->update($token->id, $record);
    }

    public function forceExpireByMinutes(NemesisToken $token, int $minutes): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'expires_at' => new DateTimeVO(now()->subMinutes($minutes)->toIso8601String()),
        ]);

        return $this->repository->update($token->id, $record);
    }

    // ============================================================================
    // Allowed Origins Management
    // ============================================================================

    public function addAllowedOrigin(NemesisToken $token, string $origin): NemesisToken
    {
        $origins = $token->allowed_origins ?? [];

        if (!in_array($origin, $origins)) {
            $origins[] = $origin;
            $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
                'allowed_origins' => $origins,
            ]);

            return $this->repository->update($token->id, $record);
        }

        return $token;
    }

    public function removeAllowedOrigin(NemesisToken $token, string $origin): NemesisToken
    {
        $origins = $token->allowed_origins ?? [];

        $key = array_search($origin, $origins, true);
        if ($key !== false) {
            unset($origins[$key]);
            $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
                'allowed_origins' => array_values($origins),
            ]);

            return $this->repository->update($token->id, $record);
        }

        return $token;
    }

    public function setAllowedOrigins(NemesisToken $token, ?array $origins): NemesisToken
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'allowed_origins' => $origins,
        ]);

        return $this->repository->update($token->id, $record);
    }

    // ============================================================================
    // Metadata Management with Validation
    // ============================================================================

    private function validateMetadata(?StrictDataObject $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return $this->metadataValidator->process($metadata->toArray());
    }

    public function getMetadata(NemesisToken $token, string $key, mixed $default = null): mixed
    {
        return $token->metadata[$key] ?? $default;
    }

    public function hasMetadata(NemesisToken $token, string $key): bool
    {
        return is_array($token->metadata) && array_key_exists($key, $token->metadata);
    }

    public function setMetadata(NemesisToken $token, string $key, mixed $value): NemesisToken
    {
        $testMetadata = [$key => $value];
        if (!$this->metadataValidator->isValid($testMetadata)) {
            $this->metadataValidator->validate($testMetadata);
        }

        $metadata = $token->metadata ?? [];
        $metadata[$key] = $value;

        $clean = $this->metadataValidator->process($metadata);

        return $this->update($token->id, $this->hydration->hydrate(NemesisTokenRecord::class, [
            'metadata' => $clean !== null ? new StrictDataObject($clean) : null,
        ]));
    }

    public function removeMetadata(NemesisToken $token, string $key): NemesisToken
    {
        $metadata = $token->metadata ?? [];

        if (array_key_exists($key, $metadata)) {
            unset($metadata[$key]);
            $clean = $this->metadataValidator->process($metadata);

            return $this->update($token->id, $this->hydration->hydrate(NemesisTokenRecord::class, [
                'metadata' => $clean !== null ? new StrictDataObject($clean) : null,
            ]));
        }

        return $token;
    }

    public function getAllMetadata(NemesisToken $token): ?array
    {
        return $token->metadata;
    }

    public function mergeMetadata(NemesisToken $token, array $metadata): NemesisToken
    {
        $existing = $token->metadata ?? [];
        $merged = array_merge($existing, $metadata);
        $clean = $this->metadataValidator->process($merged);

        return $this->update($token->id, $this->hydration->hydrate(NemesisTokenRecord::class, [
            'metadata' => $clean !== null ? new StrictDataObject($clean) : null,
        ]));
    }

    public function setAllMetadata(NemesisToken $token, ?array $metadata): NemesisToken
    {
        $clean = $this->metadataValidator->process($metadata);

        return $this->update($token->id, $this->hydration->hydrate(NemesisTokenRecord::class, [
            'metadata' => $clean !== null ? new StrictDataObject($clean) : null,
        ]));
    }

    public function clearMetadata(NemesisToken $token): NemesisToken
    {
        return $this->repository->updateRaw($token->id, ['metadata' => null]);
    }

    // ============================================================================
    // Query Methods
    // ============================================================================

    public function findByFilters(NemesisTokenFilterRecord $filters, ?int $limit = null, ?string $sortBy = null, array $columns = ['*']): Collection
    {
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

    private function isWildcardMatch(string $origin, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return false;
        }

        $escapedPattern = preg_quote($pattern, '/');
        $regexPattern = str_replace('\\*', '.*', $escapedPattern);
        $regex = '/^' . $regexPattern . '$/i';

        return preg_match($regex, $origin) === 1;
    }
}
