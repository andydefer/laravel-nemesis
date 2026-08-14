<?php

// src/Services/CookieTokenStorageService.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Services;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\Services\CookieTokenStorageInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Service for managing authentication tokens in HTTP cookies.
 *
 * This service provides methods to store, retrieve, validate, and remove
 * authentication tokens from cookies. It uses the Nemesis service to
 * validate tokens against the database.
 */
final class CookieTokenStorageService implements CookieTokenStorageInterface
{
    /**
     * Create a new CookieTokenStorage instance.
     *
     * @param  NemesisInterface  $nemesisService  The Nemesis service for token validation
     * @param  NemesisConfigInterface  $config  The Nemesis configuration
     */
    public function __construct(
        private readonly NemesisInterface $nemesisService,
        private readonly NemesisConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function store(string $plainToken): void
    {
        $webConfig = $this->config->webConfig();

        Cookie::queue(
            $webConfig->cookie_name,
            $plainToken,
            0, // Pas d'expiration, le token expire via sa propre logique
            '/',
            null,
            $webConfig->cookie_secure,
            $webConfig->cookie_httponly,
            false,
            $webConfig->cookie_samesite
        );
    }

    /**
     * {@inheritDoc}
     */
    public function get(Request $request): ?string
    {
        $webConfig = $this->config->webConfig();

        return $request->cookie($webConfig->cookie_name);
    }

    /**
     * {@inheritDoc}
     */
    public function has(Request $request): bool
    {
        $webConfig = $this->config->webConfig();

        return $request->hasCookie($webConfig->cookie_name);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(): void
    {
        $webConfig = $this->config->webConfig();

        Cookie::queue(Cookie::forget($webConfig->cookie_name));
    }

    /**
     * {@inheritDoc}
     */
    public function getValidatedToken(Request $request): ?NemesisToken
    {
        $plainToken = $this->get($request);

        if ($plainToken === null) {
            return null;
        }

        $tokenHash = hash('sha256', $plainToken);
        $token = $this->nemesisService->findByHash($tokenHash);

        if ($token === null || ! $token->isValid()) {
            return null;
        }

        return $token;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthenticatable(Request $request): ?Model
    {
        $token = $this->getValidatedToken($request);

        if ($token === null) {
            return null;
        }

        return $token->tokenable;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(Request $request): bool
    {
        return $this->getValidatedToken($request) !== null;
    }
}
