<?php

// src/Configs/NemesisConfig.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Configs;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\CleanupConfigRecord;
use AndyDefer\Nemesis\Records\CorsConfigRecord;
use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
use AndyDefer\Nemesis\Records\TokenConfigRecord;

final class NemesisConfig implements NemesisConfigInterface
{
    private HydrationService $hydration;

    public function __construct()
    {
        $this->hydration = new HydrationService;
    }

    public function tokenConfig(): TokenConfigRecord
    {
        return $this->hydration->hydrate(TokenConfigRecord::class, [
            'token_length' => (int) config('nemesis.token_length', 64),
            'hash_algorithm' => $this->getValidHashAlgorithm(),
            'expiration_minutes' => $this->getExpirationValue(),
        ]);
    }

    public function middlewareConfig(): MiddlewareConfigRecord
    {
        return $this->hydration->hydrate(MiddlewareConfigRecord::class, [
            'parameter_name' => config('nemesis.middleware.parameter_name', 'nemesis_auth'),
            'token_header' => config('nemesis.middleware.token_header', 'Authorization'),
            'security_headers' => (bool) config('nemesis.middleware.security_headers', true),
            'validate_origin' => (bool) config('nemesis.middleware.validate_origin', true),
        ]);
    }

    public function corsConfig(): CorsConfigRecord
    {
        return $this->hydration->hydrate(CorsConfigRecord::class, [
            'allow_credentials' => (bool) config('nemesis.cors.allow_credentials', true),
            'max_age' => (int) config('nemesis.cors.max_age', 86400),
            'expose_token_info' => (bool) config('nemesis.cors.expose_token_info', false),
        ]);
    }

    public function cleanupConfig(): CleanupConfigRecord
    {
        return $this->hydration->hydrate(CleanupConfigRecord::class, [
            'auto_cleanup' => (bool) config('nemesis.cleanup.auto_cleanup', true),
            'frequency' => (int) config('nemesis.cleanup.frequency', 60),
            'keep_expired_for_days' => (int) config('nemesis.cleanup.keep_expired_for_days', 30),
        ]);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    public function isUsingCustomHeader(): bool
    {
        return $this->middlewareConfig()->token_header !== 'Authorization';
    }

    public function shouldExpire(): bool
    {
        return $this->tokenConfig()->expiration_minutes !== null;
    }

    public function shouldCleanup(): bool
    {
        $config = $this->cleanupConfig();

        return $config->auto_cleanup && $config->frequency > 0;
    }

    public function isCorsEnabled(): bool
    {
        return $this->middlewareConfig()->validate_origin;
    }

    // ============================================================================
    // Private Helpers
    // ============================================================================

    private function getValidHashAlgorithm(): string
    {
        $algorithm = config('nemesis.hash_algorithm', 'sha256');

        return in_array($algorithm, hash_algos(), true) ? $algorithm : 'sha256';
    }

    private function getExpirationValue(): ?int
    {
        $expiration = config('nemesis.expiration');

        if ($expiration === null) {
            return null;
        }

        $value = (int) $expiration;

        return $value > 0 ? $value : 60;
    }
}
