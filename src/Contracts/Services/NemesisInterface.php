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

interface NemesisInterface
{
    // ============================================================================
    // Token Creation
    // ============================================================================

    public function create(NemesisTokenRecord $record, Model $tokenable): NemesisToken;

    /**
     * @return array{0: NemesisToken, 1: string} [token, plainToken]
     */
    public function createWithPlainToken(NemesisTokenRecord $record, Model $tokenable): array;

    // ============================================================================
    // CRUD Operations
    // ============================================================================

    public function update(int|string $tokenId, NemesisTokenRecord $record): NemesisToken;

    public function delete(int|string $tokenId): bool;

    public function forceDelete(int|string $tokenId): bool;

    public function restore(int|string $tokenId): bool;

    public function find(int|string $tokenId): ?NemesisToken;

    public function findWithTrashed(int|string $tokenId): ?NemesisToken;

    public function findByHash(string $tokenHash): ?NemesisToken;

    // ============================================================================
    // Bulk Delete Operations
    // ============================================================================

    public function deleteBulk(NemesisTokenFilterRecord $filters): int;

    public function forceDeleteBulk(NemesisTokenFilterRecord $filters): int;

    // ============================================================================
    // Tokenable Operations (Bulk)
    // ============================================================================

    public function getTokensFor(Model $tokenable, bool $withTrashed = false): Collection;

    public function getTokensBySource(Model $tokenable, string $source, bool $withTrashed = false): Collection;

    public function getTokensByName(Model $tokenable, string $name, bool $withTrashed = false): Collection;

    public function hasTokens(Model $tokenable, bool $withTrashed = false): bool;

    public function deleteAllTokens(Model $tokenable, bool $force = false): int;

    public function revokeAllTokens(Model $tokenable): int;

    public function restoreAllTokens(Model $tokenable): int;

    public function revokeTokensBySource(Model $tokenable, string $source, bool $force = false): int;

    public function revokeTokensByName(Model $tokenable, string $name, bool $force = false): int;

    public function revokeTokensBySourceAndName(Model $tokenable, string $source, string $name, bool $force = false): int;

    public function revokeAllTokensExceptSource(Model $tokenable, string $source, bool $force = false): int;

    // ============================================================================
    // Current Token Operations
    // ============================================================================

    public function getCurrentToken(Model $tokenable, Request $request): ?NemesisToken;

    public function revokeCurrentToken(Model $tokenable, Request $request): bool;

    public function deleteCurrentToken(Model $tokenable, Request $request): bool;

    // ============================================================================
    // Token Validation
    // ============================================================================

    public function validateToken(string $plainToken, Model $tokenable, bool $includeRevoked = false): bool;

    public function getTokenByPlainText(string $plainToken, Model $tokenable, bool $withTrashed = false): ?NemesisToken;

    public function touchToken(string $plainToken, Model $tokenable): bool;

    // ============================================================================
    // Count Operations
    // ============================================================================

    public function count(NemesisTokenFilterRecord $filters): int;

    public function exists(NemesisTokenFilterRecord $filters): bool;

    // ============================================================================
    // Expired Tokens Management
    // ============================================================================

    public function revokeExpiredTokens(Model $tokenable): int;

    public function forceDeleteExpiredTokens(Model $tokenable): int;

    // ============================================================================
    // Global Operations
    // ============================================================================

    public function findAllActive(): Collection;

    public function findAllExpired(): Collection;

    public function findAllRevoked(): Collection;

    public function revokeAllExpiredTokensGlobally(): int;

    public function forceDeleteAllExpiredTokensGlobally(): int;

    // ============================================================================
    // Token Capabilities
    // ============================================================================

    public function can(NemesisToken $token, string $ability): bool;

    public function canAll(NemesisToken $token, array $abilities): bool;

    public function canUseFromOrigin(NemesisToken $token, ?string $origin): bool;

    public function canUseFromCurrentRequest(NemesisToken $token, Request $request): bool;

    // ============================================================================
    // Token Lifecycle Operations
    // ============================================================================

    public function updateLastUsed(NemesisToken $token): NemesisToken;

    public function revoke(NemesisToken $token): bool;

    public function restoreToken(NemesisToken $token): bool;

    public function forceExpire(NemesisToken $token): NemesisToken;

    public function forceExpireByMinutes(NemesisToken $token, int $minutes): NemesisToken;

    // ============================================================================
    // Allowed Origins Management
    // ============================================================================

    public function addAllowedOrigin(NemesisToken $token, string $origin): NemesisToken;

    public function removeAllowedOrigin(NemesisToken $token, string $origin): NemesisToken;

    public function setAllowedOrigins(NemesisToken $token, ?array $origins): NemesisToken;

    // ============================================================================
    // Metadata Management
    // ============================================================================

    public function getMetadata(NemesisToken $token, string $key, mixed $default = null): mixed;

    public function hasMetadata(NemesisToken $token, string $key): bool;

    public function setMetadata(NemesisToken $token, string $key, mixed $value): NemesisToken;

    public function removeMetadata(NemesisToken $token, string $key): NemesisToken;

    public function getAllMetadata(NemesisToken $token): ?array;

    public function mergeMetadata(NemesisToken $token, array $metadata): NemesisToken;

    public function setAllMetadata(NemesisToken $token, ?array $metadata): NemesisToken;

    public function clearMetadata(NemesisToken $token): NemesisToken;

    // ============================================================================
    // Query Methods
    // ============================================================================

    public function findByFilters(
        NemesisTokenFilterRecord $filters,
        ?int $limit = null,
        ?SortColumns $sortBy = null,
        array $columns = ['*']
    ): Collection;
}
