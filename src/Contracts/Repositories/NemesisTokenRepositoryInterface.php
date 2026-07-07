<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Repositories;

use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenFilterRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Interface for the Nemesis token repository.
 *
 * Provides specialized methods for managing Nemesis tokens with support
 * for soft deletes, filtering, and bulk operations.
 *
 * @extends AbstractRepositoryInterface<NemesisToken, NemesisTokenRecord>
 */
interface NemesisTokenRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find tokens with trashed (soft-deleted) using filters.
     *
     * This method includes soft-deleted tokens in the results, allowing
     * you to retrieve both active and revoked tokens.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @return Collection<int, NemesisToken> Collection of tokens (including trashed)
     */
    public function findWithTrashedByFilters(NemesisTokenFilterRecord $filters): Collection;

    /**
     * Check if any tokens exist with trashed using filters.
     *
     * This method checks for the existence of tokens matching the filters,
     * including soft-deleted tokens.
     *
     * @param  NemesisTokenFilterRecord  $filters  The filters to apply
     * @return bool True if at least one token exists (including trashed)
     */
    public function existsWithTrashed(NemesisTokenFilterRecord $filters): bool;

    /**
     * Restore all soft-deleted tokens for a specific tokenable model.
     *
     * This method restores all trashed tokens associated with a given
     * tokenable type and ID.
     *
     * @param  string  $tokenableType  The morph class of the tokenable model
     * @param  int  $tokenableId  The ID of the tokenable model
     * @return int The number of tokens restored
     */
    public function restoreBulkForTokenable(string $tokenableType, int $tokenableId): int;
}
