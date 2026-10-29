<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Helpers;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;

interface NemesisHelperInterface
{
    public function getCurrentToken(): ?NemesisTokenRecord;

    public function getCurrentAuthenticatable(): ?Model;

    public function getCurrentAuthenticatableFormat(): ?AbstractData;

    public function hasCurrentToken(): bool;

    public function hasCurrentAuthenticatable(): bool;

    /**
     * Récupère l'ID du token depuis la requête.
     *
     * @return string|null L'ID du token (UUID) ou null si absent
     */
    public function getTokenId(): ?string;

    /**
     * Récupère le tokenable_id depuis le token.
     *
     * @return string|null L'ID du tokenable (UUID) ou null si absent
     */
    public function getTokenableId(): ?string;

    public function getTokenableType(): ?string;

    public function getTokenName(): ?string;

    public function getTokenAbilities(): ?StringTypedCollection;

    public function isTokenExpired(): bool;

    public function isTokenValid(): bool;

    public function getTokenMetadata(): ?StrictDataObject;

    public function getTokenAllowedOrigins(): ?StringTypedCollection;

    public function clear(): self;
}
