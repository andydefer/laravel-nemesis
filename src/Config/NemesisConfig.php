<?php

declare(strict_types=1);

namespace Kani\Nemesis\Config;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;
use Kani\Nemesis\Records\CleanupConfigRecord;
use Kani\Nemesis\Records\CorsConfigRecord;
use Kani\Nemesis\Records\MiddlewareConfigRecord;
use Kani\Nemesis\Records\TokenGenerationRecord;

/**
 * Configuration for the Nemesis token authentication system.
 *
 * @deprecated This class is deprecated and will be removed in version 3.0.0.
 * 
 *             ❌ WHY DEPRECATED?
 *             
 *             This class violates the Dependency Inversion Principle (DIP)
 *             by providing a concrete implementation that services couple to.
 *             
 *             ✅ NEW APPROACH:
 *             
 *             Use the interface Kani\Nemesis\Contracts\Configs\NemesisConfigInterface
 *             with the implementation Kani\Nemesis\Configs\NemesisConfig
 *             
 *             The new approach uses Records to group configuration values:
 *             - tokenConfig(): TokenConfigRecord
 *             - middlewareConfig(): MiddlewareConfigRecord
 *             - corsConfig(): CorsConfigRecord
 *             - cleanupConfig(): CleanupConfigRecord
 *             
 *             @see \Kani\Nemesis\Contracts\Configs\NemesisConfigInterface
 *             @see \Kani\Nemesis\Configs\NemesisConfig
 * 
 * @author Andy Defer
 * @deprecated since 2.0.0, will be removed in 3.0.0
 */
final class NemesisConfig extends AbstractConfig
{
    /**
     * @deprecated Use NemesisConfigInterface::tokenConfig()->token_length instead
     */
    public function getTokenLength(): int
    {
        return (int) config('nemesis.token_length', 64);
    }

    /**
     * @deprecated Use NemesisConfigInterface::tokenConfig()->hash_algorithm instead
     */
    public function getHashAlgorithm(): string
    {
        $algorithm = config('nemesis.hash_algorithm', 'sha256');

        return in_array($algorithm, hash_algos(), true) ? $algorithm : 'sha256';
    }

    /**
     * @deprecated Use NemesisConfigInterface::tokenConfig()->expiration_minutes instead
     */
    public function getExpiration(): ?int
    {
        $expiration = config('nemesis.expiration');

        if ($expiration === null) {
            return null;
        }

        $value = (int) $expiration;

        return $value > 0 ? $value : 60;
    }

    /**
     * @deprecated Use NemesisConfigInterface::middlewareConfig()->parameter_name instead
     */
    public function getParameterName(): string
    {
        return config('nemesis.middleware.parameter_name', 'nemesisAuth');
    }

    /**
     * @deprecated Use NemesisConfigInterface::middlewareConfig()->token_header instead
     */
    public function getTokenHeader(): string
    {
        return config('nemesis.middleware.token_header', 'Authorization');
    }

    /**
     * @deprecated Use NemesisConfigInterface::middlewareConfig()->security_headers instead
     */
    public function getSecurityHeaders(): bool
    {
        return (bool) config('nemesis.middleware.security_headers', true);
    }

    /**
     * @deprecated Use NemesisConfigInterface::middlewareConfig()->validate_origin instead
     */
    public function getValidateOrigin(): bool
    {
        return (bool) config('nemesis.middleware.validate_origin', true);
    }

    /**
     * @deprecated Use NemesisConfigInterface::isUsingCustomHeader() instead
     */
    public function isUsingCustomHeader(): bool
    {
        return $this->getTokenHeader() !== 'Authorization';
    }

    /**
     * @deprecated Use NemesisConfigInterface::corsConfig()->allow_credentials instead
     */
    public function getAllowCredentials(): bool
    {
        return (bool) config('nemesis.cors.allow_credentials', true);
    }

    /**
     * @deprecated Use NemesisConfigInterface::corsConfig()->max_age instead
     */
    public function getMaxAge(): int
    {
        return (int) config('nemesis.cors.max_age', 86400);
    }

    /**
     * @deprecated Use NemesisConfigInterface::corsConfig()->expose_token_info instead
     */
    public function getExposeTokenInfo(): bool
    {
        return (bool) config('nemesis.cors.expose_token_info', false);
    }

    /**
     * @deprecated Use NemesisConfigInterface::cleanupConfig()->auto_cleanup instead
     */
    public function getAutoCleanup(): bool
    {
        return (bool) config('nemesis.cleanup.auto_cleanup', true);
    }

    /**
     * @deprecated Use NemesisConfigInterface::cleanupConfig()->frequency instead
     */
    public function getCleanupFrequency(): int
    {
        return (int) config('nemesis.cleanup.frequency', 60);
    }

    /**
     * @deprecated Use NemesisConfigInterface::cleanupConfig()->keep_expired_for_days instead
     */
    public function getKeepExpiredForDays(): int
    {
        return (int) config('nemesis.cleanup.keep_expired_for_days', 30);
    }

    /**
     * @deprecated Use NemesisConfigInterface::shouldExpire() instead
     */
    public function shouldExpire(): bool
    {
        return $this->getExpiration() !== null;
    }

    /**
     * @deprecated Use NemesisConfigInterface::shouldCleanup() instead
     */
    public function shouldCleanup(): bool
    {
        return $this->getAutoCleanup() && $this->getCleanupFrequency() > 0;
    }

    /**
     * @deprecated Use NemesisConfigInterface::isCorsEnabled() instead
     */
    public function isCorsEnabled(): bool
    {
        return $this->getValidateOrigin();
    }

    /**
     * @deprecated Use NemesisConfigInterface::tokenConfig() instead
     */
    public function getTokenGenerationRecord(): TokenGenerationRecord
    {
        return TokenGenerationRecord::from([
            'length' => $this->getTokenLength(),
            'hash_algorithm' => $this->getHashAlgorithm(),
            'expiration_minutes' => $this->getExpiration(),
        ]);
    }

    /**
     * @deprecated Use NemesisConfigInterface::middlewareConfig() instead
     */
    public function getMiddlewareConfigRecord(): MiddlewareConfigRecord
    {
        return MiddlewareConfigRecord::from([
            'parameter_name' => $this->getParameterName(),
            'token_header' => $this->getTokenHeader(),
            'security_headers' => $this->getSecurityHeaders(),
            'validate_origin' => $this->getValidateOrigin(),
        ]);
    }

    /**
     * @deprecated Use NemesisConfigInterface::corsConfig() instead
     */
    public function getCorsConfigRecord(): CorsConfigRecord
    {
        return CorsConfigRecord::from([
            'allow_credentials' => $this->getAllowCredentials(),
            'max_age' => $this->getMaxAge(),
            'expose_token_info' => $this->getExposeTokenInfo(),
        ]);
    }

    /**
     * @deprecated Use NemesisConfigInterface::cleanupConfig() instead
     */
    public function getCleanupConfigRecord(): CleanupConfigRecord
    {
        return CleanupConfigRecord::from([
            'auto_cleanup' => $this->getAutoCleanup(),
            'frequency' => $this->getCleanupFrequency(),
            'keep_expired_for_days' => $this->getKeepExpiredForDays(),
        ]);
    }
}
