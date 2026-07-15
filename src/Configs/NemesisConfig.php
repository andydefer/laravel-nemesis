<?php

// src/Configs/NemesisConfig.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Configs;

use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\CleanupConfigRecord;
use AndyDefer\Nemesis\Records\CorsConfigRecord;
use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
use AndyDefer\Nemesis\Records\TokenConfigRecord;

final class NemesisConfig implements NemesisConfigInterface
{
    public function tokenConfig(): TokenConfigRecord
    {
        return TokenConfigRecord::from([
            'token_length' => config('nemesis.token_length', 64),
            'hash_algorithm' => $this->getValidHashAlgorithm(),
            'expiration_minutes' => config('nemesis.expiration'),
        ]);
    }

    public function middlewareConfig(): MiddlewareConfigRecord
    {
        return MiddlewareConfigRecord::from([
            'parameter_name' => config('nemesis.middleware.parameter_name', 'nemesis_auth'),
            'token_header' => config('nemesis.middleware.token_header', 'Authorization'),
            'security_headers' => config('nemesis.middleware.security_headers', true),
            'validate_origin' => config('nemesis.middleware.validate_origin', true),
        ]);
    }

    public function corsConfig(): CorsConfigRecord
    {
        return CorsConfigRecord::from([
            'allow_credentials' => config('nemesis.cors.allow_credentials', true),
            'max_age' => config('nemesis.cors.max_age', 86400),
            'expose_token_info' => config('nemesis.cors.expose_token_info', false),
        ]);
    }

    public function cleanupConfig(): CleanupConfigRecord
    {
        return CleanupConfigRecord::from([
            'auto_cleanup' => config('nemesis.cleanup.auto_cleanup', true),
            'frequency' => config('nemesis.cleanup.frequency', 60),
            'keep_expired_for_days' => config('nemesis.cleanup.keep_expired_for_days', 30),
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
}
