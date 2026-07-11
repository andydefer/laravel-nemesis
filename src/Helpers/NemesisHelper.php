<?php

// src/Helpers/NemesisHelper.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Helpers;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Helpers\NemesisHelperInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class NemesisHelper implements NemesisHelperInterface
{
    public ?NemesisTokenRecord $cachedToken = null;

    public ?Model $cachedAuthenticatable = null;

    private ?AbstractData $cachedFormatted = null;

    public function __construct(
        private readonly Request $request,
        private readonly NemesisConfigInterface $config,
    ) {}

    public function getCurrentToken(): ?NemesisTokenRecord
    {
        // ✅ Vérifier le cache d'abord
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $token = $this->request->input('current_nemesis_token');

        if ($token instanceof NemesisTokenRecord) {
            return $this->cachedToken = $token;
        }

        return null;
    }

    public function getCurrentAuthenticatable(): ?Model
    {
        // ✅ Vérifier le cache d'abord
        if ($this->cachedAuthenticatable !== null) {
            return $this->cachedAuthenticatable;
        }

        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $authenticatable = $this->request->input($parameterName);

        if ($authenticatable instanceof Model) {
            return $this->cachedAuthenticatable = $authenticatable;
        }

        return null;
    }

    public function getCurrentAuthenticatableFormat(): ?AbstractData
    {
        // ✅ Vérifier le cache d'abord
        if ($this->cachedFormatted !== null) {
            return $this->cachedFormatted;
        }

        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName.'_format';
        $formatted = $this->request->input($formatKey);

        if ($formatted instanceof AbstractData) {
            return $this->cachedFormatted = $formatted;
        }

        return null;
    }

    public function hasCurrentToken(): bool
    {
        return $this->getCurrentToken() !== null;
    }

    public function hasCurrentAuthenticatable(): bool
    {
        return $this->getCurrentAuthenticatable() !== null;
    }

    public function getTokenId(): ?int
    {
        $token = $this->getCurrentToken();

        return $token?->id;
    }

    public function getTokenableId(): ?int
    {
        $token = $this->getCurrentToken();

        return $token?->tokenable_id;
    }

    public function getTokenableType(): ?string
    {
        $token = $this->getCurrentToken();

        return $token?->tokenable_type;
    }

    public function getTokenName(): ?string
    {
        $token = $this->getCurrentToken();

        return $token?->name;
    }

    public function getTokenAbilities(): ?StringTypedCollection
    {
        $token = $this->getCurrentToken();

        return $token?->abilities;
    }

    public function isTokenExpired(): bool
    {
        $token = $this->getCurrentToken();

        if (! $token || ! $token->expires_at) {
            return true;
        }

        $now = new DateTimeVO;

        return $token->expires_at->isBefore($now);
    }

    public function isTokenValid(): bool
    {
        return ! $this->isTokenExpired();
    }

    public function getTokenMetadata(): ?StrictDataObject
    {
        $token = $this->getCurrentToken();

        return $token?->metadata;
    }

    public function getTokenAllowedOrigins(): ?StringTypedCollection
    {
        $token = $this->getCurrentToken();

        return $token?->allowed_origins;
    }

    public function clear(): self
    {
        $this->cachedToken = null;
        $this->cachedAuthenticatable = null;
        $this->cachedFormatted = null;

        // ✅ SUPPRIMER AUSSI LES DONNÉES DE LA REQUÊTE
        $this->request->request->remove('current_nemesis_token');

        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName.'_format';

        $this->request->request->remove($parameterName);
        $this->request->request->remove($formatKey);

        return $this;
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function isAuthenticated(): bool
    {
        return $this->hasCurrentToken() &&
               $this->hasCurrentAuthenticatable() &&
               $this->isTokenValid();
    }

    /**
     * Vérifie si l'utilisateur est un invité (non authentifié)
     */
    public function isGuest(): bool
    {
        return ! $this->isAuthenticated();
    }

    /**
     * Obtient la date d'expiration du token formatée
     */
    public function getTokenExpirationDate(): ?DateTimeVO
    {
        $token = $this->getCurrentToken();

        return $token?->expires_at;
    }

    /**
     * Obtient la dernière date d'utilisation du token
     */
    public function getTokenLastUsedAt(): ?DateTimeVO
    {
        $token = $this->getCurrentToken();

        return $token?->last_used_at;
    }

    /**
     * Obtient la source du token
     */
    public function getTokenSource(): ?string
    {
        $token = $this->getCurrentToken();

        return $token?->source;
    }

    /**
     * Vérifie si le token a une ability spécifique
     */
    public function tokenHasAbility(string $ability): bool
    {
        $abilities = $this->getTokenAbilities();

        if (! $abilities) {
            return false;
        }

        return $abilities->contains($ability);
    }

    /**
     * Vérifie si le token a toutes les abilities spécifiées
     */
    public function tokenHasAllAbilities(array $abilities): bool
    {
        $tokenAbilities = $this->getTokenAbilities();

        if (! $tokenAbilities) {
            return false;
        }

        foreach ($abilities as $ability) {
            if (! $tokenAbilities->contains($ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si une origine est autorisée
     */
    public function isOriginAllowed(string $origin): bool
    {
        $allowedOrigins = $this->getTokenAllowedOrigins();

        if (! $allowedOrigins) {
            return false;
        }

        return $allowedOrigins->contains($origin);
    }
}
