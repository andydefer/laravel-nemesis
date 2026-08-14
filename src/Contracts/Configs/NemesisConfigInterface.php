<?php

// src/Contracts/Configs/NemesisConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Configs;

use AndyDefer\Nemesis\Records\CleanupConfigRecord;
use AndyDefer\Nemesis\Records\CorsConfigRecord;
use AndyDefer\Nemesis\Records\MiddlewareConfigRecord;
use AndyDefer\Nemesis\Records\TokenConfigRecord;
use AndyDefer\Nemesis\Records\WebConfigRecord;

/**
 * Interface for Nemesis configuration.
 *
 * Services should depend on this interface, not the concrete implementation.
 * This allows for easy testing and configuration swapping.
 */
interface NemesisConfigInterface
{
    /**
     * Get token generation configuration.
     *
     * @return TokenConfigRecord The token configuration record
     */
    public function tokenConfig(): TokenConfigRecord;

    /**
     * Get middleware configuration.
     *
     * @return MiddlewareConfigRecord The middleware configuration record
     */
    public function middlewareConfig(): MiddlewareConfigRecord;

    /**
     * Get CORS configuration.
     *
     * @return CorsConfigRecord The CORS configuration record
     */
    public function corsConfig(): CorsConfigRecord;

    /**
     * Get cleanup configuration.
     *
     * @return CleanupConfigRecord The cleanup configuration record
     */
    public function cleanupConfig(): CleanupConfigRecord;

    /**
     * Get web authentication configuration.
     *
     * @return WebConfigRecord The web configuration record
     */
    public function webConfig(): WebConfigRecord;

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Check if using a custom header instead of standard Bearer token.
     *
     * @return bool True if using a custom header, false otherwise
     */
    public function isUsingCustomHeader(): bool;

    /**
     * Check if tokens should expire (has expiration set).
     *
     * @return bool True if tokens should expire, false otherwise
     */
    public function shouldExpire(): bool;

    /**
     * Check if cleanup is enabled and has valid configuration.
     *
     * @return bool True if cleanup should run, false otherwise
     */
    public function shouldCleanup(): bool;

    /**
     * Check if CORS is enabled (origin validation active).
     *
     * @return bool True if CORS validation is enabled, false otherwise
     */
    public function isCorsEnabled(): bool;
}
