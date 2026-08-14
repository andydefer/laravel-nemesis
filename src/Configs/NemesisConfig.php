<?php

// src/Configs/NemesisConfig.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Configs;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\CleanupConfigRecord;
use AndyDefer\Nemesis\Records\CorsConfigRecord;
use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
use AndyDefer\Nemesis\Records\TokenConfigRecord;
use AndyDefer\Nemesis\Records\WebConfigRecord;

/**
 * Nemesis configuration manager.
 *
 * This class provides access to all configuration values used by the Nemesis
 * authentication system. It pulls values from the Laravel config system
 * and returns them as typed Record objects.
 */
final class NemesisConfig implements NemesisConfigInterface
{
    /**
     * {@inheritDoc}
     */
    public function tokenConfig(): TokenConfigRecord
    {
        return TokenConfigRecord::from([
            'token_length' => config('nemesis.token_length', 64),
            'hash_algorithm' => $this->getValidHashAlgorithm(),
            'expiration_minutes' => config('nemesis.expiration'),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function middlewareConfig(): MiddlewareConfigRecord
    {
        return MiddlewareConfigRecord::from([
            'parameter_name' => config('nemesis.middleware.parameter_name', 'nemesis_auth'),
            'token_header' => config('nemesis.middleware.token_header', 'Authorization'),
            'security_headers' => config('nemesis.middleware.security_headers', true),
            'validate_origin' => config('nemesis.middleware.validate_origin', true),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function corsConfig(): CorsConfigRecord
    {
        return CorsConfigRecord::from([
            'allow_credentials' => config('nemesis.cors.allow_credentials', true),
            'max_age' => config('nemesis.cors.max_age', 86400),
            'expose_token_info' => config('nemesis.cors.expose_token_info', false),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function cleanupConfig(): CleanupConfigRecord
    {
        return CleanupConfigRecord::from([
            'auto_cleanup' => config('nemesis.cleanup.auto_cleanup', true),
            'frequency' => config('nemesis.cleanup.frequency', 60),
            'keep_expired_for_days' => config('nemesis.cleanup.keep_expired_for_days', 30),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function webConfig(): WebConfigRecord
    {
        return WebConfigRecord::from([
            'login_route' => config('nemesis.web.login_route', '/login'),
            'dashboard_route' => config('nemesis.web.dashboard_route', '/dashboard'),
            'verification_route' => config('nemesis.web.verification_route', '/verify-email'),
            'cookie_name' => config('nemesis.web.cookie_name', 'nemesis_token'),
            'cookie_secure' => config('nemesis.web.cookie_secure', true),
            'cookie_httponly' => config('nemesis.web.cookie_httponly', true),
            'cookie_samesite' => config('nemesis.web.cookie_samesite', 'lax'),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function isUsingCustomHeader(): bool
    {
        return $this->middlewareConfig()->token_header !== 'Authorization';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldExpire(): bool
    {
        return $this->tokenConfig()->expiration_minutes !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldCleanup(): bool
    {
        $config = $this->cleanupConfig();

        return $config->auto_cleanup && $config->frequency > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isCorsEnabled(): bool
    {
        return $this->middlewareConfig()->validate_origin;
    }

    /**
     * Get a valid hash algorithm from configuration.
     *
     * Validates that the configured hash algorithm is supported by PHP's hash_algos().
     * If the configured algorithm is not supported, falls back to 'sha256'.
     *
     * @return string A valid hash algorithm name
     */
    private function getValidHashAlgorithm(): string
    {
        $algorithm = config('nemesis.hash_algorithm', 'sha256');

        return in_array($algorithm, hash_algos(), true) ? $algorithm : 'sha256';
    }
}
