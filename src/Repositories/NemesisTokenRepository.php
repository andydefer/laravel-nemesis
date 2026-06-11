<?php

// src/Repositories/NemesisTokenRepository.php

declare(strict_types=1);

namespace Kani\Nemesis\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\NemesisTokenFilterRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;

final class NemesisTokenRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(
            modelClass: NemesisToken::class,
            recordClass: NemesisTokenRecord::class,
        );
    }

    /**
     * Find models with trashed using filters.
     *
     * @return Collection<int, NemesisToken>
     */
    public function findWithTrashedByFilters(NemesisTokenFilterRecord $filters): Collection
    {
        $query = $this->buildQuery($filters);
        $query = $query->withTrashed();

        return $query->get();
    }

    /**
     * Check if any model exists with trashed using filters.
     */
    public function existsWithTrashed(NemesisTokenFilterRecord $filters): bool
    {
        $query = $this->buildQuery($filters);
        $query = $query->withTrashed();

        return $query->exists();
    }

    /**
     * Restore all tokens for a specific tokenable.
     */
    public function restoreBulkForTokenable(string $tokenableType, int $tokenableId): int
    {
        $query = $this->model->newQuery()
            ->where('tokenable_type', $tokenableType)
            ->where('tokenable_id', $tokenableId)
            ->onlyTrashed();

        $count = $query->count();

        if ($count > 0) {
            $query->restore();
        }

        return $count;
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof NemesisTokenFilterRecord) {
            return;
        }

        if ($filters->token_hash !== null) {
            $query->where('token_hash', $filters->token_hash);
        }

        if ($filters->tokenable_type !== null) {
            $query->where('tokenable_type', $filters->tokenable_type);
        }

        if ($filters->tokenable_id !== null) {
            $query->where('tokenable_id', $filters->tokenable_id);
        }

        if ($filters->name !== null) {
            $query->where('name', 'like', '%'.$filters->name.'%');
        }

        if ($filters->source !== null) {
            $query->where('source', $filters->source);
        }

        if ($filters->is_expired === true) {
            $query->whereNotNull('expires_at')->where('expires_at', '<', now());
        } elseif ($filters->is_expired === false) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
        }

        if ($filters->is_revoked === true) {
            $query->onlyTrashed();
        } elseif ($filters->is_revoked === false) {
            $query->withoutTrashed();
        }

        if ($filters->created_before !== null) {
            $query->where('created_at', '<', $filters->created_before->toDateTimeString());
        }
    }
}
