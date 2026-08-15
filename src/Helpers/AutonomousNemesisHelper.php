<?php

// src/Helpers/AutonomousNemesisHelper.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Helpers;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Autonomous version of NemesisHelper that can work without middleware.
 *
 * This helper extends the base NemesisHelper and adds the ability to
 * read tokens directly from cookies when they are not injected by middleware.
 * It prioritizes: 1. Request data (middleware) → 2. Cookie
 */
final class AutonomousNemesisHelper extends NemesisHelper
{
    private readonly CookieTokenStorageInterface $cookieStorage;

    private readonly NemesisInterface $nemesisService;

    public function __construct(
        CookieTokenStorageInterface $cookieStorage,
        NemesisInterface $nemesisService,
    ) {
        // ✅ Appeler le constructeur parent avec les dépendances nécessaires
        parent::__construct(
            request: app(Request::class),
            config: app(NemesisConfigInterface::class)
        );

        $this->cookieStorage = $cookieStorage;
        $this->nemesisService = $nemesisService;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentToken(): ?NemesisTokenRecord
    {
        // ✅ 1. Essayer via le parent (requête injectée par middleware)
        $token = parent::getCurrentToken();

        if ($token !== null) {
            return $token;
        }

        // ✅ 2. Si pas dans la requête, essayer via le cookie
        $plainToken = $this->cookieStorage->get($this->request);

        if ($plainToken === null) {
            return null;
        }

        // ✅ 3. Valider le token depuis le cookie
        $validatedToken = $this->cookieStorage->getValidatedToken($this->request);

        if ($validatedToken === null) {
            return null;
        }

        // ✅ 4. Convertir le modèle en Record
        $tokenRecord = NemesisTokenRecord::from($validatedToken->toArray());

        // ✅ Mettre en cache
        $this->cachedToken = $tokenRecord;

        return $tokenRecord;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentAuthenticatable(): ?Model
    {
        // ✅ 1. Essayer via le parent (requête injectée par middleware)
        $authenticatable = parent::getCurrentAuthenticatable();

        if ($authenticatable !== null) {
            return $authenticatable;
        }

        // ✅ 2. Si pas dans la requête, essayer via le cookie
        $token = $this->getCurrentToken();

        if ($token === null) {
            return null;
        }

        $authenticatable = $this->cookieStorage->getAuthenticatable($this->request);

        if ($authenticatable !== null) {
            $this->cachedAuthenticatable = $authenticatable;
        }

        return $authenticatable;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentAuthenticatableFormat(): ?AbstractData
    {
        // ✅ 1. Essayer via le parent (requête injectée par middleware)
        $formatted = parent::getCurrentAuthenticatableFormat();

        if ($formatted !== null) {
            return $formatted;
        }

        // ✅ 2. Si pas dans la requête, essayer depuis l'utilisateur
        $user = $this->getCurrentAuthenticatable();

        if ($user !== null && $user instanceof MustNemesis) {
            $formatted = $user->nemesisFormat();

            if ($formatted instanceof AbstractData) {
                $this->cachedFormatted = $formatted;
            }

            return $formatted;
        }

        return null;
    }
}
