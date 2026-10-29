<?php

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

class NemesisHelper implements NemesisHelperInterface
{
    public ?NemesisTokenRecord $cachedToken = null;

    public ?Model $cachedAuthenticatable = null;

    protected ?AbstractData $cachedFormatted = null;

    public function __construct(
        protected readonly Request $request,
        protected readonly NemesisConfigInterface $config,
    ) {}

    public function getCurrentToken(): ?NemesisTokenRecord
    {
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

    public function getTokenId(): ?string
    {
        return $this->getCurrentToken()?->id;
    }

    public function getTokenableId(): ?string
    {
        return $this->getCurrentToken()?->tokenable_id;
    }

    public function getTokenableType(): ?string
    {
        return $this->getCurrentToken()?->tokenable_type;
    }

    public function getTokenName(): ?string
    {
        return $this->getCurrentToken()?->name;
    }

    public function getTokenAbilities(): ?StringTypedCollection
    {
        return $this->getCurrentToken()?->abilities;
    }

    public function isTokenExpired(): bool
    {
        $token = $this->getCurrentToken();

        if (! $token) {
            return true;
        }

        if ($token->expires_at === null) {
            return false;
        }

        return $token->expires_at->isBefore(new DateTimeVO);
    }

    public function isTokenValid(): bool
    {
        return ! $this->isTokenExpired();
    }

    public function getTokenMetadata(): ?StrictDataObject
    {
        return $this->getCurrentToken()?->metadata;
    }

    public function getTokenAllowedOrigins(): ?StringTypedCollection
    {
        return $this->getCurrentToken()?->allowed_origins;
    }

    public function clear(): self
    {
        $this->cachedToken = null;
        $this->cachedAuthenticatable = null;
        $this->cachedFormatted = null;

        $this->request->request->remove('current_nemesis_token');

        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName.'_format';

        $this->request->request->remove($parameterName);
        $this->request->request->remove($formatKey);

        return $this;
    }

    public function isAuthenticated(): bool
    {
        return $this->hasCurrentToken()
            && $this->hasCurrentAuthenticatable()
            && $this->isTokenValid();
    }

    public function isGuest(): bool
    {
        return ! $this->isAuthenticated();
    }

    public function getTokenExpirationDate(): ?DateTimeVO
    {
        return $this->getCurrentToken()?->expires_at;
    }

    public function getTokenLastUsedAt(): ?DateTimeVO
    {
        return $this->getCurrentToken()?->last_used_at;
    }

    public function getTokenSource(): ?string
    {
        return $this->getCurrentToken()?->source;
    }

    public function tokenHasAbility(string $ability): bool
    {
        $abilities = $this->getTokenAbilities();

        return $abilities !== null && $abilities->contains($ability);
    }

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

    public function isOriginAllowed(string $origin): bool
    {
        $allowedOrigins = $this->getTokenAllowedOrigins();

        return $allowedOrigins !== null && $allowedOrigins->contains($origin);
    }
}
