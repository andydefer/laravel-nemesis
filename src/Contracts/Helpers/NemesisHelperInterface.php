<?php

// src/Contracts/Helpers/NemesisHelperInterface.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Helpers;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;

interface NemesisHelperInterface
{
    /**
     * Récupère le token courant depuis la requête
     *
     * @return NemesisTokenRecord|null Le token trouvé ou null si absent
     */
    public function getCurrentToken(): ?NemesisTokenRecord;

    /**
     * Récupère le modèle authentifiable courant depuis la requête
     *
     * @return Model|null Le modèle trouvé ou null si absent
     */
    public function getCurrentAuthenticatable(): ?Model;

    /**
     * Récupère le modèle authentifiable formaté depuis la requête
     *
     * @return AbstractData|null Les données formatées ou null si absentes
     */
    public function getCurrentAuthenticatableFormat(): ?AbstractData;

    /**
     * Vérifie si un token est présent dans la requête
     *
     * @return bool True si un token est présent, false sinon
     */
    public function hasCurrentToken(): bool;

    /**
     * Vérifie si un authentifiable est présent dans la requête
     *
     * @return bool True si un authentifiable est présent, false sinon
     */
    public function hasCurrentAuthenticatable(): bool;

    /**
     * Récupère l'ID du token depuis la requête
     *
     * @return int|null L'ID du token ou null si absent
     */
    public function getTokenId(): ?int;

    /**
     * Récupère le tokenable_id depuis le token
     *
     * @return int|null L'ID du tokenable ou null si absent
     */
    public function getTokenableId(): ?int;

    /**
     * Récupère le tokenable_type depuis le token
     *
     * @return string|null Le type du tokenable ou null si absent
     */
    public function getTokenableType(): ?string;

    /**
     * Récupère le nom du token
     *
     * @return string|null Le nom du token ou null si absent
     */
    public function getTokenName(): ?string;

    /**
     * Récupère les abilities du token
     *
     * @return StringTypedCollection|null Les abilities ou null si absent
     */
    public function getTokenAbilities(): ?StringTypedCollection;

    /**
     * Vérifie si le token a expiré
     *
     * @return bool True si le token est expiré, false sinon
     */
    public function isTokenExpired(): bool;

    /**
     * Vérifie si le token est valide (non expiré)
     *
     * @return bool True si le token est valide, false sinon
     */
    public function isTokenValid(): bool;

    /**
     * Récupère les métadonnées du token
     *
     * @return StrictDataObject|null Les métadonnées ou null si absentes
     */
    public function getTokenMetadata(): ?StrictDataObject;

    /**
     * Récupère les origines autorisées du token
     *
     * @return StringTypedCollection|null Les origines autorisées ou null
     */
    public function getTokenAllowedOrigins(): ?StringTypedCollection;

    /**
     * Nettoie les données mises en cache
     */
    public function clear(): self;
}
