<?php
// src/Contracts/Configs/NemesisConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Configs;

use AndyDefer\Nemesis\Records\CleanupConfigRecord;
use AndyDefer\Nemesis\Records\CorsConfigRecord;
use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
use AndyDefer\Nemesis\Records\TokenConfigRecord;

/**
 * Interface for Nemesis configuration.
 * 
 * Services should depend on this interface, not the concrete implementation.
 */
interface NemesisConfigInterface
{
    /**
     * Get token generation configuration.
     */
    public function tokenConfig(): TokenConfigRecord;

    /**
     * Get middleware configuration.
     */
    public function middlewareConfig(): MiddlewareConfigRecord;

    /**
     * Get CORS configuration.
     */
    public function corsConfig(): CorsConfigRecord;

    /**
     * Get cleanup configuration.
     */
    public function cleanupConfig(): CleanupConfigRecord;

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Check if using a custom header instead of standard Bearer token.
     */
    public function isUsingCustomHeader(): bool;

    /**
     * Check if tokens should expire (has expiration set).
     */
    public function shouldExpire(): bool;

    /**
     * Check if cleanup is enabled and has valid configuration.
     */
    public function shouldCleanup(): bool;

    /**
     * Check if CORS is enabled (origin validation active).
     */
    public function isCorsEnabled(): bool;
}
